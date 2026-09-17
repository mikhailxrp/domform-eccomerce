<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';

const PASSWORD_RESET_TTL_MINUTES = 60;

/**
 * Аннулирует прежние неиспользованные токены пользователя и создаёт
 * новый — старые ссылки из более ранних писем перестают работать
 * (причина `INDEX(user_id)` в `database.md`). Возвращает сырой токен —
 * в БД остаётся только его sha256-хэш, как в `remember_tokens`.
 */
function createPasswordReset(int $userId): string
{
    $invalidate = getPdo()->prepare(
        'UPDATE password_resets SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL'
    );
    $invalidate->execute(['user_id' => $userId]);

    $token     = bin2hex(random_bytes(32));
    $expiresAt = (new DateTimeImmutable('+' . PASSWORD_RESET_TTL_MINUTES . ' minutes'))->format('Y-m-d H:i:s');

    $insert = getPdo()->prepare(
        'INSERT INTO password_resets (user_id, token_hash, expires_at)
         VALUES (:user_id, :token_hash, :expires_at)'
    );
    $insert->execute([
        'user_id'    => $userId,
        'token_hash' => hash('sha256', $token),
        'expires_at' => $expiresAt,
    ]);

    return $token;
}

/**
 * Возвращает запись только если токен существует, ещё не использован
 * и не истёк — во всех остальных случаях null (ссылка недействительна).
 */
function findValidPasswordReset(string $token): ?array
{
    $stmt = getPdo()->prepare(
        'SELECT id, user_id, expires_at, used_at
         FROM password_resets WHERE token_hash = :token_hash LIMIT 1'
    );
    $stmt->execute(['token_hash' => hash('sha256', $token)]);
    $reset = $stmt->fetch();

    if ($reset === false || $reset['used_at'] !== null) {
        return null;
    }
    if (strtotime((string) $reset['expires_at']) < time()) {
        return null;
    }

    return $reset;
}

function markPasswordResetUsed(int $id): void
{
    $stmt = getPdo()->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id');
    $stmt->execute(['id' => $id]);
}
