<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/CallbackRequest.php';
require_once ROOT_PATH . '/src/Core/Callback.php';
require_once ROOT_PATH . '/src/Core/Pagination.php';

class AdminCallbackController
{
    private const STATUS_OPTIONS = [CALLBACK_STATUS_NEW, CALLBACK_STATUS_PROCESSED];

    /**
     * Фильтр по умолчанию — `new` (то, что нужно обработать в первую
     * очередь), по образцу `AdminReviewController::index()`. Пустая
     * строка — явный выбор «Все статусы» (`?status=`), любое другое
     * значение откатывается к `new`, а не 500.
     */
    public function index(): void
    {
        requireRole(['manager', 'admin']);

        $status = (string) input('status', CALLBACK_STATUS_NEW);
        if ($status !== '' && !in_array($status, self::STATUS_OPTIONS, true)) {
            $status = CALLBACK_STATUS_NEW;
        }

        $page = (int) input('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $filters = ['status' => $status !== '' ? $status : null];

        $total      = countAdminCallbacks($filters);
        $pagination = buildPagination($total, $page, ADMIN_CALLBACKS_PER_PAGE);
        $callbacks  = getAdminCallbacks($filters, $pagination['page'], ADMIN_CALLBACKS_PER_PAGE);

        $queryParams = ['status' => $status];

        $paginationLinks = [];
        for ($i = 1; $i <= $pagination['total_pages']; $i++) {
            $paginationLinks[$i] = buildPaginationUrl('/admin/callbacks', $queryParams, $i);
        }

        render('admin/callbacks/index', [
            'title'           => 'Заявки на звонок',
            'callbacks'       => $callbacks,
            'statusFilter'    => $status,
            'pagination'      => $pagination,
            'paginationLinks' => $paginationLinks,
            'prevUrl'         => $pagination['has_prev'] ? buildPaginationUrl('/admin/callbacks', $queryParams, $pagination['prev_page']) : null,
            'nextUrl'         => $pagination['has_next'] ? buildPaginationUrl('/admin/callbacks', $queryParams, $pagination['next_page']) : null,
        ]);
    }

    /**
     * Идемпотентно: повторное «Обработано» на уже `processed` — тоже
     * успех (`markCallbackProcessed()` отличает это от несуществующего
     * `id`), по образцу `AdminReviewController::transition()`.
     */
    public function process(string $id): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        if (!markCallbackProcessed((int) $id)) {
            setFlash('error', 'Заявка не найдена.');
            redirect('/admin/callbacks');
        }

        setFlash('success', 'Заявка отмечена как обработанная.');
        redirect('/admin/callbacks');
    }
}
