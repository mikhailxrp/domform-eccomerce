<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Order.php';

class PaymentController
{
    /**
     * Заказ резолвится через сессию (`last_order_id`), не через id в
     * URL — тот же приём, что `CheckoutController::success()` (Фаза 2,
     * Таск 4): гость не получает адрес, по которому можно открыть
     * чужой Заказ.
     */
    public function stub(): void
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

        if ($order['payment_method'] !== 'card_online') {
            redirect('/checkout/success');
        }

        render('payment/stub', [
            'title' => 'Оплата заказа',
            'order' => $order,
        ]);
    }
}
