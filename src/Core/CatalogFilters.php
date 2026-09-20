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
        'on_sale'      => normalizeCatalogOnSale($query['on_sale'] ?? null),
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
 * Фильтр «Со скидкой» (`FR-CAT-010`, Таск 3 Фазы 6) — тот же контракт,
 * что `normalizeCatalogInStock()`: только `'1'` включает фильтр, любое
 * другое значение (включая мусор) — «не применён».
 */
function normalizeCatalogOnSale(mixed $value): bool
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
    if (!empty($filters['on_sale'])) {
        $params['on_sale'] = '1';
    }

    return http_build_query($params);
}

// ─── Поиск (Таск 5) ─────────────────────────────────────────────────────

const SEARCH_BOOLEAN_OPERATORS = ['+', '-', '*', '"', '(', ')', '<', '>', '~', '@'];
const SEARCH_QUERY_MAX_LENGTH  = 100;

/**
 * Обрезка/схлопывание пробелов и ограничение длины — годится и для
 * отображения запроса на странице (`e($q)`), и как основа для LIKE-поиска
 * по материалу/цвету/названию. Операторы BOOLEAN MODE здесь не трогаем —
 * это отображаемый пользователю текст, экранирование для `MATCH...AGAINST`
 * отдельно в `buildFulltextTerm()`.
 */
function normalizeSearchQuery(string $q): string
{
    $normalized = trim(preg_replace('/\s+/u', ' ', $q) ?? '');

    return mb_substr($normalized, 0, SEARCH_QUERY_MAX_LENGTH);
}

/**
 * Термин для `MATCH ... AGAINST (... IN BOOLEAN MODE)` — операторы
 * BOOLEAN MODE (`+ - * " ( ) < > ~ @`) вырезаются из каждого слова перед
 * добавлением `*` (префиксный поиск), иначе запрос вроде `+*"` либо ничего
 * не найдёт, либо (внутри кавычек/скобок без пары) оборвёт синтаксис
 * `AGAINST` ошибкой MySQL. Пустая строка — нечего искать (все слова
 * состояли только из операторов).
 */
function buildFulltextTerm(string $q): string
{
    $words = preg_split('/\s+/u', trim($q), -1, PREG_SPLIT_NO_EMPTY);
    if ($words === false) {
        return '';
    }

    $terms = [];
    foreach ($words as $word) {
        $clean = str_replace(SEARCH_BOOLEAN_OPERATORS, '', $word);
        if ($clean !== '') {
            $terms[] = $clean . '*';
        }
    }

    return implode(' ', $terms);
}
