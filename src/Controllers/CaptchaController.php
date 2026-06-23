<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Services\CaptchaService;
use Throwable;

final class CaptchaController
{
    public function __construct(
        private readonly CaptchaService $captcha = new CaptchaService(),
    ) {
    }

    public function issue(): void
    {
        try {
            Response::success($this->captcha->issue());
        } catch (Throwable $e) {
            error_log('Captcha issue failed: ' . $e->getMessage());
            Response::error('Не удалось загрузить проверку', 500);
        }
    }
}
