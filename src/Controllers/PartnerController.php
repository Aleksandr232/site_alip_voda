<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config;
use App\Http\Request;
use App\Http\Response;
use App\Services\PartnerService;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class PartnerController
{
    public function __construct(
        private readonly AuthController $auth = new AuthController(),
        private readonly ?PartnerService $partners = null,
    ) {
    }

    private function service(): PartnerService
    {
        return $this->partners ?? PartnerService::createDefault(dirname(__DIR__, 2));
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
                'partners' => array_map(static fn ($item) => $item->toArray(), $items),
            ]);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 401);
        } catch (Throwable $e) {
            error_log('Partner list: ' . $e->getMessage());
            Response::error(
                Config::get('APP_ENV', 'local') !== 'production' ? $e->getMessage() : 'Ошибка загрузки партнёров',
                500
            );
        }
    }

    public function create(Request $request): void
    {
        try {
            $this->auth->requireUser($request);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 401);

            return;
        }

        try {
            $item = $this->service()->create($_POST, $_FILES);
            Response::success(['partner' => $item->toArray()], 201);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            error_log('Partner create: ' . $e->getMessage());
            Response::error(
                Config::get('APP_ENV', 'local') !== 'production' ? $e->getMessage() : 'Не удалось сохранить партнёра',
                500
            );
        }
    }

    public function update(Request $request): void
    {
        try {
            $this->auth->requireUser($request);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 401);

            return;
        }

        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new InvalidArgumentException('Укажите ID');
            }

            $item = $this->service()->update($id, $_POST, $_FILES);
            Response::success(['partner' => $item->toArray()]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            error_log('Partner update: ' . $e->getMessage());
            Response::error(
                Config::get('APP_ENV', 'local') !== 'production' ? $e->getMessage() : 'Не удалось обновить партнёра',
                500
            );
        }
    }

    public function delete(Request $request): void
    {
        try {
            $this->auth->requireUser($request);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 401);

            return;
        }

        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new InvalidArgumentException('Укажите ID');
            }

            $this->service()->delete($id);
            Response::success(['message' => 'Партнёр удалён']);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            error_log('Partner delete: ' . $e->getMessage());
            Response::error('Не удалось удалить партнёра', 500);
        }
    }
}
