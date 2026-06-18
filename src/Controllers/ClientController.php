<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\ClientRepository;
use RuntimeException;
use Throwable;

final class ClientController
{
    public function __construct(
        private readonly AuthController $auth = new AuthController(),
        private readonly ClientRepository $clients = new ClientRepository(),
    ) {
    }

    public function list(Request $request): void
    {
        try {
            $this->auth->requireUser($request);
            $items = $this->clients->allWithStats();

            Response::success([
                'clients' => array_map(static fn (array $row) => $row, $items),
            ]);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 401);
        } catch (Throwable $e) {
            Response::error('Ошибка загрузки клиентов', 500);
        }
    }
}
