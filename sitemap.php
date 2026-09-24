<?php

declare(strict_types=1);

use App\Config;
use App\Services\SearchPingService;
use App\Services\SitemapService;

$root = __DIR__;

require $root . '/bootstrap.php';
Config::load($root);

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$service = SitemapService::createDefault();

try {
    echo $service->render();
    SearchPingService::pingDaily();
} catch (Throwable $e) {
    error_log('sitemap.php: ' . $e->getMessage());
    echo $service->fallback();
}
