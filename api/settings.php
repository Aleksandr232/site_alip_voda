<?php

declare(strict_types=1);

use App\Config;
use App\Controllers\SettingsController;
use App\Http\Request;
use App\Http\Response;

$root = dirname(__DIR__);

$respondJsonError = static function (string $message, int $status = 500): void {
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode([
        'success' => false,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
};

register_shutdown_function(static function () use ($respondJsonError): void {
    $error = error_get_last();
    if ($error === null) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    if (headers_sent()) {
        return;
    }

    $respondJsonError('Внутренняя ошибка сервера');
});

try {
    require $root . '/bootstrap.php';
    Config::load($root);
} catch (Throwable $e) {
    $respondJsonError('Bootstrap error: ' . $e->getMessage());
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$controller = new SettingsController();
$request = Request::fromGlobals();

try {
    if ($method === 'GET') {
        $controller->show($request);
        exit;
    }

    if ($method === 'POST') {
        $controller->update($request);
        exit;
    }

    Response::error('Метод не поддерживается', 405);
} catch (Throwable $e) {
    error_log('api/settings.php: ' . $e->getMessage());
    Response::error(
        Config::get('APP_ENV', 'local') !== 'production' ? $e->getMessage() : 'Ошибка API настроек',
        500
    );
}
