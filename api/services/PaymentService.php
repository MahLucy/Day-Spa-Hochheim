<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\AppLogger;
use App\Models\Order;
use App\Models\Payment;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Resources\Preference;
use PDO;

/**
 * Integração completa com Mercado Pago.
 *
 * Fluxo:
 * 1. Frontend chama POST /payments/create-preference com order_id
 * 2. Esta classe cria a preference no MP e retorna o preference_id
 * 3. Frontend usa o MP SDK para renderizar o checkout
 * 4. MP chama POST /payments/webhook com o resultado
 * 5. processWebhook() valida a assinatura, consulta o pagamento na API
 *    e dispara o fluxo pós-aprovação (GiftCard + Email + Invoice)
 */
final class PaymentService
{
    private Order   $orderModel;
    private Payment $paymentModel;

    public function __construct(private readonly PDO $pdo)
    {
        $this->orderModel   = new Order($pdo);
        $this->paymentModel = new Payment($pdo);

        MercadoPagoConfig::setAccessToken(env('MP_ACCESS_TOKEN'));
        MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);
    }

    /**
     * Processa pagamento via Checkout Bricks (checkout transparente).
     *
     * Recebe o formData tokenizado do SDK MP no frontend e cria o pagamento
     * diretamente via PaymentClient. O transaction_amount vem sempre do banco
     * de dados (nunca confia no valor enviado pelo frontend).
     *
     * @param int   $orderId  ID do pedido criado
     * @param array $formData Dados do formulário Bricks (token, payment_method_id, etc.)
     */
    public function processPayment(int $orderId, array $formData): array
    {
        $order = $this->orderModel->findById($orderId);
        if (!$order) {
            throw new \InvalidArgumentException("Pedido #{$orderId} não encontrado.");
        }
        if ($order['status'] !== 'pending') {
            throw new \RuntimeException("Pedido não está em estado pendente.");
        }

        $nameParts = explode(' ', trim($order['customer_name'] ?? ''), 2);
        $cpf       = preg_replace('/\D/', '', $order['customer_cpf'] ?? '');

        // Monta o payload — transaction_amount sempre do banco (segurança)
        $paymentData = [
            'transaction_amount' => (float) $order['total'],
            'description'        => 'Gift Card - Day Spa Hochheim',
            'external_reference' => (string) $orderId,
            'notification_url'   => env('MP_NOTIFICATION_URL'),
            'statement_descriptor' => 'DAY SPA HOCHHEIM',
            'payer'              => [
                'email'      => $order['customer_email'],
                'first_name' => $nameParts[0] ?? '',
                'last_name'  => $nameParts[1] ?? '',
            ],
        ];

        // CPF identificado via banco
        if (!empty($cpf)) {
            $paymentData['payer']['identification'] = ['type' => 'CPF', 'number' => $cpf];
        }

        // Campos seguros do formData do SDK
        foreach (['payment_method_id', 'token', 'installments', 'issuer_id'] as $field) {
            if (isset($formData[$field]) && $formData[$field] !== '' && $formData[$field] !== null) {
                $paymentData[$field] = $formData[$field];
            }
        }

        if (isset($paymentData['installments'])) {
            $paymentData['installments'] = (int) $paymentData['installments'];
        }

        try {
            $client  = new PaymentClient();
            $payment = $client->create($paymentData);

            $mpStatus     = $payment->status     ?? 'unknown';
            $mpPaymentId  = (string) ($payment->id ?? '');
            $mpType       = $payment->payment_type_id ?? '';
            $installments = (int) ($payment->installments ?? 1);
            $rawArr       = json_decode(json_encode($payment), true) ?? [];

            // Upsert do registro de pagamento
            $existing  = $this->paymentModel->findByOrderId($orderId);
            if ($existing) {
                $paymentDbId = (int) $existing['id'];
            } else {
                $paymentDbId = $this->paymentModel->create($orderId, [
                    'provider'               => 'mercadopago',
                    'provider_preference_id' => 'bricks-' . $orderId,
                    'amount'                 => $order['total'],
                ]);
            }

            if ($mpStatus === 'approved') {
                $this->paymentModel->markApproved(
                    $paymentDbId,
                    $mpPaymentId,
                    $this->normalizePaymentMethod($mpType),
                    $installments,
                    $rawArr
                );
                $this->orderModel->updateStatus($orderId, 'paid');

                AppLogger::info('Pagamento aprovado (checkout bricks)', [
                    'order_id'   => $orderId,
                    'payment_id' => $mpPaymentId,
                    'method'     => $mpType,
                ]);

                $this->triggerPostPaymentFlow($orderId);
            } else {
                $this->paymentModel->updateStatus($paymentDbId, $mpStatus, $rawArr);

                AppLogger::info('Pagamento pendente/recusado (checkout bricks)', [
                    'order_id'      => $orderId,
                    'payment_id'    => $mpPaymentId,
                    'status'        => $mpStatus,
                    'status_detail' => $payment->status_detail ?? '',
                ]);
            }

            $result = [
                'status'     => $mpStatus,
                'payment_id' => $mpPaymentId,
            ];

            // Dados extras para PIX (QR Code) e boleto
            if ($mpStatus === 'pending') {
                $qrCode   = $rawArr['point_of_interaction']['transaction_data']['qr_code'] ?? null;
                $qrBase64 = $rawArr['point_of_interaction']['transaction_data']['qr_code_base64'] ?? null;
                if ($qrCode) {
                    $result['qr_code']        = $qrCode;
                    $result['qr_code_base64'] = $qrBase64;
                }
                $boletoUrl = $rawArr['transaction_details']['external_resource_url'] ?? null;
                if ($boletoUrl) {
                    $result['boleto_url'] = $boletoUrl;
                }
            }

            return $result;

        } catch (\Throwable $e) {
            AppLogger::error('Erro ao criar pagamento MP (checkout bricks)', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
            throw new \RuntimeException('Falha ao processar pagamento: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Cria preferência de pagamento no Mercado Pago.
     */
    public function createPreference(int $orderId): array
    {
        $order = $this->orderModel->findById($orderId);
        if (!$order) {
            throw new \InvalidArgumentException("Pedido #{$orderId} não encontrado.");
        }
        if ($order['status'] !== 'pending') {
            throw new \RuntimeException("Pedido não está em estado pendente.");
        }

        $items = $this->orderModel->getItems($orderId);
        $mpItems = [];

        foreach ($items as $item) {
            $snapshot = $item['service_snapshot'];
            $mpItems[] = [
                'id'          => (string) $item['service_id'],
                'title'       => $snapshot['name'] ?? 'Serviço de Spa',
                'description' => $snapshot['description'] ?? '',
                'quantity'    => (int) $item['quantity'],
                'unit_price'  => (float) $item['unit_price'],
                'currency_id' => 'BRL',
                'category_id' => 'services',
            ];
        }

        $backUrl = rtrim(env('APP_URL', 'https://hochheim.com.br'), '/');

        $preferenceData = [
            'items'             => $mpItems,
            'payer'             => [
                'name'  => $order['customer_name'],
                'email' => $order['customer_email'],
            ],
            'payment_methods'   => [
                'installments'    => 12,
                'default_installments' => 1,
                'excluded_payment_methods' => [],
                'excluded_payment_types'   => [],
            ],
            'back_urls'         => [
                // MP adiciona ?collection_status=approved&external_reference=ID automaticamente
                'success' => "{$backUrl}/confirmacao",
                'failure' => "{$backUrl}/confirmacao",
                'pending' => "{$backUrl}/confirmacao",
            ],
            'notification_url'  => env('MP_NOTIFICATION_URL'),
            'external_reference' => (string) $orderId,
            'expires'           => true,
            'expiration_date_from' => date('c'),
            'expiration_date_to'   => date('c', strtotime('+24 hours')),
            'statement_descriptor' => 'DAY SPA HOCHHEIM',
        ];

        // Mercado Pago strict require: auto_return with HTTP URL fails.
        // It must be HTTPS.
        if (str_starts_with($backUrl, 'https://')) {
            $preferenceData['auto_return'] = 'approved';
        }

        try {
            $client     = new PreferenceClient();
            $preference = $client->create($preferenceData);

            // Salva ou atualiza o registro de pagamento
            $existing = $this->paymentModel->findByOrderId($orderId);
            if ($existing) {
                $this->paymentModel->updateByOrderId($orderId, 'pending', '');
            } else {
                $this->paymentModel->create($orderId, [
                    'provider'               => 'mercadopago',
                    'provider_preference_id' => $preference->id,
                    'amount'                 => $order['total'],
                ]);
            }

            AppLogger::info('Preferência MP criada', [
                'order_id'      => $orderId,
                'preference_id' => $preference->id,
            ]);

            return [
                'preference_id'  => $preference->id,
                'init_point'     => $preference->init_point,
                'sandbox_init_point' => $preference->sandbox_init_point ?? null,
            ];
        } catch (\Throwable $e) {
            AppLogger::error('Erro ao criar preferência MP', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
            throw new \RuntimeException('Falha ao iniciar pagamento: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Processa webhook do Mercado Pago.
     *
     * NUNCA confia no payload — sempre consulta a API do MP antes de processar.
     *
     * @param  array  $payload   Body JSON decodificado
     * @param  string $signature Cabeçalho X-Signature
     * @param  string $xRequestId Cabeçalho X-Request-Id
     * @return array  ['processed' => bool, 'order_id' => int|null]
     */
    public function processWebhook(array $payload, string $signature, string $xRequestId): array
    {
        // 1. Valida assinatura
        if (!$this->validateSignature($payload, $signature, $xRequestId)) {
            AppLogger::warning('Assinatura inválida no webhook MP', ['signature' => $signature]);
            throw new \RuntimeException('Assinatura do webhook inválida.', 401);
        }

        $topic  = $payload['type'] ?? $payload['topic'] ?? '';
        $dataId = $payload['data']['id'] ?? $payload['id'] ?? null;

        // Só processa notificações de pagamento
        if (!in_array($topic, ['payment', 'merchant_order'], true) || !$dataId) {
            AppLogger::info('Webhook MP ignorado', ['type' => $topic]);
            return ['processed' => false, 'order_id' => null];
        }

        // 2. Consulta pagamento direto na API MP (não confia no payload)
        try {
            $mpPayment = $this->fetchPaymentFromMP((string) $dataId);
        } catch (\Throwable $e) {
            AppLogger::error('Erro ao consultar pagamento MP', [
                'data_id' => $dataId,
                'error'   => $e->getMessage(),
            ]);
            throw $e;
        }

        $mpStatus       = $mpPayment['status'] ?? '';
        $orderId        = (int) ($mpPayment['external_reference'] ?? 0);
        $mpPaymentId    = (string) ($mpPayment['id'] ?? $dataId);
        $paymentMethod  = $this->normalizePaymentMethod($mpPayment['payment_type_id'] ?? '');
        $installments   = (int) ($mpPayment['installments'] ?? 1);

        if (!$orderId) {
            AppLogger::warning('Webhook MP: external_reference inválido', ['payload' => $mpPayment]);
            return ['processed' => false, 'order_id' => null];
        }

        // Idempotência: ignora se já processamos este pagamento
        $existingPayment = $this->paymentModel->findByProviderPaymentId($mpPaymentId);
        if ($existingPayment && $existingPayment['status'] === 'approved') {
            AppLogger::info('Webhook MP: pagamento já processado', ['payment_id' => $mpPaymentId]);
            return ['processed' => true, 'order_id' => $orderId];
        }

        $order = $this->orderModel->findById($orderId);
        if (!$order) {
            AppLogger::error('Webhook MP: pedido não encontrado', ['order_id' => $orderId]);
            return ['processed' => false, 'order_id' => null];
        }

        $payment = $this->paymentModel->findByOrderId($orderId);
        $paymentId = $payment ? (int) $payment['id'] : 0;

        match ($mpStatus) {
            'approved' => $this->handleApproved(
                $orderId, $paymentId, $mpPaymentId, $paymentMethod, $installments, $mpPayment
            ),
            'rejected', 'cancelled' => $this->handleRejected($orderId, $paymentId, $mpStatus, $mpPayment),
            default => AppLogger::info('Webhook MP: status não processado', ['status' => $mpStatus]),
        };

        return ['processed' => true, 'order_id' => $orderId];
    }

    /**
     * Simula aprovação de pagamento — SOMENTE em desenvolvimento.
     *
     * Reproduz exatamente o fluxo do webhook após um pagamento aprovado:
     * cria o registro de pagamento, atualiza o pedido, gera o Gift Card,
     * envia e-mail e emite a NFS-e.
     *
     * @throws \InvalidArgumentException se pedido não existir
     */
    public function mockApprove(int $orderId): void
    {
        $order = $this->orderModel->findById($orderId);
        if (!$order) {
            throw new \InvalidArgumentException("Pedido #{$orderId} não encontrado.");
        }

        $mockPaymentId = 'MOCK-' . strtoupper(bin2hex(random_bytes(4))) . '-' . $orderId;

        // Cria ou atualiza registro de pagamento
        $payment = $this->paymentModel->findByOrderId($orderId);

        if ($payment) {
            $this->paymentModel->markApproved(
                (int) $payment['id'],
                $mockPaymentId,
                'credit_card',
                1,
                ['mock' => true, 'simulated_at' => date('c')]
            );
        } else {
            $paymentId = $this->paymentModel->create($orderId, [
                'provider'               => 'mock',
                'provider_preference_id' => 'MOCK_PREF_' . $orderId,
                'amount'                 => $order['total'],
            ]);
            $this->paymentModel->markApproved(
                $paymentId,
                $mockPaymentId,
                'credit_card',
                1,
                ['mock' => true, 'simulated_at' => date('c')]
            );
        }

        $this->orderModel->updateStatus($orderId, 'paid');

        AppLogger::info('Pagamento mock aprovado (simulação de teste)', [
            'order_id'      => $orderId,
            'mock_payment'  => $mockPaymentId,
        ]);

        // Dispara o mesmo fluxo pós-aprovação do webhook real
        $this->triggerPostPaymentFlow($orderId);
    }

    // ─── Privados ────────────────────────────────────────────────────────────

    private function handleApproved(
        int    $orderId,
        int    $paymentId,
        string $mpPaymentId,
        string $method,
        int    $installments,
        array  $rawResponse
    ): void {
        if ($paymentId) {
            $this->paymentModel->markApproved($paymentId, $mpPaymentId, $method, $installments, $rawResponse);
        }

        $this->orderModel->updateStatus($orderId, 'paid');

        AppLogger::info('Pagamento aprovado', [
            'order_id'   => $orderId,
            'payment_id' => $mpPaymentId,
            'method'     => $method,
        ]);

        // Dispara o fluxo pós-aprovação (síncrono)
        $this->triggerPostPaymentFlow($orderId);
    }

    private function handleRejected(int $orderId, int $paymentId, string $status, array $raw): void
    {
        if ($paymentId) {
            $this->paymentModel->updateStatus($paymentId, $status, $raw);
        }

        $this->orderModel->updateStatus($orderId, $status === 'cancelled' ? 'cancelled' : 'pending');

        AppLogger::info('Pagamento rejeitado/cancelado', [
            'order_id' => $orderId,
            'status'   => $status,
        ]);

        // Envia e-mail de falha
        try {
            $order = $this->orderModel->findById($orderId);
            if ($order) {
                $emailService = new EmailService();
                $emailService->sendPaymentFailed($order);
            }
        } catch (\Throwable $e) {
            AppLogger::error('Erro ao enviar e-mail de pagamento falho', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Fluxo pós-aprovação:
     * 1. Gerar GiftCard (QR + PDF)
     * 2. Enviar e-mail
     * 3. Emitir NFS-e (independente — erro não bloqueia)
     */
    private function triggerPostPaymentFlow(int $orderId): void
    {
        // 1. Gerar Gift Card
        try {
            $giftCardService = new GiftCardService($this->pdo);
            $giftCardService->generate($orderId);
        } catch (\Throwable $e) {
            AppLogger::error('Erro ao gerar gift card', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
        }

        // 2. Enviar e-mail
        try {
            $order = $this->orderModel->findById($orderId);
            if ($order) {
                $emailService = new EmailService();
                $emailService->sendGiftCard($orderId);
            }
        } catch (\Throwable $e) {
            AppLogger::error('Erro ao enviar e-mail do gift card', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
        }

        // 3. Emitir NFS-e (erro não bloqueia o fluxo)
        try {
            $invoiceService = new InvoiceService($this->pdo);
            $invoiceService->issue($orderId);
        } catch (\Throwable $e) {
            AppLogger::error('Erro ao emitir NFS-e', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
            // Não relança — entrega do cartão não pode ser bloqueada
        }

        $this->orderModel->updateStatus($orderId, 'completed');
    }

    /**
     * Consulta pagamento diretamente na API do Mercado Pago.
     */
    private function fetchPaymentFromMP(string $paymentId): array
    {
        $client  = new PaymentClient();
        $payment = $client->get((int) $paymentId);

        if (!$payment || !$payment->id) {
            throw new \RuntimeException("Pagamento {$paymentId} não encontrado no Mercado Pago.");
        }

        // Converte para array
        return json_decode(json_encode($payment), true);
    }

    /**
     * Valida a assinatura do webhook conforme documentação do MP.
     * Formato: ts=<timestamp>,v1=<hmac>
     */
    private function validateSignature(array $payload, string $signature, string $xRequestId): bool
    {
        $secret = env('MP_WEBHOOK_SECRET', '');
        if (empty($secret)) {
            AppLogger::warning('MP_WEBHOOK_SECRET não configurado — validação ignorada');
            return true; // em produção, configure sempre
        }

        // Parseia ts e v1 da assinatura
        $parts = [];
        foreach (explode(',', $signature) as $part) {
            [$k, $v] = array_pad(explode('=', $part, 2), 2, '');
            $parts[$k] = $v;
        }

        if (empty($parts['ts']) || empty($parts['v1'])) {
            return false;
        }

        $dataId  = $payload['data']['id'] ?? '';
        $message = "id:{$dataId};request-id:{$xRequestId};ts:{$parts['ts']};";
        $hash    = hash_hmac('sha256', $message, $secret);

        return hash_equals($hash, $parts['v1']);
    }

    private function normalizePaymentMethod(string $mpType): string
    {
        return match ($mpType) {
            'credit_card'        => 'credit_card',
            'debit_card'         => 'debit_card',
            'bank_transfer'      => 'pix',
            'ticket'             => 'boleto',
            default              => 'credit_card',
        };
    }
}
