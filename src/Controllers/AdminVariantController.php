<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Product.php';
require_once ROOT_PATH . '/src/Core/CatalogFilters.php';

class AdminVariantController
{
    /**
     * Подсказки для `variant-picker.php` (Таск 4 Фазы 4) — JSON, тот же
     * порог длины запроса, что `SearchController::suggest()`.
     */
    public function search(): void
    {
        requireRole(['manager', 'admin']);

        header('Content-Type: application/json; charset=utf-8');

        $q = trim((string) input('q', ''));

        $variants = mb_strlen($q) >= SEARCH_MIN_QUERY_LENGTH
            ? searchVariantsForAdmin($q, ADMIN_VARIANT_SEARCH_LIMIT)
            : [];

        $items = array_map(static fn (array $variant): array => [
            'id'     => (int) $variant['id'],
            'sku'    => $variant['sku'],
            'name'   => $variant['product_name'],
            'price'  => $variant['price'],
            'colors' => $variant['colors'],
        ], $variants);

        echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
    }
}
