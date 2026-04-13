<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\AppLogger;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Customer;
use PDO;

/**
 * Integração NFE.io para emissão de NFS-e.
 *
 * IMPORTANTE: Erro na emissão NÃO bloqueia a entrega do cartão.
 * O registro de invoice é criado com status 'error' e pode ser
 * reemitido pelo admin posteriormente.
 *
 * Documentação: https://nfe.io/docs/desenvolvedores/rest-api/nota-fiscal-servico-v2/
 */
final class InvoiceService
{
    private const API_BASE_URL  = 'https://api.nfe.io/v1';
    private const TIMEOUT_SECS  = 30;

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
        // Evita duplicata
        $existing = $this->invoiceModel->findByOrderId($orderId);
        if ($existing && in_array($existing['status'], ['issued', 'pending'], true)) {
            AppLogger::info('NFS-e já emitida ou em andamento', ['order_id' => $orderId]);
            return $existing;
        }

        // Cria registro pendente
        $invoiceId = $existing ? (int) $existing['id'] : $this->invoiceModel->create($orderId);
        if ($existing) {
            $this->invoiceModel->resetToPending($invoiceId);
        }

        $order = $this->orderModel->findById($orderId);
        if (!$order) {
            $this->invoiceModel->markError($invoiceId, "Pedido #$orderId não encontrado.");
            return null;
        }

        $cpf = preg_replace('/\D/', '', $order['customer_cpf'] ?? '');

        $apiKey     = env('NFEIO_API_KEY', '');
        $companyId  = env('NFEIO_COMPANY_ID', '');
        $env        = env('NFEIO_ENVIRONMENT', 'homologacao');

        if (empty($apiKey) || empty($companyId)) {
            $this->invoiceModel->markError($invoiceId, 'NFE.io não configurado (API key ou Company ID ausente).');
            AppLogger::warning('NFE.io não configurado', ['order_id' => $orderId]);
            return null;
        }

        $payload = [
            'cityServiceCode'         => '0107',   // Código do serviço no município
            'description'             => 'Cartão Presente — Serviços de Spa e Bem-Estar',
            'servicesAmount'          => (float) $order['total'],
            'environment'             => $env === 'producao' ? 1 : 2,
            'borrower'                => [
                'federalTaxNumber'    => $cpf,
                'name'                => $order['customer_name'],
                'email'               => $order['customer_email'],
                'address'             => [
                    'country'         => 'BRA',
                    'postalCode'      => '89012084',
                    'street'          => 'Rua Itaiópolis',
                    'number'          => '102',
                    'district'        => 'Centro',
                    'city'            => [
                        'code'        => '4202404',
                        'name'        => 'Blumenau',
                    ],
                    'state'           => 'SC',
                ],
            ],
        ];

        try {
            $response = $this->apiRequest(
                'POST',
                "/companies/{$companyId}/serviceinvoices",
                $payload,
                $apiKey
            );

            $providerInvoiceId = $response['id'] ?? null;
            $invoiceNumber     = $response['number'] ?? null;
            $status            = $response['flowStatus'] ?? '';

            if ($providerInvoiceId) {
                AppLogger::info('NFS-e enviada para emissão', [
                    'order_id'          => $orderId,
                    'provider_invoice_id' => $providerInvoiceId,
                    'status'            => $status,
                ]);

                // Se já foi emitida (ambiente de homologação pode retornar Issued)
                if (in_array($status, ['Issued', 'IssuedWithErrors'], true)) {
                    $this->invoiceModel->markIssued(
                        $invoiceId,
                        $providerInvoiceId,
                        (string) $invoiceNumber
                    );
                } else {
                    // Fica como pending — aguarda webhook do NFE.io ou polling
                    $stmt = $this->pdo->prepare(
                        "UPDATE invoices SET provider_invoice_id = ?, status = 'pending' WHERE id = ?"
                    );
                    $stmt->execute([$providerInvoiceId, $invoiceId]);
                }

                return $response;
            }

            $error = $response['message'] ?? 'Resposta inesperada da NFE.io';
            $this->invoiceModel->markError($invoiceId, $error);
            AppLogger::error('NFS-e: resposta sem ID', ['order_id' => $orderId, 'response' => $response]);
            return null;
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
     * Reemite nota fiscal (admin action).
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
     * Processa webhook do NFE.io para atualizar status da nota.
     */
    public function processWebhook(array $payload): bool
    {
        $providerInvoiceId = $payload['id'] ?? null;
        $flowStatus        = $payload['flowStatus'] ?? '';
        $invoiceNumber     = $payload['number'] ?? null;

        if (!$providerInvoiceId) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM invoices WHERE provider_invoice_id = ? LIMIT 1'
        );
        $stmt->execute([$providerInvoiceId]);
        $invoice = $stmt->fetch();

        if (!$invoice) {
            AppLogger::warning('Webhook NFE.io: invoice não encontrada', [
                'provider_invoice_id' => $providerInvoiceId,
            ]);
            return false;
        }

        if (in_array($flowStatus, ['Issued', 'IssuedWithErrors'], true)) {
            $this->invoiceModel->markIssued(
                (int) $invoice['id'],
                $providerInvoiceId,
                (string) $invoiceNumber
            );
        } elseif ($flowStatus === 'Error') {
            $this->invoiceModel->markError((int) $invoice['id'], $payload['errorMessages'] ?? 'Erro desconhecido');
        } elseif ($flowStatus === 'Cancelled') {
            $this->invoiceModel->markCancelled((int) $invoice['id']);
        }

        AppLogger::info('Webhook NFE.io processado', [
            'provider_invoice_id' => $providerInvoiceId,
            'status'              => $flowStatus,
        ]);

        return true;
    }

    // ─── HTTP Client simples (cURL) ──────────────────────────────────────────

    private function apiRequest(string $method, string $path, array $body, string $apiKey): array
    {
        $url = self::API_BASE_URL . $path;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECS,
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
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
            throw new \RuntimeException("NFE.io resposta não é JSON válido (HTTP $status): $raw");
        }

        if ($status >= 400) {
            $message = $decoded['message'] ?? "HTTP {$status}";
            throw new \RuntimeException("NFE.io API error: $message");
        }

        return $decoded;
    }
}
