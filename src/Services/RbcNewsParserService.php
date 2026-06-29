<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use RuntimeException;
use SimpleXMLElement;

final class RbcNewsParserService
{
    public const VERSION = 2;

    private const DEFAULT_HTML_URL = 'https://www.rbc.ru/rubric/finances';

    private const DEFAULT_RSS_URL = 'https://rssexport.rbc.ru/rbcnews/news/30/full.rss';

    /** @var array<string, string> */
    private const RUBRIC_PAGES = [
        'finances' => 'https://www.rbc.ru/rubric/finances',
        'business' => 'https://www.rbc.ru/rubric/business',
        'politics' => 'https://www.rbc.ru/rubric/politics',
        'society' => 'https://www.rbc.ru/rubric/society',
        'economics' => 'https://www.rbc.ru/rubric/economics',
        'technology_and_media' => 'https://www.rbc.ru/rubric/technology_and_media',
        'sport' => 'https://www.rbc.ru/rubric/sport',
        'auto' => 'https://www.rbc.ru/rubric/auto',
        'realty' => 'https://www.rbc.ru/rubric/realty',
        'quote' => 'https://www.rbc.ru/quote',
    ];

    private string $lastDownloadError = '';

    /** @return list<array{external_id: string, title: string, url: string, summary: string, published_at: string}> */
    public function fetchLatest(int $limit = 20): array
    {
        $errors = [];

        foreach ($this->resolveSourceChain() as $source) {
            try {
                $items = $source['mode'] === 'html'
                    ? $this->parseHtmlPage($source['url'], $limit)
                    : $this->parseRssFeed($source['url'], $limit);

                if ($items !== []) {
                    return $items;
                }

                $errors[] = $source['label'] . ': новости не найдены';
            } catch (RuntimeException $e) {
                $errors[] = $source['label'] . ': ' . $e->getMessage();
            }
        }

        throw new RuntimeException(
            'РБК: не удалось получить новости. ' . implode(' | ', $errors)
        );
    }

    /**
     * @return list<array{mode: 'rss'|'html', url: string, label: string}>
     */
    private function resolveSourceChain(): array
    {
        $chain = [];
        $configured = trim((string) (Config::get('RBC_NEWS_RSS_URL') ?? ''));

        if ($configured === '') {
            $chain[] = $this->source('html', self::DEFAULT_HTML_URL, 'рубрика по умолчанию');
            $chain[] = $this->source('rss', self::DEFAULT_RSS_URL, 'общий RSS');

            return $chain;
        }

        if (preg_match('~^https?://(?:www\.)?rbc\.ru/rubric/([a-z0-9_]+)/?~i', $configured, $matches)) {
            $slug = strtolower($matches[1]);
            $chain[] = $this->source(
                'html',
                self::RUBRIC_PAGES[$slug] ?? $configured,
                'рубрика ' . $slug
            );

            return $chain;
        }

        if (str_starts_with($configured, 'https://www.rbc.ru/quote') || str_starts_with($configured, 'http://www.rbc.ru/quote')) {
            $chain[] = $this->source('html', 'https://www.rbc.ru/quote', 'инвестиции');

            return $chain;
        }

        if (preg_match('~^https?://rssexport\.rbc\.ru/rbcnews/([a-z0-9_]+)/~i', $configured, $matches)) {
            $section = strtolower($matches[1]);
            $rssUrl = preg_replace('~/(\d+)/full\.rss$~', '/30/full.rss', $configured) ?? $configured;

            if ($section !== 'news' && isset(self::RUBRIC_PAGES[$section])) {
                $chain[] = $this->source('html', self::RUBRIC_PAGES[$section], 'рубрика ' . $section);
            }

            $chain[] = $this->source('rss', $rssUrl, 'RSS ' . $section);
            $chain[] = $this->source('rss', self::DEFAULT_RSS_URL, 'общий RSS');
            $chain[] = $this->source('html', self::DEFAULT_HTML_URL, 'рубрика finances');

            return $this->uniqueSources($chain);
        }

        if (str_contains($configured, 'rbc.ru') && !str_contains($configured, 'rssexport')) {
            $chain[] = $this->source('html', $configured, 'страница РБК');

            return $chain;
        }

        $chain[] = $this->source('rss', $configured, 'RSS');
        $chain[] = $this->source('html', self::DEFAULT_HTML_URL, 'рубрика finances');
        $chain[] = $this->source('rss', self::DEFAULT_RSS_URL, 'общий RSS');

        return $this->uniqueSources($chain);
    }

    /**
     * @param list<array{mode: 'rss'|'html', url: string, label: string}> $chain
     * @return list<array{mode: 'rss'|'html', url: string, label: string}>
     */
    private function uniqueSources(array $chain): array
    {
        $seen = [];
        $result = [];

        foreach ($chain as $source) {
            $key = $source['mode'] . '|' . $source['url'];
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $source;
        }

        return $result;
    }

    /**
     * @return array{mode: 'rss'|'html', url: string, label: string}
     */
    private function source(string $mode, string $url, string $label): array
    {
        return [
            'mode' => $mode === 'html' ? 'html' : 'rss',
            'url' => $url,
            'label' => $label,
        ];
    }

