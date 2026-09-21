<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Checkout.php';
require_once ROOT_PATH . '/src/Core/OrderStatus.php';

/**
 * Маппинг статус Заказа → СМС-событие и тексты уведомлений
 * (`FR-NOTIF-001`, `.docs/phases/phase-7.md`, Таск 8) — чистые функции
 * без обращения к БД. СМС — журнал-заглушка (`ADR-043`), реальный
 * провайдер не подключается.
 */

const SMS_EVENT_ACCEPTED       = 'accepted';
const SMS_EVENT_CONFIRMED      = 'confirmed';
const SMS_EVENT_STATUS_CHANGED = 'status_changed';
const SMS_EVENT_READY          = 'ready';
const SMS_EVENT_CANCELLED      = 'cancelled';

/**
 * `delivered` — без СМС, не входит в 5 событий раздела 8 ТЗ.
 */
function smsEventForStatus(string $status, string $fulfillment): ?string
{
    return match ($status) {
        ORDER_STATUS_NEW => SMS_EVENT_ACCEPTED,
        ORDER_STATUS_CONFIRMED => SMS_EVENT_CONFIRMED,
        ORDER_STATUS_IN_PRODUCTION, ORDER_STATUS_SHIPPING => SMS_EVENT_STATUS_CHANGED,
        ORDER_STATUS_READY_FOR_SHIPMENT => SMS_EVENT_READY,
        ORDER_STATUS_CANCELLED => SMS_EVENT_CANCELLED,
        default => null,
    };
}

function smsMessageForEvent(string $event, int $orderId, string $fulfillment): string
{
    return match ($event) {
        SMS_EVENT_ACCEPTED => "Заказ №{$orderId} принят.",
        SMS_EVENT_CONFIRMED => "Заказ №{$orderId} подтверждён.",
        SMS_EVENT_STATUS_CHANGED => "Статус заказа №{$orderId} изменился.",
        SMS_EVENT_READY => $fulfillment === FULFILLMENT_PICKUP
            ? "Заказ №{$orderId} готов к выдаче."
            : "Заказ №{$orderId} готов к доставке.",
        SMS_EVENT_CANCELLED => "Заказ №{$orderId} отменён.",
        default => throw new InvalidArgumentException("Неизвестное СМС-событие: {$event}"),
    };
}

/**
 * `users.phone` авторизованного Покупателя, иначе `orders.guest_phone`
 * Гостя, иначе `null` (не должно случаться штатно — оба поля
 * валидируются при создании Заказа).
 */
function orderNotificationPhone(array $order, ?array $user): ?string
{
    if ($user !== null && !empty($user['phone'])) {
        return $user['phone'];
    }

    return $order['guest_phone'] !== null && $order['guest_phone'] !== ''
        ? $order['guest_phone']
        : null;
}
