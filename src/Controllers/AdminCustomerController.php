<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Customer.php';
require_once ROOT_PATH . '/src/Core/Pagination.php';
require_once ROOT_PATH . '/src/Core/OrderStatus.php';

class AdminCustomerController
{
    public function index(): void
    {
        requireRole(['manager', 'admin']);

        $search = trim((string) input('search', ''));

        $page = (int) input('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $total      = countCustomers($search);
        $pagination = buildPagination($total, $page, ADMIN_CUSTOMERS_PER_PAGE);
        $customers  = getCustomers($search, $pagination['page'], ADMIN_CUSTOMERS_PER_PAGE);

        $queryParams = array_filter(['search' => $search], static fn (string $value): bool => $value !== '');

        $paginationLinks = [];
        for ($i = 1; $i <= $pagination['total_pages']; $i++) {
            $paginationLinks[$i] = buildPaginationUrl('/admin/customers', $queryParams, $i);
        }

        render('admin/customers/index', [
            'title'           => 'Клиенты',
            'customers'       => $customers,
            'searchQuery'     => $search,
            'pagination'      => $pagination,
            'paginationLinks' => $paginationLinks,
            'prevUrl'         => $pagination['has_prev'] ? buildPaginationUrl('/admin/customers', $queryParams, $pagination['prev_page']) : null,
            'nextUrl'         => $pagination['has_next'] ? buildPaginationUrl('/admin/customers', $queryParams, $pagination['next_page']) : null,
        ]);
    }

    /**
     * `$key` для Гостя — телефон в сегменте пути, не query-параметр:
     * `requestPath()` (`Core/Request.php`) не делает `urldecode()`, так
     * что `+` из ссылки (`rawurlencode()` в `customers/index.php`)
     * доходит сюда percent-encoded (`%2B…`) — обязательно
     * `rawurldecode()` перед сравнением с БД.
     */
    public function show(string $type, string $key): void
    {
        requireRole(['manager', 'admin']);

        $key = rawurldecode($key);

        $customer = findCustomer($type, $key);
        if ($customer === null) {
            abort404();
        }

        render('admin/customers/show', [
            'title'    => $customer['name'] !== null && $customer['name'] !== '' ? $customer['name'] : 'Клиент',
            'customer' => $customer,
            'orders'   => getCustomerOrders($type, $customer['key']),
        ]);
    }
}
