<?php

declare(strict_types=1);

// Старый entrypoint → канонический /article/{slug}
$slug = isset($_GET['slug']) ? strtolower(trim((string) $_GET['slug'])) : '';

if ($slug !== '' && preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug)) {
    $base = '';
    $env = __DIR__ . '/.env';
    if (is_file($env)) {
        foreach (file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if ($key === 'APP_URL') {
                $base = rtrim(trim($value, " \t\"'"), '/');
                break;
            }
        }
    }

    header('Location: ' . ($base !== '' ? $base : 'https://skyclin.ru') . '/article/' . $slug, true, 301);
    exit;
}

header('Location: https://skyclin.ru/blog', true, 301);
exit;
