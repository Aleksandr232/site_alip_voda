<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\GalleryService;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class GalleryController
{
    public function __construct(
        private readonly AuthController $auth = new AuthController(),
        private readonly ?GalleryService $gallery = null,
    ) {
    }

    private function service(): GalleryService
    {
        return $this->gallery ?? GalleryService::createDefault(dirname(__DIR__, 2));
    }

    public function list(Request $request): void
    {
        try {
            $admin = isset($_GET['admin']) && $_GET['admin'] === '1';

            if ($admin) {
                $this->auth->requireUser($request);
                $items = $this->service()->listAdmin();
            } else {
                $items = $this->service()->listPublic();
            }

            Response::success([
                'items' => array_map(static fn ($item) => $item->toArray(), $items),
            ]);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 401);
        } catch (Throwable $e) {
            Response::error('Ошибка загрузки галереи', 500);
        }
    }

    public function create(Request $request): void
    {
        try {
            $this->auth->requireUser($request);
            $item = $this->service()->create($_POST, $_FILES);
            Response::success(['item' => $item->toArray()], 201);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 401);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            error_log('Gallery create: ' . $e->getMessage());
            Response::error('Не удалось сохранить', 500);
        }
    }

    public function update(Request $request): void
    {
        try {
            $this->auth->requireUser($request);
            $id = (int) ($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new InvalidArgumentException('Укажите ID');
            }

            $item = $this->service()->update($id, $_POST, $_FILES);
            Response::success(['item' => $item->toArray()]);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 401);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            error_log('Gallery update: ' . $e->getMessage());
            Response::error('Не удалось обновить', 500);
        }
    }

    public function delete(Request $request): void
    {
        try {
            $this->auth->requireUser($request);
            $id = (int) ($_POST['id'] ?? $request->body['id'] ?? 0);

            if ($id <= 0) {
                throw new InvalidArgumentException('Укажите ID');
            }

            $this->service()->delete($id);
            Response::success(['message' => 'Удалено']);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 401);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            Response::error('Не удалось удалить', 500);
        }
    }
}
