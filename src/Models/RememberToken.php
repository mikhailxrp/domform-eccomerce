<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';

const REMEMBER_TOKEN_TTL_DAYS = 30;

/**
 * Создаёт запись «запомнить меня» и возвращает пару для cookie.
 * В БД остаётся только sha256-хэш validator — сам validator (секрет)
 * уходит исключительно в cookie, как токен восстановления пароля.
 */
function createRememberToken(int $userId): array
{
    $selector  = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $expiresAt = (new DateTimeImmutable('+' . REMEMBER_TOKEN_TTL_DAYS . ' days'))->format('Y-m-d H:i:s');

    $stmt = getPdo()->prepare(
        'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at)
         VALUES (:user_id, :selector, :token_hash, :expires_at)'
    );
    $stmt->execute([
        'user_id'    => $userId,
        'selector'   => $selector,
        'token_hash' => hash('sha256', $validator),
        'expires_at' => $expiresAt,
    ]);

    return ['selector' => $selector, 'validator' => $validator];
}

function findRememberToken(string $selector): ?array
{
    $stmt = getPdo()->prepare(
        'SELECT id, user_id, selector, token_hash, expires_at
         FROM remember_tokens WHERE selector = :selector LIMIT 1'
    );
    $stmt->execute(['selector' => $selector]);
    $token = $stmt->fetch();

    return $token !== false ? $token : null;
}

/**
 * Удаляет все remember-токены пользователя — используется и при
 * «Выходе» (снимает вход со всех устройств), и при смене пароля (Таск 5).
 */
function deleteRememberTokens(int $userId): void
{
    $stmt = getPdo()->prepare('DELETE FROM remember_tokens WHERE user_id = :user_id');
    $stmt->execute(['user_id' => $userId]);
}
