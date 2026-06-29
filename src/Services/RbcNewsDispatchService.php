<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\Repositories\RbcNewsSentRepository;
use RuntimeException;

final class RbcNewsDispatchService
{
    private const CAPTION_LIMIT = 1000;

    public function __construct(
        private readonly RbcNewsParserService $parser = new RbcNewsParserService(),
        private readonly RbcNewsSentRepository $sent = new RbcNewsSentRepository(),
        private readonly TelegramService $telegram = new TelegramService(),
    ) {
    }

    /** @return array{sent: int, skipped: int, errors: list<string>} */
    public function run(): array
    {
        if (Config::get('TELEGRAM_NEWS_ENABLED', 'true') !== 'true') {
            throw new RuntimeException('Отправка новостей отключена (TELEGRAM_NEWS_ENABLED=false)');
        }

        if (!$this->telegram->isConfigured()) {
            throw new RuntimeException('Telegram не настроен');
        }

        $maxPerRun = max(1, (int) Config::get('TELEGRAM_NEWS_MAX_PER_RUN', '10'));
        $fetchLimit = max($maxPerRun, (int) Config::get('RBC_NEWS_FETCH_LIMIT', '30'));

        $items = $this->parser->fetchLatest($fetchLimit);
        $sent = 0;
        $skipped = 0;
        $errors = [];

        foreach ($items as $item) {
            if ($this->sent->exists($item['external_id'])) {
                $skipped++;
                continue;
            }

            if ($sent >= $maxPerRun) {
                break;
            }

            try {
                $item = $this->parser->enrichItem($item);
                $caption = $this->formatCaption($item);
                $post = $this->telegram->sendNewsPost($caption, (string) ($item['image'] ?? ''));

                $this->sent->markSent(
                    $item['external_id'],
                    $item['url'],
                    $item['title'],
                    $post['message_id'] > 0 ? $post['message_id'] : null,
                );
                $sent++;
            } catch (\Throwable $e) {
                $errors[] = $item['title'] . ': ' . $e->getMessage();
            }
        }

        return [
            'sent' => $sent,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /** @param array{title: string, url: string, summary: string, published_at: string, image?: string} $item */
    private function formatCaption(array $item): string
    {
        $lines = [
            '<b>' . $this->escapeHtml($item['title']) . '</b>',
        ];

        if ($item['summary'] !== '') {
            $summary = $item['summary'];
            if (mb_strlen($summary) > 700) {
                $summary = mb_substr($summary, 0, 697) . '…';
            }
            $lines[] = $this->escapeHtml($summary);
        }

        if ($item['published_at'] !== '') {
            $lines[] = '<i>' . $this->escapeHtml($item['published_at']) . '</i>';
        }

        $lines[] = '<a href="' . $this->escapeHtml($item['url']) . '">Читать на РБК</a>';

        $caption = implode("\n\n", $lines);
        if (mb_strlen($caption) > self::CAPTION_LIMIT) {
            $caption = mb_substr($caption, 0, self::CAPTION_LIMIT - 1) . '…';
        }

        return $caption;
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
