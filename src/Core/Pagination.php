<?php

declare(strict_types=1);

/**
 * Расчёт пагинации над списком — чистые функции без обращения к БД/сессии.
 */

function buildPagination(int $total, int $page, int $perPage): array
{
    $perPage    = max(1, $perPage);
    $totalPages = (int) max(1, (int) ceil($total / $perPage));
    $page       = max(1, min($page, $totalPages));

    return [
        'page'        => $page,
        'per_page'    => $perPage,
        'total'       => $total,
        'total_pages' => $totalPages,
        'has_prev'    => $page > 1,
        'has_next'    => $page < $totalPages,
        'prev_page'   => $page > 1 ? $page - 1 : null,
        'next_page'   => $page < $totalPages ? $page + 1 : null,
        'offset'      => ($page - 1) * $perPage,
    ];
}

/**
 * URL для конкретной страницы списка — переиспользуется для любого
 * пагинируемого списка (не только каталога), поэтому принимает путь и
 * набор query-параметров, а не строит их сама.
 */
function buildPaginationUrl(string $path, array $queryParams, int $page): string
{
    $queryParams['page'] = $page;
    $query = http_build_query($queryParams);

    return $query !== '' ? $path . '?' . $query : $path;
}
