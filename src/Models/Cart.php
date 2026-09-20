<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';
require_once ROOT_PATH . '/src/Core/Cart.php';
require_once ROOT_PATH . '/src/Core/Price.php';

/**
 * `$owner` — ровно один из `['user_id' => int]` (авторизованный) или
 * `['session_id' => string]` (гость, значение cookie `cart_token`,
 * `ADR-034`) — второй ключ либо отсутствует, либо `null`. Любое другое
 * сочетание — программная ошибка вызывающего кода, не пользовательский
 * ввод, поэтому падаем исключением, а не молча выбираем ключ.
 */
function validateCartOwner(array $owner): array
{
    $hasUserId    = array_key_exists('user_id', $owner) && $owner['user_id'] !== null;
    $hasSessionId = array_key_exists('session_id', $owner) && $owner['session_id'] !== null;

    if ($hasUserId === $hasSessionId) {
        throw new RuntimeException(
            'Владелец корзины должен содержать ровно один из ключей user_id/session_id.'
        );
    }

    return $hasUserId
        ? ['column' => 'user_id', 'value' => (int) $owner['user_id']]
        : ['column' => 'session_id', 'value' => (string) $owner['session_id']];
}

/**
 * Строки корзины владельца — текущая цена и `is_showroom_sample` из
 * `product_variants` (источник истины, `general.md`), `price_snapshot`
 * для сравнения цен (`Core/Cart.php:findPriceChanges()`), `is_reserved`
 * = есть активный Резерв на Вариант (конкуренция за образец — Таск 3),
 * фото — по выбранному цвету, если такого нет — главное фото Варианта.
 */
