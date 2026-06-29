<?php

declare(strict_types=1);

$root = __DIR__;

require $root . '/bootstrap.php';

use App\Config;
use App\Repositories\BlogPostRepository;
use App\Services\SeoMetaService;

Config::load($root);

$template = $root . '/blog-article.html';
if (!is_file($template)) {
    http_response_code(500);
    echo 'Template not found';
    exit;
}

$html = (string) file_get_contents($template);
$slug = isset($_GET['slug']) ? strtolower(trim((string) $_GET['slug'])) : '';
$seo = SeoMetaService::createDefault();
$post = null;

if ($slug !== '' && preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug)) {
    try {
        $found = (new BlogPostRepository())->findBySlug($slug);
        if ($found !== null && $found->status === 'published') {
            $post = $found;
        }
    } catch (Throwable $e) {
        error_log('article.php: ' . $e->getMessage());
    }
}

$html = $seo->injectArticleHead($html, $post, $slug !== '' ? $slug : null);

header('Content-Type: text/html; charset=utf-8');
echo $html;
