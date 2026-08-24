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
$slug = resolveArticleSlug();
$requestPath = normalizeRequestPath();
$seo = SeoMetaService::createDefault();

if ($slug === '' || !preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug)) {
    header('Location: ' . $seo->canonicalUrl('/blog'), true, 301);
    exit;
}

$canonical = $seo->articleCanonicalUrl($slug);

// Редиректим только прямой заход на legacy-файлы или хвостовой слэш.
// Внутренний rewrite /article/slug → blog-article.php НЕ трогаем.
if (isLegacyArticleFile($requestPath) || str_ends_with($requestPath, '/')) {
    header('Location: ' . $canonical, true, 301);
    exit;
}

$post = null;
$status = 200;

try {
    $found = (new BlogPostRepository())->findBySlug($slug);
    if ($found !== null && $found->status === 'published') {
        $post = $found;
        $canonical = $seo->articleCanonicalUrl($post->slug);
    } else {
        $status = 404;
    }
} catch (Throwable $e) {
    error_log('blog-article.php: ' . $e->getMessage());
    $status = 404;
}

try {
    $html = $seo->injectArticleHead($html, $post, $slug);
} catch (Throwable $e) {
    error_log('blog-article.php SEO: ' . $e->getMessage());
}

header('Link: <' . $canonical . '>; rel="canonical"', false);
header('Content-Type: text/html; charset=utf-8');
http_response_code($status);
echo $html;

function resolveArticleSlug(): string
{
    $slug = isset($_GET['slug']) ? strtolower(trim((string) $_GET['slug'])) : '';
    if ($slug !== '') {
        return $slug;
    }

    $uri = normalizeRequestPath();
    if (preg_match('#/article/([a-z0-9][a-z0-9-]*)/?$#i', $uri, $match)) {
        return strtolower($match[1]);
    }

    return '';
}

function normalizeRequestPath(): string
{
    // После rewrite Apache часто сохраняет исходный URL в REDIRECT_URL / REQUEST_URI
    $candidates = [
        $_SERVER['REDIRECT_URL'] ?? '',
        $_SERVER['REQUEST_URI'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        $path = (string) (parse_url((string) $candidate, PHP_URL_PATH) ?: '');
        if ($path !== '' && preg_match('#/article/[a-z0-9][a-z0-9-]*/?$#i', $path)) {
            return $path;
        }
    }

    return (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
}

function isLegacyArticleFile(string $path): bool
{
    return (bool) preg_match('#/(?:blog-article|article)\.(?:php|html)$#i', $path);
}
