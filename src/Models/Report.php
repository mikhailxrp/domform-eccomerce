<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';

/**
 * Отчёт по продажам (`FR-ADM-005`, Таск 10 Фазы 4) — только два
 * согласованных показателя (`Q-017`): количество и сумма Заказов за
 * период, без `cancelled`. `$from`/`$to` — `Y-m-d` из
 * `resolveReportPeriod()`; верхняя граница исключающая
 * (`created_at < :to + 1 день`), а не `BETWEEN`, — иначе Заказы,
 * оформленные в течение последнего дня диапазона после полуночи,
 * потерялись бы (`created_at` — `TIMESTAMP`, не `DATE`).
 */
function getSalesSummary(string $from, string $to): array
{
    $stmt = getPdo()->prepare("
        SELECT COUNT(*) AS orders_count, COALESCE(SUM(total), 0) AS orders_total
        FROM orders
        WHERE status <> 'cancelled'
          AND created_at >= :from
          AND created_at < DATE_ADD(:to, INTERVAL 1 DAY)
    ");
    $stmt->execute(['from' => $from, 'to' => $to]);

    return $stmt->fetch();
}

function getSalesByDay(string $from, string $to): array
{
    $stmt = getPdo()->prepare("
        SELECT DATE(created_at) AS day, COUNT(*) AS orders_count, COALESCE(SUM(total), 0) AS orders_total
        FROM orders
        WHERE status <> 'cancelled'
          AND created_at >= :from
          AND created_at < DATE_ADD(:to, INTERVAL 1 DAY)
        GROUP BY DATE(created_at)
        ORDER BY day ASC
    ");
    $stmt->execute(['from' => $from, 'to' => $to]);

    return $stmt->fetchAll();
}
