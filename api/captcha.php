<?php

declare(strict_types=1);

use App\Config;
use App\Controllers\CaptchaController;
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
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Cache-Control: no-store, no-cache, must-revalidate');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($method !== 'GET') {
    Response::error('Метод не поддерживается', 405);
}

(new CaptchaController())->issue();
