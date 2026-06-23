<?php

declare(strict_types=1);

use App\Config;
use App\Controllers\AuthController;
use App\Controllers\ClientController;
use App\Controllers\RequestController;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;

$root = dirname(__DIR__);

try {
    require $root . '/bootstrap.php';
    Config::load($root);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Bootstrap error: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$request = Request::fromGlobals();
$auth = new AuthController();
$requests = new RequestController();
$clients = new ClientController();
$router = new Router();

$router->post('/auth/register', fn (Request $req) => $auth->register($req));
$router->post('/auth/login', fn (Request $req) => $auth->login($req));
$router->get('/auth/me', fn (Request $req) => $auth->me($req));
$router->post('/auth/logout', fn () => $auth->logout());
$router->post('/requests', fn (Request $req) => $requests->create($req));
$router->get('/captcha', fn () => (new \App\Controllers\CaptchaController())->issue());
$router->get('/requests', fn (Request $req) => $requests->list($req));
$router->get('/requests/stats', fn (Request $req) => $requests->stats($req));
$router->post('/requests/update', fn (Request $req) => $requests->updateStatus($req));
$router->post('/requests/delete', fn (Request $req) => $requests->delete($req));
$router->get('/clients', fn (Request $req) => $clients->list($req));
$router->get('/health', function () {
    $payload = ['status' => 'ok', 'php' => PHP_VERSION];

    try {
        \App\Database::connection();
        $payload['database'] = 'connected';
        $payload['schema_ready'] = \App\Database\Installer::isReady();
    } catch (Throwable $e) {
        $payload['database'] = 'error';
        $payload['database_message'] = $e->getMessage();
    }

    Response::success($payload);
});

$router->get('/install', function () {
    try {
        $result = \App\Database\Installer::ensure(true);
        \App\Database::connection();

        Response::success([
            'message' => 'База данных и таблицы проверены',
            'database' => Config::require('DB_NAME'),
            'tables' => $result['tables'] ?? [],
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
