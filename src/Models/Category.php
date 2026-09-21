<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';

/**
 * Дерево категорий в 2 уровня — плоский список из БД собирается в
 * root => children здесь, а не рекурсивным SQL (business-правило «не
 * более 2 уровней вложенности», см. `database.md`, делает это простым).
 */
/**
 * Плоский список для `/admin/categories` (`FR-ADM-001`) — имя родителя
 * через самоджойн (2 уровня — `getCategoryTree()` рядом уже опирается
 * на то же ограничение), количество Товаров — `COUNT(DISTINCT
 * product_id)` через `product_categories`, а не `categories.id`
 * напрямую (M:N, Товар не должен считаться в другой категории дважды).
 */
function getCategoriesFlat(): array
{
    $stmt = getPdo()->query('
        SELECT
            c.id, c.parent_id, c.name, c.slug, c.description, c.sort_order,
            parent.name AS parent_name,
            (SELECT COUNT(DISTINCT pc.product_id) FROM product_categories pc WHERE pc.category_id = c.id) AS product_count
        FROM categories c
        LEFT JOIN categories parent ON parent.id = c.parent_id
        ORDER BY c.parent_id IS NOT NULL, c.sort_order, c.name
    ');

    return $stmt->fetchAll();
}

/**
 * Число видимых на витрине Товаров категории — блок «баннеры категорий»
 * Главной (`home.php`): тот же критерий «виден в каталоге», что и сам
 * листинг (`is_active` + хотя бы один активный Вариант), а не
 * `getCategoriesFlat()['product_count']` — та считает вообще все
 * Товары категории для админки, включая скрытые.
 */
function getCategoryProductCount(string $slug): int
{
    $stmt = getPdo()->prepare('
        SELECT COUNT(DISTINCT p.id)
        FROM products p
        INNER JOIN product_categories pc ON pc.product_id = p.id
        INNER JOIN categories c ON c.id = pc.category_id AND c.slug = :slug
        WHERE p.is_active = 1
          AND EXISTS (SELECT 1 FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1)
    ');
    $stmt->execute(['slug' => $slug]);

    return (int) $stmt->fetchColumn();
}

function findCategoryById(int $id): ?array
{
    $stmt = getPdo()->prepare(
        'SELECT id, parent_id, name, slug, description, sort_order
         FROM categories WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $category = $stmt->fetch();

    return $category !== false ? $category : null;
}

/**
 * Родительская Категория для `$parentId` обязана сама быть корневой
 * (`parent_id IS NULL`) — иначе получилась бы подкатегория подкатегории,
 * 3-й уровень вложенности (`database.md`: правило проверяется здесь, а
 * не CHECK-ограничением над самоссылкой). Несуществующий `$parentId`
 * тоже отклоняется — `false`, не «считаем корневой».
 */
function isValidCategoryParent(?int $parentId): bool
{
    if ($parentId === null) {
        return true;
    }

    $stmt = getPdo()->prepare('SELECT parent_id FROM categories WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $parentId]);
    $parent = $stmt->fetch();

    return $parent !== false && $parent['parent_id'] === null;
}

/**
 * `$data` — `name`, `slug`, `parent_id` (?int), `description` (?string),
 * `sort_order` (int) — уже нормализованные и проверенные на глубину
 * Controller'ом (`isValidCategoryParent()`). Дубликат `slug` (UNIQUE в
 * БД) — `null`, тот же паттерн проигрыша конкуренции, что
 * `createUser()` на дубликате email.
 */
function createCategory(array $data): ?int
{
    try {
        $stmt = getPdo()->prepare(
            'INSERT INTO categories (parent_id, name, slug, description, sort_order)
             VALUES (:parent_id, :name, :slug, :description, :sort_order)'
        );
        $stmt->execute([
            'parent_id'   => $data['parent_id'],
            'name'        => $data['name'],
            'slug'        => $data['slug'],
            'description' => $data['description'],
            'sort_order'  => $data['sort_order'],
        ]);

        return (int) getPdo()->lastInsertId();
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1062) {
            return null;
        }
        throw $e;
    }
}

function updateCategory(int $id, array $data): bool
{
    try {
        $stmt = getPdo()->prepare(
            'UPDATE categories
             SET parent_id = :parent_id, name = :name, slug = :slug,
                 description = :description, sort_order = :sort_order
             WHERE id = :id'
        );
        $stmt->execute([
            'parent_id'   => $data['parent_id'],
            'name'        => $data['name'],
            'slug'        => $data['slug'],
            'description' => $data['description'],
            'sort_order'  => $data['sort_order'],
            'id'          => $id,
        ]);

        return true;
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1062) {
            return false;
        }
        throw $e;
    }
}

/**
 * `false` — категория занята Товарами (`product_categories`, `ON
 * DELETE RESTRICT`, `database.md`) либо не существует. Перехват
 * SQLSTATE 23000 вместо предварительного `COUNT` — тот же принцип, что
 * `dod-global.md` требует для гонки при резервировании: не
 * «прочитать, потом удалить», а дать БД сказать «нет» атомарно.
 */
function deleteCategory(int $id): bool
{
    try {
        $stmt = getPdo()->prepare('DELETE FROM categories WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1451) {
            return false;
        }
        throw $e;
    }
}

function getCategoryTree(): array
{
    $stmt = getPdo()->query(
        'SELECT id, parent_id, name, slug, description, sort_order
         FROM categories
         ORDER BY parent_id IS NOT NULL, sort_order, name'
    );
    $categories = $stmt->fetchAll();

    $roots = [];
    foreach ($categories as $category) {
        if ($category['parent_id'] === null) {
            $category['children']            = [];
            $roots[(int) $category['id']] = $category;
        }
    }

    foreach ($categories as $category) {
        $parentId = $category['parent_id'];
        if ($parentId !== null && isset($roots[(int) $parentId])) {
            $roots[(int) $parentId]['children'][] = $category;
        }
    }

    return array_values($roots);
}

function findCategoryBySlug(string $slug): ?array
{
    $stmt = getPdo()->prepare(
        'SELECT id, parent_id, name, slug, description, sort_order
         FROM categories WHERE slug = :slug LIMIT 1'
    );
    $stmt->execute(['slug' => $slug]);
    $category = $stmt->fetch();

    return $category !== false ? $category : null;
}

/**
 * Путь от корня до категории для хлебных крошек. Максимум 2 звена —
 * та же глубина, что зафиксирована в `database.md`.
 */
function getCategoryPath(array $category): array
{
    $path = [$category];

    if ($category['parent_id'] !== null) {
        $stmt = getPdo()->prepare(
            'SELECT id, parent_id, name, slug, description, sort_order
             FROM categories WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $category['parent_id']]);
        $parent = $stmt->fetch();
        if ($parent !== false) {
            array_unshift($path, $parent);
        }
    }

    return $path;
}
