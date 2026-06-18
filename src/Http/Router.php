<?php

declare(strict_types=1);

namespace App\Http;

use Closure;

final class Router
{
    /** @var array<string, array<string, Closure>> */
    private array $routes = [];

    public function post(string $path, Closure $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function get(string $path, Closure $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function dispatch(Request $request): void
    {
        $handler = $this->routes[$request->method][$request->path] ?? null;
        if (!$handler) {
            Response::error('Endpoint not found', 404);
            return;
        }

        $handler($request);
    }

    private function add(string $method, string $path, Closure $handler): void
    {
        $this->routes[$method][$this->normalize($path)] = $handler;
    }

    private function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
