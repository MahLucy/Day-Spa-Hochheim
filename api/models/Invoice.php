<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Invoice
{
    public function __construct(private readonly PDO $pdo) {}

    public function findByOrderId(int $orderId): array|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM invoices WHERE order_id = ? ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$orderId]);
        return $stmt->fetch();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invoices WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = 'i.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $where[]  = 'i.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        $whereStr = implode(' AND ', $where);
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare(
            "SELECT i.*, o.total AS order_total, c.name AS customer_name
             FROM invoices i
             JOIN orders o ON o.id = i.order_id
             JOIN customers c ON c.id = o.customer_id
             WHERE {$whereStr}
             ORDER BY i.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function create(int $orderId): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO invoices (order_id, provider, status)
             VALUES (:order_id, 'focusnfe', 'pending')"
        );
        $stmt->execute([':order_id' => $orderId]);
        return (int) $this->pdo->lastInsertId();
    }

    public function saveFocusRef(int $id, string $ref): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE invoices SET focusnfe_ref = ?, provider_invoice_id = ? WHERE id = ?'
        );
        $stmt->execute([$ref, $ref, $id]);
        return $stmt->rowCount() > 0;
    }

    public function markIssued(
        int     $id,
        string  $providerInvoiceId,
        string  $invoiceNumber,
        ?string $pdfPath = null,
        ?string $xmlPath = null,
        ?string $urlDanfse = null
    ): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE invoices
             SET status = :status,
                 provider_invoice_id = :provider_invoice_id,
                 invoice_number = :invoice_number,
                 pdf_path = :pdf_path,
                 xml_path = :xml_path,
                 url_danfse = :url_danfse,
                 issued_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            ':status'              => 'issued',
            ':provider_invoice_id' => $providerInvoiceId,
            ':invoice_number'      => $invoiceNumber,
            ':pdf_path'            => $pdfPath,
            ':xml_path'            => $xmlPath,
            ':url_danfse'          => $urlDanfse,
            ':id'                  => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function markError(int $id, string $errorMessage): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE invoices SET status = 'error', error_message = ? WHERE id = ?"
        );
        $stmt->execute([$errorMessage, $id]);
        return $stmt->rowCount() > 0;
    }

    public function markCancelled(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE invoices SET status = 'cancelled' WHERE id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function resetToPending(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE invoices
             SET status = 'pending', provider_invoice_id = NULL, focusnfe_ref = NULL,
                 invoice_number = NULL, error_message = NULL, issued_at = NULL,
                 url_danfse = NULL
             WHERE id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
