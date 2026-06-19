<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config;
use App\Http\Request;
use App\Http\Response;
use App\Services\BlogPostService;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class BlogPostController
{
    public function __construct(
        private readonly AuthController $auth = new AuthController(),
        private readonly ?BlogPostService $posts = null,
    ) {
    }

    private function service(): BlogPostService
    {
        return $this->posts ?? BlogPostService::createDefault(dirname(__DIR__, 2));
    }

    public function list(Request $request): void
    {
        try {
            $admin = isset($_GET['admin']) && $_GET['admin'] === '1';

            if ($admin) {
                $this->auth->requireUser($request);
                $items = $this->service()->listAdmin();
                Response::success(['posts' => array_map(static fn ($p) => $p->toArray(), $items)]);
                return;
            }

            $slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
            if ($slug !== '') {
                $post = $this->service()->getBySlug($slug, false);
                Response::success(['post' => $post->toArray()]);
                return;
            }

            $items = $this->service()->listPublic();
            Response::success(['posts' => array_map(static fn ($p) => $p->toArray(), $items)]);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 401);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 404);
        } catch (Throwable $e) {
            error_log('Blog list: ' . $e->getMessage());
            Response::error(
                Config::get('APP_ENV', 'local') !== 'production' ? $e->getMessage() : 'Ошибка загрузки блога',
                500
            );
        }
    }

    public function create(Request $request): void
    {
        try {
            $this->auth->requireUser($request);
            $post = $this->service()->create($_POST, $_FILES);
            Response::success(['post' => $post->toArray()], 201);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 401);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            error_log('Blog create: ' . $e->getMessage());
            Response::error('Не удалось сохранить статью', 500);
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

            $post = $this->service()->update($id, $_POST, $_FILES);
            Response::success(['post' => $post->toArray()]);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 401);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            error_log('Blog update: ' . $e->getMessage());
            Response::error('Не удалось обновить статью', 500);
        }
    }

    public function delete(Request $request): void
    {
        try {
            $this->auth->requireUser($request);
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new InvalidArgumentException('Укажите ID');
            }

            $this->service()->delete($id);
            Response::success(['message' => 'Статья удалена']);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 401);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            Response::error('Не удалось удалить статью', 500);
        }
    }
}
