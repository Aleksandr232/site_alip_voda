<?php

declare(strict_types=1);

use App\Config;
use App\Controllers\RequestController;
use App\Http\Request;
use App\Http\Response;

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

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: POST, OPTIONS');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($method !== 'POST') {
    Response::error('Используйте POST для отправки заявки', 405);
    exit;
}

try {
  $request = Request::fromGlobals();
  (new RequestController())->create($request);
} catch (Throwable $e) {
    error_log('api/requests.php: ' . $e->getMessage());
    Response::error(
        Config::get('APP_ENV', 'local') !== 'production'
            ? $e->getMessage()
            : 'Не удалось отправить заявку',
        500
    );
}
