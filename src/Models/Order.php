<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';
require_once ROOT_PATH . '/src/Core/Cart.php';
require_once ROOT_PATH . '/src/Core/OrderStatus.php';
require_once ROOT_PATH . '/src/Core/Validation.php';
require_once ROOT_PATH . '/src/Core/Reserve.php';
require_once ROOT_PATH . '/src/Models/Reserve.php';

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

/**
 * Все 7 статусов всегда присутствуют в результате (0, если Заказов
 * нет) — дашборд Панели управления показывает счётчик по каждому,
 * а не только по тем, что реально встретились в `orders`.
 */
function countOrdersByStatus(): array
{
    $counts = array_fill_keys(array_keys(ORDER_STATUS_LABELS), 0);

    $stmt = getPdo()->query('SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status');
    foreach ($stmt->fetchAll() as $row) {
        if (array_key_exists($row['status'], $counts)) {
            $counts[$row['status']] = (int) $row['cnt'];
        }
    }

    return $counts;
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
 *
 * Транзакция — «владеет или участвует»: открывается только если её ещё
 * нет; из `cancelOrder()`/`markOrderPrepaid()` функция работает внутри
 * их транзакции, из контроллера `transition()` — в собственной.
 * Переход в «Доставлен/Собран» списывает образец
 * (`fulfillOrderReserves()`, `FR-STOCK-004`) той же транзакцией — статус
 * и списание применяются только вместе.
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

    $pdo             = getPdo();
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['status' => $to, 'id' => $orderId]);

        if ($to === ORDER_STATUS_DELIVERED) {
            fulfillOrderReserves($pdo, $orderId);
        }

        if ($ownsTransaction) {
            $pdo->commit();
        }

        return true;
    } catch (Throwable $e) {
        if ($ownsTransaction) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Отмена по звонку Менеджеру (`FR-ORD-002`, `BR-007`). `$note`/
 * `$prepaymentRefunded`/`$markAsSample` — уже провалидированы
 * `validateCancelInput()` до вызова (обязательность зависит от
 * ветки/`payment_status`, Model этого не проверяет повторно). Переход
 * статуса, запись `cancel_note`/`prepayment_refunded` и STOCK-эффекты —
 * одна транзакция: либо применяется всё, либо ничего; сам `UPDATE
 * orders SET status` — по-прежнему только внутри
 * `transitionOrderStatus()` (`php.md`). STOCK-эффекты (`stock.md`):
 * активный Резерв снимается (`releaseOrderReserves()` — образец снова
 * свободен), а при `$markAsSample` (стандартная ветка, Вариант уже
 * изготовлен) Варианты Заказа отмечаются Выставочными образцами.
 */
function cancelOrder(
    int $orderId,
    string $note = '',
    bool $prepaymentRefunded = false,
    bool $markAsSample = false
): bool {
    $pdo = getPdo();
    $pdo->beginTransaction();

    try {
        if (!transitionOrderStatus($orderId, ORDER_STATUS_CANCELLED)) {
            $pdo->rollBack();
            return false;
        }

        $stmt = $pdo->prepare('
            UPDATE orders
            SET cancel_note = :note, prepayment_refunded = :refunded
            WHERE id = :id
        ');
        $stmt->execute([
            'note'     => $note !== '' ? $note : null,
            'refunded' => $prepaymentRefunded ? 1 : 0,
            'id'       => $orderId,
        ]);

        releaseOrderReserves($pdo, $orderId);

        if ($markAsSample) {
            markOrderVariantsAsShowroomSample($pdo, $orderId);
        }

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Стоимость доставки (`BR-006`) — вносится вручную, отдельно от
 * `orders.total`, не пересчитывает его. `$cost === null` — сброс в
 * `NULL` (доставка ещё не согласована).
 */
function setOrderShippingCost(int $orderId, ?string $cost): void
{
    $stmt = getPdo()->prepare('UPDATE orders SET shipping_cost = :cost WHERE id = :id');
    $stmt->execute(['cost' => $cost, 'id' => $orderId]);
}

/**
 * Фиксирует получение предоплаты (`FR-PAY-002`) — единственное место,
 * пишущее `orders.payment_status` в `prepaid` (`pay.md`). Идемпотентна:
 * повторный вызов на Заказе, уже вышедшем из `unpaid`, не меняет
 * данные и возвращает `PREPAID_RESULT_ALREADY`, а не исключение.
 * `transitionOrderStatus()` тоже не бросает на запрещённом переходе
 * (например, Заказ уже отменён) — оплата в этом случае всё равно
 * фиксируется.
 *
 * Здесь же — и только здесь — закрепляется Резерв Выставочного образца
 * (`BR-003`, `FR-STOCK-002…003`): момент фактического получения
 * предоплаты, а не оформления Заказа. Проигрыш конкуренции (образец
 * уже закреплён за другим Заказом) откатывает фиксацию предоплаты
 * целиком — `PREPAID_RESULT_SAMPLE_TAKEN` (`ADR-040`): оплаченный Заказ
 * на чужой образец в системе оставаться не должен. Снятие/списание
 * Резерва — Таск 2 Фазы 5, не здесь.
 */
function markOrderPrepaid(int $orderId, string $amount): string
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
            return PREPAID_RESULT_ALREADY;
        }

        if (!createReservesForOrder($pdo, $orderId)) {
            $pdo->rollBack();
            return PREPAID_RESULT_SAMPLE_TAKEN;
        }

        transitionOrderStatus($orderId, ORDER_STATUS_CONFIRMED);

        $pdo->commit();
        return PREPAID_RESULT_OK;
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

/**
 * `$filters['status']` — один из `ORDER_STATUS_*` либо `null` (без
 * фильтра). `$filters['search']` — сырая строка из формы: число →
 * совпадение по `orders.id`, телефон в любом написании → сравнение по
 * `normalizePhone()` с уже нормализованными `users.phone`/
 * `orders.guest_phone` (обе колонки нормализуются при записи —
 * `AuthController`/`Checkout.php`). Строка, из которой не удалось
 * извлечь ни номер, ни телефон, даёт заведомо пустой результат, а не
 * полный список (иначе поиск «не нашёл» выглядел бы как «фильтр не
 * применился»).
 */
function buildAdminOrderFilterConditions(array $filters): array
{
    $conditions = [];
    $params     = [];

    $status = $filters['status'] ?? null;
    if ($status !== null) {
        $conditions[]      = 'o.status = :status';
        $params['status'] = $status;
    }

    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
        $searchConditions = [];

        if (ctype_digit($search)) {
            $searchConditions[]     = 'o.id = :search_id';
            $params['search_id']    = (int) $search;
        }

        $normalizedPhone = normalizePhone($search);
        if ($normalizedPhone !== '') {
            $searchConditions[]              = 'o.guest_phone = :search_phone_guest';
            $searchConditions[]              = 'u.phone = :search_phone_user';
            $params['search_phone_guest']    = $normalizedPhone;
            $params['search_phone_user']     = $normalizedPhone;
        }

        $conditions[] = $searchConditions !== [] ? '(' . implode(' OR ', $searchConditions) . ')' : '1 = 0';
    }

    return [$conditions, $params];
}

function bindAdminOrderFilterParams(PDOStatement $stmt, array $params): void
{
    foreach ($params as $key => $value) {
        $stmt->bindValue(":{$key}", $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
}

/**
 * Все Заказы независимо от источника — сайт или ручное создание
 * Менеджером (`FR-ORD-004`, `FR-MGR-001` правило 1); `LEFT JOIN users`,
 * потому что гостевой Заказ не ссылается ни на одну строку `users`.
 */
function getAdminOrders(array $filters, int $page, int $perPage): array
{
    [$conditions, $params] = buildAdminOrderFilterConditions($filters);
    $whereSql = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $offset   = ($page - 1) * $perPage;

    $stmt = getPdo()->prepare("
        SELECT o.*, u.name AS user_name
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        {$whereSql}
        ORDER BY o.created_at DESC, o.id DESC
        LIMIT :limit OFFSET :offset
    ");
    bindAdminOrderFilterParams($stmt, $params);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function countAdminOrders(array $filters): int
{
    [$conditions, $params] = buildAdminOrderFilterConditions($filters);
    $whereSql = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $stmt = getPdo()->prepare("
        SELECT COUNT(*)
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        {$whereSql}
    ");
    bindAdminOrderFilterParams($stmt, $params);
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

/**
 * Пересчитывает `orders.total` из актуальных `order_items` (снэпшот
 * `price` × `quantity`, не текущая цена Варианта) — вызывается только
 * изнутри чужой транзакции (`addOrderItem()`/`updateOrderItemQuantity()`/
 * `removeOrderItem()`), сама транзакцию не открывает и не коммитит.
 */
function recalculateOrderTotal(PDO $pdo, int $orderId): void
{
    $stmt = $pdo->prepare('SELECT price, quantity FROM order_items WHERE order_id = :order_id');
    $stmt->execute(['order_id' => $orderId]);

    $total = '0.00';
    foreach ($stmt->fetchAll() as $row) {
        $total = bcadd($total, bcmul((string) $row['price'], (string) $row['quantity'], 2), 2);
    }

    $update = $pdo->prepare('UPDATE orders SET total = :total WHERE id = :id');
    $update->execute(['total' => $total, 'id' => $orderId]);
}

/**
 * Добавляет позицию к уже существующему Заказу (`FR-ORD-003`, Таск 4
 * Фазы 4) — тот же снэпшот, что `createOrder()`: Вариант перечитывается
 * из `product_variants` внутри транзакции (`FOR UPDATE`), клиент не
 * присылает цену/характеристики. Неактивный/несуществующий Вариант →
 * `false`, ничего не пишется. Пересчёт `total` — в той же транзакции.
 */
function addOrderItem(int $orderId, int $variantId, ?string $color, int $qty): bool
{
    $pdo = getPdo();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('
            SELECT pv.id, pv.price, pv.sku, pv.material, pv.mechanism_type,
                   pv.is_showroom_sample, pv.is_active, p.name AS product_name
            FROM product_variants pv
            INNER JOIN products p ON p.id = pv.product_id
            WHERE pv.id = :id
            FOR UPDATE
        ');
        $stmt->execute(['id' => $variantId]);
        $variant = $stmt->fetch();

        if ($variant === false || (int) $variant['is_active'] === 0) {
            $pdo->rollBack();
            return false;
        }

        $quantity = clampCartQuantity($qty, (bool) $variant['is_showroom_sample']);

        $insert = $pdo->prepare('
            INSERT INTO order_items (
                order_id, product_variant_id, product_name, variant_sku,
                variant_material, variant_mechanism, variant_color, price, quantity
            ) VALUES (
                :order_id, :product_variant_id, :product_name, :variant_sku,
                :variant_material, :variant_mechanism, :variant_color, :price, :quantity
            )
        ');
        $insert->execute([
            'order_id'           => $orderId,
            'product_variant_id' => $variant['id'],
            'product_name'       => $variant['product_name'],
            'variant_sku'        => $variant['sku'],
            'variant_material'   => $variant['material'],
            'variant_mechanism'  => $variant['mechanism_type'],
            'variant_color'      => $color,
            'price'              => $variant['price'],
            'quantity'           => $quantity,
        ]);

        recalculateOrderTotal($pdo, $orderId);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * `$itemId` обязан принадлежать `$orderId` — оба условия в одном
 * `WHERE` защищают от подстановки чужого `itemId` в форме. Образец
 * (`is_showroom_sample` снэпшота Варианта, если он ещё не удалён
 * физически) всегда остаётся 1 — `clampCartQuantity()`.
 */
function updateOrderItemQuantity(int $orderId, int $itemId, int $qty): bool
{
    $pdo = getPdo();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('
            SELECT oi.id, pv.is_showroom_sample
            FROM order_items oi
            LEFT JOIN product_variants pv ON pv.id = oi.product_variant_id
            WHERE oi.id = :item_id AND oi.order_id = :order_id
            FOR UPDATE
        ');
        $stmt->execute(['item_id' => $itemId, 'order_id' => $orderId]);
        $item = $stmt->fetch();

        if ($item === false) {
            $pdo->rollBack();
            return false;
        }

        $quantity = clampCartQuantity($qty, (bool) ($item['is_showroom_sample'] ?? false));

        $update = $pdo->prepare('UPDATE order_items SET quantity = :quantity WHERE id = :id AND order_id = :order_id');
        $update->execute(['quantity' => $quantity, 'id' => $itemId, 'order_id' => $orderId]);

        recalculateOrderTotal($pdo, $orderId);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Последняя Позиция Заказа не удаляется — это отмена, не редактирование
 * состава (`FR-ORD-002` правило 6): `false` без изменений, чужой
 * `itemId` — тоже `false` (`rowCount()` покажет 0 совпадений).
 */
function removeOrderItem(int $orderId, int $itemId): bool
{
    $pdo = getPdo();
    $pdo->beginTransaction();

    try {
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM order_items WHERE order_id = :order_id');
        $countStmt->execute(['order_id' => $orderId]);
        if ((int) $countStmt->fetchColumn() <= 1) {
            $pdo->rollBack();
            return false;
        }

        $delete = $pdo->prepare('DELETE FROM order_items WHERE id = :id AND order_id = :order_id');
        $delete->execute(['id' => $itemId, 'order_id' => $orderId]);

        if ($delete->rowCount() === 0) {
            $pdo->rollBack();
            return false;
        }

        recalculateOrderTotal($pdo, $orderId);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Заказ + контакт клиента одним массивом: `is_guest` и
 * `customer_name`/`customer_phone`/`customer_email` вычислены здесь,
 * чтобы View не решала сама, откуда брать контакт — `users` или
 * `guest_*` (ровно один источник, `validateOrderContact()`).
 */
function findOrderForAdmin(int $id): ?array
{
    $stmt = getPdo()->prepare('
        SELECT o.*, u.name AS user_name, u.phone AS user_phone, u.email AS user_email
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        WHERE o.id = :id
        LIMIT 1
    ');
    $stmt->execute(['id' => $id]);
    $order = $stmt->fetch();

    if ($order === false) {
        return null;
    }

    $isGuest = $order['user_id'] === null;

    $order['is_guest']       = $isGuest;
    $order['customer_name']  = $isGuest ? $order['guest_name'] : $order['user_name'];
    $order['customer_phone'] = $isGuest ? $order['guest_phone'] : $order['user_phone'];
    $order['customer_email'] = $isGuest ? $order['guest_email'] : $order['user_email'];

    return $order;
}
