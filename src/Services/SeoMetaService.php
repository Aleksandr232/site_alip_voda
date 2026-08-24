<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\Models\BlogPost;
use App\Repositories\SettingsRepository;

final class SeoMetaService
{
    private const SITE_NAME = 'СкайКлин';
    private const LOCALE = 'ru_RU';
    private const DEFAULT_OG_IMAGE = '/apple-touch-icon.png';

    /** @var list<array{name: string, description: string}> */
    private const SERVICES = [
        [
            'name' => 'Мойка фасадов',
            'description' => 'Удаление пыли, копоти, высолов и органики. Витражи, композит, керамогранит, стекло.',
        ],
        [
            'name' => 'Мойка окон',
            'description' => 'Мойка окон без разводов с деминерализованной водой обратного осмоса.',
        ],
        [
            'name' => 'Альпинистские работы',
            'description' => 'Высотные работы без лесов и автовышек. Допуски СРО.',
        ],
        [
            'name' => 'Монтажные работы',
            'description' => 'Монтаж и демонтаж конструкций на фасаде и кровле промышленными альпинистами.',
        ],
        [
            'name' => 'Уборка снега с кровли',
            'description' => 'Снятие снега и наледи с кровель жилых и коммерческих зданий.',
        ],
    ];

    public function __construct(
        private readonly string $baseUrl,
        private readonly SettingsRepository $settings = new SettingsRepository(),
    ) {
    }

    public static function createDefault(): self
    {
        return new self(self::resolveBaseUrl());
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function absoluteUrl(string $path): string
    {
        if ($path === '' || $path === '/') {
            return $this->baseUrl . '/';
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return $this->baseUrl . '/' . ltrim($path, '/');
    }

    /** Канонический URL без хвостового слэша (кроме главной). */
    public function canonicalUrl(string $path): string
    {
        if ($path === '' || $path === '/') {
            return $this->baseUrl . '/';
        }

        return rtrim($this->absoluteUrl($path), '/');
    }

    public function defaultImageUrl(): string
    {
        return $this->absoluteUrl(self::DEFAULT_OG_IMAGE);
    }

    /** @param array{title: string, description: string, url: string, image?: string|null, type?: string, publishedTime?: string|null, modifiedTime?: string|null} $meta */
    public function renderSocialMeta(array $meta): string
    {
        $title = $this->escape($meta['title']);
        $description = $this->escape($meta['description']);
        $url = $this->escape($meta['url']);
        $image = $this->escape($meta['image'] ?? $this->defaultImageUrl());
        $type = $this->escape($meta['type'] ?? 'website');
        $siteName = $this->escape(self::SITE_NAME);
        $locale = self::LOCALE;

        $lines = [
            '<link rel="canonical" href="' . $url . '">',
            '<meta property="og:type" content="' . $type . '">',
            '<meta property="og:site_name" content="' . $siteName . '">',
            '<meta property="og:locale" content="' . $locale . '">',
            '<meta property="og:title" content="' . $title . '">',
            '<meta property="og:description" content="' . $description . '">',
            '<meta property="og:url" content="' . $url . '">',
            '<meta property="og:image" content="' . $image . '">',
            '<meta name="twitter:card" content="summary_large_image">',
            '<meta name="twitter:title" content="' . $title . '">',
            '<meta name="twitter:description" content="' . $description . '">',
            '<meta name="twitter:image" content="' . $image . '">',
        ];

        if (!empty($meta['publishedTime'])) {
            $lines[] = '<meta property="article:published_time" content="' . $this->escape($meta['publishedTime']) . '">';
        }

        if (!empty($meta['modifiedTime'])) {
            $lines[] = '<meta property="article:modified_time" content="' . $this->escape($meta['modifiedTime']) . '">';
        }

        return implode("\n  ", $lines);
    }

    public function renderLocalBusinessJsonLd(): string
    {
        $settings = $this->mergeSettings($this->settings->all());
        $phone = $this->normalizePhone((string) ($settings['phone'] ?? ''));
        $email = (string) ($settings['email'] ?? 'info@skyclin.ru');

        $organizationId = $this->baseUrl . '/#organization';

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            '@id' => $organizationId,
            'name' => self::SITE_NAME,
            'url' => $this->baseUrl . '/',
            'image' => $this->defaultImageUrl(),
            'logo' => $this->absoluteUrl('/favicon.svg'),
            'description' => 'Мойка фасадов и окон, монтажные работы, уборка снега с кровли альпинистами. Москва и область.',
            'telephone' => $phone,
            'email' => $email,
            'priceRange' => '₽₽',
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => 'Москва',
                'addressRegion' => 'Московская область',
                'addressCountry' => 'RU',
            ],
            'areaServed' => [
                ['@type' => 'City', 'name' => 'Москва'],
                ['@type' => 'AdministrativeArea', 'name' => 'Московская область'],
            ],
            'openingHoursSpecification' => [
                [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                    'opens' => '08:00',
                    'closes' => '20:00',
                ],
            ],
            'hasOfferCatalog' => [
                '@type' => 'OfferCatalog',
                'name' => 'Услуги ' . self::SITE_NAME,
                'itemListElement' => array_map(
                    static fn (array $service): array => [
                        '@type' => 'Offer',
                        'itemOffered' => [
                            '@type' => 'Service',
                            'name' => $service['name'],
                            'description' => $service['description'],
                            'provider' => ['@id' => $organizationId],
                            'areaServed' => 'Москва и Московская область',
                        ],
                    ],
                    self::SERVICES,
                ),
            ],
        ];

        return $this->renderJsonLd($data);
    }

