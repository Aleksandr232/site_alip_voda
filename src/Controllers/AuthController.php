<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\User;
use App\Services\AuthService;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class AuthController
{
    public function __construct(
        private readonly AuthService $auth = new AuthService(),
    ) {
    }

    public function register(Request $request): void
    {
        try {
            $name = (string) ($request->body['name'] ?? '');
            $email = (string) ($request->body['email'] ?? '');
            $password = (string) ($request->body['password'] ?? '');

            $data = $this->auth->register($name, $email, $password);
            Response::success($data, 201);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            Response::error('Ошибка регистрации', 500);
        }
    }

    public function login(Request $request): void
    {
        try {
            $email = (string) ($request->body['email'] ?? '');
            $password = (string) ($request->body['password'] ?? '');

            $data = $this->auth->login($email, $password);
            Response::success($data);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 401);
        } catch (Throwable $e) {
            Response::error('Ошибка авторизации', 500);
        }
    }

    public function me(Request $request): void
    {
        try {
            $user = $this->requireUser($request);
            Response::success(['user' => $user->toPublicArray()]);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 401);
        } catch (Throwable $e) {
            Response::error('Ошибка получения профиля', 500);
        }
    }

    public function logout(): void
    {
        Response::success(['message' => 'Выход выполнен']);
    }

    public function requireUser(Request $request): User
    {
        $token = $request->bearerToken();
        if (!$token) {
            throw new RuntimeException('Требуется авторизация');
        }

        return $this->auth->userFromToken($token);
    }
}
