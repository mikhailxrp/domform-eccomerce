<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Cart.php';
require_once ROOT_PATH . '/src/Models/User.php';
require_once ROOT_PATH . '/src/Models/Order.php';
require_once ROOT_PATH . '/src/Models/Address.php';
require_once ROOT_PATH . '/src/Core/Cart.php';
require_once ROOT_PATH . '/src/Core/Checkout.php';

class CheckoutController
{
    /**
     * Тот же текст, что `AuthController::AUTH_ERROR` — «занятый email»
     * при `create_account` не должен отличаться от обычной ошибки
     * входа (не раскрываем существование аккаунта).
     */
    private const ACCOUNT_ERROR = 'Неверный email или пароль.';

    public function index(): void
    {
        $this->renderCheckoutPage([], []);
    }

    public function acceptChanges(): void
    {
        requireCsrf();

        acceptCartPriceChanges(cartOwner());

        setFlash('success', 'Изменения приняты.');
        redirect('/checkout');
    }

    public function store(): void
    {
        requireCsrf();

        ensureSessionStarted();

        // Повторный сабмит (двойной клик/назад в браузере) уже
        // созданного этим токеном Заказа — токен одноразовый, к этому
        // моменту снят из сессии в первом успешном проходе ниже.
        if (empty($_SESSION['checkout_token']) && !empty($_SESSION['last_order_id'])) {
            redirect('/checkout/success');
        }

        if (tooManyAttempts('checkout', 5, 60)) {
            setFlash('error', 'Слишком много попыток оформления. Попробуйте через минуту.');
            redirect('/checkout');
        }
        hitRateLimit('checkout');

        $submittedToken = (string) input('checkout_token');
        if ($submittedToken === '' || !hash_equals((string) ($_SESSION['checkout_token'] ?? ''), $submittedToken)) {
            redirect('/checkout');
        }

        $owner = cartOwner();
        $items = getCartItems($owner);

        if ($items === []) {
            redirect('/cart');
        }

        $totals         = calculateCartTotals($items);
        $hasUnavailable = in_array(false, array_column($totals['items'], 'available'), true);

        if (findPriceChanges($items) !== [] || $hasUnavailable) {
            setFlash('error', 'Цена или доступность позиций изменились — проверьте заказ ещё раз.');
            redirect('/checkout');
        }

        $user            = currentUser();
        $isAuthenticated = $user !== null;

        $input = normalizeCheckoutInput([
            'name'               => input('name'),
            'phone'              => input('phone'),
            'email'              => input('email'),
            'fulfillment_method' => input('fulfillment_method'),
            'address_city'       => input('address_city'),
            'address_street'     => input('address_street'),
            'address_house'      => input('address_house'),
            'address_apartment'  => input('address_apartment'),
            'address_comment'    => input('address_comment'),
            'save_address'       => input('save_address'),
            'saved_address_id'   => input('saved_address_id'),
            'comment'            => input('comment'),
            'payment_method'     => input('payment_method'),
            'create_account'     => input('create_account'),
            'password'           => input('password'),
        ]);
        $errors = validateCheckoutInput($input, $isAuthenticated);

        if (in_array(true, $errors, true)) {
            $this->renderCheckoutPage($input, $errors);
            return;
        }

        $userId = $user['id'] ?? null;

        if ($input['create_account'] && !$isAuthenticated) {
            if (findUserByEmail($input['email']) !== null) {
                setFlash('error', self::ACCOUNT_ERROR);
                $this->renderCheckoutPage($input, []);
                return;
            }

            $newUserId = createUser(
                $input['name'],
                $input['email'],
                password_hash($input['password'], PASSWORD_DEFAULT),
                $input['phone']
            );

            if ($newUserId === null) {
                setFlash('error', self::ACCOUNT_ERROR);
                $this->renderCheckoutPage($input, []);
                return;
            }

            regenerateSession();
            $_SESSION['user_id']   = $newUserId;
            $_SESSION['user_name'] = $input['name'];
            $_SESSION['user_role'] = 'customer';
            $userId = $newUserId;

            if (isset($_COOKIE['cart_token'])) {
                mergeGuestCart($_COOKIE['cart_token'], $userId);
            }
        }

        $isDelivery = $input['fulfillment_method'] === FULFILLMENT_DELIVERY;

        $order = [
            'user_id'            => $userId,
            'guest_name'         => $userId === null ? $input['name'] : null,
            'guest_phone'        => $userId === null ? $input['phone'] : null,
            'guest_email'        => $userId === null && $input['email'] !== '' ? $input['email'] : null,
            'fulfillment_method' => $input['fulfillment_method'],
            'delivery_address'   => $isDelivery ? formatAddress($this->deliveryAddressData($input)) : null,
            'comment'            => $input['comment'],
            'payment_method'     => $input['payment_method'],
        ];

        $orderItems = array_map(static fn (array $item): array => [
            'product_variant_id' => $item['product_variant_id'],
            'color'              => $item['color'],
            'quantity'           => $item['quantity'],
        ], $items);

        $orderId = createOrder($order, $orderItems);

        if ($orderId === null) {
            logWarning('Заказ не создан — позиция стала недоступна между проверкой и оформлением', ['owner_type' => array_key_first($owner)]);
            setFlash('error', 'Один из товаров стал недоступен — проверьте заказ ещё раз.');
            redirect('/checkout');
        }

        if ($isDelivery && $input['save_address'] && $userId !== null) {
            $this->maybeSaveDeliveryAddress($userId, $input);
        }

        clearCart(cartOwner());
        refreshCartCount(cartOwner());

        unset($_SESSION['checkout_token']);
        $_SESSION['last_order_id'] = $orderId;

        redirect('/checkout/success');
    }

