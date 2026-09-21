<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';

/**
 * Декоративная галерея фото на `/about` (`about_gallery_images`,
 * `ADR-046`) — до `ABOUT_GALLERY_MAX` строк, лимит проверяется в
 * `AdminAboutGalleryController`, не здесь (та же граница ответственности,
 * что `ACCOUNT_ADDRESSES_MAX` у `addresses`).
 */

function getAboutGalleryImages(): array
{
    $stmt = getPdo()->query('SELECT id, path, sort_order FROM about_gallery_images ORDER BY sort_order ASC, id ASC');

    return $stmt->fetchAll();
}

function countAboutGalleryImages(): int
{
    return (int) getPdo()->query('SELECT COUNT(*) FROM about_gallery_images')->fetchColumn();
}

/**
 * `sort_order` — следующий по счёту (текущее количество строк), не
 * ручной ввод: перестановки в этой версии нет, порядок совпадает с
 * порядком загрузки.
 */
function createAboutGalleryImage(string $path): int
{
    $stmt = getPdo()->prepare('
        INSERT INTO about_gallery_images (path, sort_order) VALUES (:path, :sort_order)
    ');
    $stmt->execute([
        'path'       => $path,
        'sort_order' => countAboutGalleryImages(),
    ]);

    return (int) getPdo()->lastInsertId();
}

/**
 * Возвращает путь удалённой строки — вызывающий код удаляет файл
 * (`deleteStoredFile()`) отдельно, Model файлов не касается (`php.md`:
 * никакого HTML/файловых операций в Model — но и это не о файлах, а о
 * данных для них).
 */
function findAboutGalleryImage(int $id): ?array
{
    $stmt = getPdo()->prepare('SELECT id, path FROM about_gallery_images WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $image = $stmt->fetch();

    return $image !== false ? $image : null;
}

function deleteAboutGalleryImage(int $id): bool
{
    $stmt = getPdo()->prepare('DELETE FROM about_gallery_images WHERE id = :id');
    $stmt->execute(['id' => $id]);

    return $stmt->rowCount() > 0;
}
