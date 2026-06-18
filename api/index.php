<?php

declare(strict_types=1);

use App\Config;
use App\Controllers\AuthController;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

Config::load($root);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$request = Request::fromGlobals();
$auth = new AuthController();
$router = new Router();

$router->post('/auth/register', fn (Request $req) => $auth->register($req));
$router->post('/auth/login', fn (Request $req) => $auth->login($req));
$router->get('/auth/me', fn (Request $req) => $auth->me($req));
$router->post('/auth/logout', fn () => $auth->logout());
$router->get('/health', function () {
    $payload = ['status' => 'ok', 'php' => PHP_VERSION];

    try {
        $install = \App\Database\Installer::ensure();
        \App\Database::connection();
        $payload['database'] = 'connected';
        $payload['tables'] = \App\Database\Installer::status();
        if (!empty($install['created'])) {
            $payload['tables_created'] = $install['created'];
        }
    } catch (Throwable $e) {
        $payload['database'] = 'error';
        $payload['database_message'] = Config::get('APP_ENV', 'local') === 'local'
            ? $e->getMessage()
            : 'connection failed';
    }

    Response::success($payload);
});

$router->get('/install', function () {
    try {
        $result = \App\Database\Installer::ensure();
        \App\Database::connection();

        Response::success([
            'message' => 'База данных и таблицы проверены',
            'database' => Config::require('DB_NAME'),
            'tables' => \App\Database\Installer::status(),
            'created' => $result['created'] ?? [],
        ]);
    } catch (Throwable $e) {
        Response::error(
            Config::get('APP_ENV', 'local') === 'local'
                ? $e->getMessage()
                : 'Ошибка установки базы данных',
            500
        );
    }
});

try {
    $router->dispatch($request);
} catch (Throwable $e) {
    $isDev = Config::get('APP_ENV', 'local') === 'local';
    Response::error(
        $isDev ? $e->getMessage() : 'Internal server error',
        500
    );
}
