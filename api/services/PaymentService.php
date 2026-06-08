<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\AppLogger;
use App\Models\Order;
use App\Models\Payment;
use MercadoPago\MercadoPagoConfig;
use PDO;

/**
 * Integração com Mercado Pago via Orders API (v1/orders).
 *
 * Fluxo:
 * 1. Frontend renderiza o Payment Brick e envia formData tokenizado
 * 2. processPayment() cria uma order via POST /v1/orders
 * 3. Se aprovado, dispara fluxo pós-pagamento (GiftCard + Email + Invoice)
 * 4. Webhook recebe notificações do tópico "order" e consulta GET /v1/orders/{id}
 */
final class PaymentService
{
    private Order   $orderModel;
    private Payment $paymentModel;
    private string  $accessToken;

    public function __construct(private readonly PDO $pdo)
    {
        $this->orderModel   = new Order($pdo);
        $this->paymentModel = new Payment($pdo);
        $this->accessToken  = env('MP_ACCESS_TOKEN');

        MercadoPagoConfig::setAccessToken($this->accessToken);
    }

    /**
     * Processa pagamento via Orders API (checkout transparente).
     *
     * Recebe o formData tokenizado do Payment Brick e cria uma order
     * diretamente via POST /v1/orders. O valor vem sempre do banco (segurança).
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

        $totalAmount = number_format((float) $order['total'], 2, '.', '');

        // Determina o tipo de pagamento e monta payment_method
        $paymentMethodId   = $formData['payment_method_id'] ?? '';
        $paymentMethodType = $this->resolvePaymentMethodType($paymentMethodId, $formData);
        $token             = $formData['token'] ?? null;
        $installments      = min(3, max(1, isset($formData['installments']) ? (int) $formData['installments'] : 1));
        $deviceId          = $formData['device_id'] ?? '';

        AppLogger::info('processPayment — dados recebidos do frontend', [
            'order_id'            => $orderId,
            'payment_method_id'   => $paymentMethodId,
            'payment_method_type' => $paymentMethodType,
            'payment_type_input'  => $formData['payment_type'] ?? '(vazio)',
            'has_token'           => !empty($token),
            'installments'        => $installments,
            'issuer_id'           => $formData['issuer_id'] ?? '(vazio)',
            'has_payer'           => isset($formData['payer']),
            'has_device_id'       => !empty($deviceId),
        ]);

        $paymentEntry = [
            'amount' => $totalAmount,
            'payment_method' => [
                'id'   => $paymentMethodId,
                'type' => $paymentMethodType,
            ],
        ];

        if ($token) {
            $paymentEntry['payment_method']['token'] = $token;
        }
        if (in_array($paymentMethodType, ['credit_card', 'debit_card'])) {
            $paymentEntry['payment_method']['installments']        = $installments;
            $paymentEntry['payment_method']['statement_descriptor'] = 'DAY SPA HOCHHEIM';
            // Nota: issuer_id não é aceito pela Orders API (v1/orders) como
            // additionalProperty em payment_method — usado apenas na Payments API.
        }

        // Pix: definir expiração de 30 minutos
        if ($paymentMethodType === 'bank_transfer') {
            $paymentEntry['expiration_time'] = 'PT30M';
        }

        // Em sandbox/dev, a API do MP exige email com @testuser.com
        $payerEmail = $formData['payer']['email'] ?? $order['customer_email'];
        $payerCpf   = preg_replace('/\D/', '', $order['customer_cpf'] ?? '');
        if (env('APP_ENV') === 'development') {
            $payerEmail = env('MP_TEST_PAYER_EMAIL', 'test_user_123@testuser.com');
            if (!empty($payerCpf)) {
                $payerCpf = env('MP_TEST_PAYER_CPF', '12345678909');
            }
        }

        $nameParts = explode(' ', trim($order['customer_name'] ?? ''), 2);

        // Monta o payload da Orders API.
        // IMPORTANTE: para Pix (bank_transfer), a documentação oficial só aceita payer.email.
        // Campos extras (first_name, last_name, identification, phone) causam processing_error no Pix.
        // Para cartão e boleto, enviamos o payer completo pois o antifraude se beneficia dos dados.
        $isPixPayment = ($paymentMethodType === 'bank_transfer');

        $payerData = ['email' => $payerEmail];

        if (!$isPixPayment) {
            $payerData['first_name'] = $nameParts[0] ?? '';
            $payerData['last_name']  = $nameParts[1] ?? $nameParts[0] ?? '';
        }

        $orderPayload = [
            'type'               => 'online',
            'processing_mode'    => 'automatic',
            'total_amount'       => $totalAmount,
            'external_reference' => (string) $orderId,
            'payer'              => $payerData,
            'transactions'       => [
                'payments' => [$paymentEntry],
            ],
        ];

        // config.online.transaction_security é exclusivo para cartão de crédito/débito (3DS).
        // Não deve ser enviado para Pix, boleto ou outros métodos — causa processing_error.
        if (in_array($paymentMethodType, ['credit_card', 'debit_card'], true)) {
            $orderPayload['config'] = [
                'online' => [
                    'transaction_security' => [
                        'validation'      => 'on_fraud_risk',
                        'liability_shift' => 'required',
                    ],
                ],
            ];
        }

        // Identificação do pagador (CPF) — apenas para cartão e boleto
        if (!$isPixPayment) {
            if (!empty($payerCpf)) {
                $orderPayload['payer']['identification'] = ['type' => 'CPF', 'number' => $payerCpf];
            } elseif (isset($formData['payer']['identification'])) {
                $orderPayload['payer']['identification'] = $formData['payer']['identification'];
            }
        }

        // Telefone do pagador — apenas para cartão e boleto (melhora antifraude)
        if (!$isPixPayment) {
            $payerPhone = preg_replace('/\D/', '', $order['customer_phone'] ?? '');
            if (strlen($payerPhone) >= 10) {
                $orderPayload['payer']['phone'] = [
                    'area_code' => substr($payerPhone, 0, 2),
                    'number'    => substr($payerPhone, 2),
                ];
            }
        }

        // Endereço do pagador — obrigatório para boleto (Brick coleta automaticamente)
        if ($paymentMethodType === 'ticket') {
            $addr = $formData['payer']['address'] ?? [];
            if (!empty($addr)) {
                $orderPayload['payer']['address'] = [
                    'zip_code'      => preg_replace('/\D/', '', $addr['zip_code'] ?? ''),
                    'street_name'   => $addr['street_name']   ?? '',
                    'street_number' => $addr['street_number'] ?? '',
                    'neighborhood'  => $addr['neighborhood']  ?? '',
                    'city'          => $addr['city']          ?? '',
                    'state'         => $addr['federal_unit']  ?? $addr['state'] ?? '',
                ];
            }
        }

        // Items do pedido — exigido pelo quality checklist do Mercado Pago.
        // ATENÇÃO: para Pix, a documentação oficial NÃO inclui items no payload.
        // Enviar items para Pix pode causar processing_error internamente no MP.
        if (!$isPixPayment) {
            $orderItems = $this->orderModel->getItems($orderId);
            $mpItems    = [];
            foreach ($orderItems as $item) {
                $snapshot  = $item['service_snapshot'];
                $qty       = (int) $item['quantity'];
                $mpItems[] = [
                    'title'       => $snapshot['name'] ?? 'Serviço de Spa',
                    'description' => $snapshot['description'] ?? ($snapshot['name'] ?? 'Serviço de Spa'),
                    'category_id' => 'services',
                    'quantity'    => $qty,
                    'unit_price'  => number_format((float) $item['unit_price'], 2, '.', ''),
                ];
            }
            if (!empty($mpItems)) {
                $orderPayload['items'] = $mpItems;
            }
        }


        try {
            $response = $this->callOrdersApi('POST', '/v1/orders', $orderPayload, $deviceId);

            $mpOrderId    = $response['id'] ?? '';
            $mpStatus     = $response['status'] ?? 'unknown';
            $mpStatusDet  = $response['status_detail'] ?? '';
            $mpPaymentId  = $response['transactions']['payments'][0]['id'] ?? '';
            $payStatus    = $response['transactions']['payments'][0]['status'] ?? $mpStatus;
            $payStatusDet = $response['transactions']['payments'][0]['status_detail'] ?? $mpStatusDet;

            // Upsert do registro de pagamento no banco
            $existing  = $this->paymentModel->findByOrderId($orderId);
            if ($existing) {
                $paymentDbId = (int) $existing['id'];
            } else {
                $paymentDbId = $this->paymentModel->create($orderId, [
                    'provider'               => 'mercadopago',
                    'provider_preference_id' => $mpOrderId,
                    'amount'                 => $order['total'],
                ]);
            }

            // Mapeia status da Orders API
            if ($mpStatus === 'processed' && $payStatusDet === 'accredited') {
                $this->paymentModel->markApproved(
                    $paymentDbId,
                    $mpPaymentId,
                    $this->normalizePaymentMethod($paymentMethodType),
                    $installments,
                    $response
                );
                $this->orderModel->updateStatus($orderId, 'paid');

                AppLogger::info('Pagamento aprovado (Orders API)', [
                    'order_id'    => $orderId,
                    'mp_order_id' => $mpOrderId,
                    'payment_id'  => $mpPaymentId,
                ]);

                $this->triggerPostPaymentFlow($orderId);

                return ['status' => 'approved', 'payment_id' => $mpPaymentId];
            }

            // Pagamento pendente (PIX, boleto, 3DS challenge, etc.)
            if ($mpStatus === 'action_required' || $payStatus === 'action_required') {
                $this->paymentModel->updateStatus($paymentDbId, 'pending', $response);

                $result = ['status' => 'pending', 'payment_id' => $mpPaymentId, 'order_id' => $mpOrderId];

                $pm = $response['transactions']['payments'][0]['payment_method'] ?? [];

                // 3DS Challenge — cartão de crédito/débito com validação pendente
                $ts = $pm['transaction_security'] ?? [];
                if (!empty($ts['validation_url'])) {
                    $result['three_ds_url'] = $ts['validation_url'];
                    AppLogger::info('3DS Challenge requerido (Orders API)', [
                        'order_id'       => $orderId,
                        'mp_order_id'    => $mpOrderId,
                        'validation_url' => $ts['validation_url'],
                    ]);
                    return $result;
                }

                // Dados de PIX
                if (!empty($pm['qr_code'])) {
                    $result['qr_code']        = $pm['qr_code'];
                    $result['qr_code_base64'] = $pm['qr_code_base64'] ?? null;
                }
                // Dados de Boleto
                if (!empty($pm['ticket_url'])) {
                    $result['boleto_url']      = $pm['ticket_url'];
                    $result['digitable_line']  = $pm['digitable_line'] ?? null;
                    $result['barcode_content'] = $pm['barcode_content'] ?? null;
                }

                AppLogger::info('Pagamento pendente (Orders API)', [
                    'order_id'      => $orderId,
                    'mp_order_id'   => $mpOrderId,
                    'status_detail' => $payStatusDet,
                ]);

                return $result;
            }

            // Pagamento recusado ou outro status — normaliza para o ENUM do banco
            $dbStatus = match ($mpStatus) {
                'failed', 'rejected' => 'rejected',
                'expired', 'reverted' => 'cancelled',
                default => 'rejected',
            };
            $this->paymentModel->updateStatus($paymentDbId, $dbStatus, $response);
            AppLogger::info('Pagamento não aprovado (Orders API)', [
                'order_id'      => $orderId,
                'status'        => $mpStatus,
                'status_detail' => $mpStatusDet,
            ]);

            return [
                'status'        => 'rejected',
                'payment_id'    => $mpPaymentId,
                'status_detail' => $payStatusDet ?: $mpStatusDet,
                'message'       => $this->getStatusMessage($payStatusDet ?: $mpStatusDet),
            ];

        } catch (\Throwable $e) {
            AppLogger::error('Erro ao criar order MP', [
                'order_id'   => $orderId,
                'error'      => $e->getMessage(),
                'class'      => get_class($e),
                'file'       => $e->getFile() . ':' . $e->getLine(),
            ]);
            throw new \RuntimeException('Falha ao processar pagamento: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Processa webhook do Mercado Pago (tópico "order").
     */
    public function processWebhook(array $payload, string $signature, string $xRequestId): array
    {
        if (!$this->validateSignature($payload, $signature, $xRequestId)) {
            AppLogger::warning('Assinatura inválida no webhook MP', ['signature' => $signature]);
            throw new \RuntimeException('Assinatura do webhook inválida.', 401);
        }

        $topic  = $payload['type'] ?? $payload['topic'] ?? '';
        $dataId = $payload['data']['id'] ?? $payload['id'] ?? null;

        // Processa notificações de order E payment (compatibilidade)
        if (!in_array($topic, ['order', 'payment', 'merchant_order'], true) || !$dataId) {
            AppLogger::info('Webhook MP ignorado', ['type' => $topic]);
            return ['processed' => false, 'order_id' => null];
        }

        try {
            if ($topic === 'order') {
                return $this->handleOrderWebhook((string) $dataId);
            }
            // Fallback: payment topic (API antiga, compatibilidade)
            return $this->handlePaymentWebhook((string) $dataId);
        } catch (\Throwable $e) {
            AppLogger::error('Erro ao processar webhook', [
                'data_id' => $dataId,
                'topic'   => $topic,
                'error'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Simula aprovação de pagamento — SOMENTE em desenvolvimento.
     */
    public function mockApprove(int $orderId): void
    {
        $order = $this->orderModel->findById($orderId);
        if (!$order) {
            throw new \InvalidArgumentException("Pedido #{$orderId} não encontrado.");
        }

        $mockPaymentId = 'MOCK-' . strtoupper(bin2hex(random_bytes(4))) . '-' . $orderId;

        $payment = $this->paymentModel->findByOrderId($orderId);
        if ($payment) {
            $this->paymentModel->markApproved(
                (int) $payment['id'], $mockPaymentId, 'credit_card', 1,
                ['mock' => true, 'simulated_at' => date('c')]
            );
        } else {
            $paymentId = $this->paymentModel->create($orderId, [
                'provider'               => 'mock',
                'provider_preference_id' => 'MOCK_PREF_' . $orderId,
                'amount'                 => $order['total'],
            ]);
            $this->paymentModel->markApproved(
                $paymentId, $mockPaymentId, 'credit_card', 1,
                ['mock' => true, 'simulated_at' => date('c')]
            );
        }

        $this->orderModel->updateStatus($orderId, 'paid');
        AppLogger::info('Pagamento mock aprovado', ['order_id' => $orderId]);
        $this->triggerPostPaymentFlow($orderId);
    }

    /**
     * Cria preferência de pagamento (mantido para compatibilidade Checkout Pro).
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

        $items     = $this->orderModel->getItems($orderId);
        $nameParts = explode(' ', trim($order['customer_name'] ?? ''), 2);
        $mpItems   = [];
        foreach ($items as $item) {
            $snapshot  = $item['service_snapshot'];
            $mpItems[] = [
                'id'          => (string) $item['service_id'],
                'title'       => $snapshot['name'] ?? 'Serviço de Spa',
                'description' => $snapshot['description'] ?? 'Gift Card Day Spa Hochheim',
                'category_id' => 'services',
                'quantity'    => (int) $item['quantity'],
                'unit_price'  => (float) $item['unit_price'],
                'currency_id' => 'BRL',
            ];
        }

        $prefCpf   = preg_replace('/\D/', '', $order['customer_cpf'] ?? '');
        $prefPhone = preg_replace('/\D/', '', $order['customer_phone'] ?? '');

        $payerData = [
            'name'    => $nameParts[0] ?? $order['customer_name'],
            'surname' => $nameParts[1] ?? '',
            'email'   => $order['customer_email'],
        ];
        if (!empty($prefCpf)) {
            $payerData['identification'] = ['type' => 'CPF', 'number' => $prefCpf];
        }
        if (strlen($prefPhone) >= 10) {
            $payerData['phone'] = [
                'area_code' => substr($prefPhone, 0, 2),
                'number'    => substr($prefPhone, 2),
            ];
        }

        $backUrl = rtrim(env('APP_URL', 'https://hochheim.com.br'), '/');
        $preferenceData = [
            'items'       => $mpItems,
            'payer'       => $payerData,
            'back_urls'   => [
                'success' => "{$backUrl}/confirmacao",
                'failure' => "{$backUrl}/confirmacao",
                'pending' => "{$backUrl}/confirmacao",
            ],
            'notification_url'   => env('MP_NOTIFICATION_URL'),
            'external_reference' => (string) $orderId,
            'statement_descriptor' => 'DAY SPA HOCHHEIM',
        ];

        if (str_starts_with($backUrl, 'https://')) {
            $preferenceData['auto_return'] = 'approved';
        }

        try {
            $client     = new \MercadoPago\Client\Preference\PreferenceClient();
            $preference = $client->create($preferenceData);

            $existing = $this->paymentModel->findByOrderId($orderId);
            if (!$existing) {
                $this->paymentModel->create($orderId, [
                    'provider'               => 'mercadopago',
                    'provider_preference_id' => $preference->id,
                    'amount'                 => $order['total'],
                ]);
            }

            return [
                'preference_id'      => $preference->id,
                'init_point'         => $preference->init_point,
                'sandbox_init_point' => $preference->sandbox_init_point ?? null,
            ];
        } catch (\Throwable $e) {
            AppLogger::error('Erro ao criar preferência MP', ['order_id' => $orderId, 'error' => $e->getMessage()]);
            throw new \RuntimeException('Falha ao iniciar pagamento: ' . $e->getMessage(), 0, $e);
        }
    }

    // ─── Privados ────────────────────────────────────────────────────────────

    /**
     * Chamada HTTP direta à API do Mercado Pago (Orders API).
     */
    private function callOrdersApi(string $method, string $endpoint, array $body = null, string $deviceId = ''): array
    {
        $url = 'https://api.mercadopago.com' . $endpoint;

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->accessToken,
            'X-Idempotency-Key: ' . $this->generateIdempotencyKey(),
        ];

        if (!empty($deviceId)) {
            $headers[] = 'X-meli-session-id: ' . $deviceId;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        if ($body !== null) {
            $jsonBody = json_encode($body, JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);

            AppLogger::info('Orders API request', [
                'method'   => $method,
                'endpoint' => $endpoint,
                'body'     => $body,
            ]);
        }

        // Captura headers da resposta para obter x-request-id (necessário para suporte MP)
        $responseHeaders = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$responseHeaders) {
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return strlen($header);
        });

        $rawResponse = curl_exec($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError   = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new \RuntimeException("Erro de conexão com Mercado Pago: {$curlError}");
        }

        $response = json_decode($rawResponse, true);
        if ($response === null) {
            throw new \RuntimeException("Resposta inválida do Mercado Pago (HTTP {$httpCode})");
        }

        AppLogger::info('Orders API response', [
            'http_code'    => $httpCode,
            'x-request-id' => $responseHeaders['x-request-id'] ?? $responseHeaders['x-requestid'] ?? null,
            'response'  => $response,
        ]);

        // HTTP 402 = pagamento processado mas rejeitado pelo emissor ou sandbox.
        // A resposta inclui os dados da order em response['data'] — retorna para
        // processPayment() tratar como status 'failed'/'rejected'.
        if ($httpCode === 402 && isset($response['data'])) {
            AppLogger::info('Orders API payment failed (402)', [
                'data' => $response['data'],
            ]);
            return $response['data'];
        }

        if ($httpCode >= 400) {
            // Suporta { message: "..." } e { errors: [{ message: "..." }] }
            $errorMsg = $response['message']
                ?? ($response['errors'][0]['message'] ?? null)
                ?? $response['error']
                ?? "Erro HTTP {$httpCode}";
            throw new \RuntimeException("Mercado Pago API: {$errorMsg} (HTTP {$httpCode})");
        }

        return $response;
    }

    /**
     * Consulta uma order diretamente na API do Mercado Pago.
     */
    private function fetchOrderFromMP(string $mpOrderId): array
    {
        return $this->callOrdersApi('GET', "/v1/orders/{$mpOrderId}");
    }

    /**
     * Processa webhook do tipo "order".
     */
    private function handleOrderWebhook(string $mpOrderId): array
    {
        $mpOrder = $this->fetchOrderFromMP($mpOrderId);

        $mpStatus    = $mpOrder['status'] ?? '';
        $extRef      = $mpOrder['external_reference'] ?? '';
        $orderId     = (int) $extRef;

        if (!$orderId) {
            AppLogger::warning('Webhook order: external_reference inválido', ['mp_order' => $mpOrder]);
            return ['processed' => false, 'order_id' => null];
        }

        $order = $this->orderModel->findById($orderId);
        if (!$order) {
            AppLogger::error('Webhook order: pedido não encontrado', ['order_id' => $orderId]);
            return ['processed' => false, 'order_id' => null];
        }

        $payment     = $this->paymentModel->findByOrderId($orderId);
        $paymentDbId = $payment ? (int) $payment['id'] : 0;
        $mpPaymentId = $mpOrder['transactions']['payments'][0]['id'] ?? '';
        $payMethod   = $mpOrder['transactions']['payments'][0]['payment_method']['type'] ?? '';
        $installments = (int) ($mpOrder['transactions']['payments'][0]['payment_method']['installments'] ?? 1);

        // Idempotência
        if ($payment && $payment['status'] === 'approved') {
            return ['processed' => true, 'order_id' => $orderId];
        }

        if ($mpStatus === 'processed') {
            if (!$paymentDbId) {
                $paymentDbId = $this->paymentModel->create($orderId, [
                    'provider'               => 'mercadopago',
                    'provider_preference_id' => $mpOrderId,
                    'amount'                 => $order['total'],
                ]);
            }
            $this->paymentModel->markApproved(
                $paymentDbId, $mpPaymentId,
                $this->normalizePaymentMethod($payMethod),
                $installments, $mpOrder
            );
            $this->orderModel->updateStatus($orderId, 'paid');
            AppLogger::info('Pagamento aprovado via webhook order', [
                'order_id' => $orderId, 'mp_order_id' => $mpOrderId,
            ]);
            $this->triggerPostPaymentFlow($orderId);
        } elseif (in_array($mpStatus, ['expired', 'reverted'], true)) {
            if ($paymentDbId) {
                $this->paymentModel->updateStatus($paymentDbId, 'cancelled', $mpOrder);
            }
            $this->orderModel->updateStatus($orderId, 'cancelled');
        }

        return ['processed' => true, 'order_id' => $orderId];
    }

    /**
     * Processa webhook do tipo "payment" (compatibilidade com API antiga).
     */
    private function handlePaymentWebhook(string $dataId): array
    {
        $client = new \MercadoPago\Client\Payment\PaymentClient();
        $mpPayment = $client->get((int) $dataId);
        if (!$mpPayment || !$mpPayment->id) {
            throw new \RuntimeException("Pagamento {$dataId} não encontrado no MP.");
        }

        $raw = json_decode(json_encode($mpPayment), true) ?? [];
        $mpStatus      = $raw['status'] ?? '';
        $orderId       = (int) ($raw['external_reference'] ?? 0);
        $mpPaymentId   = (string) ($raw['id'] ?? $dataId);
        $payMethod     = $this->normalizePaymentMethod($raw['payment_type_id'] ?? '');
        $installments  = (int) ($raw['installments'] ?? 1);

        if (!$orderId) return ['processed' => false, 'order_id' => null];

        $existing = $this->paymentModel->findByProviderPaymentId($mpPaymentId);
        if ($existing && $existing['status'] === 'approved') {
            return ['processed' => true, 'order_id' => $orderId];
        }

        $order = $this->orderModel->findById($orderId);
        if (!$order) return ['processed' => false, 'order_id' => null];

        $payment   = $this->paymentModel->findByOrderId($orderId);
        $paymentId = $payment ? (int) $payment['id'] : 0;

        // Idempotência por order_id — mais confiável que findByProviderPaymentId(),
        // pois a Orders API usa IDs no formato "PAY01KR…" (string) enquanto o webhook
        // de "payment" entrega IDs numéricos (ex: 157661666623), causando mismatch.
        if ($payment && $payment['status'] === 'approved') {
            AppLogger::info('Webhook payment: pagamento já aprovado — ignorado (idempotência)', [
                'order_id' => $orderId,
            ]);
            return ['processed' => true, 'order_id' => $orderId];
        }

        if ($mpStatus === 'approved') {
            if (!$paymentId) {
                $paymentId = $this->paymentModel->create($orderId, [
                    'provider' => 'mercadopago',
                    'provider_preference_id' => 'webhook-' . $orderId,
                    'amount' => $order['total'],
                ]);
            }
            $this->paymentModel->markApproved($paymentId, $mpPaymentId, $payMethod, $installments, $raw);
            $this->orderModel->updateStatus($orderId, 'paid');
            $this->triggerPostPaymentFlow($orderId);
        } elseif (in_array($mpStatus, ['rejected', 'cancelled'])) {
            if ($paymentId) $this->paymentModel->updateStatus($paymentId, $mpStatus, $raw);
            $this->orderModel->updateStatus($orderId, $mpStatus === 'cancelled' ? 'cancelled' : 'pending');
        }

        return ['processed' => true, 'order_id' => $orderId];
    }

    private function triggerPostPaymentFlow(int $orderId): void
    {
        // Idempotência atômica: garante que apenas UM processo execute este fluxo,
        // mesmo quando múltiplos webhooks (payment + order) chegam simultaneamente.
        // O UPDATE só afeta 1 linha se o status atual for 'paid'; qualquer outro processo
        // concorrente receberá rowCount=0 e encerra sem duplicar gift card, e-mail ou NFS-e.
        $stmt = $this->pdo->prepare(
            "UPDATE orders SET status = 'processing' WHERE id = ? AND status = 'paid'"
        );
        $stmt->execute([$orderId]);

        if ($stmt->rowCount() === 0) {
            AppLogger::info('triggerPostPaymentFlow ignorado — pedido já em processamento ou concluído', [
                'order_id' => $orderId,
            ]);
            return;
        }

        try {
            $giftCardService = new GiftCardService($this->pdo);
            $giftCardService->generate($orderId);
        } catch (\Throwable $e) {
            AppLogger::error('Erro ao gerar gift card', ['order_id' => $orderId, 'error' => $e->getMessage()]);
        }

        try {
            $order = $this->orderModel->findById($orderId);
            if ($order) {
                $emailService = new EmailService();
                $emailService->sendGiftCard($orderId);
                AppLogger::info('E-mail de gift card enviado', ['order_id' => $orderId]);
            }
        } catch (\Throwable $e) {
            AppLogger::error('Erro ao enviar e-mail do gift card', ['order_id' => $orderId, 'error' => $e->getMessage()]);
        }

        try {
            $invoiceService = new InvoiceService($this->pdo);
            $invoiceService->issue($orderId);
        } catch (\Throwable $e) {
            AppLogger::error('Erro ao emitir NFS-e', ['order_id' => $orderId, 'error' => $e->getMessage()]);
        }

        $this->orderModel->updateStatus($orderId, 'completed');
    }

    private function validateSignature(array $payload, string $signature, string $xRequestId): bool
    {
        $secret = env('MP_WEBHOOK_SECRET', '');
        if (empty($secret)) {
            AppLogger::warning('MP_WEBHOOK_SECRET não configurado — validação ignorada');
            return true;
        }

        $parts = [];
        foreach (explode(',', $signature) as $part) {
            [$k, $v] = array_pad(explode('=', $part, 2), 2, '');
            $parts[$k] = $v;
        }

        if (empty($parts['ts']) || empty($parts['v1'])) return false;

        // Conforme documentação: data.id deve ser em minúscula
        $dataId  = strtolower($payload['data']['id'] ?? '');
        $message = "id:{$dataId};request-id:{$xRequestId};ts:{$parts['ts']};";
        $hash    = hash_hmac('sha256', $message, $secret);

        return hash_equals($hash, $parts['v1']);
    }

    private function resolvePaymentMethodType(string $methodId, array $formData): string
    {
        if ($methodId === 'pix') return 'bank_transfer';

        // IDs de boleto bancário no Brasil — "boleto" como prefixo NÃO abrange todos
        // (ex: bolbradesco, bolsantander não começam com "boleto")
        $boletoIds = ['bolbradesco', 'bolsantander', 'boletobbrasil', 'pec'];
        if (str_starts_with($methodId, 'boleto') || in_array($methodId, $boletoIds, true)) {
            return 'ticket';
        }

        // Payment Brick envia paymentTypeId via additionalData no frontend
        $type = $formData['payment_type'] ?? $formData['paymentType'] ?? '';
        if ($type === 'bank_transfer') return 'bank_transfer';
        if ($type === 'ticket')        return 'ticket';
        if ($type === 'debit_card')    return 'debit_card';
        if ($type === 'credit_card')   return 'credit_card';

        // Cartões de débito possuem IDs com prefixo "deb"
        if (str_starts_with($methodId, 'deb')) return 'debit_card';

        // Cartões com token são credit_card por padrão
        if (!empty($formData['token'])) return 'credit_card';

        return 'credit_card';
    }

    private function normalizePaymentMethod(string $mpType): string
    {
        return match ($mpType) {
            'credit_card'   => 'credit_card',
            'debit_card'    => 'debit_card',
            'bank_transfer' => 'pix',
            'ticket'        => 'boleto',
            default         => $mpType ?: 'unknown',
        };
    }

    private function generateIdempotencyKey(): string
    {
        return sprintf(
            '%s-%s-%s',
            bin2hex(random_bytes(4)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(6))
        );
    }

    private function getStatusMessage(string $statusDetail): string
    {
        return match ($statusDetail) {
            'accredited'                    => 'Pagamento aprovado.',
            'pending_contingency'           => 'Pagamento pendente de análise.',
            'pending_review_manual'         => 'Pagamento pendente de revisão.',
            'cc_rejected_bad_filled_card_number' => 'Número do cartão inválido.',
            'cc_rejected_bad_filled_date'   => 'Data de validade inválida.',
            'cc_rejected_bad_filled_other'  => 'Verifique os dados do cartão.',
            'cc_rejected_bad_filled_security_code' => 'Código de segurança inválido.',
            'cc_rejected_blacklist'         => 'Cartão não autorizado.',
            'cc_rejected_call_for_authorize' => 'Pagamento necessita autorização.',
            'cc_rejected_card_disabled'     => 'Cartão desabilitado.',
            'cc_rejected_duplicated_payment' => 'Pagamento duplicado.',
            'cc_rejected_high_risk'         => 'Pagamento recusado por alto risco.',
            'cc_rejected_insufficient_amount' => 'Saldo insuficiente.',
            'cc_rejected_max_attempts'      => 'Tentativas excedidas.',
            'cc_rejected_other_reason'      => 'Pagamento recusado.',
            default                         => 'Pagamento não aprovado. Tente novamente.',
        };
    }
}
