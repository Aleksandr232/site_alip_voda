<?php

declare(strict_types=1);

use App\Config;
use App\Services\SitemapService;

$root = __DIR__;

require $root . '/bootstrap.php';
Config::load($root);

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

try {
    \App\Database::connection();
    echo SitemapService::createDefault()->render();
} catch (Throwable $e) {
    error_log('sitemap.php: ' . $e->getMessage());
    http_response_code(500);
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\"></urlset>\n";
}
