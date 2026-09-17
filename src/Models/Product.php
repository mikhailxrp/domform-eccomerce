<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';

/**
 * Список Товаров каталога — цена и фото на карточке берутся от самого
 * дешёвого активного Варианта (тот же Вариант, что даёт «от X ₽»), а не
 * от всех Вариантов сразу. Только `is_active = 1` с ≥ 1 активным
 * Вариантом.
 */
function getCatalogProducts(array $filters, string $sort, int $page, int $perPage): array
{
    $pdo = getPdo();

    $where  = ['p.is_active = 1'];
    $params = [];

    if (isset($filters['category_id'])) {
        $where[]                = 'EXISTS (
            SELECT 1 FROM product_categories pc
            WHERE pc.product_id = p.id AND pc.category_id = :category_id
        )';
        $params['category_id'] = $filters['category_id'];
    }

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
    foreach ($params as $key => $value) {
        $stmt->bindValue(":{$key}", $value, PDO::PARAM_INT);
    }
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

    $where = [
        'p.is_active = 1',
        'EXISTS (SELECT 1 FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1)',
    ];
    $params = [];

    if (isset($filters['category_id'])) {
        $where[]                = 'EXISTS (
            SELECT 1 FROM product_categories pc
            WHERE pc.product_id = p.id AND pc.category_id = :category_id
        )';
        $params['category_id'] = $filters['category_id'];
    }

    $whereSql = implode(' AND ', $where);
    $stmt     = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE {$whereSql}");
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
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
