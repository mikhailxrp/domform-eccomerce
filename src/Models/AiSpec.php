<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';
require_once ROOT_PATH . '/src/Models/Product.php';

/**
 * Очередь разбора характеристик (`ai_spec_suggestions`,
 * `products.specs_status`, `ADR-049`, Таск 2 Фазы 9). Использует
 * `buildCatalogInClause()`/`bindCatalogFilterParams()` из
 * `Models/Product.php` — тот же приём построения `IN (...)`, что и в
 * каталоге, не дублируется здесь.
 */

/**
 * `$status` — `'pending'`/`'confirmed'` или `''` (все статусы, тот же
 * приём, что `AdminReviewController::index()`). `suggestions_count` —
 * число несведённых предложений, видно в очереди до перехода на
 * ревью (Таск 3).
 */
function getProductsForSpecsQueue(string $status, int $page, int $perPage): array
{
    $whereSql = $status !== '' ? 'WHERE p.specs_status = :status' : '';
    $offset   = ($page - 1) * $perPage;

    $stmt = getPdo()->prepare("
        SELECT p.id, p.name, p.slug, p.specs_status,
               COUNT(s.id) AS suggestions_count
        FROM products p
        LEFT JOIN ai_spec_suggestions s ON s.product_id = p.id
        {$whereSql}
        GROUP BY p.id, p.name, p.slug, p.specs_status
        ORDER BY p.created_at DESC
        LIMIT :limit OFFSET :offset
    ");

    if ($status !== '') {
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function countProductsForSpecsQueue(string $status): int
{
    $whereSql = $status !== '' ? 'WHERE specs_status = :status' : '';

    $stmt = getPdo()->prepare("SELECT COUNT(*) FROM products {$whereSql}");
    if ($status !== '') {
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
    }
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

/**
 * Минимальная выборка для пакетного разбора (`AdminAiSpecController::run()`)
 * — не переиспользует `findProductForAdmin()` (не входит в scope этого
 * таска, ей не нужны Варианты/фото/полный набор `specs`): только то,
 * что нужно промпту (`name`, `description`) и проверке допуска
 * (`specs_status`), плюс `category_ids` для `getKnownSpecValues()`
 * (`getProductCategoryIds()` — уже существующая функция `Product.php`).
 */
function findProductForSpecsRun(int $id): ?array
{
    $stmt = getPdo()->prepare(
        'SELECT id, name, slug, description, specs_status FROM products WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $product = $stmt->fetch();

    if ($product === false) {
        return null;
    }

    $product['category_ids'] = array_map(
        static fn (array $row): int => (int) $row['category_id'],
        getProductCategoryIds($id)
    );

    return $product;
}

/**
 * Значения, уже встречавшиеся в каталоге в пределах тех же Категорий
 * (`$categoryIds` — `product_categories.category_id` Товара, который
 * сейчас разбирается) — источник для «требует решения»
 * (`FR-AI-001` правило 3, `Core/AiSpecs.php::normalizeSpecSuggestions()`)
 * и подсказки модели (`buildKnownValuesHint()`). Пустой `$categoryIds`
 * (Товар без Категории — не должно случаться штатно) → пустые списки,
 * не ошибка `IN ()`.
 */
function getKnownSpecValues(array $categoryIds): array
{
    if ($categoryIds === []) {
        return ['material' => [], 'mechanism' => [], 'color' => []];
    }

    $pdo = getPdo();
    [$placeholders, $params] = buildCatalogInClause('cat', $categoryIds);

    $materialStmt = $pdo->prepare("
        SELECT DISTINCT pv.material
        FROM product_variants pv
        JOIN product_categories pc ON pc.product_id = pv.product_id
        WHERE pc.category_id IN ({$placeholders}) AND pv.material <> ''
    ");
    bindCatalogFilterParams($materialStmt, $params);
    $materialStmt->execute();

    $mechanismStmt = $pdo->prepare("
        SELECT DISTINCT pv.mechanism_type
        FROM product_variants pv
        JOIN product_categories pc ON pc.product_id = pv.product_id
        WHERE pc.category_id IN ({$placeholders}) AND pv.mechanism_type IS NOT NULL AND pv.mechanism_type <> ''
    ");
    bindCatalogFilterParams($mechanismStmt, $params);
    $mechanismStmt->execute();

    $colorStmt = $pdo->prepare("
        SELECT DISTINCT vi.color
        FROM variant_images vi
        JOIN product_variants pv ON pv.id = vi.product_variant_id
        JOIN product_categories pc ON pc.product_id = pv.product_id
        WHERE pc.category_id IN ({$placeholders}) AND vi.color IS NOT NULL AND vi.color <> ''
    ");
    bindCatalogFilterParams($colorStmt, $params);
    $colorStmt->execute();

    return [
        'material'  => $materialStmt->fetchAll(PDO::FETCH_COLUMN),
        'mechanism' => $mechanismStmt->fetchAll(PDO::FETCH_COLUMN),
        'color'     => $colorStmt->fetchAll(PDO::FETCH_COLUMN),
    ];
}

/**
 * Полностью заменяет предложения Товара — новый разбор не дополняет
 * прежний набор, а замещает его целиком (тот же приём, что
 * `syncProductSpecs()`): повторный запуск ИИ не должен копить дубли
 * старых предложений, которые Администратор ещё не рассмотрел.
 */
function replaceSpecSuggestions(int $productId, array $rows): void
{
    $pdo = getPdo();
    $pdo->beginTransaction();

    try {
        $delete = $pdo->prepare('DELETE FROM ai_spec_suggestions WHERE product_id = :product_id');
        $delete->execute(['product_id' => $productId]);

        if ($rows !== []) {
            $insert = $pdo->prepare(
                'INSERT INTO ai_spec_suggestions (product_id, target, name, value, status)
                 VALUES (:product_id, :target, :name, :value, :status)'
            );
            foreach ($rows as $row) {
                $insert->execute([
                    'product_id' => $productId,
                    'target'     => $row['target'],
                    'name'       => $row['name'],
                    'value'      => $row['value'],
                    'status'     => $row['status'],
                ]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
