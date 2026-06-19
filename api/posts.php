<?php

declare(strict_types=1);

use App\Config;
use App\Controllers\BlogPostController;
use App\Http\Request;
use App\Http\Response;

$root = dirname(__DIR__);

try {
    require $root . '/bootstrap.php';
    Config::load($root);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Bootstrap error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
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

$controller = new BlogPostController();
$request = Request::fromGlobals();

try {
    \App\Database\Installer::ensure();

    if ($method === 'GET') {
        $controller->list($request);
        exit;
    }

    if ($method === 'POST') {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'update') {
            $controller->update($request);
        } elseif ($action === 'delete') {
            $controller->delete($request);
        } else {
            $controller->create($request);
        }
        exit;
    }

    Response::error('Метод не поддерживается', 405);
} catch (Throwable $e) {
    error_log('api/posts.php: ' . $e->getMessage());
    Response::error(
        Config::get('APP_ENV', 'local') !== 'production' ? $e->getMessage() : 'Ошибка API блога',
        500
    );
}
