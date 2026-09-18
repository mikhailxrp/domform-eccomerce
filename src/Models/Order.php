<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';
require_once ROOT_PATH . '/src/Core/Cart.php';
require_once ROOT_PATH . '/src/Core/OrderStatus.php';

/**
 * `$order['user_id']` либо `$order['guest_name']`/`guest_phone`/
 * `guest_email` — ровно один источник контактов (раздел 6.1 ТЗ,
 * `database.md`) — нарушение этого контракта со стороны вызывающего
 * кода является программной ошибкой, не пользовательским вводом.
 */
function validateOrderContact(array $order): void
{
    $hasUserId = $order['user_id'] !== null;
    $hasGuest  = $order['guest_name'] !== null || $order['guest_phone'] !== null || $order['guest_email'] !== null;

    if ($hasUserId === $hasGuest) {
        throw new RuntimeException(
            'Заказ должен содержать ровно один источник контактов: user_id либо guest_*.'
        );
    }
}

/**
 * `$items` — `[['product_variant_id' => int, 'color' => ?string,
 * 'quantity' => int], ...]` из корзины владельца. Цена и снэпшот
 * характеристик Варианта перечитываются из `product_variants`
 * (`FOR UPDATE`, внутри транзакции) — клиент их не присылает
 * (`general.md`: «server is the source of truth»). Образец, на который
 * уже есть активный Резерв конкурента — откат, `null` (проигрыш
 * конкуренции, не системный сбой, `dod-global.md`). Возвращает `id`
 * созданного Заказа либо `null`.
 */
