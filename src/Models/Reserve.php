<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';
require_once ROOT_PATH . '/src/Core/Reserve.php';

/**
 * Закрепляет Резерв за каждой Позицией Заказа, чей Вариант отмечен
 * Выставочным образцом (`BR-003`, `FR-STOCK-002`). Вызывается внутри
 * уже открытой транзакции `markOrderPrepaid()` — момент фактического
 * получения предоплаты, не оформления Заказа (`FR-STOCK-003`
 * правило 1). Никакого `SELECT ... status='active'` перед `INSERT`:
 * конкуренцию решает `UNIQUE(active_variant_id)` в БД, второй `INSERT`
 * на тот же Вариант получает SQLSTATE 23000 — это проигрыш конкуренции
 * (`false`), не сбой (`dod-global.md`, `ADR-007`). `agreed_until` не
 * заполняется — срок устный (`FR-STOCK-002` правило 2). Возвращает
 * `true`, если резервы созданы либо образцов в Заказе нет.
 */
function createReservesForOrder(PDO $pdo, int $orderId): bool
{
    $itemsStmt = $pdo->prepare('
        SELECT oi.id, oi.product_variant_id
        FROM order_items oi
        INNER JOIN product_variants pv ON pv.id = oi.product_variant_id
        WHERE oi.order_id = :order_id AND pv.is_showroom_sample = 1
    ');
    $itemsStmt->execute(['order_id' => $orderId]);
    $sampleItems = $itemsStmt->fetchAll();

    if ($sampleItems === []) {
        return true;
    }

    $insertStmt = $pdo->prepare('
        INSERT INTO reserves (order_item_id, product_variant_id, status)
        VALUES (:order_item_id, :product_variant_id, :status)
    ');

    foreach ($sampleItems as $item) {
        try {
            $insertStmt->execute([
                'order_item_id'      => $item['id'],
                'product_variant_id' => $item['product_variant_id'],
                'status'             => RESERVE_STATUS_ACTIVE,
            ]);
        } catch (PDOException $e) {
            if (!isUniqueViolation($e)) {
                throw $e;
            }

            logWarning('Резерв не закреплён: Выставочный образец уже занят другим Заказом', [
                'order_id'   => $orderId,
                'variant_id' => (int) $item['product_variant_id'],
            ]);

            return false;
        }
    }

    return true;
}

/**
 * Активный Резерв **другого** Заказа на образец из состава этого Заказа
 * — для сообщения Менеджеру «образец закреплён за Заказом №N» после
 * проигрыша конкуренции (UC-02, отклонение 2а). Возвращает строку
 * `reserves` + `order_id` держателя + `product_name` образца; `null` —
 * ни один образец Заказа не занят.
 */
function findCompetingReserveForOrder(int $orderId): ?array
{
    $stmt = getPdo()->prepare('
        SELECT r.id, r.product_variant_id, r.agreed_until, r.created_at,
               holder.order_id, mine.product_name
        FROM order_items mine
        INNER JOIN reserves r
            ON r.product_variant_id = mine.product_variant_id AND r.status = :status
        INNER JOIN order_items holder ON holder.id = r.order_item_id
        WHERE mine.order_id = :order_id AND holder.order_id <> :other_than
        LIMIT 1
    ');
    $stmt->execute([
        'order_id'   => $orderId,
        'other_than' => $orderId,
        'status'     => RESERVE_STATUS_ACTIVE,
    ]);
    $reserve = $stmt->fetch();

    return $reserve === false ? null : $reserve;
}

/**
 * Списание (`FR-STOCK-004`, глоссарий 6.1): активные Резервы Заказа →
 * `fulfilled`, у их Вариантов снимается отметка образца — физический
 * экземпляр продан, повторно образцом он сам не становится. Идёт по
 * резервам, не по всем позициям-образцам: резерв — источник истины, чей
 * это образец (позиция без резерва флаг не трогает). Вызывается внутри
 * транзакции `transitionOrderStatus()` на переходе в «Доставлен/Собран».
 */
function fulfillOrderReserves(PDO $pdo, int $orderId): void
{
    $variantStmt = $pdo->prepare('
        UPDATE product_variants pv
        INNER JOIN reserves r ON r.product_variant_id = pv.id
        INNER JOIN order_items oi ON oi.id = r.order_item_id
        SET pv.is_showroom_sample = 0
        WHERE oi.order_id = :order_id AND r.status = :status
    ');
    $variantStmt->execute(['order_id' => $orderId, 'status' => RESERVE_STATUS_ACTIVE]);

    updateOrderReservesStatus($pdo, $orderId, RESERVE_STATUS_FULFILLED);
}

/**
 * Снятие Резерва при отмене Заказа (`BR-007`): активные Резервы →
 * `released`, образец снова свободен для следующего Покупателя.
 * `is_showroom_sample` не трогается — закрепление Резерва его не
 * меняло, образец физически остаётся в цеху. Не сворачивать с
 * `fulfillOrderReserves()`: разное конечное состояние (`stock.md`,
 * «Списание — не то же самое, что снятие по отмене»).
 */
function releaseOrderReserves(PDO $pdo, int $orderId): void
{
    updateOrderReservesStatus($pdo, $orderId, RESERVE_STATUS_RELEASED);
}

function updateOrderReservesStatus(PDO $pdo, int $orderId, string $newStatus): void
{
    $stmt = $pdo->prepare('
        UPDATE reserves r
        INNER JOIN order_items oi ON oi.id = r.order_item_id
        SET r.status = :new_status, r.released_at = NOW()
        WHERE oi.order_id = :order_id AND r.status = :active
    ');
    $stmt->execute([
        'new_status' => $newStatus,
        'order_id'   => $orderId,
        'active'     => RESERVE_STATUS_ACTIVE,
    ]);
}

/**
 * Отмена стандартной ветки, когда Вариант уже изготовлен (`BR-007`,
 * `ord.md`): готовый экземпляр не выбрасывается — становится Выставочным
 * образцом. Отмечаются все активные Варианты позиций Заказа (Заказ в
 * производстве целиком); Вариант, уже отмеченный образцом, остаётся
 * отмеченным — 0..1 экземпляр на Вариант (`FR-STOCK-001` правило 3).
 */
function markOrderVariantsAsShowroomSample(PDO $pdo, int $orderId): void
{
    $stmt = $pdo->prepare('
        UPDATE product_variants pv
        INNER JOIN order_items oi ON oi.product_variant_id = pv.id
        SET pv.is_showroom_sample = 1
        WHERE oi.order_id = :order_id AND pv.is_active = 1
    ');
    $stmt->execute(['order_id' => $orderId]);
}
