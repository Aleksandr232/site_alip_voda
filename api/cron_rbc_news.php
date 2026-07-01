<?php

declare(strict_types=1);

use App\Config;
use App\Database;
use App\Database\Installer;
use App\Http\Response;
use App\Services\CronAuthService;
use App\Services\RbcNewsDispatchService;
use App\Services\RbcNewsParserService;

$root = dirname(__DIR__);

require $root . '/bootstrap.php';
Config::load($root);

$meta = [
    'parser_version' => RbcNewsParserService::VERSION,
    'source_url' => trim((string) (Config::get('RBC_NEWS_RSS_URL') ?? '')),
];

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    Response::error('Разрешён только GET', 405, $meta);
    exit;
}

try {
    $auth = new CronAuthService();
    $auth->verify();

    Installer::ensure();
    Database::connection();

    $result = (new RbcNewsDispatchService())->run();

    Response::success(array_merge($meta, [
        'sent' => $result['sent'],
        'skipped' => $result['skipped'],
        'errors' => $result['errors'],
        'outside_hours' => $result['outside_hours'] ?? false,
        'moscow_time' => $result['moscow_time'] ?? null,
        'message' => $result['message'] ?? null,
        'ran_at' => date('c'),
    ]));
} catch (Throwable $e) {
    $code = (int) $e->getCode();
    $status = $code >= 400 && $code < 600 ? $code : 500;

    if ($status === 403) {
        Response::error('Доступ запрещён', 403, $meta);
        exit;
    }

    error_log('api/cron_rbc_news.php: ' . $e->getMessage());
    Response::error($e->getMessage(), $status >= 400 ? $status : 500, $meta);
}
