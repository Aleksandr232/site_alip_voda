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
        if ($path === '') {
            return $this->baseUrl . '/';
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return $this->baseUrl . '/' . ltrim($path, '/');
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
                    static fn (array $service) use ($organizationId): array => [
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
        $url = $this->absoluteUrl('/article/' . $post->slug);
        $image = $post->coverImage ? $this->absoluteUrl($post->coverImage) : $this->defaultImageUrl();

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->title,
            'description' => $post->description ?? '',
            'image' => [$image],
            'datePublished' => $this->toIso8601($post->createdAt),
            'dateModified' => $this->toIso8601($post->updatedAt ?: $post->createdAt),
            'mainEntityOfPage' => $url,
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

    public function injectArticleHead(string $html, ?BlogPost $post = null, ?string $slug = null): string
    {
        if ($post !== null) {
            $title = $post->title . ' — ' . self::SITE_NAME;
            $description = trim((string) ($post->description ?? ''));
            if ($description === '') {
                $description = 'Статья блога ' . self::SITE_NAME;
            }

            $url = $this->absoluteUrl('/article/' . $post->slug);
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
        } else {
            $path = $slug ? '/article/' . $slug : '/blog';
            $replacement = $this->renderSocialMeta([
                'title' => 'Статья — ' . self::SITE_NAME,
                'description' => 'Статья блога ' . self::SITE_NAME,
                'url' => $this->absoluteUrl($path),
                'image' => $this->defaultImageUrl(),
                'type' => 'article',
            ]);
        }

        if (str_contains($html, '<!-- seo:meta -->')) {
            return str_replace('<!-- seo:meta -->', $replacement, $html);
        }

        return preg_replace('#</head>#', '  ' . $replacement . "\n</head>", $html, 1) ?? $html;
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
