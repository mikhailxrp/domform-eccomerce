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
        // Резервированный образец больше не «в наличии» (`BR-003`,
        // `BR-004`, Таск 5 Фазы 5) — физический экземпляр закреплён за
        // другим Покупателем, показывать его как доступный значило бы
        // гарантированно сорвать Заказ (`stock.md`).
        $conditions[] = "EXISTS (
            SELECT 1 FROM product_variants pv4
            WHERE pv4.product_id = p.id AND pv4.is_active = 1 AND pv4.is_showroom_sample = 1
              AND NOT EXISTS (
                  SELECT 1 FROM reserves r4
                  WHERE r4.product_variant_id = pv4.id AND r4.status = 'active'
              )
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
 * Поиск (`FR-SRCH-002`, Таск 5) — та же форма строки, что и листинг
 * каталога (через `attachCheapestVariant()`), тот же `product-card.php`.
 * `$q` — уже нормализованный `normalizeSearchQuery()` запрос.
 */
function searchProducts(string $q, string $sort, int $page, int $perPage): array
{
    $pdo = getPdo();

    [$where, $params] = buildSearchConditions($q);

    $orderBy = match ($sort) {
        'price_asc'  => 'min_price ASC',
        'price_desc' => 'min_price DESC',
        default      => 'p.created_at DESC',
    };

    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.slug, MIN(pv.price) AS min_price
        FROM products p
        INNER JOIN product_variants pv ON pv.product_id = p.id AND pv.is_active = 1
        WHERE p.is_active = 1 AND ({$where})
        GROUP BY p.id, p.name, p.slug, p.created_at
        ORDER BY {$orderBy}
        LIMIT :limit OFFSET :offset
    ");
    bindSearchParams($stmt, $params);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();

    if ($products === []) {
        return [];
    }

    return attachCheapestVariant($pdo, $products);
}

function countSearchProducts(string $q): int
{
    $pdo = getPdo();

    [$where, $params] = buildSearchConditions($q);

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM products p
        WHERE p.is_active = 1
          AND EXISTS (SELECT 1 FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1)
          AND ({$where})
    ");
    bindSearchParams($stmt, $params);
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

/**
 * Подсказки поиска (`FR-SRCH-001`) — до `$limit` позиций, тот же формат
 * строки, что и остальной поиск.
 */
function suggestProducts(string $q, int $limit): array
{
    $pdo = getPdo();

    [$where, $params] = buildSearchConditions($q);

    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.slug, MIN(pv.price) AS min_price
        FROM products p
        INNER JOIN product_variants pv ON pv.product_id = p.id AND pv.is_active = 1
        WHERE p.is_active = 1 AND ({$where})
        GROUP BY p.id, p.name, p.slug, p.created_at
        ORDER BY p.created_at DESC
        LIMIT :limit
    ");
    bindSearchParams($stmt, $params);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();

    if ($products === []) {
        return [];
    }

    return attachCheapestVariant($pdo, $products);
}

/**
 * Условия `WHERE` для поиска — общие для `searchProducts()`,
 * `countSearchProducts()`, `suggestProducts()`, чтобы список и счётчик не
 * могли разойтись по условиям (тот же принцип, что
 * `buildCatalogFilterConditions()`). Стратегия по длине запроса —
 * `phase-1.md`, «Решения фазы»: `innodb_ft_min_token_size = 3` на
 * shared-хостинге не даёт FULLTEXT искать короче 3 символов, поэтому
 * 1–2 символа идут через префиксный `LIKE`.
 */
function buildSearchConditions(string $q): array
{
    $likeTerm = escapeLikeValue($q) . '%';

    if (mb_strlen($q) >= 3) {
        $fulltextTerm = buildFulltextTerm($q);
        if ($fulltextTerm === '') {
            return ['0 = 1', []];
        }

        $condition = '(
            MATCH(p.name, p.description) AGAINST (:fulltext_term IN BOOLEAN MODE)
            OR EXISTS (
                SELECT 1 FROM product_variants pvs
                WHERE pvs.product_id = p.id AND pvs.is_active = 1 AND pvs.material LIKE :material_term
            )
            OR EXISTS (
                SELECT 1 FROM product_variants pvc
                INNER JOIN variant_images vic ON vic.product_variant_id = pvc.id
                WHERE pvc.product_id = p.id AND pvc.is_active = 1 AND vic.color LIKE :color_term
            )
        )';

        return [$condition, [
            'fulltext_term' => $fulltextTerm,
            'material_term' => $likeTerm,
            'color_term'    => $likeTerm,
        ]];
    }

    $condition = '(
        p.name LIKE :name_term
        OR EXISTS (
            SELECT 1 FROM product_variants pvs
            WHERE pvs.product_id = p.id AND pvs.is_active = 1 AND pvs.material LIKE :material_term
        )
        OR EXISTS (
            SELECT 1 FROM product_variants pvc
            INNER JOIN variant_images vic ON vic.product_variant_id = pvc.id
            WHERE pvc.product_id = p.id AND pvc.is_active = 1 AND vic.color LIKE :color_term
        )
    )';

    return [$condition, [
        'name_term'     => $likeTerm,
        'material_term' => $likeTerm,
        'color_term'    => $likeTerm,
    ]];
}

