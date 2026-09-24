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
        $posts = [];
        try {
            $posts = $this->posts->all(true);
        } catch (\Throwable) {
            $posts = [];
        }

        $latest = $posts !== []
            ? ($posts[0]->updatedAt ?: $posts[0]->createdAt)
            : gmdate('c');

        $entries = [
            $this->urlEntry('/', 'daily', '1.0', $latest),
            $this->urlEntry('/blog', 'daily', '0.9', $latest),
        ];

        foreach ($posts as $post) {
            $image = null;
            if ($post->coverImage) {
                $image = str_starts_with($post->coverImage, 'http')
                    ? $post->coverImage
                    : $this->baseUrl . '/' . ltrim($post->coverImage, '/');
            }
            $entries[] = $this->urlEntry(
                '/article/' . $post->slug,
                'weekly',
                '0.8',
                $post->updatedAt ?: $post->createdAt,
                $image,
                $post->title,
            );
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n"
            . implode("\n", $entries)
            . "\n</urlset>\n";
    }

    public function fallback(): string
    {
        $today = gmdate('Y-m-d');

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
            . $this->urlEntry('/', 'daily', '1.0', $today) . "\n"
            . $this->urlEntry('/blog', 'daily', '0.9', $today) . "\n"
            . "</urlset>\n";
    }

    private function urlEntry(
        string $path,
        string $changefreq,
        string $priority,
        ?string $lastmod = null,
        ?string $image = null,
        ?string $imageTitle = null,
    ): string {
        if ($path === '' || $path === '/') {
            $loc = $this->baseUrl . '/';
        } else {
            $loc = $this->baseUrl . '/' . ltrim(rtrim($path, '/'), '/');
        }

        $loc = $this->xml($loc);
        $xml = "  <url>\n    <loc>{$loc}</loc>\n    <lastmod>" . $this->xml($this->formatLastmod($lastmod ?? '')) . "</lastmod>\n    <changefreq>{$changefreq}</changefreq>\n    <priority>{$priority}</priority>";

        if ($image !== null && $image !== '') {
            $imageLoc = $this->xml($image);
            $xml .= "\n    <image:image>\n      <image:loc>{$imageLoc}</image:loc>";
            if ($imageTitle !== null && $imageTitle !== '') {
                $xml .= "\n      <image:title>" . $this->xml($imageTitle) . '</image:title>';
            }
            $xml .= "\n    </image:image>";
        }

        return $xml . "\n  </url>";
    }

    private function formatLastmod(string $datetime): string
    {
        $timestamp = $datetime !== '' ? strtotime($datetime) : false;

        return $timestamp ? gmdate('Y-m-d', $timestamp) : gmdate('Y-m-d');
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
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
