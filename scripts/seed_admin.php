<?php

declare(strict_types=1);

use App\Config;
use App\Repositories\UserRepository;

$root = dirname(__DIR__);

require $root . '/bootstrap.php';
Config::load($root);

$name = $argv[1] ?? 'Администратор';
$email = $argv[2] ?? 'admin@alip-voda.ru';
$password = $argv[3] ?? 'admin123';

$users = new UserRepository();

if ($users->findByEmail($email)) {
    echo "User {$email} already exists.\n";
    exit(0);
}

$user = $users->create(
    $name,
    $email,
    password_hash($password, PASSWORD_DEFAULT),
    'admin'
);

echo "Admin created:\n";
echo "  Email: {$user->email}\n";
echo "  Password: {$password}\n";
