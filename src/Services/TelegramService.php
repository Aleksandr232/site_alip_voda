<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use CURLFile;
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
            $tempFile = $this->downloadImageToTemp($photoUrl);

            if ($tempFile !== null) {
                try {
                    return [
                        'message_id' => $this->sendPhotoFile($tempFile, $caption),
                        'with_photo' => true,
                    ];
                } finally {
                    @unlink($tempFile);
                }
            }

            try {
                return [
                    'message_id' => $this->sendPhoto($photoUrl, $caption),
                    'with_photo' => true,
                ];
            } catch (RuntimeException) {
                // fallback to text below
            }
        }

        return [
            'message_id' => $this->sendMessage($caption),
            'with_photo' => false,
        ];
    }

    private function sendPhotoFile(string $filePath, string $caption): int
    {
        $token = $this->botToken();
        $channelId = $this->channelId();

        if ($token === null || $channelId === null) {
            throw new RuntimeException('Telegram не настроен');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('Telegram API: curl недоступен для отправки фото');
        }

        $url = 'https://api.telegram.org/bot' . $token . '/sendPhoto';
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Telegram API: curl_init failed');
        }

        $fields = [
            'chat_id' => $channelId,
            'caption' => $caption,
            'parse_mode' => 'HTML',
            'photo' => new CURLFile($filePath),
        ];

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);

        $raw = curl_exec($handle);
        curl_close($handle);

        return $this->decodeMessageId($raw);
    }

    private function downloadImageToTemp(string $imageUrl): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $verifySsl = Config::get('RBC_RSS_SSL_VERIFY', 'true') !== 'false';
        $handle = curl_init($imageUrl);
        if ($handle === false) {
            return null;
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: image/avif,image/webp,image/*,*/*;q=0.8',
                'Referer: https://www.rbc.ru/',
            ],
        ]);

        $binary = curl_exec($handle);
        $code = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        curl_close($handle);

        if (!is_string($binary) || $binary === '' || $code < 200 || $code >= 300) {
            return null;
        }

        $extension = $this->guessImageExtension($imageUrl, $contentType);
        $tempFile = tempnam(sys_get_temp_dir(), 'rbc_img_');
        if ($tempFile === false) {
            return null;
        }

        $path = $tempFile . '.' . $extension;
        if (!@rename($tempFile, $path)) {
            $path = $tempFile;
        }

        if (@file_put_contents($path, $binary) === false) {
            @unlink($path);

            return null;
        }

        return $path;
    }

    private function guessImageExtension(string $imageUrl, string $contentType): string
    {
        if (str_contains($contentType, 'jpeg') || str_contains($contentType, 'jpg')) {
            return 'jpg';
        }

        if (str_contains($contentType, 'png')) {
            return 'png';
        }

        if (str_contains($contentType, 'webp')) {
            return 'webp';
        }

        if (preg_match('/\.(jpe?g|png|webp)(?:\?|$)/i', $imageUrl, $matches)) {
            return strtolower($matches[1] === 'jpeg' ? 'jpg' : $matches[1]);
        }

        return 'jpg';
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

        return $this->decodeMessageId($raw);
    }

    private function decodeMessageId(string|false $raw): int
    {
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
                CURLOPT_TIMEOUT => 30,
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
                'timeout' => 30,
            ],
        ]);

        return @file_get_contents($url, false, $context);
    }
}
