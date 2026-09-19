<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Product.php';
require_once ROOT_PATH . '/src/Models/Category.php';
require_once ROOT_PATH . '/src/Core/Pagination.php';
require_once ROOT_PATH . '/src/Core/CatalogFilters.php';

class AdminProductController
{
    public function index(): void
    {
        requireRole(['manager', 'admin']);

        $categoryId = (int) input('category', 0);
        $status     = (string) input('status', '');
        if (!in_array($status, ['active', 'hidden'], true)) {
            $status = '';
        }
        $search = trim((string) input('search', ''));

        $page = (int) input('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $filters = [
            'category_id' => $categoryId > 0 ? $categoryId : null,
            'status'      => $status !== '' ? $status : null,
            'search'      => $search !== '' ? $search : null,
        ];

        $total      = countAdminProducts($filters);
        $pagination = buildPagination($total, $page, ADMIN_PRODUCTS_PER_PAGE);
        $products   = getAdminProducts($filters, $pagination['page'], ADMIN_PRODUCTS_PER_PAGE);

        $queryParams = array_filter(
            ['category' => $categoryId > 0 ? (string) $categoryId : '', 'status' => $status, 'search' => $search],
            static fn (string $value): bool => $value !== ''
        );

        $paginationLinks = [];
        for ($i = 1; $i <= $pagination['total_pages']; $i++) {
            $paginationLinks[$i] = buildPaginationUrl('/admin/products', $queryParams, $i);
        }

        render('admin/products/index', [
            'title'           => 'Товары',
            'products'        => $products,
            'categories'      => getCategoriesFlat(),
            'categoryFilter'  => $categoryId,
            'statusFilter'    => $status,
            'searchQuery'     => $search,
            'pagination'      => $pagination,
            'paginationLinks' => $paginationLinks,
            'prevUrl'         => $pagination['has_prev'] ? buildPaginationUrl('/admin/products', $queryParams, $pagination['prev_page']) : null,
            'nextUrl'         => $pagination['has_next'] ? buildPaginationUrl('/admin/products', $queryParams, $pagination['next_page']) : null,
        ]);
    }

    public function toggle(string $id): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        $product = findProductForToggle((int) $id);
        if ($product === null) {
            abort404();
        }

        setProductActive((int) $id, !(bool) $product['is_active']);

        setFlash('success', (bool) $product['is_active'] ? 'Товар скрыт.' : 'Товар снова виден на витрине.');
        redirect('/admin/products');
    }
}
