<?php

declare(strict_types=1);

use App\Config;
use App\Database;
use App\Database\Installer;
use App\Http\Response;
use App\Services\CronAuthService;
use App\Services\RbcNewsDispatchService;

$root = dirname(__DIR__);

require $root . '/bootstrap.php';
Config::load($root);

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    Response::error('Разрешён только GET', 405);
    exit;
}

try {
    $auth = new CronAuthService();
    $auth->verify();

    Installer::ensure();
    Database::connection();

    $result = (new RbcNewsDispatchService())->run();

    Response::success([
        'sent' => $result['sent'],
        'skipped' => $result['skipped'],
        'errors' => $result['errors'],
        'ran_at' => date('c'),
    ]);
} catch (Throwable $e) {
    $code = (int) $e->getCode();
    $status = $code >= 400 && $code < 600 ? $code : 500;

    if ($status === 403) {
        Response::error('Доступ запрещён', 403);
        exit;
    }

    error_log('api/cron_rbc_news.php: ' . $e->getMessage());
    Response::error(
        Config::get('APP_ENV', 'local') !== 'production' ? $e->getMessage() : 'Ошибка cron-задачи',
        $status >= 400 ? $status : 500
    );
}
