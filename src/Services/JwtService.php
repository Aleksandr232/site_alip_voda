<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use RuntimeException;

final class JwtService
{
    public function encode(array $payload): string
    {
        $header = $this->base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'HS256'], JSON_THROW_ON_ERROR));
        $body = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', $header . '.' . $body, $this->secret(), true)
        );

        return $header . '.' . $body . '.' . $signature;
    }

    public function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid token format');
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;
        $expected = $this->base64UrlEncode(
            hash_hmac('sha256', $headerB64 . '.' . $payloadB64, $this->secret(), true)
        );

        if (!hash_equals($expected, $signatureB64)) {
            throw new RuntimeException('Invalid token signature');
        }

        $payload = json_decode($this->base64UrlDecode($payloadB64), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw new RuntimeException('Invalid token payload');
        }

        if (isset($payload['exp']) && time() >= (int) $payload['exp']) {
            throw new RuntimeException('Token expired');
        }

        return $payload;
    }

    public function createToken(int $userId, string $email, string $role): string
    {
        $ttl = (int) Config::get('JWT_TTL', '86400');
        $now = time();

        return $this->encode([
            'sub' => $userId,
            'email' => $email,
            'role' => $role,
            'iat' => $now,
            'exp' => $now + $ttl,
        ]);
    }

    private function secret(): string
    {
        return Config::require('JWT_SECRET');
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid token encoding');
        }

        return $decoded;
    }
}
