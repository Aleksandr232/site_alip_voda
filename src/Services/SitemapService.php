<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\Repositories\BlogPostRepository;

final class SitemapService
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly BlogPostRepository $posts = new BlogPostRepository(),
    ) {
    }

    public static function createDefault(): self
    {
        return new self(self::resolveBaseUrl());
    }

    public function render(): string
    {
        $entries = [
            $this->urlEntry('/', 'weekly', '1.0'),
            $this->urlEntry('/blog', 'weekly', '0.8'),
        ];

        foreach ($this->posts->all(true) as $post) {
            $entries[] = $this->urlEntry(
                '/article/' . $post->slug,
                'monthly',
                '0.7',
                $post->updatedAt ?: $post->createdAt,
            );
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
            . implode("\n", $entries)
            . "\n</urlset>\n";
    }

    private function urlEntry(string $path, string $changefreq, string $priority, ?string $lastmod = null): string
    {
        if ($path === '' || $path === '/') {
            $loc = $this->baseUrl . '/';
        } else {
            $loc = $this->baseUrl . '/' . ltrim(rtrim($path, '/'), '/');
        }

        $loc = htmlspecialchars($loc, ENT_XML1);
        $xml = "  <url>\n    <loc>{$loc}</loc>\n    <changefreq>{$changefreq}</changefreq>\n    <priority>{$priority}</priority>";

        if ($lastmod !== null && $lastmod !== '') {
            $xml .= "\n    <lastmod>" . htmlspecialchars($this->formatLastmod($lastmod), ENT_XML1) . '</lastmod>';
        }

        return $xml . "\n  </url>";
    }

    private function formatLastmod(string $datetime): string
    {
        $timestamp = strtotime($datetime);

        return $timestamp ? gmdate('Y-m-d', $timestamp) : gmdate('Y-m-d');
    }

    private static function resolveBaseUrl(): string
    {
        $configured = Config::get('APP_URL');
        if ($configured !== null && $configured !== '') {
            return rtrim($configured, '/');
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = $_SERVER['HTTP_HOST'] ?? 'skyclin.ru';

        return ($https ? 'https' : 'http') . '://' . $host;
    }
}
