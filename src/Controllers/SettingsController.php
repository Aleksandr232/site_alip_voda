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
        private readonly ?SettingsService $settings = null,
        private readonly AuthService $authService = new AuthService(),
    ) {
    }

    private function service(): SettingsService
    {
        return $this->settings ?? SettingsService::createDefault(dirname(__DIR__, 2));
    }

    public function show(Request $request): void
    {
        try {
            Response::success([
                'settings' => $this->service()->getPublic(),
            ]);
        } catch (Throwable $e) {
            error_log('Settings show: ' . $e->getMessage());
            Response::success([
                'settings' => SettingsService::DEFAULTS,
                'degraded' => true,
            ]);
        }
    }

    public function update(Request $request): void
    {
        try {
            $this->auth->requireUser($request);
            $input = array_merge($request->body, $_POST);
            $action = (string) ($input['action'] ?? '');

            if ($action === 'contacts') {
                $settings = $this->service()->updateContacts($input);
                Response::success(['settings' => $settings, 'message' => 'Контакты сохранены']);
                return;
            }

            if ($action === 'homepage') {
                $settings = $this->service()->updateHomepage($input, $_FILES);
                Response::success(['settings' => $settings, 'message' => 'Настройки главной сохранены']);
                return;
            }

            if ($action === 'calculator') {
                $settings = $this->service()->updateCalculator($input);
                Response::success(['settings' => $settings, 'message' => 'Цены калькулятора сохранены']);
                return;
            }

            if ($action === 'change_password') {
                $user = $this->auth->requireUser($request);
                $this->authService->changePassword(
                    $user,
                    (string) ($input['current_password'] ?? ''),
                    (string) ($input['new_password'] ?? ''),
                    (string) ($input['confirm_password'] ?? ''),
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
