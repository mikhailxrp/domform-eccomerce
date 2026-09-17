<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';

/**
 * Список Товаров каталога — цена и фото на карточке берутся от самого
 * дешёвого активного Варианта (тот же Вариант, что даёт «от X ₽»), а не
 * от всех Вариантов сразу. Только `is_active = 1` с ≥ 1 активным
 * Вариантом.
 *
 * `$filters` — нормализованный массив из `normalizeCatalogFilters()`
 * (`CatalogFilters.php`): `category_ids`/`colors` — int[]/string[],
 * `price_min`/`price_max` — ?int, `in_stock` — bool. Все ключи
 * необязательны — отсутствующий/пустой означает «фильтр не применён».
 */
function getCatalogProducts(array $filters, string $sort, int $page, int $perPage): array
{
    $pdo = getPdo();

    [$filterConditions, $filterParams] = buildCatalogFilterConditions($filters);
    $where = array_merge(['p.is_active = 1'], $filterConditions);

    $orderBy = match ($sort) {
        'price_asc'  => 'min_price ASC',
        'price_desc' => 'min_price DESC',
        default      => 'p.created_at DESC',
    };

    $whereSql = implode(' AND ', $where);
    $offset   = ($page - 1) * $perPage;

    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.slug, MIN(pv.price) AS min_price
        FROM products p
        INNER JOIN product_variants pv ON pv.product_id = p.id AND pv.is_active = 1
        WHERE {$whereSql}
        GROUP BY p.id, p.name, p.slug, p.created_at
        ORDER BY {$orderBy}
        LIMIT :limit OFFSET :offset
    ");
    bindCatalogFilterParams($stmt, $filterParams);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();

    if ($products === []) {
        return [];
    }

    return attachCheapestVariant($pdo, $products);
}

function countCatalogProducts(array $filters): int
{
    $pdo = getPdo();

    [$filterConditions, $filterParams] = buildCatalogFilterConditions($filters);
    $where = array_merge([
        'p.is_active = 1',
        'EXISTS (SELECT 1 FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1)',
    ], $filterConditions);

    $whereSql = implode(' AND ', $where);
    $stmt     = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE {$whereSql}");
    bindCatalogFilterParams($stmt, $filterParams);
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

/**
 * Границы цены и список цветов для сайдбара фильтров — тот же скоуп
 * (категория, если задана), что и сам листинг, иначе слайдер/чекбоксы
 * предлагали бы значения, которых для текущей категории не существует.
 */
function getFilterOptions(?int $categoryId): array
{
    $pdo = getPdo();

    $categoryJoin = $categoryId !== null
        ? 'INNER JOIN product_categories pc ON pc.product_id = p.id AND pc.category_id = :category_id'
        : '';

    $priceStmt = $pdo->prepare("
        SELECT MIN(pv.price) AS price_min, MAX(pv.price) AS price_max
        FROM products p
        INNER JOIN product_variants pv ON pv.product_id = p.id AND pv.is_active = 1
        {$categoryJoin}
        WHERE p.is_active = 1
    ");
    if ($categoryId !== null) {
        $priceStmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
    }
    $priceStmt->execute();
    $priceRow = $priceStmt->fetch();

    $colorStmt = $pdo->prepare("
        SELECT DISTINCT vi.color
        FROM variant_images vi
        INNER JOIN product_variants pv ON pv.id = vi.product_variant_id
        INNER JOIN products p ON p.id = pv.product_id
        {$categoryJoin}
        WHERE vi.color IS NOT NULL AND pv.is_active = 1 AND p.is_active = 1
        ORDER BY vi.color
    ");
    if ($categoryId !== null) {
        $colorStmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
    }
    $colorStmt->execute();

    return [
        'price_min' => $priceRow !== false && $priceRow['price_min'] !== null ? (int) $priceRow['price_min'] : 0,
        'price_max' => $priceRow !== false && $priceRow['price_max'] !== null ? (int) $priceRow['price_max'] : 0,
        'colors'    => array_column($colorStmt->fetchAll(), 'color'),
    ];
}

/**
 * Условия `WHERE`/именованные параметры для фильтров каталога — общие
 * для `getCatalogProducts()` и `countCatalogProducts()`, чтобы список и
 * счётчик товаров никогда не могли разойтись по набору условий.
 * Каждый фильтр — Товар отбирается, если хотя бы один активный Вариант
 * ему удовлетворяет (`EXISTS`), не «все Варианты сразу» — тот же
 * принцип, что уже был зафиксирован в `phase-1.md` для фильтра цены.
 */
function buildCatalogFilterConditions(array $filters): array
{
    $conditions = [];
    $params     = [];

    if (!empty($filters['category_ids'])) {
        [$inSql, $inParams] = buildCatalogInClause('category_id', $filters['category_ids']);
        $conditions[] = "EXISTS (
            SELECT 1 FROM product_categories pc
            WHERE pc.product_id = p.id AND pc.category_id IN ({$inSql})
        )";
        $params = array_merge($params, $inParams);
    }

    if (!empty($filters['colors'])) {
        [$inSql, $inParams] = buildCatalogInClause('color', $filters['colors']);
        $conditions[] = "EXISTS (
            SELECT 1 FROM product_variants pv2
            INNER JOIN variant_images vi2 ON vi2.product_variant_id = pv2.id
            WHERE pv2.product_id = p.id AND pv2.is_active = 1 AND vi2.color IN ({$inSql})
        )";
        $params = array_merge($params, $inParams);
    }

    $priceMin = $filters['price_min'] ?? null;
    $priceMax = $filters['price_max'] ?? null;
    if ($priceMin !== null || $priceMax !== null) {
        $priceConditions = ['pv3.is_active = 1'];
        if ($priceMin !== null) {
            $priceConditions[]    = 'pv3.price >= :price_min';
            $params['price_min'] = $priceMin;
        }
        if ($priceMax !== null) {
            $priceConditions[]    = 'pv3.price <= :price_max';
            $params['price_max'] = $priceMax;
        }
        $priceWhere   = implode(' AND ', $priceConditions);
        $conditions[] = "EXISTS (SELECT 1 FROM product_variants pv3 WHERE pv3.product_id = p.id AND {$priceWhere})";
    }

    if (!empty($filters['in_stock'])) {
        $conditions[] = "EXISTS (
            SELECT 1 FROM product_variants pv4
            WHERE pv4.product_id = p.id AND pv4.is_active = 1 AND pv4.is_showroom_sample = 1
        )";
    }

    return [$conditions, $params];
}

