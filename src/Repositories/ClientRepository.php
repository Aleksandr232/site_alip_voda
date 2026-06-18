<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Models\Client;

final class ClientRepository
{
    public function findByPhone(string $phone): ?Client
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, name, phone, email, created_at FROM clients WHERE phone = :phone LIMIT 1'
        );
        $stmt->execute(['phone' => $phone]);
        $row = $stmt->fetch();

        return $row ? $this->map($row) : null;
    }

    public function create(string $name, string $phone, ?string $email = null): Client
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO clients (name, phone, email) VALUES (:name, :phone, :email)'
        );
        $stmt->execute([
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
        ]);

        $client = $this->findById((int) Database::connection()->lastInsertId());
        if (!$client) {
            throw new \RuntimeException('Не удалось создать клиента');
        }

        return $client;
    }

    public function updateName(int $id, string $name): void
    {
        $stmt = Database::connection()->prepare('UPDATE clients SET name = :name WHERE id = :id');
        $stmt->execute(['id' => $id, 'name' => $name]);
    }

    public function findById(int $id): ?Client
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, name, phone, email, created_at FROM clients WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->map($row) : null;
    }

    public function countAll(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM clients')->fetchColumn();
    }

    /** @return array<int, array<string, mixed>> */
    public function allWithStats(): array
    {
        $stmt = Database::connection()->query(
            'SELECT c.id, c.name, c.phone, c.email, c.created_at, c.updated_at,
                    COUNT(r.id) AS requests_count
             FROM clients c
             LEFT JOIN requests r ON r.client_id = c.id
             GROUP BY c.id, c.name, c.phone, c.email, c.created_at, c.updated_at
             ORDER BY c.updated_at DESC'
        );

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'phone' => $row['phone'],
                'email' => $row['email'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'requests_count' => (int) $row['requests_count'],
            ];
        }

        return $rows;
    }

    private function map(array $row): Client
    {
        return new Client(
            (int) $row['id'],
            $row['name'],
            $row['phone'],
            $row['email'],
            $row['created_at'],
        );
    }
}
