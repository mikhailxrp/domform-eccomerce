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

function getSpecSuggestionsForProduct(int $productId): array
{
    $stmt = getPdo()->prepare(
        'SELECT id, target, name, value, status FROM ai_spec_suggestions WHERE product_id = :product_id ORDER BY id ASC'
    );
    $stmt->execute(['product_id' => $productId]);

    return $stmt->fetchAll();
}

/**
 * Экран ревью (Таск 3) — Товар (переиспользует `findProductForSpecsRun()`,
 * не дублирует запрос), его предложения и Варианты (`getAllProductVariants()`
 * — уже существующая функция `Product.php`, нужна для выбора, к какому
 * Варианту применить `variant_material`/`variant_mechanism`).
 */
function findProductForSpecsReview(int $id): ?array
{
    $product = findProductForSpecsRun($id);
    if ($product === null) {
        return null;
    }

    $product['suggestions'] = getSpecSuggestionsForProduct($id);
    $product['variants']    = getAllProductVariants($id);

    return $product;
}

/**
 * Применяет уже проверенный набор
 * (`Core/AiSpecs.php::validateSpecReviewInput()`) и завершает ревью —
 * одна транзакция: характеристики Товара/Варианта либо применяются и
 * статус переходит в `confirmed` целиком, либо не применяется ничего.
 * `target='spec'` — dedup по `name` (`DELETE` + `INSERT`, тот же
 * приём, что `syncProductSpecs()`); `variant_material`/
 * `variant_mechanism` — `UPDATE` с `product_id` в `WHERE` (защита от
 * чужого `variant_id` на случай подделанного POST — `dod-global.md`,
 * хотя `validateSpecReviewInput()` уже отсеивает такие значения
 * раньше). Предложения Товара удаляются целиком после обработки —
 * ревью считается законченным, даже если что-то из них было
 * отклонено (не отмечено чекбоксом).
 */
function applySpecSuggestions(int $productId, array $accepted): void
{
    $pdo = getPdo();
    $pdo->beginTransaction();

    try {
        $maxSortOrderStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) FROM product_specs WHERE product_id = :product_id');
        $maxSortOrderStmt->execute(['product_id' => $productId]);
        $nextSortOrder = ((int) $maxSortOrderStmt->fetchColumn()) + 1;

        $deleteSpecByName = $pdo->prepare('DELETE FROM product_specs WHERE product_id = :product_id AND name = :name');
        $insertSpec       = $pdo->prepare(
            'INSERT INTO product_specs (product_id, name, value, sort_order) VALUES (:product_id, :name, :value, :sort_order)'
        );
        $updateMaterial  = $pdo->prepare('UPDATE product_variants SET material = :value WHERE id = :variant_id AND product_id = :product_id');
        $updateMechanism = $pdo->prepare('UPDATE product_variants SET mechanism_type = :value WHERE id = :variant_id AND product_id = :product_id');

        foreach ($accepted as $item) {
            if ($item['target'] === 'spec') {
                $deleteSpecByName->execute(['product_id' => $productId, 'name' => $item['name']]);
                $insertSpec->execute([
                    'product_id' => $productId,
                    'name'       => $item['name'],
                    'value'      => $item['value'],
                    'sort_order' => $nextSortOrder,
                ]);
                $nextSortOrder++;
                continue;
            }

            $stmt = $item['target'] === 'variant_material' ? $updateMaterial : $updateMechanism;
            $stmt->execute([
                'value'      => $item['value'],
                'variant_id' => $item['variant_id'],
                'product_id' => $productId,
            ]);
        }

        $pdo->prepare('DELETE FROM ai_spec_suggestions WHERE product_id = :product_id')
            ->execute(['product_id' => $productId]);

        $pdo->prepare("UPDATE products SET specs_status = 'confirmed' WHERE id = :product_id")
            ->execute(['product_id' => $productId]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * «Подтвердить вручную» (Таск 3) — Товар допускается в подбор
 * (`FR-AI-004`) без единого предложения ИИ. Очищает предложения только
 * при переходе в `confirmed` — уборка очереди имеет смысл именно как
 * «ревью закончено», не при других статусах.
 */
function setProductSpecsStatus(int $productId, string $status): void
{
    $pdo = getPdo();
    $pdo->beginTransaction();

    try {
        $pdo->prepare('UPDATE products SET specs_status = :status WHERE id = :product_id')
            ->execute(['status' => $status, 'product_id' => $productId]);

        if ($status === 'confirmed') {
            $pdo->prepare('DELETE FROM ai_spec_suggestions WHERE product_id = :product_id')
                ->execute(['product_id' => $productId]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
