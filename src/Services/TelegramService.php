<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use RuntimeException;

final class TelegramService
{
    public function isConfigured(): bool
    {
        return $this->botToken() !== null && $this->channelId() !== null;
    }

    public function sendMessage(string $text, bool $disablePreview = false): int
    {
        return $this->apiCall('sendMessage', [
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => $disablePreview ? 'true' : 'false',
        ]);
    }

    public function sendPhoto(string $photoUrl, string $caption): int
    {
        return $this->apiCall('sendPhoto', [
            'photo' => $photoUrl,
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ]);
    }

    /** @return array{message_id: int, with_photo: bool} */
    public function sendNewsPost(string $caption, string $photoUrl = ''): array
    {
        $photoUrl = trim($photoUrl);
        if ($photoUrl !== '') {
            try {
                return [
                    'message_id' => $this->sendPhoto($photoUrl, $caption),
                    'with_photo' => true,
                ];
            } catch (RuntimeException $e) {
                if (!str_contains($e->getMessage(), 'wrong type') && !str_contains($e->getMessage(), 'failed to get')) {
                    throw $e;
                }
            }
        }

        return [
            'message_id' => $this->sendMessage($caption),
            'with_photo' => false,
        ];
    }

  /** @param array<string, string> $fields */
    private function apiCall(string $method, array $fields): int
    {
        $token = $this->botToken();
        $channelId = $this->channelId();

        if ($token === null || $channelId === null) {
            throw new RuntimeException('Telegram не настроен: укажите TELEGRAM_BOT_TOKEN и TELEGRAM_CHANNEL_ID в .env');
        }

        $url = 'https://api.telegram.org/bot' . $token . '/' . $method;
        $payload = http_build_query(array_merge(['chat_id' => $channelId], $fields));

        $raw = $this->post($url, $payload);
        if ($raw === false || $raw === '') {
            throw new RuntimeException('Telegram API: пустой ответ');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Telegram API: неверный JSON');
        }

        if (($decoded['ok'] ?? false) !== true) {
            $description = (string) ($decoded['description'] ?? 'неизвестная ошибка');
            throw new RuntimeException('Telegram API: ' . $description);
        }

        $messageId = $decoded['result']['message_id'] ?? null;

        return is_numeric($messageId) ? (int) $messageId : 0;
    }

    private function botToken(): ?string
    {
        $value = trim((string) (Config::get('TELEGRAM_BOT_TOKEN') ?? ''));

        return $value !== '' ? $value : null;
    }

    private function channelId(): ?string
    {
        $value = trim((string) (Config::get('TELEGRAM_CHANNEL_ID') ?? ''));

        return $value !== '' ? $value : null;
    }

    private function post(string $url, string $payload): string|false
    {
        if (function_exists('curl_init')) {
            $handle = curl_init($url);
            if ($handle === false) {
                return false;
            }

            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
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
                'timeout' => 20,
            ],
        ]);

        return @file_get_contents($url, false, $context);
    }
}
