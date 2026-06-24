<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $body,
        public readonly array $headers,
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = self::resolvePath();
        $body = self::parseBody($method);
        $headers = self::parseHeaders();

        return new self($method, $path, $body, $headers);
    }

    public function bearerToken(): ?string
    {
        $auth = $this->headers['authorization'] ?? $this->headers['Authorization'] ?? '';

        if ($auth === '' && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
        }

        if ($auth === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        if (preg_match('/Bearer\s+(\S+)/i', $auth, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private static function resolvePath(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        // /api/health или /папка-сайта/api/health
        if (preg_match('#/api(?:/(.*))?$#', $uri, $matches)) {
            $tail = trim($matches[1] ?? '', '/');
            $uri = $tail !== '' ? '/' . $tail : '/';
        } else {
            $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
            if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with($uri, $scriptDir)) {
                $uri = substr($uri, strlen($scriptDir)) ?: '/';
            }
        }

        $uri = '/' . trim($uri, '/');
        return $uri === '/' ? '/' : rtrim($uri, '/');
    }

    private static function parseBody(string $method): array
    {
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return [];
        }

        if (!empty($_POST)) {
            return $_POST;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        $raw = file_get_contents('php://input') ?: '';

        $trimmed = ltrim($raw);
        $looksJson = $trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[');

        if (str_contains($contentType, 'application/json') || $looksJson) {
            if ($raw === '') {
                return [];
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return [];
            }

            return $decoded;
        }

        if (str_contains($contentType, 'application/x-www-form-urlencoded') && $raw !== '') {
            $parsed = [];
            parse_str($raw, $parsed);

            return is_array($parsed) ? $parsed : [];
        }

        return $_POST;
    }

    private static function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }

        if (isset($_SERVER['AUTHORIZATION'])) {
            $headers['authorization'] = $_SERVER['AUTHORIZATION'];
        } elseif (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                $headers[strtolower($name)] = $value;
            }
        }

        return $headers;
    }
}
