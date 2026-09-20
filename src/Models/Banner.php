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
