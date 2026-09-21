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
