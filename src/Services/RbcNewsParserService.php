<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use RuntimeException;
use SimpleXMLElement;

final class RbcNewsParserService
{
    private const DEFAULT_RSS_URL = 'https://rssexport.rbc.ru/rbcnews/news/20/full.rss';

    private const RUBRIC_MAP = [
        'finances' => 'finances',
        'business' => 'business',
        'politics' => 'politics',
        'society' => 'society',
        'economics' => 'economics',
        'technology_and_media' => 'technology_and_media',
        'sport' => 'sport',
        'auto' => 'auto',
        'realty' => 'realty',
        'pro' => 'pro',
    ];

    private string $lastDownloadError = '';

    /** @return list<array{external_id: string, title: string, url: string, summary: string, published_at: string}> */
    public function fetchLatest(int $limit = 20): array
    {
        $url = $this->resolveFeedUrl();
        $xml = $this->download($url);

        if ($xml === '') {
            $detail = $this->lastDownloadError !== '' ? ': ' . $this->lastDownloadError : '';
            throw new RuntimeException('РБК: не удалось загрузить RSS' . $detail);
        }

        if (!str_contains($xml, '<rss') && !str_contains($xml, '<feed')) {
            throw new RuntimeException(
                'РБК: ответ не похож на RSS. Проверьте RBC_NEWS_RSS_URL — нужен адрес rssexport.rbc.ru, не страница rbc.ru'
            );
        }

        $feed = @simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA);
        if ($feed === false) {
            throw new RuntimeException('РБК: не удалось разобрать RSS');
        }

        $items = [];
        $channel = $feed->channel ?? $feed;

        foreach ($channel->item ?? [] as $item) {
            $title = $this->cleanText((string) ($item->title ?? ''));
            $link = trim((string) ($item->link ?? ''));
            if ($title === '' || $link === '') {
                continue;
            }

            $summary = $this->cleanText((string) ($item->description ?? ''));
            $pubDate = trim((string) ($item->pubDate ?? ''));

            $items[] = [
                'external_id' => $this->externalId($link),
                'title' => $title,
                'url' => $link,
                'summary' => $summary,
                'published_at' => $pubDate,
            ];

            if (count($items) >= $limit) {
                break;
            }
        }

        if ($items === []) {
            throw new RuntimeException('РБК: в RSS нет новостей');
        }

        return $items;
    }

    private function resolveFeedUrl(): string
    {
        $url = trim((string) (Config::get('RBC_NEWS_RSS_URL') ?? ''));
        if ($url === '') {
            return self::DEFAULT_RSS_URL;
        }

        if (preg_match('~^https?://(?:www\.)?rbc\.ru/rubric/([a-z0-9_]+)/?~i', $url, $matches)) {
            $slug = strtolower($matches[1]);
            $section = self::RUBRIC_MAP[$slug] ?? $slug;

            return 'https://rssexport.rbc.ru/rbcnews/' . $section . '/20/full.rss';
        }

        if (preg_match('~^https?://rssexport\.rbc\.ru/~i', $url)) {
            return $url;
        }

        if (str_contains($url, 'rbc.ru') && !str_contains($url, 'rssexport')) {
            return self::DEFAULT_RSS_URL;
        }

        return $url;
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
                'Accept: application/rss+xml, application/xml, text/xml, */*',
                'Accept-Language: ru-RU,ru;q=0.9',
            ],
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; SkayClinBot/1.0; +https://skyclin.ru)',
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
                    'Accept: application/rss+xml, application/xml, text/xml, */*',
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
