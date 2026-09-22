<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';

/**
 * Баннеры слайдера Главной (`banners`, `ADR-022/023`, `FR-HOME-001`) —
 * только чтение; UI редактирования в Панели управления — Фаза 8
 * (`FR-ADM-003`).
 */
function getActiveBanners(): array
{
    $stmt = getPdo()->query('
        SELECT id, image_path, title, link, sort_order
        FROM banners
        WHERE is_active = 1
        ORDER BY sort_order ASC, id ASC
    ');

    return $stmt->fetchAll();
}

/**
 * `/admin/banners` (Таск 7 Фазы 8) — все баннеры, включая выключенные
 * (`is_active = 0`), в отличие от `getActiveBanners()` витрины.
 */
function getAllBanners(): array
{
    $stmt = getPdo()->query('
        SELECT id, image_path, title, link, sort_order, is_active, created_at
        FROM banners
        ORDER BY sort_order ASC, id ASC
    ');

    return $stmt->fetchAll();
}

function findBannerById(int $id): ?array
{
    $stmt = getPdo()->prepare('
        SELECT id, image_path, title, link, sort_order, is_active, created_at
        FROM banners
        WHERE id = :id
    ');
    $stmt->execute(['id' => $id]);

    $banner = $stmt->fetch();

    return $banner === false ? null : $banner;
}

/**
 * `$data['image_path']` — уже сохранённый `storeContentImage()` путь,
 * изображение обязательно при создании (`validateBannerInput()`
 * проверяет это раньше, здесь только запись).
 */
function createBanner(array $data): int
{
    $stmt = getPdo()->prepare('
        INSERT INTO banners (image_path, title, link, sort_order, is_active)
        VALUES (:image_path, :title, :link, :sort_order, 1)
    ');
    $stmt->execute([
        'image_path' => $data['image_path'],
        'title'      => $data['title'] !== '' ? $data['title'] : null,
        'link'       => $data['link'] !== '' ? $data['link'] : null,
        'sort_order' => $data['sort_order'],
    ]);

    return (int) getPdo()->lastInsertId();
}

/**
 * `$data['image_path']` — `null`, если новый файл не загружали
 * (старое изображение сохраняется), иначе новый веб-путь
 * (`AdminBannerController::update()` решает, что подставить, до
 * вызова этой функции). `rowCount() === 0` здесь не отличить от «то
 * же значение уже было», но `findBannerById()` в контроллере уже
 * проверил существование строки раньше — отдельная проверка, как у
 * `setReviewStatus()`, тут не нужна.
 */
function updateBanner(int $id, array $data): bool
{
    $stmt = getPdo()->prepare('
        UPDATE banners
        SET image_path = COALESCE(:image_path, image_path), title = :title, link = :link, sort_order = :sort_order
        WHERE id = :id
    ');

    return $stmt->execute([
        'image_path' => $data['image_path'],
        'title'      => $data['title'] !== '' ? $data['title'] : null,
        'link'       => $data['link'] !== '' ? $data['link'] : null,
        'sort_order' => $data['sort_order'],
        'id'         => $id,
    ]);
}

/**
 * В отличие от `setReviewStatus()`/`markCallbackProcessed()`, здесь не
 * нужна отдельная проверка существования при `rowCount() === 0`:
 * `NOT is_active` всегда меняет значение на противоположное, значит
 * ноль задетых строк однозначно означает «такого `id` нет».
 */
function toggleBanner(int $id): bool
{
    $stmt = getPdo()->prepare('UPDATE banners SET is_active = NOT is_active WHERE id = :id');
    $stmt->execute(['id' => $id]);

    return $stmt->rowCount() > 0;
}
