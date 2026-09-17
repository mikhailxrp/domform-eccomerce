<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';

/**
 * Дерево категорий в 2 уровня — плоский список из БД собирается в
 * root => children здесь, а не рекурсивным SQL (business-правило «не
 * более 2 уровней вложенности», см. `database.md`, делает это простым).
 */
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
