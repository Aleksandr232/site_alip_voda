<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use RuntimeException;
use SimpleXMLElement;

final class RbcNewsParserService
{
    private const DEFAULT_RSS_URL = 'https://rssexport.rbc.ru/rbcnews/news/20/full.rss';

    /** @return list<array{external_id: string, title: string, url: string, summary: string, published_at: string}> */
    public function fetchLatest(int $limit = 20): array
    {
        $url = trim((string) (Config::get('RBC_NEWS_RSS_URL') ?? ''));
        if ($url === '') {
            $url = self::DEFAULT_RSS_URL;
        }

        $xml = $this->download($url);
        if ($xml === '') {
            throw new RuntimeException('РБК: не удалось загрузить RSS');
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

        return $items;
    }

    private function download(string $url): string
    {
        if (function_exists('curl_init')) {
            $handle = curl_init($url);
            if ($handle === false) {
                return '';
            }

            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_USERAGENT => 'SkayClin-RBC-Parser/1.0',
            ]);

            $response = curl_exec($handle);
            $code = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            curl_close($handle);

            if (!is_string($response) || $code < 200 || $code >= 300) {
                return '';
            }

            return $response;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 25,
                'header' => "User-Agent: SkayClin-RBC-Parser/1.0\r\n",
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        return is_string($response) ? $response : '';
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
