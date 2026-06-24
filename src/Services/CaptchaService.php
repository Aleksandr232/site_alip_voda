<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class CaptchaService
{
    private const TTL_SECONDS = 600;
    private const SESSION_KEY = 'captcha_sum';
    private const SESSION_EXPIRES_KEY = 'captcha_expires';

    public function issue(): array
    {
        $this->ensureSession();

        $left = random_int(2, 12);
        $right = random_int(2, 12);

        $_SESSION[self::SESSION_KEY] = $left + $right;
        $_SESSION[self::SESSION_EXPIRES_KEY] = time() + self::TTL_SECONDS;

        return [
            'question' => "{$left} + {$right}",
        ];
    }

    public function verify(mixed $answer): void
    {
        $this->ensureSession();

        $answer = $this->normalizeAnswer($answer);
        if ($answer === '') {
            throw new InvalidArgumentException('Введите ответ на проверочный вопрос');
        }

        $expected = $_SESSION[self::SESSION_KEY] ?? null;
        $expiresAt = (int) ($_SESSION[self::SESSION_EXPIRES_KEY] ?? 0);

        if (!is_int($expected) || $expiresAt <= 0) {
            throw new InvalidArgumentException('Проверка устарела. Обновите пример и попробуйте снова');
        }

        if (time() > $expiresAt) {
            $this->clearChallenge();
            throw new InvalidArgumentException('Время ответа истекло. Обновите пример и попробуйте снова');
        }

        if ((int) $answer !== $expected) {
            throw new InvalidArgumentException('Неверный ответ. Проверьте пример и попробуйте снова');
        }

        $this->clearChallenge();
    }

    private function normalizeAnswer(mixed $answer): string
    {
        if (is_int($answer) || is_float($answer)) {
            return (string) max(0, (int) round($answer));
        }

        $text = trim((string) $answer);
        if ($text === '') {
            return '';
        }

        return preg_replace('/\D+/', '', $text) ?? '';
    }

    private function clearChallenge(): void
    {
        unset($_SESSION[self::SESSION_KEY], $_SESSION[self::SESSION_EXPIRES_KEY]);
    }

    private function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }
}
