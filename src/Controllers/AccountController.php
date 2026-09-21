<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Customer.php';
require_once ROOT_PATH . '/src/Models/Order.php';
require_once ROOT_PATH . '/src/Core/Pagination.php';
require_once ROOT_PATH . '/src/Core/Warranty.php';

class AccountController
{
    /**
     * Обзор личного кабинета (`FR-ACC-*`, `.docs/phases/phase-7.md`,
     * Таск 1) — приветствие и сводка счётчиков. Число Заказов читается
     * через уже существующую `getCustomerOrders()` (Панель управления,
     * Фаза 4) — той же функцией, что видит Менеджер, только по своему
     * `user_id`; отдельная Model для кабинета не заводится ради одного
     * счётчика. Адреса и Избранное появятся в Тасках 4/6 этой фазы — до
     * них счётчики нулевые, своих Model у них ещё нет.
     */
    public function index(): void
    {
        requireAuth();

        $user = currentUser();

        render('account/index', [
            'title'          => 'Личный кабинет',
            'activeSection'  => 'dashboard',
            'userName'       => $user['name'],
            'ordersCount'    => count(getCustomerOrders('user', (string) $user['id'])),
            'addressesCount' => 0,
            'favoritesCount' => 0,
        ]);
    }

    /**
     * История заказов (`FR-ACC-001`, Таск 2) — только Заказы текущего
     * Покупателя, `getUserOrders()`/`countUserOrders()` фильтруют по
     * `user_id` внутри `WHERE`, не постфактум.
     */
    public function orders(): void
    {
        requireAuth();

        $user = currentUser();

        $page = (int) input('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $total      = countUserOrders($user['id']);
        $pagination = buildPagination($total, $page, ACCOUNT_ORDERS_PER_PAGE);
        $orders     = getUserOrders($user['id'], $pagination['page'], ACCOUNT_ORDERS_PER_PAGE);

        $paginationLinks = [];
        for ($i = 1; $i <= $pagination['total_pages']; $i++) {
            $paginationLinks[$i] = buildPaginationUrl('/account/orders', [], $i);
        }

        render('account/orders', [
            'title'           => 'Мои заказы',
            'activeSection'   => 'orders',
            'orders'          => $orders,
            'pagination'      => $pagination,
            'paginationLinks' => $paginationLinks,
            'prevUrl'         => $pagination['has_prev'] ? buildPaginationUrl('/account/orders', [], $pagination['prev_page']) : null,
            'nextUrl'         => $pagination['has_next'] ? buildPaginationUrl('/account/orders', [], $pagination['next_page']) : null,
        ]);
    }

    /**
     * Карточка заказа (`FR-ACC-001`) — `findOrderForUser()` возвращает
     * `null` и на чужой, и на несуществующий `id` одинаково (`dod-
     * global.md`: подмена чужого id не должна открывать чужой Заказ) —
     * оба случая закрываются общим `abort404()`, без различения кода
     * ответа между «нет такого Заказа» и «это не ваш Заказ».
     */
    public function orderShow(string $id): void
    {
        requireAuth();

        $user  = currentUser();
        $order = findOrderForUser((int) $id, $user['id']);
        if ($order === null) {
            abort404();
        }

        $items = array_map(static function (array $item): array {
            $item['line_total'] = bcmul((string) $item['price'], (string) $item['quantity'], 2);
            return $item;
        }, getOrderItems((int) $id));

        render('account/order-show', [
            'title'              => 'Заказ №' . $order['id'],
            'activeSection'      => 'orders',
            'order'              => $order,
            'items'              => $items,
            'fulfillmentLabel'   => FULFILLMENT_LABELS[$order['fulfillment_method']] ?? $order['fulfillment_method'],
            'paymentLabel'       => PAYMENT_METHOD_LABELS[$order['payment_method']] ?? $order['payment_method'],
            'paymentStatusLabel' => PAYMENT_STATUS_LABELS[$order['payment_status']] ?? $order['payment_status'],
            'warrantyUntil'      => warrantyExpiresAt($order['delivered_at']),
            'underWarranty'      => isUnderWarranty($order['delivered_at'], date('Y-m-d')),
        ]);
    }
}