function bindSearchParams(PDOStatement $stmt, array $params): void
{
    foreach ($params as $key => $value) {
        $stmt->bindValue(":{$key}", $value, PDO::PARAM_STR);
    }
}

/**
 * Экранирует `\`/`%`/`_` перед подстановкой в `LIKE 'q%'` — иначе ввод
 * вроде `50%` вёл бы себя как маска, а не как буквальный текст запроса.
 */
function escapeLikeValue(string $value): string
{
    return addcslashes($value, '\\_%');
}

/**
 * Поиск активного Варианта для добавления в состав Заказа
 * (`variant-picker`, Таск 4 Фазы 4) — по названию Товара (FULLTEXT/
 * префикс, тот же порог в 3 символа, что `buildSearchConditions()`) и
 * артикулу Варианта (всегда префиксом, независимо от длины запроса,
 * артикул короче «настоящего» поискового запроса). Только активные
 * Товар и Вариант — недоступный для продажи Вариант нельзя добавить в
 * существующий Заказ так же, как и в новый (`createOrder()`).
 */
function searchVariantsForAdmin(string $q, int $limit): array
{
    $pdo = getPdo();

    $likeTerm = escapeLikeValue($q) . '%';

    if (mb_strlen($q) >= 3) {
        $fulltextTerm  = buildFulltextTerm($q);
        $nameCondition = $fulltextTerm !== ''
            ? 'MATCH(p.name, p.description) AGAINST (:fulltext_term IN BOOLEAN MODE)'
            : '0 = 1';
        $params = $fulltextTerm !== '' ? ['fulltext_term' => $fulltextTerm] : [];
    } else {
        $nameCondition = 'p.name LIKE :name_term';
        $params        = ['name_term' => $likeTerm];
    }

    $stmt = $pdo->prepare("
        SELECT pv.id, pv.sku, pv.price, pv.material, pv.mechanism_type,
               pv.is_showroom_sample, p.name AS product_name
        FROM product_variants pv
        INNER JOIN products p ON p.id = pv.product_id
        WHERE pv.is_active = 1 AND p.is_active = 1
          AND ({$nameCondition} OR pv.sku LIKE :sku_term)
        ORDER BY p.name ASC, pv.sku ASC
        LIMIT :limit
    ");
    foreach ($params as $key => $value) {
        $stmt->bindValue(":{$key}", $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':sku_term', $likeTerm, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $variants = $stmt->fetchAll();

    if ($variants === []) {
        return [];
    }

    $variantIds      = array_map('intval', array_column($variants, 'id'));
    $colorsByVariant = [];
    foreach (getVariantImages($variantIds) as $image) {
        if ($image['color'] !== null) {
            $colorsByVariant[(int) $image['product_variant_id']][$image['color']] = true;
        }
    }

    return array_map(static function (array $variant) use ($colorsByVariant): array {
        return $variant + ['colors' => array_keys($colorsByVariant[(int) $variant['id']] ?? [])];
    }, $variants);
}

/**
 * Точное совпадение по артикулу — резерв для `variant-picker.php`, когда
 * форма отправлена без JS и `variant_id` от подсказки не пришёл
 * (`dod-global.md`: форма должна работать и без JS).
 */
function findActiveVariantIdBySku(string $sku): ?int
{
    $stmt = getPdo()->prepare('
        SELECT pv.id
        FROM product_variants pv
        INNER JOIN products p ON p.id = pv.product_id
        WHERE pv.sku = :sku AND pv.is_active = 1 AND p.is_active = 1
        LIMIT 1
    ');
    $stmt->execute(['sku' => $sku]);
    $id = $stmt->fetchColumn();

    return $id !== false ? (int) $id : null;
}

/**
 * Условия `WHERE`/параметры для списка Товаров в Панели управления
 * (Таск 7 Фазы 4) — общие для `getAdminProducts()`/`countAdminProducts()`,
 * тот же принцип, что `buildAdminOrderFilterConditions()` в
 * `Order.php`: список и счётчик не должны разойтись по условиям.
 * Фильтр по категории — `EXISTS` на `product_categories` целиком, не
 * только на основную (`is_primary`) — Товар в двух категориях должен
 * быть виден в обеих, как и в каталоге витрины
 * (`buildCatalogFilterConditions()`).
 */
function buildAdminProductFilterConditions(array $filters): array
{
    $conditions = [];
    $params     = [];

    $categoryId = $filters['category_id'] ?? null;
    if ($categoryId !== null) {
        $conditions[]           = 'EXISTS (
            SELECT 1 FROM product_categories pcf
            WHERE pcf.product_id = p.id AND pcf.category_id = :category_id
        )';
        $params['category_id'] = $categoryId;
    }

    $status = $filters['status'] ?? null;
    if ($status === 'active') {
        $conditions[] = 'p.is_active = 1';
    } elseif ($status === 'hidden') {
        $conditions[] = 'p.is_active = 0';
    }

    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
        $likeTerm = escapeLikeValue($search) . '%';

        if (mb_strlen($search) >= 3) {
            $fulltextTerm  = buildFulltextTerm($search);
            $nameCondition = $fulltextTerm !== ''
                ? 'MATCH(p.name, p.description) AGAINST (:search_fulltext IN BOOLEAN MODE)'
                : '0 = 1';
            if ($fulltextTerm !== '') {
                $params['search_fulltext'] = $fulltextTerm;
            }
        } else {
            $nameCondition          = 'p.name LIKE :search_name';
            $params['search_name'] = $likeTerm;
        }

        $conditions[]           = "({$nameCondition} OR EXISTS (
            SELECT 1 FROM product_variants pvs
            WHERE pvs.product_id = p.id AND pvs.sku LIKE :search_sku
        ))";
        $params['search_sku'] = $likeTerm;
    }

    // «Только образцы» (Таск 4 Фазы 5, `FR-STOCK-001`) — Товары, у
    // которых хотя бы один Вариант отмечен Выставочным образцом.
    if (!empty($filters['only_samples'])) {
        $conditions[] = 'EXISTS (
            SELECT 1 FROM product_variants pvo
            WHERE pvo.product_id = p.id AND pvo.is_showroom_sample = 1
        )';
    }

    return [$conditions, $params];
}

