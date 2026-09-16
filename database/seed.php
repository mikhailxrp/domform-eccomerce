<?php

declare(strict_types=1);

/**
 * Создаёт первого Администратора из ADMIN_NAME / ADMIN_EMAIL /
 * ADMIN_PASSWORD (.env) — без него Менеджеров некому заводить
 * (раздел 5.1 ТЗ: пользователей и роли назначает только Администратор).
 *
 * Запускать один раз из консоли: php database/seed.php
 * Идемпотентен — повторный запуск с тем же ADMIN_EMAIL ничего не меняет.
 */

require_once dirname(__DIR__) . '/config/config.php';

try {
    $name     = trim(env('ADMIN_NAME'));
    $email    = trim(env('ADMIN_EMAIL'));
    $password = env('ADMIN_PASSWORD');
} catch (RuntimeException $e) {
    fwrite(STDERR, "❌ {$e->getMessage()}. Заполни ADMIN_NAME/ADMIN_EMAIL/ADMIN_PASSWORD в .env.\n");
    exit(1);
}

if ($name === '') {
    fwrite(STDERR, "❌ ADMIN_NAME пустой.\n");
    exit(1);
}

if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "❌ ADMIN_EMAIL не похож на email: {$email}\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "❌ ADMIN_PASSWORD короче 8 символов.\n");
    exit(1);
}

$pdo = getPdo();

$existing = $pdo->prepare('SELECT id FROM users WHERE email = :email');
$existing->execute(['email' => $email]);

if ($existing->fetch() !== false) {
    echo "ℹ️ Пользователь с email {$email} уже существует — пропущено.\n";
    exit(0);
}

$insert = $pdo->prepare(
    'INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, :role)'
);
$insert->execute([
    'name'          => $name,
    'email'         => $email,
    'password_hash' => password_hash($password, PASSWORD_BCRYPT),
    'role'          => 'admin',
]);

echo "✅ Администратор {$email} создан.\n";
