<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use InvalidArgumentException;

final class CaptchaService
{
    private const TTL_SECONDS = 600;

    public function issue(): array
    {
        $left = random_int(2, 12);
        $right = random_int(2, 12);
        $expiresAt = time() + self::TTL_SECONDS;

        $payload = implode(':', [(string) $left, (string) $right, (string) $expiresAt]);
        $signature = hash_hmac('sha256', $payload, $this->secret());

        return [
            'question' => "{$left} + {$right}",
            'token' => $signature . '.' . rtrim(strtr(base64_encode($payload), '+/', '-_'), '='),
        ];
    }

    public function verify(string $token, mixed $answer): void
    {
        $answer = $this->normalizeAnswer($answer);
        if ($answer === '') {
            throw new InvalidArgumentException('Введите ответ на проверочный вопрос');
        }

        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException('Проверка не пройдена. Обновите пример и попробуйте снова');
        }

        [$signature, $encodedPayload] = $parts;
        $payload = base64_decode(strtr($encodedPayload, '-_', '+/'), true);
        if ($payload === false || !str_contains($payload, ':')) {
            throw new InvalidArgumentException('Проверка не пройдена. Обновите пример и попробуйте снова');
        }

        $expectedSignature = hash_hmac('sha256', $payload, $this->secret());
        if (!hash_equals($expectedSignature, $signature)) {
            throw new InvalidArgumentException('Проверка не пройдена. Обновите пример и попробуйте снова');
        }

        [$left, $right, $expiresAt] = explode(':', $payload, 3);
        if (!ctype_digit($left) || !ctype_digit($right) || !ctype_digit($expiresAt)) {
            throw new InvalidArgumentException('Проверка не пройдена. Обновите пример и попробуйте снова');
        }

        if (time() > (int) $expiresAt) {
            throw new InvalidArgumentException('Время ответа истекло. Обновите пример и попробуйте снова');
        }

        if ((int) $answer !== ((int) $left + (int) $right)) {
            throw new InvalidArgumentException('Неверный ответ. Проверьте пример и попробуйте снова');
        }
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

    private function secret(): string
    {
        $secret = Config::get('JWT_SECRET');
        if ($secret === null || $secret === '') {
            throw new \RuntimeException('JWT_SECRET is not configured');
        }

        return $secret;
    }
}
