<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

final class RbcNewsSentRepository
{
    public function exists(string $externalId): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT 1 FROM rbc_news_sent WHERE external_id = :external_id LIMIT 1'
        );
        $stmt->execute(['external_id' => $externalId]);

        return (bool) $stmt->fetchColumn();
    }

    public function markSent(
        string $externalId,
        string $sourceUrl,
        string $title,
        ?int $telegramMessageId = null,
    ): void {
        $stmt = Database::connection()->prepare(
            'INSERT INTO rbc_news_sent (external_id, source_url, title, telegram_message_id)
             VALUES (:external_id, :source_url, :title, :telegram_message_id)'
        );
        $stmt->execute([
            'external_id' => $externalId,
            'source_url' => $sourceUrl,
            'title' => $title,
            'telegram_message_id' => $telegramMessageId,
        ]);
    }
}