function bindAdminProductFilterParams(PDOStatement $stmt, array $params): void
{
    foreach ($params as $key => $value) {
        $stmt->bindValue(":{$key}", $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
}

/**
 * Список Товаров для `/admin/products` (`FR-ADM-001`, `admin-
 * assembly.md`) — основная категория (`is_primary = 1`), количество
 * Вариантов и диапазон их цен одним `LEFT JOIN` на агрегат (без N+1),
 * флаг Выставочного образца — если он есть хотя бы у одного Варианта.
 * Фото — отдельным батч-запросом (`attachAdminProductPhoto()`), тем же
 * приёмом, что `attachCheapestVariant()` для витрины, но без фильтра
 * `is_active` — скрытый Товар в админке должен показывать своё фото,
 * не пустое место. Диапазон цен, наоборот, — только по активным
 * Вариантам (`phase-4.md`, Таск 7: «диапазон цен — из активных
 * Вариантов») — деактивированный Вариант не продаётся, его цена не
 * должна попадать в диапазон, который видит Менеджер; количество
 * Вариантов при этом считается по всем (активным и нет) — это
 * управленческая информация о структуре Товара, не о витрине.
 */
function getAdminProducts(array $filters, int $page, int $perPage): array
{
    $pdo = getPdo();

    [$conditions, $params] = buildAdminProductFilterConditions($filters);
    $whereSql = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $offset   = ($page - 1) * $perPage;

    $stmt = $pdo->prepare("
        SELECT
            p.id, p.name, p.slug, p.is_active, p.is_featured,
            c.name AS category_name,
            COALESCE(vc.variant_count, 0) AS variant_count,
            vc.min_price, vc.max_price,
            COALESCE(vc.has_sample, 0) AS has_showroom_sample
        FROM products p
        LEFT JOIN product_categories pc ON pc.product_id = p.id AND pc.is_primary = 1
        LEFT JOIN categories c ON c.id = pc.category_id
        LEFT JOIN (
            SELECT product_id, COUNT(*) AS variant_count,
                   MIN(CASE WHEN is_active = 1 THEN price END) AS min_price,
                   MAX(CASE WHEN is_active = 1 THEN price END) AS max_price,
                   MAX(is_showroom_sample) AS has_sample
            FROM product_variants
            GROUP BY product_id
        ) vc ON vc.product_id = p.id
        {$whereSql}
        ORDER BY p.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    bindAdminProductFilterParams($stmt, $params);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();

    if ($products === []) {
        return [];
    }

    $products = attachAdminProductPhoto($pdo, $products);

    return attachAdminProductVariants($pdo, $products);
}

function countAdminProducts(array $filters): int
{
    [$conditions, $params] = buildAdminProductFilterConditions($filters);
    $whereSql = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $stmt = getPdo()->prepare("SELECT COUNT(*) FROM products p {$whereSql}");
    bindAdminProductFilterParams($stmt, $params);
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

/**
 * Главное фото самого дешёвого Варианта — тот же батч-приём, что
 * `attachCheapestVariant()`, но намеренно без `pv.is_active = 1`: это
 * список для Менеджера/Администратора, скрытый Товар должен быть узнаваем
 * по фото, а не показывать пустую карточку.
 */
function attachAdminProductPhoto(PDO $pdo, array $products): array
{
    $productIds   = array_column($products, 'id');
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));

    $stmt = $pdo->prepare("
        SELECT ranked.product_id, img.path AS image_path
        FROM (
            SELECT pv.*, ROW_NUMBER() OVER (PARTITION BY pv.product_id ORDER BY pv.price ASC, pv.id ASC) AS rn
            FROM product_variants pv
            WHERE pv.product_id IN ({$placeholders})
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

    $imageByProduct = [];
    foreach ($stmt->fetchAll() as $row) {
        $imageByProduct[(int) $row['product_id']] = $row['image_path'];
    }

    return array_map(static function (array $product) use ($imageByProduct): array {
        $product['image_path'] = $imageByProduct[(int) $product['id']] ?? null;
        return $product;
    }, $products);
}

/**
 * Варианты Товаров текущей страницы списка — один батч-запрос по всем
 * `product_id` (не N+1), для построчных переключателей «Выставочный
 * образец» прямо в списке (Таск 4 Фазы 5, `FR-STOCK-001` правило 1).
 * `has_active_reserve` — есть ли на Вариант активный Резерв: снять
 * отметку образца при нём нельзя (`BR-003`), переключатель во View
 * рисуется задизейбленным.
 */
function attachAdminProductVariants(PDO $pdo, array $products): array
{
    $productIds   = array_column($products, 'id');
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));

    $stmt = $pdo->prepare("
        SELECT
            pv.id, pv.product_id, pv.sku, pv.material, pv.is_showroom_sample,
            EXISTS (
                SELECT 1 FROM reserves r
                WHERE r.product_variant_id = pv.id AND r.status = 'active'
            ) AS has_active_reserve
        FROM product_variants pv
        WHERE pv.product_id IN ({$placeholders})
        ORDER BY pv.id
    ");
    $stmt->execute($productIds);

    $variantsByProduct = [];
    foreach ($stmt->fetchAll() as $row) {
        $variantsByProduct[(int) $row['product_id']][] = $row;
    }

    return array_map(static function (array $product) use ($variantsByProduct): array {
        $product['variants'] = $variantsByProduct[(int) $product['id']] ?? [];
        return $product;
    }, $products);
}

/**
 * Для 404 в `AdminProductController::toggleShowroom()` — тот же паттерн,
 * что `findProductForToggle()`.
 */
function findVariantForShowroomToggle(int $variantId): ?array
{
    $stmt = getPdo()->prepare('SELECT id, product_id, is_showroom_sample FROM product_variants WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $variantId]);
    $variant = $stmt->fetch();

    return $variant !== false ? $variant : null;
}

/**
 * Переключатель «Выставочный образец» прямо в списке Товаров
 * (`FR-STOCK-001` правило 1). Включение разрешено всегда; выключение
 * отклоняется, если на Вариант есть активный Резерв (`BR-003`,
 * `stock.md`) — иначе оплаченный Резерв «потеряет» свой образец.
 * Существование Варианта проверяется отдельным `SELECT`, не
 * `rowCount()` после `UPDATE`: повторное включение уже включённого
 * Варианта — успех, а не ложное «не найден» (та же ловушка, что была
 * исправлена в `setReserveAgreedUntil()`, Таск 3 Фазы 5). Возвращает
 * `false` — Вариант не найден либо (при выключении) резерв активен.
 */
function setVariantShowroomSample(int $variantId, bool $on): bool
{
    $pdo = getPdo();

    $exists = $pdo->prepare('SELECT 1 FROM product_variants WHERE id = :id LIMIT 1');
    $exists->execute(['id' => $variantId]);
    if ($exists->fetchColumn() === false) {
        return false;
    }

    if (!$on) {
        $reserved = $pdo->prepare("
            SELECT 1 FROM reserves WHERE product_variant_id = :id AND status = 'active' LIMIT 1
        ");
        $reserved->execute(['id' => $variantId]);
        if ($reserved->fetchColumn() !== false) {
            return false;
        }
    }

    $stmt = $pdo->prepare('UPDATE product_variants SET is_showroom_sample = :on WHERE id = :id');
    $stmt->execute(['on' => $on ? 1 : 0, 'id' => $variantId]);

    return true;
}

/**
 * Текущее состояние для `AdminProductController::toggle()` — нужно
 * знать `is_active` до переключения (текст уведомления «скрыт»/«снова
 * виден») и отличить несуществующий Товар от существующего.
 */
function findProductForToggle(int $id): ?array
{
    $stmt = getPdo()->prepare('SELECT id, is_active FROM products WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $product = $stmt->fetch();

    return $product !== false ? $product : null;
}

/**
 * Товар не удаляется физически — только скрывается/показывается
 * (`database.md`, `ADR-004`); `order_items` уже оформленных Заказов
 * хранят собственный снэпшот и не ссылаются на `products` напрямую, так
 * что скрытие никак их не задевает.
 */
function setProductActive(int $id, bool $active): void
{
    $stmt = getPdo()->prepare('UPDATE products SET is_active = :active WHERE id = :id');
    $stmt->execute(['active' => $active ? 1 : 0, 'id' => $id]);
}

/**
 * `sku`, уже занятые Вариантами ДРУГИХ Товаров (`$excludeProductId` —
 * `0` при создании, id редактируемого Товара при правке — свои же
 * прежние артикулы не конфликт) — точечная ошибка поля в Controller'е,
 * до попытки записи (`AdminProductController::markConflictingSkus()`).
 * Возвращает найденные `sku` в нижнем регистре — сравнение с формой
 * регистронезависимое, как и сам `UNIQUE` на `utf8mb4_unicode_ci`.
 */
function findConflictingSkus(array $skus, int $excludeProductId): array
{
    if ($skus === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($skus), '?'));
    $stmt         = getPdo()->prepare("
        SELECT sku FROM product_variants
        WHERE sku IN ({$placeholders}) AND product_id != ?
    ");
    $stmt->execute([...array_values($skus), $excludeProductId]);

    return array_map('mb_strtolower', array_column($stmt->fetchAll(), 'sku'));
}

/**
 * Все Варианты Товара для формы редактирования (Таск 8 Фазы 4) —
 * в отличие от `getProductVariants()` (только `is_active = 1`, для
 * витрины), здесь и деактивированные тоже: Менеджер должен видеть и
 * снова включить ранее убранный Вариант, не только активные.
 */
function getAllProductVariants(int $productId): array
{
    $stmt = getPdo()->prepare('
        SELECT id, sku, material, mechanism_type, price, production_time,
               is_showroom_sample, discount_percent, is_active
        FROM product_variants
        WHERE product_id = :product_id
        ORDER BY id ASC
    ');
    $stmt->execute(['product_id' => $productId]);

    return $stmt->fetchAll();
}

function getProductCategoryIds(int $productId): array
{
    $stmt = getPdo()->prepare(
        'SELECT category_id, is_primary FROM product_categories WHERE product_id = :product_id'
    );
    $stmt->execute(['product_id' => $productId]);

    return $stmt->fetchAll();
}

/**
 * Товар для формы редактирования (`FR-ADM-001`, Таск 8 Фазы 4) — со
 * всеми Вариантами (не только активными), характеристиками и списком
 * категорий с выделенной основной. Форма (`admin/products/form.php`)
 * ожидает именно эту форму: `category_ids` — плоский список int,
 * `primary_category_id` — `0`, если основная почему-то не выставлена
 * (не должно происходить при обычной работе через эту же форму, но
 * `getProductCategoryIds()` не гарантирует это на уровне БД).
 */
function findProductForAdmin(int $id): ?array
{
    $stmt = getPdo()->prepare(
        'SELECT id, name, slug, description, is_active, is_featured FROM products WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $product = $stmt->fetch();

    if ($product === false) {
        return null;
    }

    $categoryRows = getProductCategoryIds($id);
    $primaryRow   = null;
    foreach ($categoryRows as $categoryRow) {
        if ((int) $categoryRow['is_primary'] === 1) {
            $primaryRow = $categoryRow;
            break;
        }
    }

    $product['variants'] = getAllProductVariants($id);
    foreach ($product['variants'] as &$variant) {
        $variant['images'] = getVariantImagesForAdmin((int) $variant['id']);
    }
    unset($variant);

    $product['specs']               = getProductSpecs($id);
    $product['category_ids']        = array_map(static fn (array $row): int => (int) $row['category_id'], $categoryRows);
    $product['primary_category_id'] = $primaryRow !== null ? (int) $primaryRow['category_id'] : 0;

    return $product;
}

/**
 * Пересобирает `product_categories` Товара — `DELETE` всех строк и
 * `INSERT` нового набора, тот же приём, что `product_specs`
 * (`syncProductSpecs()`) — набор категорий целиком заменяется формой
 * при каждом сохранении, не патчится построчно.
 */
function syncProductCategories(PDO $pdo, int $productId, array $categoryIds, int $primaryId): void
{
    $delete = $pdo->prepare('DELETE FROM product_categories WHERE product_id = :product_id');
    $delete->execute(['product_id' => $productId]);

    $insert = $pdo->prepare(
        'INSERT INTO product_categories (product_id, category_id, is_primary)
         VALUES (:product_id, :category_id, :is_primary)'
    );
    foreach ($categoryIds as $categoryId) {
        $insert->execute([
            'product_id'  => $productId,
            'category_id' => $categoryId,
            'is_primary'  => $categoryId === $primaryId ? 1 : 0,
        ]);
    }
}

function syncProductSpecs(PDO $pdo, int $productId, array $specs): void
{
    $delete = $pdo->prepare('DELETE FROM product_specs WHERE product_id = :product_id');
    $delete->execute(['product_id' => $productId]);

    $insert = $pdo->prepare(
        'INSERT INTO product_specs (product_id, name, value, sort_order)
         VALUES (:product_id, :name, :value, :sort_order)'
    );
    foreach (array_values($specs) as $index => $spec) {
        $insert->execute([
            'product_id' => $productId,
            'name'       => $spec['name'],
            'value'      => $spec['value'],
            'sort_order' => $index,
        ]);
    }
}

/**
 * `$variants` — Вариант с `id > 0`, принадлежащий этому Товару
 * (сверено с `$ownedIds`, прочитанным из БД до цикла, не с доверием к
 * тому, что прислала форма) → `UPDATE`; иначе (новый, `id = 0`, или
 * `id` чужого Товара — `dod-global.md`: чужие данные не трогаем) →
 * `INSERT` новой строки, подменённый чужой `id` просто игнорируется.
 * Существующий Вариант, отсутствующий среди `$variants` (снят с
 * формы), → `is_active = 0`, не `DELETE` — Варианты физически не
 * удаляются (`ADR-004`), на них могут ссылаться `order_items`.
 */
function syncProductVariants(PDO $pdo, int $productId, array $variants): void
{
    $ownedStmt = $pdo->prepare('SELECT id FROM product_variants WHERE product_id = :product_id');
    $ownedStmt->execute(['product_id' => $productId]);
    $ownedIds = array_map('intval', array_column($ownedStmt->fetchAll(), 'id'));

    $updateStmt = $pdo->prepare(
        'UPDATE product_variants
         SET sku = :sku, material = :material, mechanism_type = :mechanism_type,
             price = :price, production_time = :production_time,
             is_showroom_sample = :is_showroom_sample, discount_percent = :discount_percent,
             is_active = :is_active
         WHERE id = :id AND product_id = :product_id'
    );
    $insertStmt = $pdo->prepare(
        'INSERT INTO product_variants (
            product_id, sku, material, mechanism_type, price, production_time,
            is_showroom_sample, discount_percent, is_active
        ) VALUES (
            :product_id, :sku, :material, :mechanism_type, :price, :production_time,
            :is_showroom_sample, :discount_percent, :is_active
        )'
    );

    $submittedIds = [];

    foreach ($variants as $variant) {
        $params = [
            'sku'                => $variant['sku'],
            'material'           => $variant['material'],
            'mechanism_type'     => $variant['mechanism_type'] !== '' ? $variant['mechanism_type'] : null,
            'price'              => $variant['price'],
            'production_time'    => $variant['production_time'],
            'is_showroom_sample' => $variant['is_showroom_sample'] ? 1 : 0,
            'discount_percent'   => $variant['discount_percent'] !== '' ? $variant['discount_percent'] : null,
            'is_active'          => $variant['is_active'] ? 1 : 0,
        ];

        if ($variant['id'] > 0 && in_array($variant['id'], $ownedIds, true)) {
            $updateStmt->execute($params + ['id' => $variant['id'], 'product_id' => $productId]);
            $submittedIds[] = $variant['id'];
        } else {
            $insertStmt->execute($params + ['product_id' => $productId]);
            $submittedIds[] = (int) $pdo->lastInsertId();
        }
    }

    $missingIds = array_diff($ownedIds, $submittedIds);
    if ($missingIds !== []) {
        $placeholders   = implode(',', array_fill(0, count($missingIds), '?'));
        $deactivateStmt = $pdo->prepare("UPDATE product_variants SET is_active = 0 WHERE id IN ({$placeholders})");
        $deactivateStmt->execute(array_values($missingIds));
    }
}

/**
 * Дубликат `sku` (UNIQUE в БД, `SQLSTATE 23000`/MySQL 1062) — либо с
 * чужим Товаром (в форме уникальность внутри неё уже проверена
 * `validateProductInput()`), либо гонка параллельного сохранения —
 * откат всей транзакции и `null`, тот же паттерн, что `createUser()`/
 * `createCategory()`.
 */
function createProductWithVariants(array $product, array $variants, array $specs, array $categoryIds, int $primaryId): ?int
{
    $pdo = getPdo();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO products (name, slug, description, is_active, is_featured)
             VALUES (:name, :slug, :description, :is_active, :is_featured)'
        );
        $stmt->execute([
            'name'        => $product['name'],
            'slug'        => $product['slug'],
            'description' => $product['description'] !== '' ? $product['description'] : null,
            'is_active'   => $product['is_active'] ? 1 : 0,
            'is_featured' => $product['is_featured'] ? 1 : 0,
        ]);
        $productId = (int) $pdo->lastInsertId();

        syncProductCategories($pdo, $productId, $categoryIds, $primaryId);
        syncProductSpecs($pdo, $productId, $specs);
        syncProductVariants($pdo, $productId, $variants);

        $pdo->commit();
        return $productId;
    } catch (PDOException $e) {
        $pdo->rollBack();
        if (($e->errorInfo[1] ?? null) === 1062) {
            return null;
        }
        throw $e;
    }
}

function updateProductWithVariants(int $id, array $product, array $variants, array $specs, array $categoryIds, int $primaryId): bool
{
    $pdo = getPdo();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'UPDATE products
             SET name = :name, slug = :slug, description = :description,
                 is_active = :is_active, is_featured = :is_featured
             WHERE id = :id'
        );
        $stmt->execute([
            'name'        => $product['name'],
            'slug'        => $product['slug'],
            'description' => $product['description'] !== '' ? $product['description'] : null,
            'is_active'   => $product['is_active'] ? 1 : 0,
            'is_featured' => $product['is_featured'] ? 1 : 0,
            'id'          => $id,
        ]);

        syncProductCategories($pdo, $id, $categoryIds, $primaryId);
        syncProductSpecs($pdo, $id, $specs);
        syncProductVariants($pdo, $id, $variants);

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        if (($e->errorInfo[1] ?? null) === 1062) {
            return false;
        }
        throw $e;
    }
}

/**
 * Товар для карточки — сразу с его primary-категорией (`is_primary = 1`,
 * ровно одна на Товар — гарантировано сидами Таска 1 Фазы 1): и
 * хлебные крошки, и «Похожие товары» нужна именно она. Неактивный
 * Товар не находится вовсе — тот же результат, что и несуществующий
 * slug, контроллеру не нужно различать эти два случая для 404.
 */
function findProductBySlug(string $slug): ?array
{
    $stmt = getPdo()->prepare('
        SELECT
            p.id, p.name, p.slug, p.description,
            c.id AS category_id, c.parent_id AS category_parent_id,
            c.name AS category_name, c.slug AS category_slug
        FROM products p
        INNER JOIN product_categories pc ON pc.product_id = p.id AND pc.is_primary = 1
        INNER JOIN categories c ON c.id = pc.category_id
        WHERE p.slug = :slug AND p.is_active = 1
        LIMIT 1
    ');
    $stmt->execute(['slug' => $slug]);
    $product = $stmt->fetch();

    return $product !== false ? $product : null;
}

function getProductVariants(int $productId): array
{
    $stmt = getPdo()->prepare("
        SELECT
            pv.id, pv.sku, pv.material, pv.mechanism_type, pv.price,
            pv.production_time, pv.is_showroom_sample,
            EXISTS (
                SELECT 1 FROM reserves r
                WHERE r.product_variant_id = pv.id AND r.status = 'active'
            ) AS has_active_reserve
        FROM product_variants pv
        WHERE pv.product_id = :product_id AND pv.is_active = 1
        ORDER BY pv.price ASC, pv.id ASC
    ");
    $stmt->execute(['product_id' => $productId]);

    return $stmt->fetchAll();
}

/**
 * Плоский список фото по нескольким Вариантам сразу — группировка по
 * `product_variant_id` (для JSON селектора Варианта) остаётся на
 * Controller, здесь только сырые строки.
 */
function getVariantImages(array $variantIds): array
{
    if ($variantIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
    $stmt         = getPdo()->prepare("
        SELECT product_variant_id, color, is_swatch, path, sort_order, is_main
        FROM variant_images
        WHERE product_variant_id IN ({$placeholders})
        ORDER BY product_variant_id, sort_order, id
    ");
    $stmt->execute($variantIds);

    return $stmt->fetchAll();
}

/**
 * Фото одного Варианта для Панели управления (`FR-ADM-001`, Таск 9
 * Фазы 4) — с `id` каждой строки, в отличие от `getVariantImages()`
 * (только для витрины, группирует сразу несколько Вариантов и `id` ей
 * не нужен).
 */
function getVariantImagesForAdmin(int $variantId): array
{
    $stmt = getPdo()->prepare('
        SELECT id, color, is_swatch, path, sort_order, is_main
        FROM variant_images
        WHERE product_variant_id = :variant_id
        ORDER BY sort_order ASC, id ASC
    ');
    $stmt->execute(['variant_id' => $variantId]);

    return $stmt->fetchAll();
}

/**
 * `$productId`/`$variantId` — из URL, принадлежность проверяется прямо
 * здесь (не отдельным `find*`) — чужой Вариант или чужой Товар просто
 * не находится, `null` трактуется Controller'ом как отказ. Первое фото
 * Варианта становится главным автоматически — иначе Вариант остаётся
 * без главного фото до первого ручного переключения.
 */
function addVariantImage(int $productId, int $variantId, array $data): ?int
{
    $pdo = getPdo();

    $check = $pdo->prepare(
        'SELECT id FROM product_variants WHERE id = :variant_id AND product_id = :product_id LIMIT 1'
    );
    $check->execute(['variant_id' => $variantId, 'product_id' => $productId]);
    if ($check->fetch() === false) {
        return null;
    }

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM variant_images WHERE product_variant_id = :variant_id');
    $countStmt->execute(['variant_id' => $variantId]);
    $isFirstImage = (int) $countStmt->fetchColumn() === 0;

    $stmt = $pdo->prepare(
        'INSERT INTO variant_images (product_variant_id, color, is_swatch, path, sort_order, is_main)
         VALUES (:variant_id, :color, :is_swatch, :path, :sort_order, :is_main)'
    );
    $stmt->execute([
        'variant_id' => $variantId,
        'color'      => $data['color'],
        'is_swatch'  => $data['is_swatch'] ? 1 : 0,
        'path'       => $data['path'],
        'sort_order' => $data['sort_order'],
        'is_main'    => $isFirstImage ? 1 : 0,
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Цвет/образец/порядок — не `is_main` (отдельная `setMainVariantImage()`,
 * своя транзакция «снять со всех — поставить одной»).
 */
function updateVariantImage(int $productId, int $variantId, int $imageId, array $data): bool
{
    $stmt = getPdo()->prepare('
        UPDATE variant_images vi
        INNER JOIN product_variants pv ON pv.id = vi.product_variant_id
        SET vi.color = :color, vi.is_swatch = :is_swatch, vi.sort_order = :sort_order
        WHERE vi.id = :image_id AND vi.product_variant_id = :variant_id AND pv.product_id = :product_id
    ');
    $stmt->execute([
        'color'      => $data['color'],
        'is_swatch'  => $data['is_swatch'] ? 1 : 0,
        'sort_order' => $data['sort_order'],
        'image_id'   => $imageId,
        'variant_id' => $variantId,
        'product_id' => $productId,
    ]);

    return $stmt->rowCount() > 0;
}

/**
 * Возвращает путь удалённой строки — файл стирает
 * `deleteStoredFile()` (Controller), Model файловую систему не трогает.
 * Если удалённое фото было главным, а у Варианта остались другие —
 * следующее по `sort_order` становится главным в той же транзакции,
 * чтобы Вариант не остался без главного фото.
 */
function deleteVariantImage(int $productId, int $variantId, int $imageId): ?string
{
    $pdo = getPdo();

    $stmt = $pdo->prepare('
        SELECT vi.path, vi.is_main
        FROM variant_images vi
        INNER JOIN product_variants pv ON pv.id = vi.product_variant_id
        WHERE vi.id = :image_id AND vi.product_variant_id = :variant_id AND pv.product_id = :product_id
        LIMIT 1
    ');
    $stmt->execute(['image_id' => $imageId, 'variant_id' => $variantId, 'product_id' => $productId]);
    $image = $stmt->fetch();

    if ($image === false) {
        return null;
    }

    $pdo->beginTransaction();
    try {
        $delete = $pdo->prepare('DELETE FROM variant_images WHERE id = :image_id');
        $delete->execute(['image_id' => $imageId]);

        if ((int) $image['is_main'] === 1) {
            $next = $pdo->prepare('
                SELECT id FROM variant_images
                WHERE product_variant_id = :variant_id
                ORDER BY sort_order ASC, id ASC
                LIMIT 1
            ');
            $next->execute(['variant_id' => $variantId]);
            $nextId = $next->fetchColumn();

            if ($nextId !== false) {
                $setMain = $pdo->prepare('UPDATE variant_images SET is_main = 1 WHERE id = :id');
                $setMain->execute(['id' => $nextId]);
            }
        }

        $pdo->commit();
        return $image['path'];
    } catch (PDOException $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * В транзакции: снимает `is_main` со всех фото Варианта, затем
 * выставляет ровно указанному — «ровно одно главное фото» не
 * наблюдается нарушенным между двумя отдельными запросами.
 */
function setMainVariantImage(int $productId, int $variantId, int $imageId): bool
{
    $pdo = getPdo();

    $check = $pdo->prepare('
        SELECT vi.id
        FROM variant_images vi
        INNER JOIN product_variants pv ON pv.id = vi.product_variant_id
        WHERE vi.id = :image_id AND vi.product_variant_id = :variant_id AND pv.product_id = :product_id
        LIMIT 1
    ');
    $check->execute(['image_id' => $imageId, 'variant_id' => $variantId, 'product_id' => $productId]);
    if ($check->fetch() === false) {
        return false;
    }

    $pdo->beginTransaction();
    try {
        $reset = $pdo->prepare('UPDATE variant_images SET is_main = 0 WHERE product_variant_id = :variant_id');
        $reset->execute(['variant_id' => $variantId]);

        $setMain = $pdo->prepare('UPDATE variant_images SET is_main = 1 WHERE id = :image_id');
        $setMain->execute(['image_id' => $imageId]);

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function getProductSpecs(int $productId): array
{
    $stmt = getPdo()->prepare('
        SELECT name, value
        FROM product_specs
        WHERE product_id = :product_id
        ORDER BY sort_order ASC, id ASC
    ');
    $stmt->execute(['product_id' => $productId]);

    return $stmt->fetchAll();
}

/**
 * Тот же формат строки, что и `getCatalogProducts()` (через
 * `attachCheapestVariant()`) — «Похожие товары» рендерятся тем же
 * `product-card.php`, без второго шаблона мини-карточки.
 */
function getRelatedProducts(int $productId, int $categoryId, int $limit): array
{
    $pdo = getPdo();

    $stmt = $pdo->prepare('
        SELECT p.id, p.name, p.slug, MIN(pv.price) AS min_price
        FROM products p
        INNER JOIN product_variants pv ON pv.product_id = p.id AND pv.is_active = 1
        INNER JOIN product_categories pc ON pc.product_id = p.id AND pc.category_id = :category_id
        WHERE p.is_active = 1 AND p.id != :product_id
        GROUP BY p.id, p.name, p.slug, p.created_at
        ORDER BY p.created_at DESC
        LIMIT :limit
    ');
    $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
    $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();

    if ($products === []) {
        return [];
    }

    return attachCheapestVariant($pdo, $products);
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
