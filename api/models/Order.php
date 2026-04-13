<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Order
{
    public function __construct(private readonly PDO $pdo) {}

    public function findById(int $id): array|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT o.*, c.name AS customer_name, c.email AS customer_email,
                    c.phone AS customer_phone, c.cpf AS customer_cpf
             FROM orders o
             JOIN customers c ON c.id = o.customer_id
             WHERE o.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]    = 'o.status = ?';
            $params[]   = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $where[]  = 'o.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'o.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['customer_name'])) {
            $where[]  = 'c.name LIKE ?';
            $params[] = '%' . $filters['customer_name'] . '%';
        }

        $whereStr = implode(' AND ', $where);
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare(
            "SELECT o.*, c.name AS customer_name, c.email AS customer_email
             FROM orders o
             JOIN customers c ON c.id = o.customer_id
             WHERE {$whereStr}
             ORDER BY o.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countAll(array $filters = []): int
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = 'o.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $where[]  = 'o.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'o.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM orders o
             JOIN customers c ON c.id = o.customer_id
             WHERE ' . implode(' AND ', $where)
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function create(int $customerId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO orders (customer_id, status, subtotal, discount, total, gift_card_format, notes)
             VALUES (:customer_id, :status, :subtotal, :discount, :total, :gift_card_format, :notes)'
        );
        $stmt->execute([
            ':customer_id'      => $customerId,
            ':status'           => 'pending',
            ':subtotal'         => $data['subtotal'],
            ':discount'         => $data['discount'] ?? '0.00',
            ':total'            => $data['total'],
            ':gift_card_format' => $data['gift_card_format'] ?? 'digital',
            ':notes'            => $data['notes'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
        return $stmt->rowCount() > 0;
    }

    public function createItem(int $orderId, array $item): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO order_items (order_id, service_id, service_snapshot, quantity, unit_price, subtotal)
             VALUES (:order_id, :service_id, :service_snapshot, :quantity, :unit_price, :subtotal)'
        );
        $stmt->execute([
            ':order_id'         => $orderId,
            ':service_id'       => $item['service_id'],
            ':service_snapshot' => json_encode($item['snapshot'], JSON_UNESCAPED_UNICODE),
            ':quantity'         => $item['quantity'],
            ':unit_price'       => $item['unit_price'],
            ':subtotal'         => $item['subtotal'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function getItems(int $orderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT oi.*, s.name AS service_name_current
             FROM order_items oi
             LEFT JOIN services s ON s.id = oi.service_id
             WHERE oi.order_id = ?
             ORDER BY oi.id'
        );
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll();

        // Decodifica snapshots
        foreach ($items as &$item) {
            $item['service_snapshot'] = json_decode($item['service_snapshot'], true);
        }
        return $items;
    }

    /** Receita diária de hoje. */
    public function revenueToday(): string
    {
        $stmt = $this->pdo->query(
            "SELECT COALESCE(SUM(total), 0)
             FROM orders
             WHERE status IN ('paid', 'completed')
             AND DATE(created_at) = CURDATE()"
        );
        return (string) $stmt->fetchColumn();
    }

    /** Receita do mês atual. */
    public function revenueThisMonth(): string
    {
        $stmt = $this->pdo->query(
            "SELECT COALESCE(SUM(total), 0)
             FROM orders
             WHERE status IN ('paid', 'completed')
             AND YEAR(created_at) = YEAR(NOW())
             AND MONTH(created_at) = MONTH(NOW())"
        );
        return (string) $stmt->fetchColumn();
    }

    /** Faturamento por mês nos últimos N meses. */
    public function revenueByMonth(int $months = 12): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
                    COALESCE(SUM(total), 0)           AS revenue,
                    COUNT(*)                          AS orders
             FROM orders
             WHERE status IN ('paid', 'completed')
             AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
             GROUP BY month
             ORDER BY month ASC"
        );
        $stmt->execute([$months]);
        return $stmt->fetchAll();
    }

    /** Contagem de pedidos por status hoje. */
    public function countToday(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()"
        );
        return (int) $stmt->fetchColumn();
    }
}