    /** @return list<array{external_id: string, title: string, url: string, summary: string, published_at: string}> */
    private function parseRssFeed(string $url, int $limit): array
    {
        $xml = $this->download($url);
        if ($xml === '') {
            $detail = $this->lastDownloadError !== '' ? $this->lastDownloadError : 'пустой ответ';
            throw new RuntimeException('RSS ' . $url . ' — ' . $detail);
        }

        if (!str_contains($xml, '<rss') && !str_contains($xml, '<feed')) {
            throw new RuntimeException('RSS ' . $url . ' — ответ не похож на RSS');
        }

        $feed = @simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA);
        if ($feed === false) {
            throw new RuntimeException('RSS ' . $url . ' — не удалось разобрать XML');
        }

        $items = [];
        $channel = $feed->channel ?? $feed;

        foreach ($channel->item ?? [] as $item) {
            $title = $this->cleanText((string) ($item->title ?? ''));
            $link = trim((string) ($item->link ?? ''));
            if ($title === '' || $link === '') {
                continue;
            }

            $items[] = [
                'external_id' => $this->externalId($link),
                'title' => $title,
                'url' => $link,
                'summary' => $this->cleanText((string) ($item->description ?? '')),
                'published_at' => trim((string) ($item->pubDate ?? '')),
            ];

            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    /** @return list<array{external_id: string, title: string, url: string, summary: string, published_at: string}> */
    private function parseHtmlPage(string $url, int $limit): array
    {
        $html = $this->download($url);
        if ($html === '') {
            $detail = $this->lastDownloadError !== '' ? $this->lastDownloadError : 'пустой ответ';
            throw new RuntimeException('страница ' . $url . ' — ' . $detail);
        }

        $items = [];
        $seen = [];

        if (preg_match_all(
            '/data-metronome-document-id="([a-f0-9]{20,32})"[^>]*data-metronome-href="([^"]+)"[^>]*data-metronome-text="([^"]*)"/iu',
            $html,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $documentId = strtolower($match[1]);
                if (isset($seen[$documentId])) {
                    continue;
                }

                $seen[$documentId] = true;
                $link = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $title = $this->cleanText(html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                if ($title === '' || $link === '') {
                    continue;
                }

                $items[] = [
                    'external_id' => 'rbc:' . $documentId,
                    'title' => $title,
                    'url' => $link,
                    'summary' => '',
                    'published_at' => '',
                ];

                if (count($items) >= $limit) {
                    break;
                }
            }
        }

        if ($items === [] && preg_match_all(
            '/<meta itemProp="url" content="(https:\/\/www\.rbc\.ru\/[^"]+)"/iu',
            $html,
            $urlMatches
        )) {
            foreach ($urlMatches[1] as $link) {
                $id = $this->externalId($link);
                if (isset($seen[$id])) {
                    continue;
                }

                $seen[$id] = true;
                $items[] = [
                    'external_id' => $id,
                    'title' => 'Новость РБК',
                    'url' => $link,
                    'summary' => '',
                    'published_at' => '',
                ];

                if (count($items) >= $limit) {
                    break;
                }
            }
        }

        return $items;
    }

    private function download(string $url): string
    {
        $this->lastDownloadError = '';

        if (function_exists('curl_init')) {
            return $this->downloadWithCurl($url);
        }

        return $this->downloadWithStream($url);
    }

    private function downloadWithCurl(string $url): string
    {
        $handle = curl_init($url);
        if ($handle === false) {
            $this->lastDownloadError = 'curl_init failed';

            return '';
        }

        $verifySsl = Config::get('RBC_RSS_SSL_VERIFY', 'true') !== 'false';

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/rss+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: ru-RU,ru;q=0.9',
            ],
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        ]);

        $response = curl_exec($handle);
        $code = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if (!is_string($response) || $response === '') {
            $this->lastDownloadError = $error !== '' ? $error : 'пустой ответ';
            if ($code > 0) {
                $this->lastDownloadError .= ' (HTTP ' . $code . ')';
            }

            return '';
        }

        if ($code < 200 || $code >= 300) {
            $this->lastDownloadError = 'HTTP ' . $code;

            return '';
        }

        return $response;
    }

    private function downloadWithStream(string $url): string
    {
        $verifySsl = Config::get('RBC_RSS_SSL_VERIFY', 'true') !== 'false';
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'header' => implode("\r\n", [
                    'User-Agent: Mozilla/5.0 (compatible; SkayClinBot/1.0)',
                    'Accept: text/html,application/rss+xml,application/xml,*/*',
                    'Accept-Language: ru-RU,ru;q=0.9',
                ]),
            ],
            'ssl' => [
                'verify_peer' => $verifySsl,
                'verify_peer_name' => $verifySsl,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if (!is_string($response) || $response === '') {
            $this->lastDownloadError = 'file_get_contents failed';

            return '';
        }

        return $response;
    }

    private function externalId(string $url): string
    {
        $normalized = strtolower(rtrim(trim($url), '/'));

        if (preg_match('~/(?:fulltext/)?([a-f0-9]{20,32})~i', $normalized, $matches)) {
            return 'rbc:' . strtolower($matches[1]);
        }

        if (preg_match('~/(?:fulltext/)?(\d{6,})~', $normalized, $matches)) {
            return 'rbc:' . $matches[1];
        }

        return 'rbc:' . sha1($normalized);
    }

    private function cleanText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
