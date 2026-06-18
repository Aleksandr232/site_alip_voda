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
