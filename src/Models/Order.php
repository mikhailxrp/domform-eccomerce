<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';
require_once ROOT_PATH . '/src/Core/Cart.php';

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
