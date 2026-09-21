<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Customer.php';
require_once ROOT_PATH . '/src/Models/Order.php';
require_once ROOT_PATH . '/src/Models/User.php';
require_once ROOT_PATH . '/src/Models/RememberToken.php';
require_once ROOT_PATH . '/src/Core/Pagination.php';
require_once ROOT_PATH . '/src/Core/Warranty.php';
require_once ROOT_PATH . '/src/Core/Account.php';
require_once ROOT_PATH . '/src/Models/Address.php';
require_once ROOT_PATH . '/src/Core/Address.php';

class AccountController
{
    /**
     * Общая ошибка для двух разных причин отказа в `updateDetails()`
     * (неверный текущий пароль / email занят другим аккаунтом) — тот
     * же приём, что `AuthController::AUTH_ERROR`: сообщение не
     * раскрывает, какая именно причина сработала.
     */
    private const PROFILE_ERROR = 'Не удалось сохранить изменения. Проверьте текущий пароль и email.';

    private const PASSWORD_ERROR = 'Неверный текущий пароль.';

    /**
     * Обзор личного кабинета (`FR-ACC-*`, `.docs/phases/phase-7.md`,
     * Таск 1) — приветствие и сводка счётчиков. Число Заказов читается
     * через уже существующую `getCustomerOrders()` (Панель управления,
     * Фаза 4) — той же функцией, что видит Менеджер, только по своему
     * `user_id`; отдельная Model для кабинета не заводится ради одного
     * счётчика. Адреса — `countUserAddresses()` (Таск 4). Избранное
     * появится в Таске 6 этой фазы — до него счётчик нулевой, своей
     * Model ещё нет.
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
            'addressesCount' => countUserAddresses($user['id']),
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

    /**
     * Личные данные и смена пароля (`FR-ACC-004`, Таск 3). Используется
     * и для GET-показа, и для повторного рендера с `$old`/`$errors`
     * после ошибки валидации в `updateDetails()`/`updatePassword()` —
     * тот же приём, что `ReviewController::store()` → `ProductController
     * ::show()` в Фазе 6, только внутри одного контроллера.
     * `$user['password_hash']` из `findUserById()` в View не уходит —
     * передаётся отдельными полями, без самого хэша.
     */
    public function details(array $old = [], array $errors = [], array $passwordErrors = []): void
    {
        requireAuth();

        $user = findUserById(currentUser()['id']);

        render('account/details', [
            'title'          => 'Личные данные',
            'activeSection'  => 'details',
            'name'           => $user['name'],
            'email'          => $user['email'],
            'phone'          => $user['phone'],
            'old'            => $old,
            'errors'         => $errors,
            'passwordErrors' => $passwordErrors,
        ]);
    }

    /**
     * Смена email требует текущий пароль (`FR-ACC-004` правило 2) —
     * `password_verify()` и занятость email другим аккаунтом
     * (`isEmailTakenByOther()`) требуют БД, поэтому проверяются здесь,
     * не в чистой `validateProfileInput()`. Обе причины отказа
     * подсвечивают **оба** поля (`current_password` и `email`) общим
     * `self::PROFILE_ERROR` — тем же приёмом, что `AuthController::
     * login()` подсвечивает оба поля на неверном email/пароле, чтобы
     * не раскрывать, какое из двух условий не выполнено.
     */
    public function updateDetails(): void
    {
        requireCsrf();

        $user = findUserById(currentUser()['id']);

        $input = [
            'name'             => (string) input('name'),
            'email'            => (string) input('email'),
            'current_password' => (string) input('current_password'),
        ];

        $errors = validateProfileInput($input, $user['email']);

        $name         = trim($input['name']);
        $email        = mb_strtolower(trim($input['email']), 'UTF-8');
        $emailChanged = $email !== mb_strtolower($user['email'], 'UTF-8');

        if (!in_array(true, $errors, true) && $emailChanged) {
            $passwordOk = password_verify($input['current_password'], $user['password_hash']);
            $emailTaken = isEmailTakenByOther($email, (int) $user['id']);

            if (!$passwordOk || $emailTaken) {
                $errors['current_password'] = true;
                $errors['email']            = true;
                // Отдельный флаг для View — подменяет специфичный текст
                // («Введите корректный email», «Укажите текущий пароль»)
                // общей формулировкой: оба поля отказали по одной из
                // двух business-rule причин, а не по формату/пустоте, и
                // текст под полем не должен намекать, какая именно.
                $errors['account_error'] = true;
                setFlash('error', self::PROFILE_ERROR);
            }
        }

        if (in_array(true, $errors, true)) {
            $this->details(['name' => $name, 'email' => $email], $errors, []);
            return;
        }

        updateUserProfile((int) $user['id'], $name, $email);
        $_SESSION['user_name'] = $name;

        setFlash('success', 'Личные данные обновлены.');
        redirect('/account/details');
    }

