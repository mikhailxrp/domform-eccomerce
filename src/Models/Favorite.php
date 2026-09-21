<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';

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
