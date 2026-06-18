<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Models\User;
use PDO;

final class UserRepository
{
    public function findByEmail(string $email): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, name, email, password_hash, role, created_at FROM users WHERE email = :email LIMIT 1'
        );
        $stmt->execute(['email' => mb_strtolower(trim($email))]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?User
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, name, email, role, created_at FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->mapUser($row) : null;
    }

    public function countAll(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public function create(string $name, string $email, string $passwordHash, string $role): User
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, :role)'
        );
        $stmt->execute([
            'name' => trim($name),
            'email' => mb_strtolower(trim($email)),
            'password_hash' => $passwordHash,
            'role' => $role,
        ]);

        $user = $this->findById((int) Database::connection()->lastInsertId());
        if (!$user) {
            throw new \RuntimeException('Failed to create user');
        }

        return $user;
    }

    private function mapUser(array $row): User
    {
        return new User(
            (int) $row['id'],
            $row['name'],
            $row['email'],
            $row['role'],
            $row['created_at'],
        );
    }
}
