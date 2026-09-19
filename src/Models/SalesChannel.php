<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';

/**
 * Демо-раздел «Каналы продаж» (без реальной интеграции — переключатель
 * только показывает возможность). `is_locked` — канал «Заявки с сайта»:
 * это сам магазин, отключать нечего, чекбокс во View задизейблен.
 */
function getSalesChannels(): array
{
    $stmt = getPdo()->query(
        'SELECT id, code, name, description, icon, is_locked, is_enabled, sort_order
         FROM sales_channels
         ORDER BY sort_order, id'
    );

    return $stmt->fetchAll();
}

/**
 * `$enabledCodes` — коды каналов, присланные формой как включённые
 * (чекбоксы). Обновляются только не заблокированные (`is_locked = 0`)
 * строки — попытка выключить/включить `website` через подделанный POST
 * ни на что не влияет. Один запрос на канал, без динамического SQL.
 */
function updateSalesChannels(array $enabledCodes): void
{
    $channels = getSalesChannels();

    $stmt = getPdo()->prepare(
        'UPDATE sales_channels SET is_enabled = :is_enabled WHERE code = :code AND is_locked = 0'
    );

    foreach ($channels as $channel) {
        if ($channel['is_locked']) {
            continue;
        }

        $stmt->execute([
            'is_enabled' => in_array($channel['code'], $enabledCodes, true) ? 1 : 0,
            'code'       => $channel['code'],
        ]);
    }
}
