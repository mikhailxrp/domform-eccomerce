<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';

/**
 * Статические страницы (`content_pages`, `ADR-022`, `FR-CNT-001…006`).
 * Тело — плоский текст с мини-разметкой (`ADR-044`), рендерится только
 * через `renderContentBody()` (`Core/Content.php`). Редактирование в
 * Панели управления — Таск 6 Фазы 8 (`FR-ADM-003`).
 */
function findContentPageBySlug(string $slug): ?array
{
    $stmt = getPdo()->prepare('
        SELECT id, slug, title, body, image_path, updated_at
        FROM content_pages
        WHERE slug = :slug
        LIMIT 1
    ');
    $stmt->execute(['slug' => $slug]);

    $page = $stmt->fetch();

    return $page === false ? null : $page;
}

function getAllContentPages(): array
{
    $stmt = getPdo()->query('
        SELECT id, slug, title, image_path, updated_at
        FROM content_pages
        ORDER BY id ASC
    ');

    return $stmt->fetchAll();
}

/**
 * `/admin/content/{slug}/update` (Таск 6, `FR-ADM-003`) — `$title`/`$body`
 * уже прошли `validateContentPageInput()` в контроллере. `false` только
 * когда `slug` не из whitelist `CONTENT_PAGE_SLUGS` — `AdminContentController::edit()`
 * уже отдал 404 на этом же условии раньше, `rowCount() === 0` здесь
 * означает именно отсутствие строки, а не «значение не изменилось»
 * (заголовок/тело почти никогда не совпадают байт-в-байт с прежними).
 */
function updateContentPage(string $slug, string $title, string $body): bool
{
    $stmt = getPdo()->prepare('
        UPDATE content_pages
        SET title = :title, body = :body
        WHERE slug = :slug
    ');
    $stmt->execute(['title' => $title, 'body' => $body, 'slug' => $slug]);

    if ($stmt->rowCount() > 0) {
        return true;
    }

    $exists = getPdo()->prepare('SELECT id FROM content_pages WHERE slug = :slug');
    $exists->execute(['slug' => $slug]);

    return $exists->fetch() !== false;
}

/**
 * Загрузка/удаление фото страницы — отдельная функция от
 * `updateContentPage()` (`AdminContentController::update()` вызывает
 * оба при загрузке нового файла, `deleteImage()` — только этот).
 * `$path === null` — «Удалить фото».
 */
function updateContentPageImage(string $slug, ?string $path): void
{
    $stmt = getPdo()->prepare('UPDATE content_pages SET image_path = :image_path WHERE slug = :slug');
    $stmt->execute(['image_path' => $path, 'slug' => $slug]);
}
