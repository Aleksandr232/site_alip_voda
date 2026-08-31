<?php

declare(strict_types=1);

$root = __DIR__;
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($base !== '' && $base !== '/' && str_starts_with($uri, $base)) {
    $uri = substr($uri, strlen($base)) ?: '/';
}

$path = trim($uri, '/');

// SEO: дубли → канонические URL
if ($path === 'index.html' || $path === 'index.php') {
    header('Location: ' . ($base !== '' ? $base : '') . '/', true, 301);
    return true;
}

if ($path === 'blog.html' || $path === 'blog.php') {
    header('Location: ' . ($base !== '' ? $base : '') . '/blog', true, 301);
    return true;
}

if ($path === 'blog/') {
    header('Location: ' . ($base !== '' ? $base : '') . '/blog', true, 301);
    return true;
}

if (preg_match('#^article/([a-z0-9][a-z0-9-]*)/$#i', $path, $m)) {
    header('Location: ' . ($base !== '' ? $base : '') . '/article/' . strtolower($m[1]), true, 301);
    return true;
}

if ($path === 'rss.xml') {
    require $root . '/rss.php';
    return true;
}

if ($path === 'sitemap.xml') {
    require $root . '/sitemap.php';
    return true;
}

if (preg_match('#^article/([a-z0-9][a-z0-9-]*)$#i', $path, $articleMatch)) {
    $_GET['slug'] = strtolower($articleMatch[1]);
    $file = $root . '/blog-article.php';
    if (is_file($file)) {
        try {
            require $file;
            return true;
        } catch (Throwable) {
            // fallback to static template below
        }
    }

    $file = $root . '/blog-article.html';
    if (is_file($file)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($file);
        return true;
    }
}

if ($path === 'api' || str_starts_with($path, 'api/')) {
    require $root . '/api/index.php';
    return true;
}

$routes = require $root . '/config/routes.php';
$redirects = require $root . '/config/redirects.php';

if (isset($routes[$path])) {
    $file = $root . '/' . $routes[$path];
    if (!is_file($file)) {
        http_response_code(404);
        echo 'File not found';
        return true;
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if ($ext === 'php') {
        require $file;
        return true;
    }

  header('Content-Type: text/html; charset=utf-8');
  readfile($file);
  return true;
}

$legacy = str_replace('\\', '/', ltrim($uri, '/'));
if (isset($redirects[$legacy])) {
    $target = $redirects[$legacy];
    $location = ($base !== '' ? $base : '') . ($target === '' ? '/' : '/' . $target);
    header('Location: ' . $location, true, 301);
    return true;
}

$direct = $root . $uri;
if ($uri !== '/' && is_file($direct)) {
    return false;
}

if (is_dir($root . $uri) && is_file($root . $uri . '/index.html')) {
    readfile($root . $uri . '/index.html');
    return true;
}

http_response_code(404);
echo 'Not found';
return true;
