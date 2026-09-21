<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';
require_once ROOT_PATH . '/src/Core/Price.php';
require_once ROOT_PATH . '/src/Models/Product.php';

/**
 * Избранное — на уровне Товара, не Варианта (`database.md`,
 * `FR-CAT-009`/`FR-ACC-003`, Таск 6 Фазы 7). `favorites` заведена в БД
 * ещё в Фазе 0 (`ADR-016`), здесь — только доступ к ней.
 */

/**
 * `INSERT` без предварительного `SELECT` — конкуренцию (двойной клик,
 * два открытых окна) решает `UNIQUE(user_id, product_id)` в БД: дубль
 * ловится перехватом SQLSTATE 23000 / MySQL 1062 и трактуется как
 * «уже в избранном» → снимаем (`DELETE`), а не как сбой (`dod-global.md`,
 * тот же приём, что `createUser()`). Возвращает `true`, если Товар
 * добавлен в избранное, `false` — если убран.
 */
function toggleFavorite(int $userId, int $productId): bool
{
    try {
        $stmt = getPdo()->prepare('INSERT INTO favorites (user_id, product_id) VALUES (:user_id, :product_id)');
        $stmt->execute(['user_id' => $userId, 'product_id' => $productId]);

        return true;
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) !== 1062) {
            throw $e;
        }

        $stmt = getPdo()->prepare('DELETE FROM favorites WHERE user_id = :user_id AND product_id = :product_id');
        $stmt->execute(['user_id' => $userId, 'product_id' => $productId]);

        return false;
    }
}

/**
 * Один запрос на страницу (каталог/поиск/похожие/Главная) — карточки
 * проверяют членство через `in_array()`, не запросом на карточку.
 */
function getFavoriteProductIds(int $userId): array
{
    $stmt = getPdo()->prepare('SELECT product_id FROM favorites WHERE user_id = :user_id');
    $stmt->execute(['user_id' => $userId]);

    return array_map('intval', array_column($stmt->fetchAll(), 'product_id'));
}

function countFavorites(int $userId): int
{
    $stmt = getPdo()->prepare('SELECT COUNT(*) FROM favorites WHERE user_id = :user_id');
    $stmt->execute(['user_id' => $userId]);

    return (int) $stmt->fetchColumn();
}

/**
 * Страница `/account/favorites` (Таск 7). `INNER JOIN product_variants
 * ... is_active = 1` — по образцу `getRelatedProducts()`: неактивный
 * Товар и Товар без единого активного Варианта отсекаются самим
 * запросом, `attachCheapestVariant()` ниже подбирает самый дешёвый
 * активный Вариант так же, как для мини-карточки.
 */
function getFavoriteProducts(int $userId): array
{
    $pdo      = getPdo();
    $priceSql = discountedPriceSql('pv');

    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.slug, MIN({$priceSql}) AS min_price
        FROM favorites f
        INNER JOIN products p ON p.id = f.product_id AND p.is_active = 1
        INNER JOIN product_variants pv ON pv.product_id = p.id AND pv.is_active = 1
        WHERE f.user_id = :user_id
        GROUP BY p.id, p.name, p.slug, f.created_at
        ORDER BY f.created_at DESC
    ");
    $stmt->execute(['user_id' => $userId]);
    $products = $stmt->fetchAll();

    if ($products === []) {
        return [];
    }

    return attachCheapestVariant($pdo, $products);
}

function removeFavorite(int $userId, int $productId): bool
{
    $stmt = getPdo()->prepare('DELETE FROM favorites WHERE user_id = :user_id AND product_id = :product_id');
    $stmt->execute(['user_id' => $userId, 'product_id' => $productId]);

    return $stmt->rowCount() > 0;
}

/**
 * Идемпотентное добавление — для переноса из корзины
 * (`CartController::moveToFavorites()`, `FR-CART-004`): уже избранный
 * Товар не должен ни падать, ни создавать дубль. Перехват SQLSTATE
 * 23000 / MySQL 1062, тот же приём, что `toggleFavorite()` — но здесь
 * дубль просто игнорируется, не снимает отметку.
 */
function addFavorite(int $userId, int $productId): void
{
    try {
        $stmt = getPdo()->prepare('INSERT INTO favorites (user_id, product_id) VALUES (:user_id, :product_id)');
        $stmt->execute(['user_id' => $userId, 'product_id' => $productId]);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) !== 1062) {
            throw $e;
        }
    }
}
