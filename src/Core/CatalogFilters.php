<?php

declare(strict_types=1);

/**
 * Нормализация и сериализация фильтров каталога — чистые функции без
 * обращения к БД/сессии/суперглобалям (принимают уже готовый массив
 * запроса, а не читают `$_GET` сами).
 */

const CATALOG_ALLOWED_SORTS = ['newest', 'price_asc', 'price_desc'];

function normalizeCatalogFilters(array $query): array
{
    $priceMin = normalizeCatalogPriceBound($query['price_min'] ?? null);
    $priceMax = normalizeCatalogPriceBound($query['price_max'] ?? null);

    if ($priceMin !== null && $priceMax !== null && $priceMin > $priceMax) {
        [$priceMin, $priceMax] = [$priceMax, $priceMin];
    }

    return [
        'sort'         => normalizeCatalogSort($query['sort'] ?? null),
        'category_ids' => normalizeCatalogCategoryIds($query['category'] ?? null),
        'colors'       => normalizeCatalogColors($query['color'] ?? null),
        'price_min'    => $priceMin,
        'price_max'    => $priceMax,
        'in_stock'     => normalizeCatalogInStock($query['in_stock'] ?? null),
    ];
}

function normalizeCatalogSort(mixed $value): string
{
    return is_string($value) && in_array($value, CATALOG_ALLOWED_SORTS, true) ? $value : 'newest';
}

function normalizeCatalogCategoryIds(mixed $value): array
{
    $items = is_array($value) ? $value : ($value !== null ? [$value] : []);

    $ids = [];
    foreach ($items as $item) {
        if (is_numeric($item) && (int) $item > 0) {
            $ids[(int) $item] = (int) $item;
        }
    }
    sort($ids);

    return array_values($ids);
}

function normalizeCatalogColors(mixed $value): array
{
    $items = is_array($value) ? $value : ($value !== null ? [$value] : []);

    $colors = [];
    foreach ($items as $item) {
        if (!is_string($item)) {
            continue;
        }
        $color = trim($item);
        if ($color === '' || mb_strlen($color) > 100) {
            continue;
        }
        $colors[$color] = $color;
    }
    sort($colors);

    return array_values($colors);
}

function normalizeCatalogPriceBound(mixed $value): ?int
{
    if (!is_numeric($value)) {
        return null;
    }
    $intValue = (int) $value;

    return $intValue >= 0 ? $intValue : null;
}

function normalizeCatalogInStock(mixed $value): bool
{
    return $value === '1' || $value === 1 || $value === true;
}

/**
 * Query-строка фильтров без `page` (пагинация добавляет её сама) — для
 * ссылок пагинации и «Сбросить фильтры». Значения по умолчанию не
 * попадают в строку, URL остаётся чистым, когда фильтр не применён.
 */
function buildCatalogQueryString(array $filters): string
{
    $params = [];

    if (($filters['sort'] ?? 'newest') !== 'newest') {
        $params['sort'] = $filters['sort'];
    }
    if (!empty($filters['category_ids'])) {
        $params['category'] = $filters['category_ids'];
    }
    if (!empty($filters['colors'])) {
        $params['color'] = $filters['colors'];
    }
    if (($filters['price_min'] ?? null) !== null) {
        $params['price_min'] = $filters['price_min'];
    }
    if (($filters['price_max'] ?? null) !== null) {
        $params['price_max'] = $filters['price_max'];
    }
    if (!empty($filters['in_stock'])) {
        $params['in_stock'] = '1';
    }

    return http_build_query($params);
}
