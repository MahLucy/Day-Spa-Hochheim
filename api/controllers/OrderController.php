<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\AppLogger;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Models\Order;
use App\Services\EmailService;
use App\Services\OrderService;

final class OrderController
{
    private OrderService $orderService;
    private Order        $orderModel;

    public function __construct()
    {
        $pdo                 = \Database::getInstance();
        $this->orderService  = new OrderService($pdo);
        $this->orderModel    = new Order($pdo);
    }

    /**
     * POST /api/orders
     *
     * Body:
     * {
     *   "customer": { "name", "cpf", "email", "phone", "birthdate", "gender" },
     *   "items": [ { "service_id": 1, "quantity": 1 }, ... ],
     *   "gift_card_format": "digital",
     *   "notes": ""
     * }
     */
    public function create(): never
    {
        $body = Validator::getJsonBody();

        // Valida estrutura mínima
        if (empty($body['customer']) || !is_array($body['customer'])) {
            Response::error('Campo "customer" é obrigatório.', 400);
        }
        if (empty($body['items']) || !is_array($body['items'])) {
            Response::error('Campo "items" é obrigatório e deve conter pelo menos um serviço.', 400);
        }

        $validator = new Validator();
        $validator->validate($body, [
            'gift_card_format' => 'in:digital,printed',
        ]);
        if ($validator->hasErrors()) {
            Response::validationError($validator->getErrors());
        }

        try {
            $result = $this->orderService->createOrder(
                $body['customer'],
                $body['items'],
                [
                    'gift_card_format' => $body['gift_card_format'] ?? 'digital',
                    'notes'            => $body['notes'] ?? null,
                ]
            );

            // Envia e-mail de confirmação do pedido (não-bloqueante)
            try {
                $emailService = new EmailService();
                $emailService->sendOrderConfirmation($result['order_id']);
            } catch (\Throwable $e) {
                AppLogger::warning('Falha ao enviar e-mail de confirmação', [
                    'order_id' => $result['order_id'],
                    'error'    => $e->getMessage(),
                ]);
            }

            Response::created([
                'order_id' => $result['order_id'],
                'total'    => $result['total'],
            ], 'Pedido criado. Prossiga com o pagamento.');
        } catch (\InvalidArgumentException $e) {
            $errors = json_decode($e->getMessage(), true);
            if (is_array($errors)) {
                Response::validationError($errors);
            }
            Response::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            AppLogger::error('Erro ao criar pedido', ['error' => $e->getMessage()]);
            Response::serverError('Falha ao processar pedido. Tente novamente.');
        }
    }

    // ─── Admin ──────────────────────────────────────────────────────────────

    /** GET /api/admin/orders */
    public function adminIndex(): never
    {
        $filters = [
            'status'        => $_GET['status'] ?? '',
            'date_from'     => $_GET['date_from'] ?? '',
            'date_to'       => $_GET['date_to'] ?? '',
            'customer_name' => $_GET['customer_name'] ?? '',
        ];

        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $limit  = min(100, max(10, (int) ($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $orders = $this->orderModel->findAll($filters, $limit, $offset);
        $total  = $this->orderModel->countAll($filters);

        Response::success([
            'orders'     => $orders,
            'total'      => $total,
            'page'       => $page,
            'limit'      => $limit,
            'last_page'  => (int) ceil($total / $limit),
        ]);
    }

    /** GET /api/admin/orders/:id */
    public function adminShow(int $id): never
    {
        try {
            $order = $this->orderService->getOrderDetail($id);
            Response::success($order);
        } catch (\InvalidArgumentException $e) {
            Response::notFound($e->getMessage());
        }
    }

    /** POST /api/admin/orders/:id/resend-email */
    public function resendEmail(int $id): never
    {
        $order = $this->orderModel->findById($id);
        if (!$order) {
            Response::notFound('Pedido não encontrado.');
        }

        try {
            $emailService = new EmailService();
            $emailService->sendGiftCard($id);
            AppLogger::adminAction('resend_email', 'order', $id);
            Response::success(null, 'E-mail reenviado com sucesso.');
        } catch (\Throwable $e) {
            AppLogger::error('Erro ao reenviar e-mail', [
                'order_id' => $id,
                'error'    => $e->getMessage(),
            ]);
            Response::serverError('Falha ao reenviar e-mail: ' . $e->getMessage());
        }
    }
}
