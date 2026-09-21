<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Customer.php';

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
}
