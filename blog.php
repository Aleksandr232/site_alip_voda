<?php

declare(strict_types=1);

use App\Config;
use App\Services\BlogSsrService;

$root = __DIR__;

require $root . '/bootstrap.php';
Config::load($root);

$template = $root . '/blog.html';
if (!is_file($template)) {
    http_response_code(500);
    echo 'Template not found';
    exit;
}

$html = (string) file_get_contents($template);

try {
    $html = BlogSsrService::createDefault()->injectBlogIndex($html);
} catch (Throwable $e) {
    error_log('blog.php: ' . $e->getMessage());
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=300');
echo $html;
