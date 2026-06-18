<?php

declare(strict_types=1);

namespace App\Database;

use App\Config;
use PDO;
use PDOException;

final class Installer
{
    /** @var array<string, string> */
    private static array $tables = [
        'users' => <<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'editor') NOT NULL DEFAULT 'editor',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        'clients' => <<<'SQL'
CREATE TABLE IF NOT EXISTS clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(32) NOT NULL,
    email VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_clients_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        'requests' => <<<'SQL'
CREATE TABLE IF NOT EXISTS requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    service_type VARCHAR(50) NOT NULL,
    message TEXT NULL,
    status ENUM('new', 'in_progress', 'done') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_requests_status (status),
    KEY idx_requests_client (client_id),
    CONSTRAINT fk_requests_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        'gallery_items' => <<<'SQL'
CREATE TABLE IF NOT EXISTS gallery_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    before_image VARCHAR(500) NOT NULL,
    after_image VARCHAR(500) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('published', 'hidden') NOT NULL DEFAULT 'published',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_gallery_status_sort (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    ];

    public static function ensure(): array
    {
        if (Config::get('DB_AUTO_INSTALL', 'true') !== 'true') {
            return ['skipped' => true, 'tables' => [], 'created' => []];
        }

        $pdo = self::connect();
        $created = [];
        $existing = [];

        foreach (self::$tables as $table => $sql) {
            $exists = self::tableExists($pdo, $table);
            $pdo->exec($sql);

            if ($exists) {
                $existing[] = $table;
            } else {
                $created[] = $table;
            }
        }

        $status = [];
        foreach (array_keys(self::$tables) as $table) {
            $status[$table] = true;
        }

        return [
            'skipped' => false,
            'tables' => $status,
            'created' => $created,
            'existing' => $existing,
        ];
    }

    public static function status(): array
    {
        $pdo = self::connect();
        $tables = [];

        foreach (array_keys(self::$tables) as $table) {
            $tables[$table] = self::tableExists($pdo, $table);
        }

        return $tables;
    }

    private static function connect(): PDO
    {
        $host = Config::get('DB_HOST', '127.0.0.1');
        $port = Config::get('DB_PORT', '3306');
        $name = Config::require('DB_NAME');
        $user = Config::require('DB_USER');
        $pass = Config::get('DB_PASS', '');

        try {
            return self::createConnection($host, $port, $name, $user, $pass, true);
        } catch (PDOException $e) {
            if (!self::isUnknownDatabase($e)) {
                throw new \RuntimeException('Не удалось подключиться к MySQL: ' . $e->getMessage(), 0, $e);
            }
        }

        try {
            $server = self::createConnection($host, $port, $name, $user, $pass, false);
            $dbName = self::quoteIdentifier($name);
            $server->exec(
                "CREATE DATABASE IF NOT EXISTS {$dbName}
                 CHARACTER SET utf8mb4
                 COLLATE utf8mb4_unicode_ci"
            );
        } catch (PDOException $e) {
            throw new \RuntimeException(
                'База данных не найдена и не удалось создать её автоматически. ' .
                'Создайте базу в панели хостинга или проверьте DB_NAME в .env. ' .
                $e->getMessage(),
                0,
                $e
            );
        }

        return self::createConnection($host, $port, $name, $user, $pass, true);
    }

    private static function createConnection(
        string $host,
        string $port,
        string $name,
        string $user,
        string $pass,
        bool $withDatabase
    ): PDO {
        $dsn = $withDatabase
            ? sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name)
            : sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);

        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $stmt->execute(['table' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private static function isUnknownDatabase(PDOException $e): bool
    {
        return str_contains($e->getMessage(), 'Unknown database');
    }

    private static function quoteIdentifier(string $value): string
    {
        return '`' . str_replace('`', '``', $value) . '`';
    }
}
