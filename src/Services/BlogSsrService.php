<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogPost;
use App\Repositories\BlogPostRepository;

final class BlogSsrService
{
    public function __construct(
        private readonly BlogPostRepository $posts = new BlogPostRepository(),
        private readonly ?SeoMetaService $seo = null,
    ) {
    }

    public static function createDefault(): self
    {
        return new self(new BlogPostRepository(), SeoMetaService::createDefault());
    }

    private function seo(): SeoMetaService
    {
        return $this->seo ?? SeoMetaService::createDefault();
    }

    public function injectHome(string $html): string
    {
        $posts = $this->published();
        $preview = array_slice($posts, 0, 2);
        $cards = $preview === []
            ? ''
            : implode('', array_map(fn (BlogPost $post) => $this->renderCard($post), $preview));

        $html = $this->replaceGrid(
            $html,
            'blog-preview-grid',
            '<div class="blog-preview__grid" id="blog-preview-grid" data-ssr="1">' . $cards . '</div>',
        );

        return $this->injectDiscovery($html);
    }

    public function injectBlogIndex(string $html): string
    {
        $posts = $this->published();
        if ($posts === []) {
            $inner = '<p class="gallery__loading">Статей пока нет.</p>';
        } else {
            $inner = '';
            foreach ($posts as $index => $post) {
                $inner .= $this->renderCard($post, $index === 0);
            }
        }

        $html = $this->replaceGrid(
            $html,
            'blog-grid',
            '<div class="blog-grid" id="blog-grid" data-ssr="1">' . $inner . '</div>',
        );

        $html = $this->injectDiscovery($html);
        $html = $this->injectBlogJsonLd($html, $this->published());

        return $html;
    }

    /** @param BlogPost[] $allPublished */
    public function injectRelated(string $html, string $currentSlug, array $allPublished): string
    {
        $related = [];
        foreach ($allPublished as $post) {
            if ($post->slug === $currentSlug) {
                continue;
            }
            $related[] = $post;
            if (count($related) >= 4) {
                break;
            }
        }

        if ($related === []) {
            return $html;
        }

        $items = '';
        foreach ($related as $post) {
            $url = $this->escape('/article/' . $post->slug);
            $items .= '<li><a href="' . $url . '">' . $this->escape($post->title) . '</a></li>';
        }

        $html = preg_replace(
            '#<div class="sidebar-card" id="article-related-wrap"[^>]*>#',
            '<div class="sidebar-card" id="article-related-wrap" data-ssr="1">',
            $html,
            1,
        ) ?? $html;

        return preg_replace(
            '#id="article-related"></ul>#',
            'id="article-related">' . $items . '</ul>',
            $html,
            1,
        ) ?? $html;
    }

