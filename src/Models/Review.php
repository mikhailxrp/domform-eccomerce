<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';

/**
 * Отзывы (`reviews`, `ADR-015`) — одна сущность и для отзывов о Товаре
 * (`product_id` заполнен, `FR-CARD-006`), и о магазине (`product_id
 * IS NULL`, `FR-HOME-007` — Таск 6 Фазы 6). Здесь только витринная
 * часть Таска 4: создание и чтение одобренных отзывов Товара.
 * Модерация (`/admin/reviews`) — Таск 5.
 */

/**
 * Новый отзыв — всегда `status='pending'` (`FR-ADM-004` правило 1),
 * не виден на витрине до одобрения Менеджером/Администратором.
 */
function createReview(array $data): int
{
    $stmt = getPdo()->prepare("
        INSERT INTO reviews (product_id, name, email, rating, text, status)
        VALUES (:product_id, :name, :email, :rating, :text, 'pending')
    ");
    $stmt->execute([
        'product_id' => $data['product_id'],
        'name'       => $data['name'],
        'email'      => $data['email'],
        'rating'     => $data['rating'],
        'text'       => $data['text'],
    ]);

    return (int) getPdo()->lastInsertId();
}

/**
 * Одобренные отзывы конкретного Товара — по `INDEX(product_id, status)`
 * (`database.md`). Без пагинации: реальный масштаб проекта — единицы
 * отзывов на Товар (`dev-log.md`/`database.md` уже опираются на тот же
 * аргумент для каталога на ≤200 Товаров), список короткий по факту, а
 * не искусственно урезанный.
 */
function getApprovedProductReviews(int $productId): array
{
    $stmt = getPdo()->prepare("
        SELECT id, name, rating, text, created_at
        FROM reviews
        WHERE product_id = :product_id AND status = 'approved'
        ORDER BY created_at DESC, id DESC
    ");
    $stmt->execute(['product_id' => $productId]);

    return $stmt->fetchAll();
}
