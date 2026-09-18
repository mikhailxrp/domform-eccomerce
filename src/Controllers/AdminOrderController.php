<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Order.php';
require_once ROOT_PATH . '/src/Core/Checkout.php';
require_once ROOT_PATH . '/src/Core/Pagination.php';
require_once ROOT_PATH . '/src/Core/Payment.php';
require_once ROOT_PATH . '/src/Core/OrderActions.php';

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

        $hasShowroomSample = orderHasShowroomSample((int) $id);

        render('admin/orders/show', [
            'title'              => 'Заказ №' . $order['id'],
            'order'              => $order,
            'items'              => getOrderItems((int) $id),
            'allowedTransitions' => allowedOrderTransitions($order['status'], $hasShowroomSample, $order['fulfillment_method']),
            'canCancel'          => canCancelOrder($order['status']),
        ]);
    }

    public function transition(string $id): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        $to = (string) input('to', '');
        if (!array_key_exists($to, ORDER_STATUS_LABELS) || !transitionOrderStatus((int) $id, $to)) {
            setFlash('error', 'Такой переход недоступен для текущего статуса Заказа.');
            redirect('/admin/orders/' . $id);
        }

        setFlash('success', 'Статус Заказа обновлён.');
        redirect('/admin/orders/' . $id);
    }

    public function markPrepaid(string $id): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        $order = findOrderById((int) $id);
        if ($order === null) {
            abort404();
        }

        $amount = (string) input('amount', '');
        $error  = validatePaymentAmount($amount, $order['total'], '0.00');

        if ($error !== null) {
            setFlash('error', $error);
            redirect('/admin/orders/' . $id);
        }

        if (!markOrderPrepaid((int) $id, $amount)) {
            setFlash('error', 'Предоплата уже отмечена.');
            redirect('/admin/orders/' . $id);
        }

        setFlash('success', 'Предоплата отмечена.');
        redirect('/admin/orders/' . $id);
    }

    public function markPaidFull(string $id): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        $order = findOrderById((int) $id);
        if ($order === null) {
            abort404();
        }

        $remaining = bcsub($order['total'], $order['prepaid_amount'] ?? '0.00', 2);

        if (!markOrderPaidFull((int) $id, $remaining)) {
            setFlash('error', 'Остаток уже отмечен либо предоплата ещё не внесена.');
            redirect('/admin/orders/' . $id);
        }

        setFlash('success', 'Остаток отмечен как полученный.');
        redirect('/admin/orders/' . $id);
    }

    public function setShipping(string $id): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        $order = findOrderById((int) $id);
        if ($order === null) {
            abort404();
        }

        $raw = trim((string) input('shipping_cost', ''));

        if ($raw === '') {
            setOrderShippingCost((int) $id, null);
            setFlash('success', 'Стоимость доставки сброшена.');
            redirect('/admin/orders/' . $id);
        }

        $error = validateShippingCost($raw);
        if ($error !== null) {
            setFlash('error', $error);
            redirect('/admin/orders/' . $id);
        }

        setOrderShippingCost((int) $id, $raw);
        setFlash('success', 'Стоимость доставки сохранена.');
        redirect('/admin/orders/' . $id);
    }

    public function cancel(string $id): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        $order = findOrderById((int) $id);
        if ($order === null) {
            abort404();
        }

        if (!canCancelOrder($order['status'])) {
            setFlash('error', 'Отмена недоступна для текущего статуса Заказа.');
            redirect('/admin/orders/' . $id);
        }

        $input = [
            'branch'           => (string) input('branch', ''),
            'note'             => trim((string) input('note', '')),
            'refund_confirmed' => (string) input('refund_confirmed', '') === '1',
        ];

        $errors = validateCancelInput($input, $order['payment_status']);

        if ($errors['branch']) {
            setFlash('error', 'Выберите ветку отмены.');
            redirect('/admin/orders/' . $id);
        }
        if ($errors['note']) {
            setFlash('error', 'Укажите причину для нестандартного размера.');
            redirect('/admin/orders/' . $id);
        }
        if ($errors['refund_confirmed']) {
            setFlash('error', 'Отметьте возврат предоплаты.');
            redirect('/admin/orders/' . $id);
        }

        cancelOrder((int) $id, $input['note'], $input['refund_confirmed']);

        setFlash('success', 'Заказ отменён.');
        redirect('/admin/orders/' . $id);
    }
}