    public function renderRss(): string
    {
        $channelLink = $this->seo()->canonicalUrl('/blog');
        $items = '';

        foreach ($this->published() as $post) {
            $url = $this->seo()->articleCanonicalUrl($post->slug);
            $title = $this->escapeXml($post->title);
            $desc = $this->escapeXml((string) ($post->description ?? ''));
            $pub = $this->rfc822($post->createdAt);
            $items .= "    <item>\n"
                . "      <title>{$title}</title>\n"
                . "      <link>{$url}</link>\n"
                . "      <guid isPermaLink=\"true\">{$url}</guid>\n"
                . "      <pubDate>{$pub}</pubDate>\n"
                . "      <description>{$desc}</description>\n"
                . "    </item>\n";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0">'."\n"
            . "  <channel>\n"
            . "    <title>СкайКлин — блог</title>\n"
            . "    <link>{$channelLink}</link>\n"
            . "    <description>Статьи о мойке фасадов, окнах, промышленном альпинизме и высотных работах в Казани.</description>\n"
            . "    <language>ru</language>\n"
            . $items
            . "  </channel>\n"
            . "</rss>\n";
    }

    /** @return BlogPost[] */
    private function published(): array
    {
        try {
            return $this->posts->all(true);
        } catch (\Throwable) {
            return [];
        }
    }

    private function renderCard(BlogPost $post, bool $large = false): string
    {
        $url = $this->escape('/article/' . $post->slug);
        $title = $this->escape($post->title);
        $date = $this->formatRuDate($post->createdAt);
        $datetime = $this->formatDateAttr($post->createdAt);
        $largeClass = $large ? ' blog-card--large' : '';
        $image = $post->coverImage
            ? '<img src="' . $this->escape($post->coverImage) . '" alt="' . $title . '">'
            : '<div class="blog-card__placeholder"></div>';
        $lead = $post->description
            ? '<p>' . $this->escape($post->description) . '</p>'
            : '';
        $linkText = $large ? 'Читать статью →' : 'Читать →';

        return '<article class="blog-card' . $largeClass . '">'
            . '<a href="' . $url . '" class="blog-card__image">' . $image . '</a>'
            . '<div class="blog-card__body">'
            . '<div class="blog-card__meta"><time class="blog-card__date" datetime="' . $this->escape($datetime) . '">' . $this->escape($date) . '</time></div>'
            . '<h2><a href="' . $url . '">' . $title . '</a></h2>'
            . $lead
            . '<a href="' . $url . '" class="blog-card__link">' . $linkText . '</a>'
            . '</div></article>';
    }

    /** @param BlogPost[] $posts */
    private function injectBlogJsonLd(string $html, array $posts): string
    {
        if ($posts === []) {
            return $html;
        }

        $elements = [];
        foreach (array_values($posts) as $i => $post) {
            $elements[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'url' => $this->seo()->articleCanonicalUrl($post->slug),
                'name' => $post->title,
            ];
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => 'Блог СкайКлин',
            'url' => $this->seo()->canonicalUrl('/blog'),
            'mainEntity' => [
                '@type' => 'ItemList',
                'itemListElement' => $elements,
            ],
        ];

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $tag = '<script type="application/ld+json">' . $json . '</script>';

        if (str_contains($html, '</head>')) {
            return preg_replace('#</head>#', '  ' . $tag . "\n</head>", $html, 1) ?? $html;
        }

        return $html;
    }

    public function injectDiscovery(string $html): string
    {
        $rss = '<link rel="alternate" type="application/rss+xml" title="СкайКлин — блог" href="'
            . $this->escape($this->seo()->absoluteUrl('/rss.xml')) . '">';

        if (!str_contains($html, 'application/rss+xml')) {
            $html = preg_replace('#</head>#', '  ' . $rss . "\n</head>", $html, 1) ?? $html;
        }

        if (!str_contains($html, 'yandex-verification')) {
            $html = preg_replace(
                '#</head>#',
                '  <meta name="yandex-verification" content="d1ed3cbaf813266b">'."\n</head>",
                $html,
                1,
            ) ?? $html;
        }

        return $html;
    }

    private function replaceGrid(string $html, string $id, string $replacement): string
    {
        $pattern = '#<div class="[^"]*" id="' . preg_quote($id, '#') . '"[^>]*>.*?</div>#s';
        $replaced = preg_replace($pattern, $replacement, $html, 1);

        return is_string($replaced) ? $replaced : $html;
    }

    private function formatRuDate(string $datetime): string
    {
        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return $datetime;
        }

        $months = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
            5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
            9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
        ];

        return (int) date('j', $timestamp) . ' ' . ($months[(int) date('n', $timestamp)] ?? '') . ' ' . date('Y', $timestamp);
    }

    private function formatDateAttr(string $datetime): string
    {
        $timestamp = strtotime($datetime);

        return $timestamp ? date('Y-m-d', $timestamp) : $datetime;
    }

    private function rfc822(string $datetime): string
    {
        $timestamp = strtotime($datetime);

        return $timestamp ? gmdate('D, d M Y H:i:s', $timestamp) . ' GMT' : gmdate('D, d M Y H:i:s') . ' GMT';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
