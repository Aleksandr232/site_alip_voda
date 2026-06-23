<?php

declare(strict_types=1);

use App\Config;
use App\Database;
use App\Database\Installer;

$root = dirname(__DIR__);

require $root . '/bootstrap.php';
Config::load($root);

try {
    $result = Installer::ensure(true);
    Database::connection();

    echo 'База данных: ' . Config::require('DB_NAME') . PHP_EOL;
    echo PHP_EOL;

    $status = Installer::status();
    foreach ($status as $table => $exists) {
        echo sprintf("  %-20s %s\n", $table, $exists ? 'OK' : 'НЕТ');
    }

    if (!empty($result['created'])) {
        echo PHP_EOL . 'Созданы таблицы: ' . implode(', ', $result['created']) . PHP_EOL;
    } else {
        echo PHP_EOL . 'Все таблицы уже существуют.' . PHP_EOL;
    }

    echo PHP_EOL . 'Создайте администратора:' . PHP_EOL;
    echo '  php scripts/seed_admin.php' . PHP_EOL;
    echo '  или зарегистрируйтесь на /login' . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Ошибка: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
