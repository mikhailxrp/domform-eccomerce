<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Order.php';
require_once ROOT_PATH . '/src/Core/Checkout.php';
require_once ROOT_PATH . '/src/Core/Pagination.php';

class AdminOrderController
{
    public function index(): void
    {
        requireRole(['manager', 'admin']);

        $status = (string) input('status', '');
        if (!array_key_exists($status, ORDER_STATUS_LABELS)) {
            $status = '';
        }

        $search = trim((string) input('search', ''));

        $page = (int) input('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $filters = [
            'status' => $status !== '' ? $status : null,
            'search' => $search !== '' ? $search : null,
        ];

        $total      = countAdminOrders($filters);
        $pagination = buildPagination($total, $page, ADMIN_ORDERS_PER_PAGE);
        $orders     = getAdminOrders($filters, $pagination['page'], ADMIN_ORDERS_PER_PAGE);

        $queryParams = array_filter(
            ['status' => $status, 'search' => $search],
            static fn (string $value): bool => $value !== ''
        );

        $paginationLinks = [];
        for ($i = 1; $i <= $pagination['total_pages']; $i++) {
            $paginationLinks[$i] = buildPaginationUrl('/admin/orders', $queryParams, $i);
        }

        render('admin/orders/index', [
            'title'           => 'Заказы',
            'orders'          => $orders,
            'statusFilter'    => $status,
            'searchQuery'     => $search,
            'pagination'      => $pagination,
            'paginationLinks' => $paginationLinks,
            'prevUrl'         => $pagination['has_prev'] ? buildPaginationUrl('/admin/orders', $queryParams, $pagination['prev_page']) : null,
            'nextUrl'         => $pagination['has_next'] ? buildPaginationUrl('/admin/orders', $queryParams, $pagination['next_page']) : null,
        ]);
    }

    public function show(string $id): void
    {
        requireRole(['manager', 'admin']);

        $order = findOrderForAdmin((int) $id);
        if ($order === null) {
            abort404();
        }

        render('admin/orders/show', [
            'title' => 'Заказ №' . $order['id'],
            'order' => $order,
            'items' => getOrderItems((int) $id),
        ]);
    }
}
