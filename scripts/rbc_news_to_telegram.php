<?php

declare(strict_types=1);

use App\Config;
use App\Database;
use App\Database\Installer;
use App\Services\RbcNewsDispatchService;

$root = dirname(__DIR__);

require $root . '/bootstrap.php';
Config::load($root);

function logLine(string $message): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
}

try {
    Installer::ensure();
    Database::connection();

    $service = new RbcNewsDispatchService();
    $result = $service->run();

    if (!empty($result['outside_hours'])) {
        logLine($result['message'] ?? 'Вне окна отправки');
        exit(0);
    }

    logLine('Готово. Отправлено: ' . $result['sent'] . ', пропущено (уже в базе): ' . $result['skipped']);

    foreach ($result['errors'] as $error) {
        logLine('Ошибка: ' . $error);
    }

    exit($result['errors'] !== [] && $result['sent'] === 0 ? 1 : 0);
} catch (Throwable $e) {
    logLine('Критическая ошибка: ' . $e->getMessage());
    exit(1);
}
