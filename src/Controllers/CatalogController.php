<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Category.php';
require_once ROOT_PATH . '/src/Models/Product.php';
require_once ROOT_PATH . '/src/Core/Pagination.php';

class CatalogController
{
    private const ALLOWED_SORTS = ['newest', 'price_asc', 'price_desc'];

    public function index(): void
    {
        $this->renderCatalog(null);
    }

    public function category(string $slug): void
    {
        $category = findCategoryBySlug($slug);
        if ($category === null) {
            abort404();
        }

        $this->renderCatalog($category);
    }

    private function renderCatalog(?array $category): void
    {
        $sort = (string) input('sort', 'newest');
        if (!in_array($sort, self::ALLOWED_SORTS, true)) {
            $sort = 'newest';
        }

        $page = (int) input('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $path    = $category !== null ? '/catalog/' . $category['slug'] : '/catalog';
        $filters = $category !== null ? ['category_id' => (int) $category['id']] : [];

        $total      = countCatalogProducts($filters);
        $pagination = buildPagination($total, $page, CATALOG_PER_PAGE);
        $products   = getCatalogProducts($filters, $sort, $pagination['page'], CATALOG_PER_PAGE);

        $queryParams     = $sort !== 'newest' ? ['sort' => $sort] : [];
        $paginationLinks = [];
        for ($i = 1; $i <= $pagination['total_pages']; $i++) {
            $paginationLinks[$i] = buildPaginationUrl($path, $queryParams, $i);
        }

        render('catalog/index', [
            'title'           => $category['name'] ?? 'Каталог',
            'category'        => $category,
            'breadcrumbs'     => $category !== null ? getCategoryPath($category) : [],
            'products'        => $products,
            'sort'            => $sort,
            'path'            => $path,
            'pagination'      => $pagination,
            'paginationLinks' => $paginationLinks,
            'prevUrl'         => $pagination['has_prev'] ? buildPaginationUrl($path, $queryParams, $pagination['prev_page']) : null,
            'nextUrl'         => $pagination['has_next'] ? buildPaginationUrl($path, $queryParams, $pagination['next_page']) : null,
        ]);
    }
}
