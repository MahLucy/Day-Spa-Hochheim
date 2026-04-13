<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use App\Helpers\Validator;

final class Customer
{
    public function __construct(private readonly PDO $pdo) {}

    public function findById(int $id): array|false
    {
        $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function findByCpf(string $cpf): array|false
    {
        $cpf  = preg_replace('/\D/', '', $cpf);
        $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE cpf = ? LIMIT 1');
        $stmt->execute([$cpf]);
        return $stmt->fetch();
    }

    public function findByEmail(string $email): array|false
    {
        $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    /**
     * Cria ou atualiza cliente por CPF.
     * Retorna o ID do customer.
     */
    public function upsert(array $data): int
    {
        $cpf      = preg_replace('/\D/', '', $data['cpf']);
        $existing = $this->findByCpf($cpf);

        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE customers
                 SET name = :name, email = :email, phone = :phone, whatsapp = :whatsapp,
                     birthdate = :birthdate, gender = :gender
                 WHERE id = :id'
            );
            $stmt->execute([
                ':name'      => $data['name'],
                ':email'     => $data['email'],
                ':phone'     => $data['phone'] ?? null,
                ':whatsapp'  => $data['whatsapp'] ?? null,
                ':birthdate' => Validator::dateToIso($data['birthdate'] ?? null),
                ':gender'    => $data['gender'] ?? null,
                ':id'        => $existing['id'],
            ]);
            return (int) $existing['id'];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO customers (name, cpf, birthdate, gender, email, phone, whatsapp)
             VALUES (:name, :cpf, :birthdate, :gender, :email, :phone, :whatsapp)'
        );
        $stmt->execute([
            ':name'      => $data['name'],
            ':cpf'       => $cpf,
            ':birthdate' => Validator::dateToIso($data['birthdate'] ?? null),
            ':gender'    => $data['gender'] ?? null,
            ':email'     => $data['email'],
            ':phone'     => $data['phone'] ?? null,
            ':whatsapp'  => $data['whatsapp'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findAll(int $limit = 100, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM customers ORDER BY created_at DESC LIMIT ? OFFSET ?'
        );
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }
}
