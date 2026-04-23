<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\AppLogger;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Middleware\WebhookMiddleware;
use App\Services\PaymentService;

final class PaymentController
{
    private PaymentService $paymentService;

    public function __construct()
    {
        $pdo                   = \Database::getInstance();
        $this->paymentService  = new PaymentService($pdo);
    }

    /**
     * POST /api/payments/create-preference
     *
     * Body: { "order_id": 123 }
     */
    public function createPreference(): never
    {
        $body = Validator::getJsonBody();

        $orderId = (int) ($body['order_id'] ?? 0);
        if ($orderId <= 0) {
            Response::error('order_id inválido.', 400);
        }

        try {
            $preference = $this->paymentService->createPreference($orderId);
            Response::success($preference, 'Preferência criada.');
        } catch (\InvalidArgumentException $e) {
            Response::notFound($e->getMessage());
        } catch (\RuntimeException $e) {
            AppLogger::error('Falha ao criar preferência MP', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
            Response::serverError('Falha ao iniciar pagamento. Tente novamente.');
        }
    }

    /**
     * POST /api/payments/process
     *
     * Processa pagamento via Checkout Bricks (transparente — sem redirect).
     * Recebe os dados tokenizados do Mercado Pago SDK e cria o pagamento
     * diretamente na API do MP. Nunca recebe dados raw de cartão.
     *
     * Body: { order_id, payment_method_id, token?, installments?, issuer_id?, payer }
     */
    public function processPayment(): never
    {
        $body    = Validator::getJsonBody();
        $orderId = (int) ($body['order_id'] ?? 0);

        if ($orderId <= 0) {
            Response::error('order_id inválido.', 400);
        }

        // Separa o order_id do restante (formData do SDK)
        $formData = array_filter($body, static fn($k) => $k !== 'order_id', ARRAY_FILTER_USE_KEY);

        if (empty($formData['payment_method_id'])) {
            Response::error('Método de pagamento não informado.', 400);
        }

        try {
            $result = $this->paymentService->processPayment($orderId, $formData);
            Response::success($result, 'Pagamento processado.');
        } catch (\InvalidArgumentException $e) {
            Response::notFound($e->getMessage());
        } catch (\RuntimeException $e) {
            AppLogger::error('Falha ao processar pagamento (bricks)', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
            Response::serverError('Falha ao processar pagamento. Tente novamente.');
        }
    }

    /**
     * POST /api/payments/mock-approve
     *
     * Simula aprovação de pagamento — disponível APENAS em APP_ENV=development.
     * Reproduz o fluxo completo do webhook sem passar pelo Mercado Pago.
     *
     * Body: { "order_id": 123 }
     */
    public function mockApprove(): never
    {
        if (env('APP_ENV') !== 'development') {
            Response::notFound('Rota não encontrada.');
        }

        $body    = Validator::getJsonBody();
        $orderId = (int) ($body['order_id'] ?? 0);

        if ($orderId <= 0) {
            Response::error('order_id inválido.', 400);
        }

        try {
            $this->paymentService->mockApprove($orderId);
            Response::success(
                ['order_id' => $orderId],
                'Pagamento simulado com sucesso. Gift Card em processamento.'
            );
        } catch (\InvalidArgumentException $e) {
            Response::notFound($e->getMessage());
        } catch (\Throwable $e) {
            AppLogger::error('Erro no mock de pagamento', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
            Response::serverError('Erro na simulação: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/payments/webhook
     *
     * Endpoint chamado pelo Mercado Pago após evento de pagamento.
     * NUNCA confia no payload — sempre consulta a API MP.
     */
    public function webhook(): never
    {
        // Rate limiting + extração de cabeçalhos de assinatura
        $headers = WebhookMiddleware::handle();

        $payload = Validator::getJsonBody();

        AppLogger::info('Webhook MP recebido', [
            'type'    => $payload['type'] ?? 'unknown',
            'data_id' => $payload['data']['id'] ?? 'unknown',
        ]);

        try {
            $result = $this->paymentService->processWebhook(
                $payload,
                $headers['signature'],
                $headers['request_id']
            );

            // MP espera 200 OK — qualquer outro código gera retentativa
            Response::success(['processed' => $result['processed']], 'OK');
        } catch (\RuntimeException $e) {
            $code = $e->getCode();
            if ($code === 401) {
                AppLogger::error('Assinatura inválida no webhook', ['error' => $e->getMessage()]);
                Response::unauthorized('Assinatura inválida.');
            }
            AppLogger::error('Erro ao processar webhook MP', ['error' => $e->getMessage()]);
            // Retorna 200 para evitar retentativas em erros não-transientes
            Response::success(['processed' => false], 'Erro interno registrado.');
        }
    }
}
