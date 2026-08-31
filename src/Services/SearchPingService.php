<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;

final class SearchPingService
{
    public static function pingSitemap(): void
    {
        $sitemap = rtrim((string) (Config::get('APP_URL') ?: 'https://skyclin.ru'), '/') . '/sitemap.xml';
        $url = 'https://webmaster.yandex.ru/ping?sitemap=' . rawurlencode($sitemap);

        register_shutdown_function(static function () use ($url): void {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 2,
                    'ignore_errors' => true,
                    'header' => "User-Agent: SkyClinSitemapPing/1.0\r\n",
                ],
            ]);
            @file_get_contents($url, false, $context);
        });
    }
}
