<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';

function findUserByEmail(string $email): ?array
{
    $stmt = getPdo()->prepare(
        'SELECT id, name, email, password_hash, phone, role, is_blocked, created_at
         FROM users WHERE email = :email LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    return $user !== false ? $user : null;
}

function findUserById(int $id): ?array
{
    $stmt = getPdo()->prepare(
        'SELECT id, name, email, password_hash, phone, role, is_blocked, created_at
         FROM users WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();

    return $user !== false ? $user : null;
}

/**
 * Создаёт Покупателя (role='customer').
 * Возвращает null при гонке на UNIQUE(email) — вторая параллельная
 * регистрация с тем же email проигрывает конкуренцию, а не падает
 * системной ошибкой (SQLSTATE 23000 / MySQL error 1062).
 */
function createUser(string $name, string $email, string $passwordHash, string $phone): ?int
{
    try {
        $stmt = getPdo()->prepare(
            "INSERT INTO users (name, email, password_hash, phone, role)
             VALUES (:name, :email, :password_hash, :phone, 'customer')"
        );
        $stmt->execute([
            'name'          => $name,
            'email'         => $email,
            'password_hash' => $passwordHash,
            'phone'         => $phone,
        ]);

        return (int) getPdo()->lastInsertId();
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1062) {
            return null;
        }
        throw $e;
    }
}
