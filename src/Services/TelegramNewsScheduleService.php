<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use DateTimeImmutable;
use DateTimeZone;

final class TelegramNewsScheduleService
{
    public function isAllowedNow(): bool
    {
        $from = $this->hourFrom();
        $to = $this->hourTo();
        $minutes = $this->moscowMinutesNow();

        return $minutes >= $from * 60 && $minutes <= $to * 60;
    }

    public function moscowTime(): string
    {
        return (new DateTimeImmutable('now', $this->timezone()))->format('H:i');
    }

    public function windowLabel(): string
    {
        return sprintf(
            '%02d:00–%02d:00 МСК',
            $this->hourFrom(),
            $this->hourTo()
        );
    }

    private function hourFrom(): int
    {
        return $this->clampHour((int) Config::get('TELEGRAM_NEWS_HOUR_FROM', '9'), 9);
    }

    private function hourTo(): int
    {
        return $this->clampHour((int) Config::get('TELEGRAM_NEWS_HOUR_TO', '23'), 23);
    }

    private function clampHour(int $hour, int $default): int
    {
        if ($hour < 0 || $hour > 23) {
            return $default;
        }

        return $hour;
    }

    private function moscowMinutesNow(): int
    {
        $now = new DateTimeImmutable('now', $this->timezone());

        return (int) $now->format('G') * 60 + (int) $now->format('i');
    }

    private function timezone(): DateTimeZone
    {
        return new DateTimeZone('Europe/Moscow');
    }
}
