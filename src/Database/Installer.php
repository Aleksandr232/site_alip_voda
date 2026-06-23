<?php

declare(strict_types=1);

namespace App\Database;

use App\Config;
use PDO;
use PDOException;

final class Installer
{
    private const SCHEMA_VERSION = 1;

    private static bool $runtimeReady = false;

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
        'blog_posts' => <<<'SQL'
CREATE TABLE IF NOT EXISTS blog_posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT NULL,
    keywords VARCHAR(500) NULL,
    content MEDIUMTEXT NULL,
    cover_image VARCHAR(500) NULL,
    video_path VARCHAR(500) NULL,
    status ENUM('published', 'draft') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_blog_slug (slug),
    KEY idx_blog_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        'partners' => <<<'SQL'
CREATE TABLE IF NOT EXISTS partners (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    website VARCHAR(500) NULL,
    logo_image VARCHAR(500) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('published', 'hidden') NOT NULL DEFAULT 'published',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_partners_status_sort (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        'site_settings' => <<<'SQL'
CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    ];

    public static function isReady(): bool
    {
        if (self::$runtimeReady) {
            return true;
        }

        if (Config::get('DB_AUTO_INSTALL', 'true') !== 'true') {
            self::$runtimeReady = true;

            return true;
        }

        $lock = self::readLockVersion();

        return $lock === self::SCHEMA_VERSION;
    }

    public static function ensure(bool $force = false): array
    {
        if (!$force && self::isReady()) {
            return [
                'skipped' => false,
                'cached' => true,
                'tables' => array_fill_keys(array_keys(self::$tables), true),
                'created' => [],
                'existing' => array_keys(self::$tables),
            ];
        }

        if (Config::get('DB_AUTO_INSTALL', 'true') !== 'true' && !$force) {
            self::$runtimeReady = true;

            return ['skipped' => true, 'tables' => [], 'created' => []];
        }

        $pdo = self::connect();
        \App\Database::adopt($pdo);

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

        self::migrate($pdo);
        self::writeLock();
        self::$runtimeReady = true;

        return [
            'skipped' => false,
            'tables' => $status,
            'created' => $created,
            'existing' => $existing,
        ];
    }

    public static function status(): array
    {
        $pdo = \App\Database::connection();
        $tables = [];

        foreach (array_keys(self::$tables) as $table) {
            $tables[$table] = self::tableExists($pdo, $table);
        }

        return $tables;
    }

    private static function lockPath(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'schema.lock';
    }

    private static function readLockVersion(): ?int
    {
        $path = self::lockPath();
        if (!is_file($path)) {
            return null;
        }

        $version = (int) trim((string) file_get_contents($path));

        return $version > 0 ? $version : null;
    }

    private static function writeLock(): void
    {
        $dir = dirname(self::lockPath());
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return;
        }

        file_put_contents(self::lockPath(), (string) self::SCHEMA_VERSION, LOCK_EX);
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
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare('SHOW TABLES LIKE :table');
        $stmt->execute(['table' => $table]);

        return (bool) $stmt->fetchColumn();
    }

    private static function migrate(PDO $pdo): void
    {
        if (self::tableExists($pdo, 'partners') && !self::columnExists($pdo, 'partners', 'website')) {
            $pdo->exec('ALTER TABLE partners ADD COLUMN website VARCHAR(500) NULL AFTER name');
        }

        self::seedSiteSettings($pdo);
        self::ensureSetting($pdo, 'phone_visible', '1');
    }

    private static function ensureSetting(PDO $pdo, string $key, string $value): void
    {
        if (!self::tableExists($pdo, 'site_settings')) {
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM site_settings WHERE setting_key = :key'
        );
        $stmt->execute(['key' => $key]);

        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO site_settings (setting_key, setting_value) VALUES (:key, :value)'
        );
        $insert->execute(['key' => $key, 'value' => $value]);
    }

    private static function seedSiteSettings(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'site_settings')) {
            return;
        }

        $defaults = [
            'phone' => '+7 (900) 123-45-67',
            'phone_visible' => '1',
            'email' => 'info@skyclin.ru',
            'hours' => 'Пн–Сб, 8:00–20:00',
            'hero_title' => 'Чистота и безопасность на высоте без лесов и подъёмников',
            'hero_lead' => 'Мойка фасадов и окон, монтажные работы и зимняя уборка снега с кровли — промышленными альпинистами. Высокое давление, обратный осмос, допуски СРО.',
            'stat_years' => '12+',
            'stat_objects' => '500+',
        ];

        $count = (int) $pdo->query('SELECT COUNT(*) FROM site_settings')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO site_settings (setting_key, setting_value) VALUES (:key, :value)'
        );

        foreach ($defaults as $key => $value) {
            $stmt->execute(['key' => $key, 'value' => $value]);
        }
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(sprintf('SHOW COLUMNS FROM `%s` LIKE :column', str_replace('`', '``', $table)));
        $stmt->execute(['column' => $column]);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
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