function createOrder(array $order, array $items): ?int
{
    validateOrderContact($order);

    $pdo = getPdo();
    $pdo->beginTransaction();

    try {
        $variantStmt = $pdo->prepare('
            SELECT pv.id, pv.price, pv.sku, pv.material, pv.mechanism_type,
                   pv.is_showroom_sample, pv.is_active, p.name AS product_name
            FROM product_variants pv
            INNER JOIN products p ON p.id = pv.product_id
            WHERE pv.id = :id
            FOR UPDATE
        ');
        $reserveStmt = $pdo->prepare("
            SELECT 1 FROM reserves
            WHERE product_variant_id = :variant_id AND status = 'active'
            LIMIT 1
        ");

        $total       = '0.00';
        $orderItems  = [];

        foreach ($items as $item) {
            $variantStmt->execute(['id' => $item['product_variant_id']]);
            $variant = $variantStmt->fetch();

            if ($variant === false || (int) $variant['is_active'] === 0) {
                $pdo->rollBack();
                return null;
            }

            if ((int) $variant['is_showroom_sample'] === 1) {
                $reserveStmt->execute(['variant_id' => $variant['id']]);
                if ($reserveStmt->fetch() !== false) {
                    $pdo->rollBack();
                    return null;
                }
            }

            $lineTotal = bcmul((string) $variant['price'], (string) $item['quantity'], 2);
            $total     = bcadd($total, $lineTotal, 2);

            $orderItems[] = [
                'product_variant_id' => $variant['id'],
                'product_name'       => $variant['product_name'],
                'variant_sku'        => $variant['sku'],
                'variant_material'   => $variant['material'],
                'variant_mechanism'  => $variant['mechanism_type'],
                'variant_color'      => $item['color'],
                'price'              => $variant['price'],
                'quantity'           => $item['quantity'],
            ];
        }

        $orderStmt = $pdo->prepare('
            INSERT INTO orders (
                user_id, guest_name, guest_phone, guest_email,
                fulfillment_method, delivery_address, comment,
                payment_method, total
            ) VALUES (
                :user_id, :guest_name, :guest_phone, :guest_email,
                :fulfillment_method, :delivery_address, :comment,
                :payment_method, :total
            )
        ');
        $orderStmt->execute([
            'user_id'            => $order['user_id'],
            'guest_name'         => $order['guest_name'],
            'guest_phone'        => $order['guest_phone'],
            'guest_email'        => $order['guest_email'],
            'fulfillment_method' => $order['fulfillment_method'],
            'delivery_address'   => $order['delivery_address'],
            'comment'            => $order['comment'],
            'payment_method'     => $order['payment_method'],
            'total'              => $total,
        ]);
        $orderId = (int) $pdo->lastInsertId();

        $itemStmt = $pdo->prepare('
            INSERT INTO order_items (
                order_id, product_variant_id, product_name, variant_sku,
                variant_material, variant_mechanism, variant_color, price, quantity
            ) VALUES (
                :order_id, :product_variant_id, :product_name, :variant_sku,
                :variant_material, :variant_mechanism, :variant_color, :price, :quantity
            )
        ');
        foreach ($orderItems as $orderItem) {
            $itemStmt->execute(['order_id' => $orderId] + $orderItem);
        }

        $pdo->commit();

        return $orderId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function findOrderById(int $id): ?array
{
    $stmt = getPdo()->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $order = $stmt->fetch();

    return $order !== false ? $order : null;
}

function getOrderItems(int $orderId): array
{
    $stmt = getPdo()->prepare('SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id');
    $stmt->execute(['order_id' => $orderId]);

    return $stmt->fetchAll();
}

/**
 * Хотя бы одна Позиция Заказа — Выставочный образец (`order_items` →
 * `product_variants`, вариант мог быть удалён — `ON DELETE SET NULL`,
 * тогда `product_variant_id IS NULL` и в join не попадёт, что и нужно:
 * про удалённый физически Вариант нечего спросить у `product_variants`).
 */
function orderHasShowroomSample(int $orderId): bool
{
    $stmt = getPdo()->prepare('
        SELECT EXISTS (
            SELECT 1 FROM order_items oi
            INNER JOIN product_variants pv ON pv.id = oi.product_variant_id
            WHERE oi.order_id = :order_id AND pv.is_showroom_sample = 1
        )
    ');
    $stmt->execute(['order_id' => $orderId]);

    return (bool) $stmt->fetchColumn();
}

/**
 * Единственное место в проекте с `UPDATE orders SET status` (`php.md`).
 * Запрещённый переход (в т.ч. неизвестный текущий/целевой статус,
 * несуществующий Заказ) — `false` без изменений, не исключение: вызов
 * из Панели управления (Фаза 4) должен уметь просто не предложить
 * недопустимый переход, а не падать.
 */
function transitionOrderStatus(int $orderId, string $to): bool
{
    $order = findOrderById($orderId);
    if ($order === null) {
        return false;
    }

    $canTransition = canTransitionOrder(
        $order['status'],
        $to,
        orderHasShowroomSample($orderId),
        $order['fulfillment_method']
    );

    if (!$canTransition) {
        return false;
    }

    $sql = $to === ORDER_STATUS_DELIVERED
        ? 'UPDATE orders SET status = :status, delivered_at = NOW() WHERE id = :id'
        : 'UPDATE orders SET status = :status WHERE id = :id';

    $stmt = getPdo()->prepare($sql);
    $stmt->execute(['status' => $to, 'id' => $orderId]);

    return true;
}

/**
 * Отмена по звонку Менеджеру (`FR-ORD-002` правило 4, `BR-007`) —
 * стандартная ветка: снятие Резерва при отмене — Фаза 5 (`phase-2.md`,
 * «Решения фазы»), здесь не реализовано.
 */
function cancelOrder(int $orderId): bool
{
    return transitionOrderStatus($orderId, ORDER_STATUS_CANCELLED);
}

/**
 * Фиксирует получение предоплаты (`FR-PAY-002`) — единственное место,
 * пишущее `orders.payment_status` в `prepaid` (`pay.md`). Идемпотентна:
 * повторный вызов на Заказе, уже вышедшем из `unpaid`, не меняет
 * данные и возвращает `false`, а не исключение. `transitionOrderStatus()`
 * тоже не бросает на запрещённом переходе (например, Заказ уже
 * отменён) — оплата в этом случае всё равно фиксируется.
 */
function markOrderPrepaid(int $orderId, string $amount): bool
{
    $pdo = getPdo();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('
            UPDATE orders
            SET payment_status = :prepaid, prepaid_amount = :amount
            WHERE id = :id AND payment_status = :unpaid
        ');
        $stmt->execute([
            'prepaid' => PAYMENT_STATUS_PREPAID,
            'amount'  => $amount,
            'id'      => $orderId,
            'unpaid'  => PAYMENT_STATUS_UNPAID,
        ]);

        if ($stmt->rowCount() === 0) {
            $pdo->commit();
            return false;
        }

        transitionOrderStatus($orderId, ORDER_STATUS_CONFIRMED);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Фиксирует получение остатка при получении Заказа (`FR-PAY-002`) —
 * единственное место, пишущее `orders.payment_status` в `paid_full`.
 * Не меняет `orders.status` — эта ось не управляется оплатой
 * (`pay.md`). Идемпотентна, как `markOrderPrepaid()`. `$amount` (сумма
 * остатка) не хранится отдельной колонкой — `database.md` фиксирует
 * только `prepaid_amount` (предоплата), а не остаток; полная сумма уже
 * известна из `orders.total`. Значение уходит в лог как аудиторский
 * след, а не как модификация данных Заказа.
 */
function markOrderPaidFull(int $orderId, string $amount): bool
{
    $pdo = getPdo();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('
            UPDATE orders
            SET payment_status = :paid_full
            WHERE id = :id AND payment_status = :prepaid
        ');
        $stmt->execute([
            'paid_full' => PAYMENT_STATUS_PAID_FULL,
            'id'        => $orderId,
            'prepaid'   => PAYMENT_STATUS_PREPAID,
        ]);

        $updated = $stmt->rowCount() > 0;
        $pdo->commit();

        if ($updated) {
            logInfo('Остаток по Заказу зафиксирован', ['order_id' => $orderId, 'amount' => $amount]);
        }

        return $updated;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Привязывает гостевые Заказы к аккаунту при регистрации тем же email
 * (`FR-ORD-005`, `ord.md`) — только к незанятым (`user_id IS NULL`),
 * `guest_*` очищаются, чтобы «ровно один источник контактов» оставалось
 * верным и после привязки. Возвращает число привязанных Заказов.
 */
function linkGuestOrdersToUser(string $email, int $userId): int
{
    $stmt = getPdo()->prepare('
        UPDATE orders
        SET user_id = :user_id, guest_name = NULL, guest_phone = NULL, guest_email = NULL
        WHERE guest_email = :email AND user_id IS NULL
    ');
    $stmt->execute(['user_id' => $userId, 'email' => $email]);

    return $stmt->rowCount();
}
