<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Validator;
use App\Helpers\AppLogger;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Service;
use PDO;

/**
 * Cria e gerencia pedidos.
 * Regras de negócio:
 *  - CPF validado antes de salvar
 *  - Serviços devem existir e estar ativos
 *  - Valores calculados server-side (nunca confia no frontend)
 *  - Transação atômica: customer + order + items criados ou nada
 */
final class OrderService
{
    private Customer $customerModel;
    private Order    $orderModel;
    private Service  $serviceModel;

    public function __construct(private readonly PDO $pdo)
    {
        $this->customerModel = new Customer($pdo);
        $this->orderModel    = new Order($pdo);
        $this->serviceModel  = new Service($pdo);
    }

    /**
     * Cria um pedido completo.
     *
     * @param array $customerData  Dados do comprador
     * @param array $items         [ ['service_id' => int, 'quantity' => int], ... ]
     * @param array $options       ['gift_card_format', 'notes', 'discount']
     * @return array               ['order_id', 'customer_id', 'total', 'items']
     * @throws \InvalidArgumentException para dados inválidos
     * @throws \RuntimeException         para erros de banco
     */
    public function createOrder(array $customerData, array $items, array $options = []): array
    {
        $this->validateCustomer($customerData);
        $resolvedItems = $this->resolveAndValidateItems($items);

        $subtotal = array_sum(array_column($resolvedItems, 'subtotal'));
        $discount = (float) ($options['discount'] ?? 0);
        $total    = max(0, $subtotal - $discount);

        $this->pdo->beginTransaction();
        try {
            // Upsert do cliente (por CPF)
            $customerId = $this->customerModel->upsert($customerData);

            $recipient = $options['recipient'] ?? null;

            // Cria o pedido
            $orderId = $this->orderModel->create($customerId, [
                'subtotal'         => number_format($subtotal, 2, '.', ''),
                'discount'         => number_format($discount, 2, '.', ''),
                'total'            => number_format($total, 2, '.', ''),
                'gift_card_format' => $options['gift_card_format'] ?? 'digital',
                'notes'            => $options['notes'] ?? null,
                'recipient_name'   => isset($recipient['name'])  ? trim($recipient['name'])  : null,
                'recipient_email'  => isset($recipient['email']) ? strtolower(trim($recipient['email'])) : null,
                'recipient_phone'  => isset($recipient['phone']) ? preg_replace('/\D/', '', $recipient['phone']) : null,
            ]);

            // Insere os itens
            foreach ($resolvedItems as $item) {
                $this->orderModel->createItem($orderId, $item);
            }

            $this->pdo->commit();

            AppLogger::info('Pedido criado', ['order_id' => $orderId, 'total' => $total]);

            return [
                'order_id'    => $orderId,
                'customer_id' => $customerId,
                'subtotal'    => $subtotal,
                'discount'    => $discount,
                'total'       => $total,
                'items'       => $resolvedItems,
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            AppLogger::error('Erro ao criar pedido', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Falha ao criar pedido: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Busca pedido com todos os detalhes para exibição.
     */
    public function getOrderDetail(int $orderId): array
    {
        $order = $this->orderModel->findById($orderId);
        if (!$order) {
            throw new \InvalidArgumentException("Pedido #{$orderId} não encontrado.");
        }

        $order['items'] = $this->orderModel->getItems($orderId);
        return $order;
    }

    // ─── Validações internas ──────────────────────────────────────────────────

    private function validateCustomer(array $data): void
    {
        $validator = new Validator();
        $validator->validate($data, [
            'name'  => 'required|string|min:2|max:255',
            'cpf'   => 'required|cpf',
            'email' => 'required|email',
            'phone' => 'phone',
        ]);

        if ($validator->hasErrors()) {
            throw new \InvalidArgumentException(
                json_encode($validator->getErrors(), JSON_UNESCAPED_UNICODE)
            );
        }
    }

    private function resolveAndValidateItems(array $items): array
    {
        if (empty($items)) {
            throw new \InvalidArgumentException('O pedido deve conter pelo menos um serviço.');
        }

        $serviceIds = array_column($items, 'service_id');
        $services   = $this->serviceModel->findManyByIds($serviceIds);
        $serviceMap = array_column($services, null, 'id');

        $resolved = [];
        foreach ($items as $item) {
            $serviceId = (int) $item['service_id'];
            $quantity  = max(1, (int) ($item['quantity'] ?? 1));

            if (!isset($serviceMap[$serviceId])) {
                throw new \InvalidArgumentException(
                    "Serviço #{$serviceId} não encontrado ou inativo."
                );
            }

            $service  = $serviceMap[$serviceId];
            $unitPrice = (float) $service['price'];
            $subtotal  = $unitPrice * $quantity;

            $resolved[] = [
                'service_id' => $serviceId,
                'quantity'   => $quantity,
                'unit_price' => number_format($unitPrice, 2, '.', ''),
                'subtotal'   => number_format($subtotal, 2, '.', ''),
                'snapshot'   => [
                    'id'               => $service['id'],
                    'name'             => $service['name'],
                    'category'         => $service['category'],
                    'description'      => $service['description'],
                    'price'            => $service['price'],
                    'duration_minutes' => $service['duration_minutes'],
                    'sessions'         => $service['sessions'],
                ],
            ];
        }

        return $resolved;
    }
}
