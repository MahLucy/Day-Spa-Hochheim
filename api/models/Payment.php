<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Payment
{
    public function __construct(private readonly PDO $pdo) {}

    public function findByOrderId(int $orderId): array|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM payments WHERE order_id = ? ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$orderId]);
        return $stmt->fetch();
    }

    public function findByProviderPaymentId(string $providerPaymentId): array|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM payments WHERE provider_payment_id = ? LIMIT 1'
        );
        $stmt->execute([$providerPaymentId]);
        return $stmt->fetch();
    }

    public function findByPreferenceId(string $preferenceId): array|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM payments WHERE provider_preference_id = ? LIMIT 1'
        );
        $stmt->execute([$preferenceId]);
        return $stmt->fetch();
    }

    public function create(int $orderId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO payments (order_id, provider, provider_preference_id, status, amount)
             VALUES (:order_id, :provider, :provider_preference_id, :status, :amount)'
        );
        $stmt->execute([
            ':order_id'               => $orderId,
            ':provider'               => $data['provider'] ?? 'mercadopago',
            ':provider_preference_id' => $data['provider_preference_id'] ?? null,
            ':status'                 => 'pending',
            ':amount'                 => $data['amount'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function markApproved(
        int    $id,
        string $providerPaymentId,
        string $method,
        int    $installments,
        array  $rawResponse
    ): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE payments
             SET status = :status,
                 provider_payment_id = :provider_payment_id,
                 method = :method,
                 installments = :installments,
                 paid_at = :paid_at,
                 raw_response = :raw_response
             WHERE id = :id'
        );
        $stmt->execute([
            ':status'              => 'approved',
            ':provider_payment_id' => $providerPaymentId,
            ':method'              => $method,
            ':installments'        => $installments,
            ':paid_at'             => date('Y-m-d H:i:s'),
            ':raw_response'        => json_encode($rawResponse, JSON_UNESCAPED_UNICODE),
            ':id'                  => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function updateStatus(int $id, string $status, array $rawResponse = []): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE payments SET status = ?, raw_response = ? WHERE id = ?'
        );
        $stmt->execute([
            $status,
            json_encode($rawResponse, JSON_UNESCAPED_UNICODE),
            $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function updateByOrderId(int $orderId, string $status, string $providerPaymentId = ''): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE payments SET status = ?, provider_payment_id = ? WHERE order_id = ?'
        );
        $stmt->execute([$status, $providerPaymentId, $orderId]);
        return $stmt->rowCount() > 0;
    }
}
