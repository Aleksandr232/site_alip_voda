<?php

declare(strict_types=1);

namespace App\Database;

use App\Config;
use PDO;
use PDOException;

final class Installer
{
    public static function ensure(): void
    {
        if (Config::get('DB_AUTO_INSTALL', 'true') !== 'true') {
            return;
        }

        $host = Config::get('DB_HOST', '127.0.0.1');
        $port = Config::get('DB_PORT', '3306');
        $name = Config::require('DB_NAME');
        $user = Config::require('DB_USER');
        $pass = Config::get('DB_PASS', '');

        $serverDsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);

        try {
            $pdo = new PDO($serverDsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('Не удалось подключиться к MySQL: ' . $e->getMessage(), 0, $e);
        }

        $dbName = self::quoteIdentifier($name);
        $pdo->exec(
            "CREATE DATABASE IF NOT EXISTS {$dbName}
             CHARACTER SET utf8mb4
             COLLATE utf8mb4_unicode_ci"
        );

        $pdo->exec("USE {$dbName}");

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role ENUM(\'admin\', \'editor\') NOT NULL DEFAULT \'editor\',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_users_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private static function quoteIdentifier(string $value): string
    {
        return '`' . str_replace('`', '``', $value) . '`';
    }
}