    /**
     * Смена пароля — инвалидирует все remember-токены (выход со всех
     * устройств, кроме текущей сессии — `deleteRememberTokens()`,
     * тот же вызов, что при выходе/сбросе пароля по ссылке) и
     * перевыпускает текущую сессию (`regenerateSession()`); она хранит
     * `$_SESSION` как есть — `user_id`/`user_name`/`user_role`
     * сохраняются, меняется только id сессии и CSRF-токен.
     */
    public function updatePassword(): void
    {
        requireCsrf();

        $user = findUserById(currentUser()['id']);

        $input = [
            'current_password'     => (string) input('current_password'),
            'new_password'         => (string) input('new_password'),
            'new_password_confirm' => (string) input('new_password_confirm'),
        ];

        $errors = validatePasswordChangeInput($input);

        if (in_array(true, $errors, true)) {
            $this->details([], [], $errors);
            return;
        }

        if (!password_verify($input['current_password'], $user['password_hash'])) {
            setFlash('error', self::PASSWORD_ERROR);
            // 'wrong_password' — отдельно от структурной ошибки «поле
            // пустое», чтобы View не показывала «Введите текущий
            // пароль» тому, кто его как раз ввёл, просто неверно.
            $this->details([], [], ['current_password' => true, 'wrong_password' => true]);
            return;
        }

        updateUserPasswordHash((int) $user['id'], password_hash($input['new_password'], PASSWORD_DEFAULT));
        deleteRememberTokens((int) $user['id']);
        regenerateSession();

        setFlash('success', 'Пароль изменён.');
        redirect('/account/details');
    }

    /**
     * Список адресов + форма добавления/редактирования на одной
     * странице (`FR-ACC-002`, Таск 4) — `?edit={id}` подставляет
     * существующий адрес в форму; чужой/несуществующий `id` в
     * `?edit=` тихо игнорируется (список без формы редактирования),
     * а не 404 — это необязательный query-параметр отображения, не
     * адрес действия.
     */
    public function addresses(): void
    {
        requireAuth();

        $user   = currentUser();
        $editId = (int) input('edit', 0);

        $editingAddress = $editId > 0 ? findUserAddress($editId, $user['id']) : null;

        $this->renderAddresses(getUserAddresses($user['id']), $editingAddress, [], []);
    }

    /**
     * Новый адрес (`FR-ACC-002`) — лимит `ACCOUNT_ADDRESSES_MAX`
     * проверяется уже после структурной валидации (не тратим лишний
     * запрос на заведомо невалидную форму) и до `createAddress()`
     * (Model создаёт запись безусловно — лимит бизнес-правило
     * Controller, не инвариант хранения, как «ровно один основной»).
     */
    public function storeAddress(): void
    {
        requireAuth();
        requireCsrf();

        $user   = currentUser();
        $input  = $this->addressInputFromRequest();
        $errors = validateAddressInput($input);

        if (in_array(true, $errors, true)) {
            $this->renderAddresses(getUserAddresses($user['id']), null, $input, $errors);
            return;
        }

        if (countUserAddresses($user['id']) >= ACCOUNT_ADDRESSES_MAX) {
            setFlash('error', 'Достигнут лимит сохранённых адресов (' . ACCOUNT_ADDRESSES_MAX . ').');
            redirect('/account/addresses');
        }

        createAddress($input + ['user_id' => $user['id']]);

        setFlash('success', 'Адрес добавлен.');
        redirect('/account/addresses');
    }

    /**
     * Редактирование адреса — `findUserAddress()` до валидации, чтобы
     * чужой/несуществующий `id` получал 404 сразу, не после проверки
     * полей формы.
     */
    public function updateAddress(string $id): void
    {
        requireAuth();
        requireCsrf();

        $user    = currentUser();
        $address = findUserAddress((int) $id, $user['id']);
        if ($address === null) {
            abort404();
        }

        $input  = $this->addressInputFromRequest();
        $errors = validateAddressInput($input);

        if (in_array(true, $errors, true)) {
            $this->renderAddresses(getUserAddresses($user['id']), $address, $input, $errors);
            return;
        }

        updateAddress((int) $id, $user['id'], $input);

        setFlash('success', 'Адрес обновлён.');
        redirect('/account/addresses');
    }

    public function deleteAddress(string $id): void
    {
        requireAuth();
        requireCsrf();

        $user = currentUser();

        if (!deleteAddress((int) $id, $user['id'])) {
            setFlash('error', 'Адрес не найден.');
            redirect('/account/addresses');
        }

        setFlash('success', 'Адрес удалён.');
        redirect('/account/addresses');
    }

    public function setDefaultAddress(string $id): void
    {
        requireAuth();
        requireCsrf();

        $user = currentUser();

        if (!setDefaultAddress((int) $id, $user['id'])) {
            setFlash('error', 'Адрес не найден.');
            redirect('/account/addresses');
        }

        setFlash('success', 'Основной адрес изменён.');
        redirect('/account/addresses');
    }

    private function addressInputFromRequest(): array
    {
        return [
            'title'     => trim((string) input('title')),
            'city'      => trim((string) input('city')),
            'street'    => trim((string) input('street')),
            'house'     => trim((string) input('house')),
            'apartment' => trim((string) input('apartment')),
            'comment'   => trim((string) input('comment')),
        ];
    }

    private function renderAddresses(array $addresses, ?array $editingAddress, array $old, array $errors): void
    {
        render('account/addresses', [
            'title'          => 'Мои адреса',
            'activeSection'  => 'addresses',
            'addresses'      => $addresses,
            'editingAddress' => $editingAddress,
            'old'            => $old,
            'errors'         => $errors,
            'maxReached'     => count($addresses) >= ACCOUNT_ADDRESSES_MAX,
        ]);
    }
}
