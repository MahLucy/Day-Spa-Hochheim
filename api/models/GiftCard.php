<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class GiftCard
{
    public function __construct(private readonly PDO $pdo) {}

    public function findByCode(string $code): array|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT gc.*, o.gift_card_format, o.total AS order_total,
                    o.recipient_name, o.recipient_email,
                    c.name AS customer_name, c.email AS customer_email
             FROM gift_cards gc
             JOIN orders o ON o.id = gc.order_id
             JOIN customers c ON c.id = o.customer_id
             WHERE gc.code = ? LIMIT 1'
        );
        $stmt->execute([$code]);
        return $stmt->fetch();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->pdo->prepare('SELECT * FROM gift_cards WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function findByOrderId(int $orderId): array|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM gift_cards WHERE order_id = ? LIMIT 1'
        );
        $stmt->execute([$orderId]);
        return $stmt->fetch();
    }

    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = 'gc.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['code'])) {
            $where[]  = 'gc.code LIKE ?';
            $params[] = '%' . $filters['code'] . '%';
        }
        if (!empty($filters['customer_name'])) {
            $where[]  = 'c.name LIKE ?';
            $params[] = '%' . $filters['customer_name'] . '%';
        }

        $whereStr = implode(' AND ', $where);
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare(
            "SELECT gc.*, c.name AS customer_name, c.email AS customer_email
             FROM gift_cards gc
             JOIN orders o ON o.id = gc.order_id
             JOIN customers c ON c.id = o.customer_id
             WHERE {$whereStr}
             ORDER BY gc.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function create(int $orderId, string $code, \DateTimeImmutable $validUntil): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO gift_cards (order_id, code, valid_until, status)
             VALUES (:order_id, :code, :valid_until, :status)'
        );
        $stmt->execute([
            ':order_id'   => $orderId,
            ':code'       => $code,
            ':valid_until' => $validUntil->format('Y-m-d'),
            ':status'     => 'active',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updatePaths(int $id, ?string $qrCodePath, ?string $pdfPath): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE gift_cards SET qr_code_path = ?, pdf_path = ? WHERE id = ?'
        );
        $stmt->execute([$qrCodePath, $pdfPath, $id]);
        return $stmt->rowCount() > 0;
    }

    public function createItem(int $giftCardId, array $item): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO gift_card_items (gift_card_id, service_name, service_duration, service_category, quantity)
             VALUES (:gift_card_id, :service_name, :service_duration, :service_category, :quantity)'
        );
        $stmt->execute([
            ':gift_card_id'     => $giftCardId,
            ':service_name'     => $item['service_name'],
            ':service_duration' => $item['service_duration'],
            ':service_category' => $item['service_category'],
            ':quantity'         => $item['quantity'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function getItems(int $giftCardId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM gift_card_items WHERE gift_card_id = ? ORDER BY id'
        );
        $stmt->execute([$giftCardId]);
        return $stmt->fetchAll();
    }

    public function redeem(string $code, string $redeemedBy, string $notes = ''): bool
    {
        $card = $this->findByCode($code);
        if (!$card) {
            return false;
        }
        if ($card['status'] !== 'active') {
            return false;
        }
        if (strtotime($card['valid_until']) < strtotime('today')) {
            // Auto-expirar
            $this->pdo->prepare('UPDATE gift_cards SET status = ? WHERE code = ?')
                 ->execute(['expired', $code]);
            return false;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE gift_cards
             SET status = 'redeemed', redeemed_at = NOW(), redeemed_by = ?, redemption_notes = ?
             WHERE code = ?"
        );
        $stmt->execute([$redeemedBy, $notes, $code]);
        return $stmt->rowCount() > 0;
    }

    /** Verifica e atualiza expirados automaticamente. */
    public function expireOutdated(): int
    {
        $stmt = $this->pdo->query(
            "UPDATE gift_cards SET status = 'expired'
             WHERE status = 'active' AND valid_until < CURDATE()"
        );
        return $stmt->rowCount();
    }

    public function countByStatus(): array
    {
        $stmt = $this->pdo->query(
            'SELECT status, COUNT(*) AS total FROM gift_cards GROUP BY status'
        );
        return $stmt->fetchAll();
    }

    public function countActive(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM gift_cards WHERE status = 'active'"
        );
        return (int) $stmt->fetchColumn();
    }
}
