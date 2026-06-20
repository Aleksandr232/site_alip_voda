<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;

final class SettingsRepository
{
    /** @return array<string, string> */
    public function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT setting_key, setting_value FROM site_settings'
        );

        $settings = [];
        foreach ($stmt->fetchAll() as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        return $settings;
    }

    /** @param array<string, string> $values */
    public function setMany(array $values): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO site_settings (setting_key, setting_value)
             VALUES (:key, :value)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );

        foreach ($values as $key => $value) {
            $stmt->execute([
                'key' => $key,
                'value' => $value,
            ]);
        }
    }
}