    public function renderArticleJsonLd(BlogPost $post): string
    {
        $url = $this->articleCanonicalUrl($post->slug);
        $image = $post->coverImage ? $this->absoluteUrl($post->coverImage) : $this->defaultImageUrl();

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->title,
            'description' => $post->description ?? '',
            'image' => [$image],
            'datePublished' => $this->toIso8601($post->createdAt),
            'dateModified' => $this->toIso8601($post->updatedAt ?: $post->createdAt),
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $url,
            ],
            'author' => [
                '@type' => 'Organization',
                'name' => self::SITE_NAME,
                'url' => $this->baseUrl . '/',
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => self::SITE_NAME,
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => $this->absoluteUrl('/favicon.svg'),
                ],
            ],
        ];

        return $this->renderJsonLd($data);
    }

    public function articleCanonicalUrl(string $slug): string
    {
        return $this->canonicalUrl('/article/' . $slug);
    }

    public function injectArticleHead(string $html, ?BlogPost $post = null, ?string $slug = null): string
    {
        try {
            if ($post !== null) {
                $title = $post->title . ' — ' . self::SITE_NAME;
                $description = trim((string) ($post->description ?? ''));
                if ($description === '') {
                    $description = 'Статья блога ' . self::SITE_NAME;
                }

                $url = $this->articleCanonicalUrl($post->slug);
                $image = $post->coverImage ? $this->absoluteUrl($post->coverImage) : $this->defaultImageUrl();

                $html = preg_replace('#<title>[^<]*</title>#', '<title>' . $this->escape($title) . '</title>', $html, 1) ?? $html;
                $html = preg_replace(
                    '#<meta name="description" content="[^"]*">#',
                    '<meta name="description" content="' . $this->escape($description) . '">',
                    $html,
                    1,
                ) ?? $html;

                if ($post->keywords) {
                    $html = preg_replace(
                        '#<meta name="keywords" content="[^"]*">#',
                        '<meta name="keywords" content="' . $this->escape($post->keywords) . '">',
                        $html,
                        1,
                    ) ?? $html;
                }

                $replacement = $this->renderSocialMeta([
                    'title' => $title,
                    'description' => $description,
                    'url' => $url,
                    'image' => $image,
                    'type' => 'article',
                    'publishedTime' => $this->toIso8601($post->createdAt),
                    'modifiedTime' => $this->toIso8601($post->updatedAt ?: $post->createdAt),
                ]) . "\n  " . $this->renderArticleJsonLd($post);

                $html = $this->replaceSeoMeta($html, $replacement);
                $html = $this->injectArticleBody($html, $post);

                return $html;
            }

            $canonicalSlug = $slug && preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) ? $slug : null;
            $path = $canonicalSlug ? '/article/' . $canonicalSlug : '/blog';
            $replacement = $this->renderSocialMeta([
                'title' => 'Статья — ' . self::SITE_NAME,
                'description' => 'Статья блога ' . self::SITE_NAME,
                'url' => $this->canonicalUrl($path),
                'image' => $this->defaultImageUrl(),
                'type' => 'article',
            ]);

            if ($canonicalSlug === null) {
                $replacement .= "\n  <meta name=\"robots\" content=\"noindex,follow\">";
            }

            return $this->replaceSeoMeta($html, $replacement);
        } catch (Throwable) {
            return $html;
        }
    }

    private function replaceSeoMeta(string $html, string $replacement): string
    {
        // Всегда один canonical: удаляем старые перед вставкой
        $html = preg_replace('#<link\s+rel=["\']canonical["\'][^>]*>\s*#i', '', $html) ?? $html;

        if (str_contains($html, '<!-- seo:meta -->')) {
            return str_replace('<!-- seo:meta -->', $replacement, $html);
        }

        return preg_replace('#</head>#', '  ' . $replacement . "\n</head>", $html, 1) ?? $html;
    }

    private function injectArticleBody(string $html, BlogPost $post): string
    {
        $title = $this->escape($post->title);
        $description = trim((string) ($post->description ?? ''));
        $lead = $description !== '' ? $this->escape($description) : '';
        $date = $this->formatRuDate($post->createdAt);
        $datetime = $this->formatDateAttr($post->createdAt);
        $contentHtml = $this->renderArticleContent($post->content ?? '');

        $html = preg_replace(
            '#id="article-loading"[^>]*>#',
            'id="article-loading" hidden>',
            $html,
            1,
        ) ?? $html;

        $html = preg_replace(
            '#id="article-error"[^>]*\s*hidden#',
            'id="article-error" hidden',
            $html,
            1,
        ) ?? $html;

        $html = preg_replace(
            '#<article class="article" id="article-shell" hidden>#',
            '<article class="article" id="article-shell" data-ssr="1">',
            $html,
            1,
        ) ?? $html;

        $html = preg_replace(
            '#id="article-breadcrumb-title">[^<]*</span>#',
            'id="article-breadcrumb-title">' . $title . '</span>',
            $html,
            1,
        ) ?? $html;

        $html = preg_replace(
            '#id="article-date" datetime="[^"]*">[^<]*</time>#',
            'id="article-date" datetime="' . $this->escape($datetime) . '">' . $this->escape($date) . '</time>',
            $html,
            1,
        ) ?? $html;

        $html = preg_replace(
            '#id="article-title">[^<]*</h1>#',
            'id="article-title">' . $title . '</h1>',
            $html,
            1,
        ) ?? $html;

        if ($lead !== '') {
            $html = preg_replace(
                '#id="article-lead"[^>]*>[^<]*</p>#',
                'id="article-lead">' . $lead . '</p>',
                $html,
                1,
            ) ?? $html;
        } else {
            $html = preg_replace(
                '#id="article-lead"[^>]*>[^<]*</p>#',
                'id="article-lead" hidden></p>',
                $html,
                1,
            ) ?? $html;
        }

        if ($post->coverImage) {
            $cover = '<img src="' . $this->escape($post->coverImage) . '" alt="' . $title . '">';
            $html = preg_replace(
                '#id="article-cover"[^>]*>.*?</div>#s',
                'id="article-cover">' . $cover . '</div>',
                $html,
                1,
            ) ?? $html;
        }

        if ($post->videoPath) {
            $video = '<video controls playsinline src="' . $this->escape($post->videoPath) . '"></video>';
            $html = preg_replace(
                '#id="article-video"[^>]*>.*?</div>#s',
                'id="article-video">' . $video . '</div>',
                $html,
                1,
            ) ?? $html;
        }

        $html = preg_replace(
            '#id="article-content"></div>#',
            'id="article-content">' . $contentHtml . '</div>',
            $html,
            1,
        ) ?? $html;

        return $html;
    }

    private function renderArticleContent(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (preg_match('/<[a-z][\s\S]*>/i', $text)) {
            return $text;
        }

        $blocks = preg_split('/\n\s*\n/', $text) ?: [];
        $html = [];

        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            $html[] = '<p>' . nl2br($this->escape($block), false) . '</p>';
        }

        return implode("\n", $html);
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

        $day = (int) date('j', $timestamp);
        $month = $months[(int) date('n', $timestamp)] ?? date('m', $timestamp);
        $year = date('Y', $timestamp);

        return $day . ' ' . $month . ' ' . $year;
    }

    private function formatDateAttr(string $datetime): string
    {
        $timestamp = strtotime($datetime);

        return $timestamp ? date('Y-m-d', $timestamp) : $datetime;
    }

    /** @param array<string, string> $stored */
    private function mergeSettings(array $stored): array
    {
        $result = SettingsService::DEFAULTS;

        foreach (array_keys(SettingsService::DEFAULTS) as $key) {
            if (array_key_exists($key, $stored) && $stored[$key] !== '') {
                $result[$key] = $stored[$key];
            }
        }

        return $result;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '+79001234567';
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '8')) {
            return '+7' . substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return '+7' . $digits;
        }

        return '+' . $digits;
    }

    private function toIso8601(string $datetime): string
    {
        $timestamp = strtotime($datetime);

        return $timestamp ? gmdate('c', $timestamp) : gmdate('c');
    }

    /** @param array<string, mixed> $data */
    private function renderJsonLd(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return '<script type="application/ld+json">' . $json . '</script>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
