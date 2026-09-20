<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';

/**
 * Возврат (`FR-RET-001`, `database.md`, `ADR-012`) — полностью
 * офлайн-процесс по звонку: таблица `returns` только фиксирует факт
 * обращения, без собственной статусной модели. На Заказ — 0..N строк:
 * несколько звонков по одному Заказу (например, гарантийный случай
 * после уже зафиксированного возврата) — не ошибка, отдельные записи.
 */

/**
 * Создаёт запись только для Заказа в статусе «Доставлен/Собран»
 * (раздел 6.1 ТЗ: «не ранее Доставлен/Собран») — атомарно, одним
 * запросом `INSERT ... SELECT ... WHERE status = 'delivered'`, без
 * гонки «прочитать статус отдельным SELECT, потом вставить»: если
 * Заказ не найден или не доставлен, `SELECT` не отдаёт ни одной строки
 * и `INSERT` не выполняется. `false` — Заказ не найден либо не в
 * `delivered`, запись не создана.
 */
function createReturn(int $orderId, ?string $note): bool
{
    $stmt = getPdo()->prepare("
        INSERT INTO returns (order_id, note)
        SELECT id, :note FROM orders WHERE id = :order_id AND status = 'delivered'
    ");
    $stmt->execute([
        'order_id' => $orderId,
        'note'     => $note !== '' ? $note : null,
    ]);

    return $stmt->rowCount() > 0;
}

/**
 * Прошлые обращения по конкретному Заказу — для блока «Возврат и
 * гарантия» на его карточке (Таск 6 Фазы 5).
 */
function getOrderReturns(int $orderId): array
{
    $stmt = getPdo()->prepare('
        SELECT id, order_id, note, created_at
        FROM returns
        WHERE order_id = :order_id
        ORDER BY created_at DESC, id DESC
    ');
    $stmt->execute(['order_id' => $orderId]);

    return $stmt->fetchAll();
}

/**
 * Список обращений для `/admin/returns` — клиент и дата доставки тем
 * же приёмом, что `findOrderForAdmin()` (гость/Покупатель одним полем
 * `customer_name`), без отдельного `$filters` пока фильтров не
 * появилось (сигнатура готова под них — тот же паттерн, что
 * `getAdminOrders()`/`getAdminProducts()`).
 */
function getAdminReturns(array $filters, int $page, int $perPage): array
{
    $offset = ($page - 1) * $perPage;

    $stmt = getPdo()->prepare('
        SELECT
            r.id, r.order_id, r.note, r.created_at,
            o.delivered_at,
            o.user_id, o.guest_name, u.name AS user_name
        FROM returns r
        INNER JOIN orders o ON o.id = r.order_id
        LEFT JOIN users u ON u.id = o.user_id
        ORDER BY r.created_at DESC, r.id DESC
        LIMIT :limit OFFSET :offset
    ');
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return array_map(static function (array $row): array {
        $row['customer_name'] = $row['user_id'] !== null ? $row['user_name'] : $row['guest_name'];
        return $row;
    }, $stmt->fetchAll());
}

function countAdminReturns(array $filters): int
{
    return (int) getPdo()->query('SELECT COUNT(*) FROM returns')->fetchColumn();
}
