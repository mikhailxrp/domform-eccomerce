<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';

/**
 * Заявки на обратный звонок (`callback_requests`, `ADR-047`, Таск 4
 * Фазы 8) — видны Менеджеру/Администратору в Панели там же, где
 * Заказы (Таск 5).
 */

/**
 * `$data` — результат `normalizeCallbackInput()`. `comment` — пустая
 * строка после `trim()` сохраняется как `NULL` (поле необязательное).
 */
function createCallbackRequest(array $data): int
{
    $stmt = getPdo()->prepare("
        INSERT INTO callback_requests (name, phone, comment, status)
        VALUES (:name, :phone, :comment, 'new')
    ");
    $stmt->execute([
        'name'    => $data['name'],
        'phone'   => $data['phone'],
        'comment' => $data['comment'] !== '' ? $data['comment'] : null,
    ]);

    return (int) getPdo()->lastInsertId();
}
