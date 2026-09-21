<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Order.php';
require_once ROOT_PATH . '/src/Models/Reserve.php';
require_once ROOT_PATH . '/src/Models/Product.php';
require_once ROOT_PATH . '/src/Models/User.php';
require_once ROOT_PATH . '/src/Models/ReturnRequest.php';
require_once ROOT_PATH . '/src/Core/Checkout.php';
require_once ROOT_PATH . '/src/Core/Pagination.php';
require_once ROOT_PATH . '/src/Core/Payment.php';
require_once ROOT_PATH . '/src/Core/OrderActions.php';
require_once ROOT_PATH . '/src/Core/ManualOrder.php';
require_once ROOT_PATH . '/src/Core/Warranty.php';
require_once ROOT_PATH . '/src/Services/Sms.php';

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
            'canEditItems'       => canEditOrderItems($order['status']),
            'reserves'           => getOrderReserves((int) $id),
            'returns'            => getOrderReturns((int) $id),
            'smsNotifications'   => getOrderSmsNotifications((int) $id),
        ]);
    }

    public function addItem(string $id): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        $order = findOrderById((int) $id);
        if ($order === null) {
            abort404();
        }

        if (!canEditOrderItems($order['status'])) {
            setFlash('error', 'Редактирование состава недоступно для текущего статуса Заказа.');
            redirect('/admin/orders/' . $id);
        }

        $variantId = (int) input('variant_id', 0);
        $sku       = trim((string) input('sku', ''));
        $color     = trim((string) input('color', ''));
        $qty       = (int) input('quantity', 1);

        if ($variantId <= 0 && $sku !== '') {
            $variantId = findActiveVariantIdBySku($sku) ?? 0;
        }

        if ($variantId <= 0 || $qty < 1 || !addOrderItem((int) $id, $variantId, $color !== '' ? $color : null, $qty)) {
            setFlash('error', 'Не удалось добавить позицию — проверьте артикул.');
            redirect('/admin/orders/' . $id);
        }

        setFlash('success', 'Позиция добавлена.');
        redirect('/admin/orders/' . $id);
    }

    public function updateItem(string $id, string $itemId): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        $order = findOrderById((int) $id);
        if ($order === null) {
            abort404();
        }

        if (!canEditOrderItems($order['status'])) {
            setFlash('error', 'Редактирование состава недоступно для текущего статуса Заказа.');
            redirect('/admin/orders/' . $id);
        }

        $qty = (int) input('quantity', 0);

        if ($qty < 1 || !updateOrderItemQuantity((int) $id, (int) $itemId, $qty)) {
            setFlash('error', 'Не удалось изменить количество.');
            redirect('/admin/orders/' . $id);
        }

        setFlash('success', 'Количество обновлено.');
        redirect('/admin/orders/' . $id);
    }

    public function removeItem(string $id, string $itemId): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        $order = findOrderById((int) $id);
        if ($order === null) {
            abort404();
        }

        if (!canEditOrderItems($order['status'])) {
            setFlash('error', 'Редактирование состава недоступно для текущего статуса Заказа.');
            redirect('/admin/orders/' . $id);
        }

        if (!removeOrderItem((int) $id, (int) $itemId)) {
            setFlash('error', 'Нельзя удалить последнюю позицию — отмените Заказ.');
            redirect('/admin/orders/' . $id);
        }

        setFlash('success', 'Позиция удалена.');
        redirect('/admin/orders/' . $id);
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

        $order = findOrderById((int) $id);
        $event = $order !== null ? smsEventForStatus($to, $order['fulfillment_method']) : null;
        if ($event !== null) {
            sendOrderSms($order, $event);
        }

        setFlash('success', 'Статус Заказа обновлён.');
        redirect('/admin/orders/' . $id);
    }

    /**
     * Устно согласованный срок Резерва (`FR-STOCK-002` правило 2) —
     * только для справки Менеджеру, система по нему не действует.
     */
    public function setReserveAgreedUntil(string $id, string $reserveId): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        if (findOrderById((int) $id) === null) {
            abort404();
        }

        $validated = validateAgreedUntil((string) input('agreed_until', ''));
        if ($validated['error'] !== null) {
            setFlash('error', $validated['error']);
            redirect('/admin/orders/' . $id);
        }

        if (!setReserveAgreedUntil((int) $id, (int) $reserveId, $validated['value'])) {
            setFlash('error', 'Резерв не найден или уже снят.');
            redirect('/admin/orders/' . $id);
        }

        setFlash('success', $validated['value'] === null ? 'Срок резерва очищен.' : 'Срок резерва сохранён.');
        redirect('/admin/orders/' . $id);
    }

    /**
     * Ручное снятие Резерва (`FR-STOCK-002` правило 3, UC-02 2в) — Заказ
     * при этом не отменяется, это отдельное решение Менеджера по звонку.
     */
    public function releaseReserve(string $id, string $reserveId): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        if (findOrderById((int) $id) === null) {
            abort404();
        }

        if (!releaseReserveById((int) $id, (int) $reserveId)) {
            setFlash('error', 'Резерв не найден или уже снят.');
            redirect('/admin/orders/' . $id);
        }

        setFlash('success', 'Резерв снят — образец снова доступен для продажи.');
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

        $result = markOrderPrepaid((int) $id, $amount);

        match ($result) {
            PREPAID_RESULT_OK           => setFlash('success', 'Предоплата отмечена.'),
            PREPAID_RESULT_ALREADY      => setFlash('error', 'Предоплата уже отмечена.'),
            PREPAID_RESULT_SAMPLE_TAKEN => setFlash('error', $this->sampleTakenMessage((int) $id)),
        };

        $this->notifyPrepaidConfirmed($result, (int) $id);

        redirect('/admin/orders/' . $id);
    }

    /**
     * `markOrderPrepaid()` переводит Заказ в `confirmed` внутри своей
     * транзакции, но `transitionOrderStatus()` там не бросает на
     * запрещённом переходе (`Models/Order.php`) — поэтому реальный
     * статус нужно перечитать, а не полагаться на `PREPAID_RESULT_OK`.
     */
    private function notifyPrepaidConfirmed(string $prepaidResult, int $orderId): void
    {
        if ($prepaidResult !== PREPAID_RESULT_OK) {
            return;
        }

        $order = findOrderById($orderId);
        if ($order !== null && $order['status'] === ORDER_STATUS_CONFIRMED) {
            sendOrderSms($order, SMS_EVENT_CONFIRMED);
        }
    }

    /**
     * Проигрыш конкуренции за Выставочный образец (`BR-003`, UC-02 2а):
     * предоплата откачена, Менеджер по звонку предлагает такой же
     * Вариант под заказ — сообщение называет Заказ-держатель, чтобы было
     * с чего начать разговор.
     */
    private function sampleTakenMessage(int $orderId): string
    {
        $competing = findCompetingReserveForOrder($orderId);
        $holder    = $competing !== null ? ' за Заказом №' . $competing['order_id'] : ' за другим Заказом';

        return 'Предоплата не отмечена: Выставочный образец уже закреплён' . $holder
            . ' — предложите Покупателю такой же Вариант под заказ.';
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
            'mark_as_sample'   => (string) input('mark_as_sample', '') === '1',
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
        if ($errors['mark_as_sample']) {
            setFlash('error', 'Оставить Вариант Выставочным образцом можно только при отмене стандартного размера.');
            redirect('/admin/orders/' . $id);
        }

        cancelOrder((int) $id, $input['note'], $input['refund_confirmed'], $input['mark_as_sample']);

        sendOrderSms($order, SMS_EVENT_CANCELLED);

        setFlash('success', 'Заказ отменён.');
        redirect('/admin/orders/' . $id);
    }

    /**
     * Ручное создание Заказа по звонку/WhatsApp (`FR-MGR-002`, Таск 5
     * Фазы 4). `?phone=` — необязательный результат мини-формы «Найти»
     * (без JS это обычный GET-редирект на этот же маршрут,
     * `admin.js` его дополнительно перехватывает через
     * `/admin/customers/lookup`, не трогая этот путь).
     */
    public function create(): void
    {
        requireRole(['manager', 'admin']);

        $lookupPhone = trim((string) input('phone', ''));

        $this->renderCreatePage($lookupPhone, $this->lookupCustomerByPhone($lookupPhone), [], []);
    }

    public function store(): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();
        ensureSessionStarted();

        $submittedToken = (string) input('manual_order_token', '');
        if ($submittedToken === '' || !hash_equals((string) ($_SESSION['manual_order_token'] ?? ''), $submittedToken)) {
            redirect('/admin/orders/create');
        }

        $rawItems      = is_array(input('items', [])) ? input('items', []) : [];
        $resolvedItems = [];
        foreach (normalizeManualOrderItems($rawItems) as $item) {
            if ($item['variant_id'] <= 0 && $item['sku'] !== '') {
                $item['variant_id'] = findActiveVariantIdBySku($item['sku']) ?? 0;
            }
            $resolvedItems[] = $item;
        }

        $input = normalizeManualOrderInput([
            'customer_mode'      => input('customer_mode'),
            'user_id'            => input('user_id'),
            'guest_name'         => input('guest_name'),
            'guest_phone'        => input('guest_phone'),
            'guest_email'        => input('guest_email'),
            'fulfillment_method' => input('fulfillment_method'),
            'delivery_address'   => input('delivery_address'),
            'payment_method'     => input('payment_method'),
            'comment'            => input('comment'),
            'prepaid_amount'     => input('prepaid_amount'),
            'items'              => $resolvedItems,
        ]);

        $customer = null;
        if ($input['customer_mode'] === MANUAL_ORDER_CUSTOMER_USER) {
            $customer = findUserById($input['user_id']);
        }

        $errors = validateManualOrderInput($input);
        $errors['user_id'] = $input['customer_mode'] === MANUAL_ORDER_CUSTOMER_USER
            && ($customer === null || $customer['role'] !== 'customer');

        if (in_array(true, $errors, true)) {
            $lookupPhone = (string) input('phone', '');
            $this->renderCreatePage($lookupPhone, $this->lookupCustomerByPhone($lookupPhone), $input, $errors);
            return;
        }

        $order = [
            'user_id'            => $input['customer_mode'] === MANUAL_ORDER_CUSTOMER_USER ? $customer['id'] : null,
            'guest_name'         => $input['customer_mode'] === MANUAL_ORDER_CUSTOMER_GUEST ? $input['guest_name'] : null,
            'guest_phone'        => $input['customer_mode'] === MANUAL_ORDER_CUSTOMER_GUEST ? $input['guest_phone'] : null,
            'guest_email'        => $input['customer_mode'] === MANUAL_ORDER_CUSTOMER_GUEST && $input['guest_email'] !== '' ? $input['guest_email'] : null,
            'fulfillment_method' => $input['fulfillment_method'],
            'delivery_address'   => $input['fulfillment_method'] === FULFILLMENT_DELIVERY ? $input['delivery_address'] : null,
            'comment'            => $input['comment'],
            'payment_method'     => $input['payment_method'],
        ];

        $orderItems = array_map(static fn (array $item): array => [
            'product_variant_id' => $item['variant_id'],
            'color'              => $item['color'] !== '' ? $item['color'] : null,
            'quantity'           => $item['quantity'],
        ], $input['items']);

        $orderId = createOrder($order, $orderItems);

        if ($orderId === null) {
            setFlash('error', 'Один из товаров стал недоступен — проверьте позиции.');
            $lookupPhone = (string) input('phone', '');
            $this->renderCreatePage($lookupPhone, $this->lookupCustomerByPhone($lookupPhone), $input, []);
            return;
        }

        sendOrderSms(array_merge($order, ['id' => $orderId]), SMS_EVENT_ACCEPTED);

        $prepaidResult = markOrderPrepaid($orderId, $input['prepaid_amount']);
        $this->notifyPrepaidConfirmed($prepaidResult, $orderId);

        unset($_SESSION['manual_order_token']);

        // Заказ уже создан (`new`/`unpaid`), но образец успел уйти другому
        // Заказу между `createOrder()` и фиксацией предоплаты — Менеджер
        // должен увидеть это сразу, а не «Заказ создан».
        if ($prepaidResult === PREPAID_RESULT_SAMPLE_TAKEN) {
            setFlash('error', 'Заказ №' . $orderId . ' создан без предоплаты. ' . $this->sampleTakenMessage($orderId));
            redirect('/admin/orders/' . $orderId);
        }

        setFlash('success', 'Заказ создан.');
        redirect('/admin/orders/' . $orderId);
    }

    /**
     * JSON-подсказка для `admin.js` (прогрессивное улучшение мини-формы
     * «Найти» на `create()`) — сам поиск и его результат идентичны
     * нестрогому (без JS) пути через `?phone=`.
     */
    public function lookupCustomer(): void
    {
        requireRole(['manager', 'admin']);

        header('Content-Type: application/json; charset=utf-8');

        $customer = $this->lookupCustomerByPhone((string) input('phone', ''));

        // Только то, что нужно форме создания Заказа — `findUserById()`/
        // `findCustomerByPhone()` возвращают полную строку `users`,
        // включая `password_hash`, которому нельзя попадать в ответ.
        $safeCustomer = $customer !== null
            ? ['id' => (int) $customer['id'], 'name' => $customer['name'], 'phone' => $customer['phone']]
            : null;

        echo json_encode(['customer' => $safeCustomer], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Общий поиск для отображения найденного Покупателя — используется
     * и в `create()`/`lookupCustomer()`, и при перерисовке формы после
     * неудачной `store()`, независимо от того, какой `customer_mode`
     * был реально отправлен (иначе выбор «Оформить как гостя» стирал бы
     * карточку уже найденного по телефону Покупателя).
     */
    private function lookupCustomerByPhone(string $phone): ?array
    {
        $normalized = normalizePhone($phone);

        return validatePhone($normalized) ? findCustomerByPhone($normalized) : null;
    }

    private function renderCreatePage(string $lookupPhone, ?array $foundCustomer, array $old, array $errors): void
    {
        ensureSessionStarted();
        if (empty($_SESSION['manual_order_token'])) {
            $_SESSION['manual_order_token'] = bin2hex(random_bytes(16));
        }

        $itemRows = max(MANUAL_ORDER_DEFAULT_ITEM_ROWS, count($old['items'] ?? []));

        render('admin/orders/create', [
            'title'              => 'Новый заказ',
            'lookupPhone'        => $lookupPhone,
            'foundCustomer'      => $foundCustomer,
            'fulfillmentOptions' => FULFILLMENT_LABELS,
            'paymentOptions'     => PAYMENT_METHOD_LABELS,
            'manualOrderToken'   => $_SESSION['manual_order_token'],
            'old'                => $old,
            'errors'             => $errors,
            'itemRows'           => $itemRows,
        ]);
    }
}
