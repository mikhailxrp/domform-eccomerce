<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Product.php';
require_once ROOT_PATH . '/src/Core/Pagination.php';
require_once ROOT_PATH . '/src/Core/CatalogFilters.php';

class SearchController
{
    public function index(): void
    {
        $q    = normalizeSearchQuery((string) input('q', ''));
        $sort = normalizeCatalogSort(input('sort', null));

        $page = (int) input('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $total      = $q !== '' ? countSearchProducts($q) : 0;
        $pagination = buildPagination($total, $page, CATALOG_PER_PAGE);
        $products   = $q !== '' ? searchProducts($q, $sort, $pagination['page'], CATALOG_PER_PAGE) : [];

        $path        = '/search';
        $queryParams = ['q' => $q];
        if ($sort !== 'newest') {
            $queryParams['sort'] = $sort;
        }

        $paginationLinks = [];
        for ($i = 1; $i <= $pagination['total_pages']; $i++) {
            $paginationLinks[$i] = buildPaginationUrl($path, $queryParams, $i);
        }

        render('search/index', [
            'q'               => $q,
            'products'        => $products,
            'filters'         => ['sort' => $sort],
            'resetUrl'        => '/catalog',
            'pagination'      => $pagination,
            'paginationLinks' => $paginationLinks,
            'prevUrl'         => $pagination['has_prev'] ? buildPaginationUrl($path, $queryParams, $pagination['prev_page']) : null,
            'nextUrl'         => $pagination['has_next'] ? buildPaginationUrl($path, $queryParams, $pagination['next_page']) : null,
        ]);
    }

    /**
     * Подсказки под полем поиска в шапке (`FR-SRCH-001`) — JSON,
     * ограничено по частоте: дебаунс на клиенте не защищает от
     * скриптовой накрутки запросов.
     */
    public function suggest(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (tooManyAttempts('suggest', 20, 10)) {
            http_response_code(429);
            echo json_encode(['items' => []], JSON_UNESCAPED_UNICODE);
            return;
        }
        hitRateLimit('suggest');

        $q = normalizeSearchQuery((string) input('q', ''));

        $products = mb_strlen($q) >= SEARCH_MIN_QUERY_LENGTH
            ? suggestProducts($q, SEARCH_SUGGEST_LIMIT)
            : [];

        $items = array_map(static function (array $product): array {
            $placeholderNumber = str_pad((string) ((($product['id'] - 1) % 13) + 1), 2, '0', STR_PAD_LEFT);

            return [
                'name'  => $product['name'],
                'url'   => '/product/' . $product['slug'],
                'image' => '/assets/images/product/product-' . $placeholderNumber . '.jpg',
            ];
        }, $products);

        echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
    }
}
