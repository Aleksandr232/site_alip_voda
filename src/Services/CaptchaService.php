<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use InvalidArgumentException;

final class CaptchaService
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /** @return array{enabled: bool, site_key?: string} */
    public function publicConfig(): array
    {
        $siteKey = $this->siteKey();
        if ($siteKey === null || $this->secretKey() === null) {
            return ['enabled' => false];
        }

        return [
            'enabled' => true,
            'site_key' => $siteKey,
        ];
    }

    public function isEnabled(): bool
    {
        return $this->siteKey() !== null && $this->secretKey() !== null;
    }

    public function verify(mixed $token): void
    {
        $secret = $this->secretKey();
        if ($secret === null) {
            if (Config::get('APP_ENV', 'local') === 'local') {
                return;
            }

            throw new InvalidArgumentException('Капча не настроена на сервере');
        }

        $token = trim((string) $token);
        if ($token === '') {
            throw new InvalidArgumentException('Подтвердите, что вы не робот');
        }

        $result = $this->postForm([
            'secret' => $secret,
            'response' => $token,
            'remoteip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);

        if (($result['success'] ?? false) !== true) {
            throw new InvalidArgumentException('Проверка капчи не пройдена. Попробуйте снова');
        }
    }

    /** @param array<string, string> $fields @return array<string, mixed> */
    private function postForm(array $fields): array
    {
        $payload = http_build_query($fields);
        $raw = $this->post($payload);

        if ($raw === false || $raw === '') {
            error_log('Turnstile verify: empty response from Cloudflare');
            throw new InvalidArgumentException('Не удалось проверить капчу. Попробуйте позже');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            error_log('Turnstile verify: invalid JSON: ' . $raw);
            throw new InvalidArgumentException('Не удалось проверить капчу. Попробуйте позже');
        }

        return $decoded;
    }

    private function post(string $payload): string|false
    {
        if (function_exists('curl_init')) {
            $handle = curl_init(self::VERIFY_URL);
            if ($handle === false) {
                return false;
            }

            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            ]);

            $response = curl_exec($handle);
            curl_close($handle);

            return is_string($response) ? $response : false;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 10,
            ],
        ]);

        return @file_get_contents(self::VERIFY_URL, false, $context);
    }

    private function siteKey(): ?string
    {
        $value = trim((string) (Config::get('TURNSTILE_SITE_KEY') ?? ''));

        return $value !== '' ? $value : null;
    }

    private function secretKey(): ?string
    {
        $value = trim((string) (Config::get('TURNSTILE_SECRET_KEY') ?? ''));

        return $value !== '' ? $value : null;
    }
}
