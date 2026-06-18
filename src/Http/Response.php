<?php

declare(strict_types=1);

namespace App\Http;

final class Response
{
    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public static function error(string $message, int $status = 400, array $extra = []): void
    {
        self::json(array_merge(['success' => false, 'message' => $message], $extra), $status);
    }

    public static function success(mixed $data = [], int $status = 200): void
    {
        if (is_array($data)) {
            self::json(array_merge(['success' => true], $data), $status);
            return;
        }

        self::json(['success' => true, 'data' => $data], $status);
    }
}
