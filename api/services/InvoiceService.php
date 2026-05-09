<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\AppLogger;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Customer;
use PDO;

/**
 * Integração Focus NFe para emissão de NFS-e em Blumenau/SC.
 *
 * Blumenau usa o formato nacional (endpoint /nfsen) mas NÃO opera no
 * Ambiente Nacional — não habilitar essa opção no painel Focus NFe.
 * Provedor: Simpliss | Autenticação: HTTP Basic Auth (token:senha_vazia)
 *
 * IMPORTANTE: Erro na emissão NÃO bloqueia a entrega do cartão.
 * O registro de invoice fica com status 'error' e pode ser
 * reemitido pelo admin posteriormente.
 */
final class InvoiceService
{
    private const BASE_HOMOLOG = 'https://homologacao.focusnfe.com.br/v2';
    private const BASE_PROD    = 'https://api.focusnfe.com.br/v2';
    private const ENDPOINT     = '/nfsen';   // formato nacional — Blumenau/SC
    private const TIMEOUT_SECS = 30;

    // Constantes de Blumenau (IBGE 4202404)
    private const MUNICIPIO_BLUMENAU    = '4202404';
    private const CODIGO_TRIBUTACAO_ISS = '171401';
    private const CODIGO_NBS            = '113012000';

    private Invoice  $invoiceModel;
    private Order    $orderModel;
    private Customer $customerModel;

    public function __construct(private readonly PDO $pdo)
    {
        $this->invoiceModel  = new Invoice($pdo);
        $this->orderModel    = new Order($pdo);
        $this->customerModel = new Customer($pdo);
    }

