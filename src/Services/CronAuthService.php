<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use RuntimeException;

final class CronAuthService
{
    public function isConfigured(): bool
    {
        return $this->secret() !== null;
    }

    public function verify(): void
    {
        $secret = $this->secret();
        if ($secret === null) {
            throw new RuntimeException('CRON_SECRET не задан в .env (минимум 16 символов)');
        }

        $provided = trim((string) ($_GET['key'] ?? $_SERVER['HTTP_X_CRON_KEY'] ?? ''));
        if ($provided === '' || !hash_equals($secret, $provided)) {
            throw new RuntimeException('Неверный ключ cron', 403);
        }
    }

    private function secret(): ?string
    {
        $value = trim((string) (Config::get('CRON_SECRET') ?? ''));

        return strlen($value) >= 16 ? $value : null;
    }
}
