<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Service
{
    public function __construct(private readonly PDO $pdo) {}

    public function findAll(bool $activeOnly = true): array
    {
        $sql  = 'SELECT * FROM services';
        $args = [];

        if ($activeOnly) {
            $sql  .= ' WHERE active = 1';
        }

        $sql .= ' ORDER BY category, name';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetchAll();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->pdo->prepare('SELECT * FROM services WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function findManyByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM services WHERE id IN ({$placeholders}) AND active = 1"
        );
        $stmt->execute($ids);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO services (name, category, description, price, duration_minutes, sessions, image_path, active)
             VALUES (:name, :category, :description, :price, :duration_minutes, :sessions, :image_path, :active)'
        );
        $stmt->execute([
            ':name'             => $data['name'],
            ':category'         => $data['category'],
            ':description'      => $data['description'] ?? null,
            ':price'            => $data['price'],
            ':duration_minutes' => $data['duration_minutes'] ?? 60,
            ':sessions'         => $data['sessions'] ?? 1,
            ':image_path'       => $data['image_path'] ?? null,
            ':active'           => $data['active'] ?? 1,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        $allowed = ['name', 'category', 'description', 'price', 'duration_minutes', 'sessions', 'image_path', 'active'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[]          = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql  = 'UPDATE services SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    /** Soft-delete: apenas desativa. */
    public function deactivate(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE services SET active = 0 WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function updateImagePath(int $id, string $imagePath): bool
    {
        $stmt = $this->pdo->prepare('UPDATE services SET image_path = ? WHERE id = ?');
        $stmt->execute([$imagePath, $id]);
        return $stmt->rowCount() > 0;
    }

    public function countByCategory(): array
    {
        $stmt = $this->pdo->query(
            'SELECT category, COUNT(*) as total FROM services WHERE active = 1 GROUP BY category'
        );
        return $stmt->fetchAll();
    }

    public function topSelling(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.id, s.name, s.category, s.price,
                    COALESCE(SUM(oi.quantity), 0) AS total_sold
             FROM services s
             LEFT JOIN order_items oi ON oi.service_id = s.id
             LEFT JOIN orders o ON o.id = oi.order_id AND o.status IN (\'paid\', \'completed\')
             GROUP BY s.id, s.name, s.category, s.price
             ORDER BY total_sold DESC
             LIMIT ?'
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
}
