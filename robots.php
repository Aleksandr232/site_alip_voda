<?php

declare(strict_types=1);

use App\Config;

$root = __DIR__;

require $root . '/bootstrap.php';
Config::load($root);

$baseUrl = rtrim((string) (Config::get('APP_URL') ?: 'https://skyclin.ru'), '/');

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=86400');

$disallow = <<<'RULES'
Disallow: /login
Disallow: /dashboard
Disallow: /requests
Disallow: /clients
Disallow: /posts
Disallow: /reviews
Disallow: /gallery
Disallow: /partners
Disallow: /settings
Disallow: /admin/
Disallow: /api/
Disallow: /storage/
Disallow: /src/
Disallow: /vendor/
Disallow: /scripts/
Disallow: /content/
Disallow: /index.html
Disallow: /blog.html
Disallow: /blog-article.html
Disallow: /*?slug=
RULES;

echo "# СкайКлин — {$baseUrl}\n";
echo "# Публичный сайт: главная, блог, статьи\n\n";

echo "User-agent: *\n";
echo "Allow: /\n";
echo "Allow: /blog\n";
echo "Allow: /article/\n";
echo $disallow . "\n\n";

echo "User-agent: Yandex\n";
echo "Allow: /\n";
echo "Allow: /blog\n";
echo "Allow: /article/\n";
echo $disallow . "\n";
echo "Host: {$baseUrl}\n\n";

echo "User-agent: Googlebot\n";
echo "Allow: /\n";
echo "Allow: /blog\n";
echo "Allow: /article/\n";
echo $disallow . "\n\n";

echo "Sitemap: {$baseUrl}/sitemap.xml\n";
