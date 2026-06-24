<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config;
use App\Http\Request;
use App\Http\Response;
use App\Services\CaptchaService;
use App\Services\RequestService;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class RequestController
{
    public function __construct(
        private readonly AuthController $auth = new AuthController(),
        private readonly RequestService $requests = new RequestService(),
        private readonly CaptchaService $captcha = new CaptchaService(),
    ) {
    }

    public function create(Request $request): void
    {
        try {
            $name = (string) ($request->body['name'] ?? '');
            $phone = (string) ($request->body['phone'] ?? '');
            $serviceType = (string) ($request->body['type'] ?? $request->body['service_type'] ?? '');
            $message = isset($request->body['message']) ? (string) $request->body['message'] : null;
            $captchaAnswer = $request->body['captcha_answer'] ?? $request->body['captchaAnswer'] ?? '';

            $this->captcha->verify($captchaAnswer);

            $item = $this->requests->create($name, $phone, $serviceType, $message);
            Response::success([
                'message' => 'Заявка принята. Мы свяжемся с вами в ближайшее время.',
                'request' => $item->toArray(),
            ], 201);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            error_log('Request create failed: ' . $e->getMessage());
            Response::error(
                Config::get('APP_ENV', 'local') !== 'production'
                    ? $e->getMessage()
                    : 'Не удалось отправить заявку',
                500
            );
        }
    }

    public function list(Request $request): void
    {
        try {
            $this->auth->requireUser($request);
            $status = isset($_GET['status']) ? (string) $_GET['status'] : null;
            $items = $this->requests->list($status);

            Response::success([
                'requests' => array_map(static fn ($item) => $item->toArray(), $items),
            ]);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 401);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            Response::error('Ошибка загрузки заявок', 500);
        }
    }

    public function stats(Request $request): void
    {
        try {
            $this->auth->requireUser($request);
            Response::success(['stats' => $this->requests->stats()]);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 401);
        } catch (Throwable $e) {
            Response::error('Ошибка загрузки статистики', 500);
        }
    }

    public function updateStatus(Request $request): void
    {
        try {
            $this->auth->requireUser($request);
            $id = (int) ($request->body['id'] ?? 0);
            $status = (string) ($request->body['status'] ?? '');

            if ($id <= 0) {
                throw new InvalidArgumentException('Укажите ID заявки');
            }

            $item = $this->requests->updateStatus($id, $status);
            Response::success(['request' => $item->toArray()]);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 401);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            Response::error('Не удалось обновить статус', 500);
        }
    }

    public function delete(Request $request): void
    {
        try {
            $this->auth->requireUser($request);
            $id = (int) ($request->body['id'] ?? 0);

            if ($id <= 0) {
                throw new InvalidArgumentException('Укажите ID заявки');
            }

            $this->requests->delete($id);
            Response::success(['message' => 'Заявка удалена']);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 401);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            Response::error('Не удалось удалить заявку', 500);
        }
    }
}