    public function success(): void
    {
        ensureSessionStarted();
        $orderId = $_SESSION['last_order_id'] ?? null;

        if (!is_int($orderId)) {
            redirect('/');
        }

        $order = findOrderById($orderId);
        if ($order === null) {
            redirect('/');
        }

        $items = array_map(static function (array $item): array {
            $item['line_total'] = bcmul((string) $item['price'], (string) $item['quantity'], 2);
            return $item;
        }, getOrderItems($orderId));

        render('checkout/success', [
            'title'             => 'Заказ принят',
            'order'             => $order,
            'items'             => $items,
            'fulfillmentLabel'  => FULFILLMENT_LABELS[$order['fulfillment_method']] ?? $order['fulfillment_method'],
            'paymentLabel'      => PAYMENT_METHOD_LABELS[$order['payment_method']] ?? $order['payment_method'],
        ]);
    }

    private function renderCheckoutPage(array $old, array $errors): void
    {
        $owner = cartOwner();
        $items = getCartItems($owner);

        if ($items === []) {
            redirect('/cart');
        }

        $totals       = calculateCartTotals($items);
        $priceChanges = findPriceChanges($items);
        $changedIds   = array_column($priceChanges, 'id');

        $summaryItems = array_map(static function (array $item) use ($changedIds): array {
            $item['price_changed'] = in_array($item['id'], $changedIds, true);
            return $item;
        }, $totals['items']);

        $hasUnavailable = in_array(false, array_column($summaryItems, 'available'), true);

        ensureSessionStarted();
        if (empty($_SESSION['checkout_token'])) {
            $_SESSION['checkout_token'] = bin2hex(random_bytes(16));
        }

        $user    = currentUser();
        $prefill = ['name' => '', 'phone' => '', 'email' => ''];

        $addresses      = [];
        $addressPrefill = [
            'address_city'      => 'Краснодар',
            'address_street'    => '',
            'address_house'     => '',
            'address_apartment' => '',
            'address_comment'   => '',
        ];

        if ($user !== null) {
            $account = findUserById($user['id']);
            if ($account !== null) {
                $prefill = [
                    'name'  => $account['name'],
                    'phone' => (string) ($account['phone'] ?? ''),
                    'email' => $account['email'],
                ];
            }

            $addresses = getUserAddresses($user['id']);

            foreach ($addresses as $address) {
                if ((int) $address['is_default'] === 1) {
                    $addressPrefill = [
                        'address_city'      => $address['city'],
                        'address_street'    => $address['street'],
                        'address_house'     => $address['house'],
                        'address_apartment' => (string) ($address['apartment'] ?? ''),
                        'address_comment'   => (string) ($address['comment'] ?? ''),
                    ];
                    break;
                }
            }
        }

        render('checkout/index', [
            'title'              => 'Оформление заказа',
            'items'              => $summaryItems,
            'total'              => $totals['total'],
            'isBlocked'          => $priceChanges !== [] || $hasUnavailable,
            'isAuthenticated'    => $user !== null,
            'prefill'            => $prefill,
            'addresses'          => $addresses,
            'addressPrefill'     => $addressPrefill,
            'fulfillmentOptions' => FULFILLMENT_LABELS,
            'paymentOptions'     => PAYMENT_METHOD_LABELS,
            'checkoutToken'      => $_SESSION['checkout_token'],
            'old'                => $old,
            'errors'             => $errors,
        ]);
    }

    /**
     * `$input` — результат `normalizeCheckoutInput()`; поля `address_*`
     * собираются в форму `validateAddressInput()`/`formatAddress()`
     * (`Core/Address.php`) — один и тот же состав адреса на чекауте и
     * в книге адресов кабинета (`Q-DEV-002`, Таск 4).
     */
    private function deliveryAddressData(array $input): array
    {
        return [
            'city'      => $input['address_city'],
            'street'    => $input['address_street'],
            'house'     => $input['address_house'],
            'apartment' => $input['address_apartment'],
            'comment'   => $input['address_comment'],
        ];
    }

    /**
     * Заказ важнее адреса (`phase-7.md`, Таск 5): совпадение с уже
     * сохранённым адресом или достижение `ACCOUNT_ADDRESSES_MAX` тихо
     * пропускает сохранение, без flash-ошибки — заказ уже создан.
     */
    private function maybeSaveDeliveryAddress(int $userId, array $input): void
    {
        $addressData          = $this->deliveryAddressData($input);
        $addressData['title'] = '';
        $formatted            = formatAddress($addressData);

        foreach (getUserAddresses($userId) as $existing) {
            if (formatAddress($existing) === $formatted) {
                return;
            }
        }

        if (countUserAddresses($userId) >= ACCOUNT_ADDRESSES_MAX) {
            return;
        }

        $addressData['user_id'] = $userId;
        createAddress($addressData);
    }
}
