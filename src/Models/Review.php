<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';

/**
 * Отзывы (`reviews`, `ADR-015`) — одна сущность и для отзывов о Товаре
 * (`product_id` заполнен, `FR-CARD-006`), и о магазине (`product_id
 * IS NULL`, `FR-HOME-007` — Таск 6 Фазы 6). Витринная часть (Таск 4) —
 * создание и чтение одобренных отзывов Товара; модерация `/admin/reviews`
 * (Таск 5) — ниже.
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

/**
 * Очередь модерации `/admin/reviews` (Таск 5, `FR-ADM-004`) — тип «о
 * Товаре»/«о магазине» определяется на Views по `product_id`,
 * `LEFT JOIN products` даёт `slug`/`name` для ссылки. `$filters['status']
 * === null` — без фильтра (все статусы), иначе — точное совпадение;
 * по образцу `getAdminReturns()`/`getAdminOrders()`.
 */
function getAdminReviews(array $filters, int $page, int $perPage): array
{
    $offset = ($page - 1) * $perPage;

    $where  = '';
    $params = [];
    if ($filters['status'] !== null) {
        $where             = 'WHERE r.status = :status';
        $params['status']  = $filters['status'];
    }

    $stmt = getPdo()->prepare("
        SELECT
            r.id, r.product_id, r.name, r.email, r.rating, r.text, r.status, r.created_at,
            p.slug AS product_slug, p.name AS product_name
        FROM reviews r
        LEFT JOIN products p ON p.id = r.product_id
        {$where}
        ORDER BY r.created_at DESC, r.id DESC
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

function countAdminReviews(array $filters): int
{
    $where  = '';
    $params = [];
    if ($filters['status'] !== null) {
        $where            = 'WHERE status = :status';
        $params['status'] = $filters['status'];
    }

    $stmt = getPdo()->prepare("SELECT COUNT(*) FROM reviews {$where}");
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

/**
 * `rowCount() === 0` сам по себе не отличает «нет такого id» от «уже
 * был этот статус» — соединение не включает `MYSQL_ATTR_FOUND_ROWS`
 * (`Core/Database.php`), MySQL по умолчанию считает изменённые, а не
 * затронутые строки. Поэтому при нуле — отдельная проверка
 * существования: `false` только когда строки действительно нет
 * (нужно для идемпотентного повторного «Одобрить»/«Отклонить»).
 */
function setReviewStatus(int $id, string $status): bool
{
    $stmt = getPdo()->prepare('UPDATE reviews SET status = :status WHERE id = :id');
    $stmt->execute(['status' => $status, 'id' => $id]);

    if ($stmt->rowCount() > 0) {
        return true;
    }

    $exists = getPdo()->prepare('SELECT id FROM reviews WHERE id = :id');
    $exists->execute(['id' => $id]);

    return $exists->fetch() !== false;
}

/**
 * Бейдж «на модерации» в сайдбаре (`Views/layout/admin-header.php`) —
 * считается на каждый запрос Панели напрямую, без кэша (масштаб
 * проекта — единицы/десятки отзывов).
 */
function countPendingReviews(): int
{
    return (int) getPdo()->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")->fetchColumn();
}

/**
 * Отзыв о магазине, внесённый вручную Менеджером/Администратором
 * (`FR-HOME-007` правило 5: перенос из Instagram/WhatsApp) —
 * `product_id IS NULL`, сразу `approved`: модератор и автор одно лицо,
 * лишний клик «одобрить» не нужен (`phase-6.md`, «Решения фазы»).
 */
function createStoreReview(array $data): int
{
    $stmt = getPdo()->prepare("
        INSERT INTO reviews (product_id, name, email, rating, text, status)
        VALUES (NULL, :name, :email, :rating, :text, 'approved')
    ");
    $stmt->execute([
        'name'   => $data['name'],
        'email'  => $data['email'],
        'rating' => $data['rating'],
        'text'   => $data['text'],
    ]);

    return (int) getPdo()->lastInsertId();
}
