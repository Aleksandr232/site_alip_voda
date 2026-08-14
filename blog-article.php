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
$requestPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');

// Старый /blog-article.php?slug=... → канонический /article/slug
if (
    $slug !== ''
    && preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug)
    && !preg_match('#/article/' . preg_quote($slug, '#') . '/?$#i', $requestPath)
) {
    $base = rtrim((string) (Config::get('APP_URL') ?: ''), '/');
    $location = ($base !== '' ? $base : '') . '/article/' . $slug;
    header('Location: ' . $location, true, 301);
    exit;
}

try {
    if ($slug !== '' && preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug)) {
        $post = null;

        try {
            $found = (new BlogPostRepository())->findBySlug($slug);
            if ($found !== null && $found->status === 'published') {
                $post = $found;
            }
        } catch (Throwable $e) {
            error_log('blog-article.php: ' . $e->getMessage());
        }

        $html = SeoMetaService::createDefault()->injectArticleHead($html, $post, $slug);
    }
} catch (Throwable $e) {
    error_log('blog-article.php SEO: ' . $e->getMessage());
}

header('Content-Type: text/html; charset=utf-8');
echo $html;

function resolveArticleSlug(): string
{
    $slug = isset($_GET['slug']) ? strtolower(trim((string) $_GET['slug'])) : '';
    if ($slug !== '') {
        return $slug;
    }

    $uri = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
    if (preg_match('#/article/([a-z0-9][a-z0-9-]*)/?$#i', $uri, $match)) {
        return strtolower($match[1]);
    }

    return '';
}
