<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/ReturnRequest.php';
require_once ROOT_PATH . '/src/Models/Order.php';
require_once ROOT_PATH . '/src/Core/Pagination.php';
require_once ROOT_PATH . '/src/Core/Warranty.php';

class AdminReturnController
{
    public function index(): void
    {
        requireRole(['manager', 'admin']);

        $page = (int) input('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $filters = [];

        $total      = countAdminReturns($filters);
        $pagination = buildPagination($total, $page, ADMIN_RETURNS_PER_PAGE);
        $returns    = getAdminReturns($filters, $pagination['page'], ADMIN_RETURNS_PER_PAGE);

        $paginationLinks = [];
        for ($i = 1; $i <= $pagination['total_pages']; $i++) {
            $paginationLinks[$i] = buildPaginationUrl('/admin/returns', [], $i);
        }

        render('admin/returns/index', [
            'title'           => 'Возвраты',
            'returns'         => $returns,
            'pagination'      => $pagination,
            'paginationLinks' => $paginationLinks,
            'prevUrl'         => $pagination['has_prev'] ? buildPaginationUrl('/admin/returns', [], $pagination['prev_page']) : null,
            'nextUrl'         => $pagination['has_next'] ? buildPaginationUrl('/admin/returns', [], $pagination['next_page']) : null,
        ]);
    }

    /**
     * Фиксация Возврата на карточке Заказа (`FR-RET-001`) — только для
     * Заказа в `delivered`; `createReturn()` сама отклоняет любой
     * другой статус атомарно, здесь только 404 на несуществующий Заказ.
     */
    public function store(string $id): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        if (findOrderById((int) $id) === null) {
            abort404();
        }

        $note = trim((string) input('note', ''));

        if (!createReturn((int) $id, $note)) {
            setFlash('error', 'Возврат можно оформить только для Заказа в статусе «Доставлен/Собран».');
            redirect('/admin/orders/' . $id);
        }

        setFlash('success', 'Возврат зафиксирован.');
        redirect('/admin/orders/' . $id);
    }
}