    /**
     * Emite NFS-e para um pedido pago.
     * Cria o registro no banco antes de chamar a API.
     * Em caso de erro, marca como 'error' e não relança.
     */
    public function issue(int $orderId): ?array
    {
        $existing = $this->invoiceModel->findByOrderId($orderId);
        if ($existing && in_array($existing['status'], ['issued', 'pending'], true)) {
            AppLogger::info('NFS-e já emitida ou em andamento', ['order_id' => $orderId]);
            return $existing;
        }

        $invoiceId = $existing ? (int) $existing['id'] : $this->invoiceModel->create($orderId);
        if ($existing) {
            $this->invoiceModel->resetToPending($invoiceId);
        }

        $token = env('FOCUSNFE_TOKEN', '');
        if (empty($token)) {
            $this->invoiceModel->markError($invoiceId, 'Focus NFe não configurado (FOCUSNFE_TOKEN ausente).');
            AppLogger::warning('Focus NFe não configurado', ['order_id' => $orderId]);
            return null;
        }

        $order = $this->orderModel->findById($orderId);
        if (!$order) {
            $this->invoiceModel->markError($invoiceId, "Pedido #$orderId não encontrado.");
            return null;
        }

        $ref = $this->buildRef($orderId, $invoiceId);

        try {
            $payload  = $this->buildPayload($order);
            $response = $this->apiRequest('POST', self::ENDPOINT . '?ref=' . urlencode($ref), $payload, $token);

            $status = $response['status'] ?? '';

            AppLogger::info('NFS-e enviada para emissão', [
                'order_id' => $orderId,
                'ref'      => $ref,
                'status'   => $status,
            ]);

            $this->invoiceModel->saveFocusRef($invoiceId, $ref);

            if ($status === 'autorizado') {
                $this->invoiceModel->markIssued(
                    $invoiceId,
                    $ref,
                    $response['numero'] ?? '',
                    null,
                    null,
                    $response['url_danfse'] ?? null
                );
            }
            // status 'processando_autorizacao' → fica como 'pending', será atualizado via consulta

            return $response;
        } catch (\Throwable $e) {
            $this->invoiceModel->markError($invoiceId, $e->getMessage());
            AppLogger::error('Erro ao emitir NFS-e', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Consulta o status de uma NFS-e na Focus NFe e atualiza o banco.
     */
    public function consultStatus(int $invoiceId): ?array
    {
        $invoice = $this->invoiceModel->findById($invoiceId);
        if (!$invoice || empty($invoice['focusnfe_ref'])) {
            return null;
        }

        $token = env('FOCUSNFE_TOKEN', '');
        if (empty($token)) {
            return null;
        }

        try {
            $ref      = $invoice['focusnfe_ref'];
            $response = $this->apiRequest('GET', self::ENDPOINT . '/' . urlencode($ref), [], $token);
            $status   = $response['status'] ?? '';

            if ($status === 'autorizado') {
                $this->invoiceModel->markIssued(
                    $invoiceId,
                    $ref,
                    $response['numero'] ?? '',
                    null,
                    null,
                    $response['url_danfse'] ?? null
                );
            } elseif ($status === 'erro_autorizacao') {
                $erros = $response['erros'] ?? [];
                $msg   = implode('; ', array_column($erros, 'mensagem'));
                $this->invoiceModel->markError($invoiceId, $msg ?: 'Erro de autorização');
            } elseif ($status === 'cancelado') {
                $this->invoiceModel->markCancelled($invoiceId);
            }

            return $response;
        } catch (\Throwable $e) {
            AppLogger::error('Erro ao consultar NFS-e', [
                'invoice_id' => $invoiceId,
                'error'      => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Cancela uma NFS-e autorizada na Focus NFe.
     * Apenas notas com status 'issued' (autorizado) podem ser canceladas.
     * O cancelamento é definitivo e não pode ser desfeito.
     */
    public function cancel(int $invoiceId, string $justificativa): array
    {
        $invoice = $this->invoiceModel->findById($invoiceId);
        if (!$invoice) {
            throw new \RuntimeException("Invoice #$invoiceId não encontrada.");
        }
        if ($invoice['status'] !== 'issued') {
            throw new \RuntimeException("Apenas notas autorizadas podem ser canceladas (status atual: {$invoice['status']}).");
        }
        if (empty($invoice['focusnfe_ref'])) {
            throw new \RuntimeException("Referência Focus NFe ausente — nota não pode ser cancelada via API.");
        }
        if (strlen($justificativa) < 15 || strlen($justificativa) > 255) {
            throw new \InvalidArgumentException("Justificativa deve ter entre 15 e 255 caracteres.");
        }

        $token = env('FOCUSNFE_TOKEN', '');
        if (empty($token)) {
            throw new \RuntimeException('FOCUSNFE_TOKEN não configurado.');
        }

        $ref      = $invoice['focusnfe_ref'];
        $response = $this->apiRequest('DELETE', self::ENDPOINT . '/' . urlencode($ref), ['justificativa' => $justificativa], $token);

        $status = $response['status'] ?? '';

        if ($status === 'cancelado') {
            $this->invoiceModel->markCancelled($invoiceId);
            AppLogger::info('NFS-e cancelada', ['invoice_id' => $invoiceId, 'ref' => $ref]);
        } elseif ($status === 'erro_cancelamento') {
            $erros = $response['erros'] ?? [];
            $msg   = implode('; ', array_column($erros, 'mensagem'));
            throw new \RuntimeException("Erro ao cancelar NFS-e: $msg");
        }

        return $response;
    }

    /**
     * Reemite nota fiscal (ação admin).
     */
    public function reissue(int $orderId): ?array
    {
        $existing = $this->invoiceModel->findByOrderId($orderId);
        if ($existing && $existing['status'] === 'issued') {
            $this->invoiceModel->markCancelled((int) $existing['id']);
        }

        return $this->issue($orderId);
    }

    /**
     * Processa webhook da Focus NFe.
     * A Focus NFe envia um POST com o campo 'ref' quando o status muda.
     * Realizamos uma consulta para obter o status atualizado.
     */
    public function processWebhook(array $payload): bool
    {
        $ref = $payload['ref'] ?? null;
        if (!$ref) {
            AppLogger::warning('Webhook Focus NFe: campo ref ausente', ['payload' => $payload]);
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM invoices WHERE focusnfe_ref = ? LIMIT 1'
        );
        $stmt->execute([$ref]);
        $invoice = $stmt->fetch();

        if (!$invoice) {
            AppLogger::warning('Webhook Focus NFe: invoice não encontrada', ['ref' => $ref]);
            return false;
        }

        $result = $this->consultStatus((int) $invoice['id']);

        AppLogger::info('Webhook Focus NFe processado', [
            'ref'    => $ref,
            'status' => $result['status'] ?? 'desconhecido',
        ]);

        return $result !== null;
    }

    // ─── Privados ─────────────────────────────────────────────────────────────

    private function buildRef(int $orderId, int $invoiceId): string
    {
        return 'ORD' . $orderId . '-INV' . $invoiceId;
    }

    private function buildPayload(array $order): array
    {
        $now   = new \DateTimeImmutable('now', new \DateTimeZone('America/Sao_Paulo'));
        $cpf   = preg_replace('/\D/', '', $order['customer_cpf'] ?? '');
        $phone = preg_replace('/\D/', '', $order['customer_phone'] ?? '');

        $items     = $this->orderModel->getItems((int) $order['id']);
        $descricao = $this->buildDescricao($items, $order);

        $payload = [
            'data_emissao'                               => $now->format('Y-m-d\TH:i:sO'),
            'data_competencia'                           => $now->format('Y-m-d'),
            'codigo_municipio_emissora'                  => (int) self::MUNICIPIO_BLUMENAU,
            'cnpj_prestador'                             => preg_replace('/\D/', '', env('FOCUSNFE_CNPJ_PRESTADOR', '')),
            'codigo_opcao_simples_nacional'              => (int) env('FOCUSNFE_OPCAO_SIMPLES', 3),
            'regime_tributario_simples_nacional'         => (int) env('FOCUSNFE_REGIME_TRIBUTARIO', 1),
            'regime_especial_tributacao'                 => (int) env('FOCUSNFE_REGIME_ESPECIAL', 0),
            'razao_social_tomador'                       => $order['customer_name'],
            'email_tomador'                              => $order['customer_email'],
            'codigo_municipio_prestacao'                 => self::MUNICIPIO_BLUMENAU,
            'codigo_tributacao_nacional_iss'             => self::CODIGO_TRIBUTACAO_ISS,
            'descricao_servico'                          => $descricao,
            'codigo_nbs'                                 => self::CODIGO_NBS,
            'valor_servico'                              => (float) $order['total'],
            'tributacao_iss'                             => 1,
            'tipo_retencao_iss'                          => 1,
            'percentual_aliquota_relativa_municipio'     => env('FOCUSNFE_ALIQUOTA_ISS', '2.7463'),
            'percentual_total_tributos_simples_nacional' => env('FOCUSNFE_TOTAL_TRIBUTOS', '17.52'),
        ];

        $im = env('FOCUSNFE_IM_PRESTADOR', '');
        if ($im !== '') {
            $payload['inscricao_municipal_prestador'] = $im;
        }

        if (strlen($cpf) === 11) {
            $payload['cpf_tomador'] = $cpf;
        } elseif (strlen($cpf) === 14) {
            $payload['cnpj_tomador'] = $cpf;
        }

        if ($phone) {
            $payload['telefone_tomador'] = substr($phone, 0, 11);
        }

        return $payload;
    }

    private function buildDescricao(array $items, array $order): string
    {
        if (empty($items)) {
            return 'Cartão Presente — Day Spa Hochheim';
        }

        $parts = [];
        foreach ($items as $item) {
            $snapshot = is_string($item['service_snapshot'])
                ? json_decode($item['service_snapshot'], true)
                : $item['service_snapshot'];
            $name     = $snapshot['name'] ?? 'Serviço';
            $parts[]  = $item['quantity'] > 1 ? $item['quantity'] . 'x ' . $name : $name;
        }

        return 'Cartão Presente: ' . implode(', ', $parts) . ' — Pedido #' . $order['id'];
    }

    private function baseUrl(): string
    {
        $env = env('FOCUSNFE_ENVIRONMENT', 'homologacao');
        return $env === 'producao' ? self::BASE_PROD : self::BASE_HOMOLOG;
    }

    private function apiRequest(string $method, string $path, array $body, string $token): array
    {
        $url = $this->baseUrl() . $path;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECS,
            CURLOPT_USERPWD        => $token . ':',   // Basic Auth: token como usuário, senha vazia
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            if (!empty($body)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }
        }

        $raw    = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("cURL error: $error");
        }

        $decoded = json_decode($raw, true);
        if ($decoded === null) {
            throw new \RuntimeException("Focus NFe resposta inválida (HTTP $status): $raw");
        }

        if ($status >= 400) {
            $message = $decoded['mensagem'] ?? $decoded['message'] ?? "HTTP {$status}";
            throw new \RuntimeException("Focus NFe API error [{$status}]: $message");
        }

        return $decoded;
    }
}