function buildCatalogInClause(string $prefix, array $values): array
{
    $placeholders = [];
    $params       = [];
    foreach (array_values($values) as $index => $value) {
        $key            = "{$prefix}_{$index}";
        $placeholders[] = ":{$key}";
        $params[$key]   = $value;
    }

    return [implode(',', $placeholders), $params];
}

function bindCatalogFilterParams(PDOStatement $stmt, array $params): void
{
    foreach ($params as $key => $value) {
        $stmt->bindValue(":{$key}", $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
}

/**
 * Добавляет к каждому Товару данные самого дешёвого активного Варианта
 * (id/материал/цвет/фото) одним батч-запросом — без N+1 на страницу
 * каталога.
 */
function attachCheapestVariant(PDO $pdo, array $products): array
{
    $productIds  = array_column($products, 'id');
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));

    $stmt = $pdo->prepare("
        SELECT ranked.product_id, ranked.id AS variant_id, ranked.material, img.path AS image_path, img.color
        FROM (
            SELECT pv.*, ROW_NUMBER() OVER (PARTITION BY pv.product_id ORDER BY pv.price ASC, pv.id ASC) AS rn
            FROM product_variants pv
            WHERE pv.is_active = 1 AND pv.product_id IN ({$placeholders})
        ) ranked
        LEFT JOIN (
            SELECT vi.*, ROW_NUMBER() OVER (
                PARTITION BY vi.product_variant_id ORDER BY vi.is_main DESC, vi.sort_order ASC, vi.id ASC
            ) AS rn2
            FROM variant_images vi
        ) img ON img.product_variant_id = ranked.id AND img.rn2 = 1
        WHERE ranked.rn = 1
    ");
    $stmt->execute($productIds);

    $variantByProduct = [];
    foreach ($stmt->fetchAll() as $row) {
        $variantByProduct[(int) $row['product_id']] = $row;
    }

    return array_map(static function (array $product) use ($variantByProduct): array {
        $variant = $variantByProduct[(int) $product['id']] ?? null;

        return [
            'id'         => (int) $product['id'],
            'name'       => $product['name'],
            'slug'       => $product['slug'],
            'min_price'  => $product['min_price'],
            'variant_id' => $variant !== null ? (int) $variant['variant_id'] : null,
            'material'   => $variant['material'] ?? null,
            'color'      => $variant['color'] ?? null,
            'image_path' => $variant['image_path'] ?? null,
        ];
    }, $products);
}
