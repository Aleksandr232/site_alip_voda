<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuthService;
use App\Services\SettingsService;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class SettingsController
{
    public function __construct(
        private readonly AuthController $auth = new AuthController(),
        private readonly SettingsService $settings = new SettingsService(),
        private readonly AuthService $authService = new AuthService(),
    ) {
    }

    public function show(Request $request): void
    {
        try {
            Response::success([
                'settings' => $this->settings->getPublic(),
            ]);
        } catch (Throwable $e) {
            error_log('Settings show: ' . $e->getMessage());
            Response::error(
                Config::get('APP_ENV', 'local') !== 'production' ? $e->getMessage() : 'Ошибка загрузки настроек',
                500
            );
        }
    }

    public function update(Request $request): void
    {
        try {
            $this->auth->requireUser($request);
            $action = (string) ($request->body['action'] ?? '');

            if ($action === 'contacts') {
                $settings = $this->settings->updateContacts($request->body);
                Response::success(['settings' => $settings, 'message' => 'Контакты сохранены']);
                return;
            }

            if ($action === 'homepage') {
                $settings = $this->settings->updateHomepage($request->body);
                Response::success(['settings' => $settings, 'message' => 'Настройки главной сохранены']);
                return;
            }

            if ($action === 'change_password') {
                $user = $this->auth->requireUser($request);
                $this->authService->changePassword(
                    $user,
                    (string) ($request->body['current_password'] ?? ''),
                    (string) ($request->body['new_password'] ?? ''),
                    (string) ($request->body['confirm_password'] ?? ''),
                );
                Response::success(['message' => 'Пароль изменён']);
                return;
            }

            Response::error('Неизвестное действие', 422);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 401);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            error_log('Settings update: ' . $e->getMessage());
            Response::error('Не удалось сохранить', 500);
        }
    }
}
