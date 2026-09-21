<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Category.php';
require_once ROOT_PATH . '/src/Models/Product.php';
require_once ROOT_PATH . '/src/Models/Favorite.php';
require_once ROOT_PATH . '/src/Core/Pagination.php';
require_once ROOT_PATH . '/src/Core/CatalogFilters.php';

class CatalogController
{
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

    /**
     * Чекбоксы категории в сайдбаре показываются только на `/catalog`
     * (без фиксированной категории) — на `/catalog/{slug}` категория уже
     * задана путём, повторный виджет с тем же выбором был бы избыточен
     * и потребовал бы решать неоднозначность «фильтр не тронут» против
     * «пользователь снял единственную галочку» у чекбоксов в GET-форме.
     */
    private function renderCatalog(?array $category): void
    {
        $filters = normalizeCatalogFilters($_GET);

        $page = (int) input('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $path = $category !== null ? '/catalog/' . $category['slug'] : '/catalog';

        $modelFilters = $filters;
        $linkFilters  = $filters;
        if ($category !== null) {
            $modelFilters['category_ids'] = [(int) $category['id']];
            $linkFilters['category_ids']  = [];
        }

        $total      = countCatalogProducts($modelFilters);
        $pagination = buildPagination($total, $page, CATALOG_PER_PAGE);
        $products   = getCatalogProducts($modelFilters, $filters['sort'], $pagination['page'], CATALOG_PER_PAGE);

        $baseQuery = buildCatalogQueryString($linkFilters);
        parse_str($baseQuery, $queryParams);

        $paginationLinks = [];
        for ($i = 1; $i <= $pagination['total_pages']; $i++) {
            $paginationLinks[$i] = buildPaginationUrl($path, $queryParams, $i);
        }

        $user = currentUser();

        $viewData = [
            'category'        => $category,
            'products'        => $products,
            'filters'         => $filters,
            'path'            => $path,
            'resetUrl'        => $path,
            'pagination'      => $pagination,
            'paginationLinks' => $paginationLinks,
            'prevUrl'         => $pagination['has_prev'] ? buildPaginationUrl($path, $queryParams, $pagination['prev_page']) : null,
            'nextUrl'         => $pagination['has_next'] ? buildPaginationUrl($path, $queryParams, $pagination['next_page']) : null,
            'favoriteIds'     => $user !== null ? getFavoriteProductIds($user['id']) : [],
        ];

        if (isFetchRequest()) {
            render('components/catalog-grid', $viewData);
            return;
        }

        render('catalog/index', array_merge($viewData, [
            'title'          => $category['name'] ?? 'Каталог',
            'breadcrumbs'    => $category !== null ? getCategoryPath($category) : [],
            'categoryTree'   => getCategoryTree(),
            'filterOptions'  => getFilterOptions($category !== null ? (int) $category['id'] : null),
        ]));
    }
}
