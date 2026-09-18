<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Cart.php';
require_once ROOT_PATH . '/src/Models/User.php';
require_once ROOT_PATH . '/src/Core/Cart.php';
require_once ROOT_PATH . '/src/Core/Checkout.php';

class CheckoutController
{
    public function index(): void
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

        if ($user !== null) {
            $account = findUserById($user['id']);
            if ($account !== null) {
                $prefill = [
                    'name'  => $account['name'],
                    'phone' => (string) ($account['phone'] ?? ''),
                    'email' => $account['email'],
                ];
            }
        }

        render('checkout/index', [
            'title'              => 'Оформление заказа',
            'items'              => $summaryItems,
            'total'              => $totals['total'],
            'isBlocked'          => $priceChanges !== [] || $hasUnavailable,
            'isAuthenticated'    => $user !== null,
            'prefill'            => $prefill,
            'fulfillmentOptions' => FULFILLMENT_LABELS,
            'paymentOptions'     => PAYMENT_METHOD_LABELS,
            'old'                => [],
            'errors'             => [],
        ]);
    }

    public function acceptChanges(): void
    {
        requireCsrf();

        acceptCartPriceChanges(cartOwner());

        setFlash('success', 'Изменения приняты.');
        redirect('/checkout');
    }
}
