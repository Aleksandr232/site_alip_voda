<?php

declare(strict_types=1);

use App\Config;

$root = __DIR__;

require $root . '/bootstrap.php';
Config::load($root);

$baseUrl = rtrim((string) (Config::get('APP_URL') ?: 'https://skyclin.ru'), '/');
$host = parse_url($baseUrl, PHP_URL_HOST) ?: 'skyclin.ru';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=86400');
header('Link: <' . $baseUrl . '/sitemap.xml>; rel="sitemap"', false);

$allow = <<<'RULES'
Allow: /
Allow: /blog
Allow: /article/
Allow: /uploads/
Allow: /assets/
Allow: /css/
Allow: /js/
Allow: /rss.xml
Allow: /sitemap.xml
Allow: /api/posts.php
Allow: /api/partners.php
Allow: /api/gallery.php
Allow: /api/settings.php
RULES;

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
Disallow: /cron/
Disallow: /index.html
Disallow: /index.php
Disallow: /blog.html
Disallow: /blog.php
Disallow: /blog-article.html
Disallow: /blog-article.php
Disallow: /article.php
Disallow: /blog-article.php?
Disallow: /article.php?
Disallow: /blog-article.html?
RULES;

$clean = 'Clean-param: utm_source&utm_medium&utm_campaign&utm_content&utm_term&yclid&gclid&fbclid&from&ref /';

echo "# СкайКлин — {$baseUrl}\n";
echo "# Канонические URL: /, /blog, /article/{slug}\n\n";

echo "User-agent: *\n";
echo $allow . "\n";
echo $disallow . "\n";
echo $clean . "\n\n";

foreach (['Yandex', 'YandexBot'] as $agent) {
    echo "User-agent: {$agent}\n";
    echo $allow . "\n";
    echo $disallow . "\n";
    echo $clean . "\n";
    echo "Host: {$baseUrl}\n\n";
}

echo "User-agent: YandexImages\n";
echo "Allow: /uploads/\n";
echo "Allow: /assets/\n";
echo "Allow: /article/\n";
echo "Allow: /css/\n";
echo "Disallow: /admin/\n";
echo "Disallow: /api/\n\n";

echo "User-agent: Googlebot\n";
echo $allow . "\n";
echo $disallow . "\n\n";

echo "Sitemap: {$baseUrl}/sitemap.xml\n";
