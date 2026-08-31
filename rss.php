<?php

declare(strict_types=1);

use App\Config;
use App\Services\BlogSsrService;

$root = __DIR__;

require $root . '/bootstrap.php';
Config::load($root);

header('Content-Type: application/rss+xml; charset=utf-8');
header('Cache-Control: public, max-age=1800');

try {
    echo BlogSsrService::createDefault()->renderRss();
} catch (Throwable $e) {
    error_log('rss.php: ' . $e->getMessage());
    http_response_code(500);
    echo '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>СкайКлин</title></channel></rss>';
}
