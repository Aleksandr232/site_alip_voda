<?php

declare(strict_types=1);

use App\Config;
use App\Database;
use App\Database\Installer;

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';
Config::load($root);

try {
    Installer::ensure();
    Database::connection();
    echo "База данных готова: " . Config::require('DB_NAME') . PHP_EOL;
    echo "Таблица users создана (если её ещё не было)." . PHP_EOL;
    echo PHP_EOL;
    echo "Дальше создайте администратора:" . PHP_EOL;
    echo "  php scripts/seed_admin.php" . PHP_EOL;
    echo "или зарегистрируйтесь на странице /login" . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Ошибка: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
