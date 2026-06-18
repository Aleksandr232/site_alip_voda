<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use InvalidArgumentException;
use RuntimeException;

final class AuthService
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly JwtService $jwt = new JwtService(),
    ) {
    }

    public function register(string $name, string $email, string $password): array
    {
        $this->validateCredentials($name, $email, $password);

        if ($this->users->findByEmail($email)) {
            throw new InvalidArgumentException('Пользователь с таким email уже существует');
        }

        $role = $this->users->countAll() === 0 ? 'admin' : 'editor';
        $user = $this->users->create(
            $name,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $role
        );

        return $this->buildAuthResponse($user);
    }

    public function login(string $email, string $password): array
    {
        $email = mb_strtolower(trim($email));
        if ($email === '' || $password === '') {
            throw new InvalidArgumentException('Укажите email и пароль');
        }

        $row = $this->users->findByEmail($email);
        if (!$row || !password_verify($password, $row['password_hash'])) {
            throw new InvalidArgumentException('Неверный email или пароль');
        }

        $user = new User(
            (int) $row['id'],
            $row['name'],
            $row['email'],
            $row['role'],
            $row['created_at'],
        );

        return $this->buildAuthResponse($user);
    }

    public function userFromToken(string $token): User
    {
        $payload = $this->jwt->decode($token);
        $userId = (int) ($payload['sub'] ?? 0);
        if ($userId <= 0) {
            throw new RuntimeException('Invalid token subject');
        }

        $user = $this->users->findById($userId);
        if (!$user) {
            throw new RuntimeException('User not found');
        }

        return $user;
    }

    private function buildAuthResponse(User $user): array
    {
        return [
            'token' => $this->jwt->createToken($user->id, $user->email, $user->role),
            'token_type' => 'Bearer',
            'user' => $user->toPublicArray(),
        ];
    }

    private function validateCredentials(string $name, string $email, string $password): void
    {
        $name = trim($name);
        $email = mb_strtolower(trim($email));

        if ($name === '' || mb_strlen($name) < 2) {
            throw new InvalidArgumentException('Имя должно содержать минимум 2 символа');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Некорректный email');
        }

        if (mb_strlen($password) < 6) {
            throw new InvalidArgumentException('Пароль должен быть не короче 6 символов');
        }
    }
}
