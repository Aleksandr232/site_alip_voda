<?php

declare(strict_types=1);

namespace App;

use App\Database\Installer;
use PDO;
use PDOException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO && self::isAlive(self::$connection)) {
            return self::$connection;
        }

        self::$connection = null;

        if (!Installer::isReady()) {
            Installer::ensure();
        }

        if (!self::$connection instanceof PDO) {
            self::$connection = self::open();
        }

        return self::$connection;
    }

    public static function adopt(PDO $connection): void
    {
        self::$connection = $connection;
    }

    public static function disconnect(): void
    {
        self::$connection = null;
    }

    private static function open(): PDO
    {
        $host = Config::get('DB_HOST', '127.0.0.1');
        $port = Config::get('DB_PORT', '3306');
        $name = Config::require('DB_NAME');
        $user = Config::require('DB_USER');
        $pass = Config::get('DB_PASS', '');

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

        try {
            return new PDO($dsn, $user, $pass, self::options());
        } catch (PDOException $e) {
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /** @return array<int, mixed> */
    private static function options(): array
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ];

        if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        return $options;
    }

    private static function isAlive(PDO $connection): bool
    {
        try {
            $connection->query('SELECT 1');

            return true;
        } catch (PDOException) {
            return false;
        }
    }

    public static function isDisconnectError(PDOException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'server has gone away')
            || str_contains($message, 'lost connection')
            || str_contains($message, 'error while sending')
            || str_contains($message, 'broken pipe');
    }
}
