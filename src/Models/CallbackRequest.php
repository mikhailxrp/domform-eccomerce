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

/**
 * Список `/admin/callbacks` (Таск 5, `FR-CNT-001`) — `$filters['status']
 * === null` означает «все статусы», иначе точное совпадение; по образцу
 * `getAdminReviews()`.
 */
function getAdminCallbacks(array $filters, int $page, int $perPage): array
{
    $offset = ($page - 1) * $perPage;

    $where  = '';
    $params = [];
    if ($filters['status'] !== null) {
        $where            = 'WHERE status = :status';
        $params['status'] = $filters['status'];
    }

    $stmt = getPdo()->prepare("
        SELECT id, name, phone, comment, status, created_at, processed_at
        FROM callback_requests
        {$where}
        ORDER BY created_at DESC, id DESC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function countAdminCallbacks(array $filters): int
{
    $where  = '';
    $params = [];
    if ($filters['status'] !== null) {
        $where            = 'WHERE status = :status';
        $params['status'] = $filters['status'];
    }

    $stmt = getPdo()->prepare("SELECT COUNT(*) FROM callback_requests {$where}");
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

/**
 * Бейдж «Заявки» в сайдбаре (`admin-header.php`) — считается на каждый
 * запрос Панели напрямую, без кэша, тем же приёмом, что
 * `countPendingReviews()` (масштаб проекта — единицы/десятки заявок).
 */
function countNewCallbacks(): int
{
    return (int) getPdo()->query("SELECT COUNT(*) FROM callback_requests WHERE status = 'new'")->fetchColumn();
}

/**
 * `rowCount() === 0` сам по себе не отличает «нет такого id» от «уже
 * был этот статус» (MySQL считает изменённые, а не затронутые строки,
 * `MYSQL_ATTR_FOUND_ROWS` не включён — `Core/Database.php`). Отдельная
 * проверка существования — тот же приём, что `setReviewStatus()`
 * (`Models/Review.php`): `false` только когда строки действительно нет,
 * чтобы повторное «Обработано» оставалось идемпотентным.
 */
function markCallbackProcessed(int $id): bool
{
    // `processed_at` фиксируется только при первом переходе в
    // `processed` (`IF(status = 'processed', processed_at, NOW())`) —
    // повторный вызов не обновляет метку времени и, как следствие, даёт
    // `rowCount() === 0`, что и включает проверку существования ниже.
    // Порядок колонок в SET важен: MySQL применяет присваивания слева
    // направо, поэтому `processed_at` должен идти раньше `status` —
    // иначе выражение `IF(status = 'processed', ...)` увидит уже новое
    // значение `status` из этого же `UPDATE`, а не старое.
    $stmt = getPdo()->prepare("
        UPDATE callback_requests
        SET processed_at = IF(status = 'processed', processed_at, NOW()), status = 'processed'
        WHERE id = :id
    ");
    $stmt->execute(['id' => $id]);

    if ($stmt->rowCount() > 0) {
        return true;
    }

    $exists = getPdo()->prepare('SELECT id FROM callback_requests WHERE id = :id');
    $exists->execute(['id' => $id]);

    return $exists->fetch() !== false;
}
