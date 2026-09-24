<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\Repositories\BlogPostRepository;

final class SearchPingService
{
    /** Публичный ключ IndexNow: файл /{key}.txt на сайте. */
    public const INDEXNOW_KEY = '8f3a2c1b9e7d4a60b5c4d2e1f0a9b8c7';

    private const DAILY_TTL = 43200;

    /** @var list<string> */
    private const INDEXNOW_ENDPOINTS = [
        'https://yandex.com/indexnow',
        'https://api.indexnow.org/indexnow',
    ];

    public static function key(): string
    {
        $configured = Config::get('INDEXNOW_KEY');

        return $configured !== null && $configured !== '' ? $configured : self::INDEXNOW_KEY;
    }

    public static function baseUrl(): string
    {
        return rtrim((string) (Config::get('APP_URL') ?: 'https://skyclin.ru'), '/');
    }

    public static function pingSitemap(): void
    {
        self::submitUrls(self::collectPublicUrls());
    }

    /** @param list<string> $urls */
    public static function submitUrls(array $urls): void
    {
        $base = self::baseUrl();
        $normalized = [];

        foreach ($urls as $url) {
            $url = trim($url);
            if ($url === '') {
                continue;
            }
            if (!preg_match('#^https?://#i', $url)) {
                $url = $base . '/' . ltrim($url, '/');
            }
            $normalized[$url] = $url;
        }

        $list = array_values($normalized);
        if ($list === []) {
            return;
        }

        $sitemap = $base . '/sitemap.xml';

        register_shutdown_function(static function () use ($list, $sitemap): void {
            self::httpGet('https://webmaster.yandex.ru/ping?sitemap=' . rawurlencode($sitemap));
            self::sendIndexNow($list);
        });
    }

    public static function pingDaily(): void
    {
        $lockPath = dirname(__DIR__, 2) . '/storage/indexnow.last';
        $dir = dirname($lockPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $handle = @fopen($lockPath, 'c+');
        if ($handle === false) {
            self::pingSitemap();
            return;
        }

        try {
            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                return;
            }

            $raw = stream_get_contents($handle);
            $last = is_string($raw) ? (int) trim($raw) : 0;
            if ($last > 0 && (time() - $last) < self::DAILY_TTL) {
                return;
            }

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, (string) time());
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        self::pingSitemap();
    }

    /** @return list<string> */
    private static function collectPublicUrls(): array
    {
        $base = self::baseUrl();
        $urls = [
            $base . '/',
            $base . '/blog',
        ];

        try {
            foreach ((new BlogPostRepository())->all(true) as $post) {
                $urls[] = $base . '/article/' . $post->slug;
            }
        } catch (\Throwable) {
        }

        return $urls;
    }

    /** @param list<string> $urls */
    private static function sendIndexNow(array $urls): void
    {
        $key = self::key();
        $host = parse_url(self::baseUrl(), PHP_URL_HOST) ?: 'skyclin.ru';
        $payload = json_encode([
            'host' => $host,
            'key' => $key,
            'keyLocation' => self::baseUrl() . '/' . $key . '.txt',
            'urlList' => $urls,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (!is_string($payload) || $payload === '') {
            return;
        }

        foreach (self::INDEXNOW_ENDPOINTS as $endpoint) {
            self::httpPost($endpoint, $payload);
        }
    }

    private static function httpGet(string $url): void
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 3,
                'ignore_errors' => true,
                'header' => "User-Agent: SkyClinIndexNow/1.0\r\n",
            ],
        ]);
        @file_get_contents($url, false, $context);
    }

    private static function httpPost(string $url, string $payload): void
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => 3,
                'ignore_errors' => true,
                'header' => "Content-Type: application/json; charset=utf-8\r\n"
                    . "User-Agent: SkyClinIndexNow/1.0\r\n",
                'content' => $payload,
            ],
        ]);
        @file_get_contents($url, false, $context);
    }
}