function getCartItems(array $owner): array
{
    $ownerCondition = validateCartOwner($owner);
    $priceSql       = discountedPriceSql('pv');

    $stmt = getPdo()->prepare("
        SELECT
            ci.id,
            ci.product_variant_id,
            ci.color,
            ci.quantity,
            ci.price_snapshot,
            {$priceSql} AS price,
            pv.price AS old_price,
            pv.discount_percent,
            pv.sku,
            pv.material,
            pv.mechanism_type,
            pv.is_showroom_sample,
            pv.is_active AS variant_is_active,
            p.id AS product_id,
            p.name AS product_name,
            p.slug AS product_slug,
            EXISTS (
                SELECT 1 FROM reserves r
                WHERE r.product_variant_id = pv.id AND r.status = 'active'
            ) AS is_reserved,
            (
                SELECT img.path FROM variant_images img
                WHERE img.product_variant_id = pv.id
                ORDER BY (img.color <=> ci.color) DESC, img.is_main DESC, img.sort_order ASC, img.id ASC
                LIMIT 1
            ) AS image_path
        FROM cart_items ci
        INNER JOIN product_variants pv ON pv.id = ci.product_variant_id
        INNER JOIN products p ON p.id = pv.product_id
        WHERE ci.{$ownerCondition['column']} = :owner_value
        ORDER BY ci.id DESC
    ");
    $stmt->execute(['owner_value' => $ownerCondition['value']]);

    return $stmt->fetchAll();
}

const ADD_TO_CART_OK        = 'ok';
const ADD_TO_CART_NOT_FOUND = 'not_found';
const ADD_TO_CART_RESERVED  = 'reserved';

/**
 * Тот же Вариант+цвет в корзине владельца — увеличивает количество
 * (с clamp), иначе создаёт новую строку со снэпшотом текущей цены.
 * Неактивный/несуществующий Вариант → `ADD_TO_CART_NOT_FOUND`, строка
 * не создаётся. Выставочный образец с активным Резервом (`BR-003`,
 * `BR-004`, Таск 5 Фазы 5) → `ADD_TO_CART_RESERVED`, тоже без записи —
 * позицию, которую нельзя оформить (`createOrder()` откажет всё равно),
 * не добавляем молча, чтобы Покупатель сразу увидел понятное сообщение,
 * а не узнал об этом только на чекауте.
 */
function addCartItem(array $owner, int $variantId, ?string $color, int $qty): string
{
    $ownerCondition = validateCartOwner($owner);
    $pdo            = getPdo();

    $variantStmt = $pdo->prepare("
        SELECT
            pv.price, pv.discount_percent, pv.is_showroom_sample,
            EXISTS (
                SELECT 1 FROM reserves r
                WHERE r.product_variant_id = pv.id AND r.status = 'active'
            ) AS has_active_reserve
        FROM product_variants pv
        WHERE pv.id = :id AND pv.is_active = 1
    ");
    $variantStmt->execute(['id' => $variantId]);
    $variant = $variantStmt->fetch();

    if ($variant === false) {
        return ADD_TO_CART_NOT_FOUND;
    }

    // Снэпшот пишется по эффективной (скидочной) цене (`ADR-041`,
    // `FR-DISC-002` правило 2) — та же цена, что видит Покупатель в
    // корзине сразу после добавления.
    $effectivePrice = discountedPrice((string) $variant['price'], $variant['discount_percent']);

    $isShowroomSample = (bool) $variant['is_showroom_sample'];

    if (isReservedSampleUnavailable($isShowroomSample, (bool) $variant['has_active_reserve'])) {
        return ADD_TO_CART_RESERVED;
    }

    $existingStmt = $pdo->prepare("
        SELECT id, quantity FROM cart_items
        WHERE {$ownerCondition['column']} = :owner_value
          AND product_variant_id = :variant_id
          AND color <=> :color
        LIMIT 1
    ");
    $existingStmt->execute([
        'owner_value' => $ownerCondition['value'],
        'variant_id'  => $variantId,
        'color'       => $color,
    ]);
    $existing = $existingStmt->fetch();

    if ($existing !== false) {
        $newQty = clampCartQuantity((int) $existing['quantity'] + $qty, $isShowroomSample);

        $updateStmt = $pdo->prepare('UPDATE cart_items SET quantity = :quantity WHERE id = :id');
        $updateStmt->execute(['quantity' => $newQty, 'id' => $existing['id']]);

        return ADD_TO_CART_OK;
    }

    $insertStmt = $pdo->prepare("
        INSERT INTO cart_items ({$ownerCondition['column']}, product_variant_id, color, quantity, price_snapshot)
        VALUES (:owner_value, :variant_id, :color, :quantity, :price_snapshot)
    ");
    $insertStmt->execute([
        'owner_value'    => $ownerCondition['value'],
        'variant_id'     => $variantId,
        'color'          => $color,
        'quantity'       => clampCartQuantity($qty, $isShowroomSample),
        'price_snapshot' => $effectivePrice,
    ]);

    return ADD_TO_CART_OK;
}

/**
 * Возвращает `false` без изменений, если строка не принадлежит
 * владельцу (условие в `WHERE`, не отдельная проверка) или не найдена.
 */
function updateCartItemQuantity(array $owner, int $itemId, int $qty): bool
{
    $ownerCondition = validateCartOwner($owner);
    $pdo            = getPdo();

    $itemStmt = $pdo->prepare("
        SELECT pv.is_showroom_sample
        FROM cart_items ci
        INNER JOIN product_variants pv ON pv.id = ci.product_variant_id
        WHERE ci.id = :id AND ci.{$ownerCondition['column']} = :owner_value
        LIMIT 1
    ");
    $itemStmt->execute(['id' => $itemId, 'owner_value' => $ownerCondition['value']]);
    $item = $itemStmt->fetch();

    if ($item === false) {
        return false;
    }

    $clampedQty = clampCartQuantity($qty, (bool) $item['is_showroom_sample']);

    $updateStmt = $pdo->prepare("
        UPDATE cart_items SET quantity = :quantity
        WHERE id = :id AND {$ownerCondition['column']} = :owner_value
    ");
    $updateStmt->execute([
        'quantity'    => $clampedQty,
        'id'          => $itemId,
        'owner_value' => $ownerCondition['value'],
    ]);

    return $updateStmt->rowCount() > 0;
}

function removeCartItem(array $owner, int $itemId): bool
{
    $ownerCondition = validateCartOwner($owner);

    $stmt = getPdo()->prepare("
        DELETE FROM cart_items WHERE id = :id AND {$ownerCondition['column']} = :owner_value
    ");
    $stmt->execute(['id' => $itemId, 'owner_value' => $ownerCondition['value']]);

    return $stmt->rowCount() > 0;
}

function countCartItems(array $owner): int
{
    $ownerCondition = validateCartOwner($owner);

    $stmt = getPdo()->prepare("
        SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE {$ownerCondition['column']} = :owner_value
    ");
    $stmt->execute(['owner_value' => $ownerCondition['value']]);

    return (int) $stmt->fetchColumn();
}

function clearCart(array $owner): void
{
    $ownerCondition = validateCartOwner($owner);

    $stmt = getPdo()->prepare("DELETE FROM cart_items WHERE {$ownerCondition['column']} = :owner_value");
    $stmt->execute(['owner_value' => $ownerCondition['value']]);
}

/**
 * «Принять изменения» на `/checkout` (`FR-CHK-003`): недоступные из-за
 * активного Резерва на образец позиции удаляются из корзины,
 * `price_snapshot` оставшихся строк подтягивается к текущей цене
 * Варианта — после вызова `findPriceChanges()`/`is_reserved` для этого
 * владельца снова пусты.
 */
function acceptCartPriceChanges(array $owner): void
{
    $ownerCondition = validateCartOwner($owner);
    $pdo            = getPdo();

    $deleteStmt = $pdo->prepare("
        DELETE ci FROM cart_items ci
        INNER JOIN product_variants pv ON pv.id = ci.product_variant_id
        WHERE ci.{$ownerCondition['column']} = :owner_value
          AND EXISTS (
              SELECT 1 FROM reserves r
              WHERE r.product_variant_id = pv.id AND r.status = 'active'
          )
    ");
    $deleteStmt->execute(['owner_value' => $ownerCondition['value']]);

    // Снэпшот подтягивается к эффективной (скидочной) цене — той же,
    // что теперь отдаёт `getCartItems()` (`ADR-041`); синхронизация с
    // сырой `pv.price` оставляла бы снэпшот «отстающим» навсегда при
    // активной скидке, и `findPriceChanges()` продолжал бы показывать
    // разницу сразу после «Принять изменения».
    $priceSql = discountedPriceSql('pv');
    $syncStmt = $pdo->prepare("
        UPDATE cart_items ci
        INNER JOIN product_variants pv ON pv.id = ci.product_variant_id
        SET ci.price_snapshot = {$priceSql}
        WHERE ci.{$ownerCondition['column']} = :owner_value
    ");
    $syncStmt->execute(['owner_value' => $ownerCondition['value']]);
}

/**
 * Переносит гостевую корзину (`cart_token`, `$token`) в корзину
 * зарегистрированного/вошедшего пользователя: тот же Вариант+цвет —
 * количества суммируются (с clamp, образец остаётся 1), иначе гостевая
 * строка просто переходит во владение пользователя. Идемпотентно —
 * после переноса у гостевого токена не остаётся строк, повторный вызов
 * с тем же токеном ничего не находит и не ломает.
 */
function mergeGuestCart(string $token, int $userId): void
{
    $pdo = getPdo();

    $guestItemsStmt = $pdo->prepare('
        SELECT ci.id, ci.product_variant_id, ci.color, ci.quantity, pv.is_showroom_sample
        FROM cart_items ci
        INNER JOIN product_variants pv ON pv.id = ci.product_variant_id
        WHERE ci.session_id = :token
    ');
    $guestItemsStmt->execute(['token' => $token]);
    $guestItems = $guestItemsStmt->fetchAll();

    if ($guestItems === []) {
        return;
    }

    $findUserItemStmt = $pdo->prepare('
        SELECT id, quantity FROM cart_items
        WHERE user_id = :user_id AND product_variant_id = :variant_id AND color <=> :color
        LIMIT 1
    ');
    $updateQuantityStmt = $pdo->prepare('UPDATE cart_items SET quantity = :quantity WHERE id = :id');
    $reassignRowStmt    = $pdo->prepare('UPDATE cart_items SET user_id = :user_id, session_id = NULL WHERE id = :id');
    $deleteRowStmt      = $pdo->prepare('DELETE FROM cart_items WHERE id = :id');

    $pdo->beginTransaction();
    try {
        foreach ($guestItems as $guestItem) {
            $findUserItemStmt->execute([
                'user_id'    => $userId,
                'variant_id' => $guestItem['product_variant_id'],
                'color'      => $guestItem['color'],
            ]);
            $userItem = $findUserItemStmt->fetch();

            if ($userItem !== false) {
                $mergedQty = clampCartQuantity(
                    (int) $userItem['quantity'] + (int) $guestItem['quantity'],
                    (bool) $guestItem['is_showroom_sample']
                );
                $updateQuantityStmt->execute(['quantity' => $mergedQty, 'id' => $userItem['id']]);
                $deleteRowStmt->execute(['id' => $guestItem['id']]);
                continue;
            }

            $reassignRowStmt->execute(['user_id' => $userId, 'id' => $guestItem['id']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
