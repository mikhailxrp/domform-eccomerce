<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Checkout.php';

/**
 * Статусная модель Заказа (раздел 6.3 ТЗ, диаграмма + таблица
 * переходов) — чистые функции без обращения к БД, единственный
 * источник истины о том, какие переходы разрешены. `Models/Order.php`
 * (`transitionOrderStatus()`) — единственное место, которое реально
 * пишет `orders.status` (`php.md`: никогда raw `UPDATE orders SET
 * status`).
 */

const ORDER_STATUS_NEW                 = 'new';
const ORDER_STATUS_CONFIRMED           = 'confirmed';
const ORDER_STATUS_IN_PRODUCTION       = 'in_production';
const ORDER_STATUS_READY_FOR_SHIPMENT  = 'ready_for_shipment';
const ORDER_STATUS_SHIPPING            = 'shipping';
const ORDER_STATUS_DELIVERED           = 'delivered';
const ORDER_STATUS_CANCELLED           = 'cancelled';

const ORDER_STATUS_LABELS = [
    ORDER_STATUS_NEW                => 'Новый',
    ORDER_STATUS_CONFIRMED          => 'Подтверждён',
    ORDER_STATUS_IN_PRODUCTION      => 'В производстве',
    ORDER_STATUS_READY_FOR_SHIPMENT => 'Готов к отгрузке',
    ORDER_STATUS_SHIPPING           => 'В доставке',
    ORDER_STATUS_DELIVERED          => 'Доставлен/Собран',
    ORDER_STATUS_CANCELLED          => 'Отменён',
];

const PAYMENT_STATUS_UNPAID    = 'unpaid';
const PAYMENT_STATUS_PREPAID   = 'prepaid';
const PAYMENT_STATUS_PAID_FULL = 'paid_full';

const PAYMENT_STATUS_LABELS = [
    PAYMENT_STATUS_UNPAID    => 'Не оплачен',
    PAYMENT_STATUS_PREPAID   => 'Внесена предоплата',
    PAYMENT_STATUS_PAID_FULL => 'Оплачен полностью',
];

function orderStatusLabel(string $status): string
{
    return ORDER_STATUS_LABELS[$status] ?? $status;
}

const ORDER_STATUS_BADGE_CLASSES = [
    ORDER_STATUS_NEW                => 'bg-secondary',
    ORDER_STATUS_CONFIRMED          => 'bg-info',
    ORDER_STATUS_IN_PRODUCTION      => 'bg-primary',
    ORDER_STATUS_READY_FOR_SHIPMENT => 'bg-warning text-dark',
    ORDER_STATUS_SHIPPING           => 'bg-warning',
    ORDER_STATUS_DELIVERED          => 'bg-success',
    ORDER_STATUS_CANCELLED          => 'bg-danger',
];

function orderStatusBadgeClass(string $status): string
{
    return ORDER_STATUS_BADGE_CLASSES[$status] ?? 'bg-light text-dark';
}

/**
 * Разрешённые следующие статусы из `$from` (раздел 6.3 ТЗ): образец
 * (`$hasShowroomSample`) пропускает «В производстве» — идёт сразу в
 * «Готов к отгрузке»; из «Готов к отгрузке» ветвление по
 * `$fulfillment` (`Core/Checkout.php:FULFILLMENT_DELIVERY/_PICKUP`).
 * Терминальные статусы («Доставлен/Собран», «Отменён») и любой
 * нераспознанный статус — без переходов.
 */
function allowedOrderTransitions(string $from, bool $hasShowroomSample, string $fulfillment): array
{
    return match ($from) {
        ORDER_STATUS_NEW => [ORDER_STATUS_CONFIRMED, ORDER_STATUS_CANCELLED],
        ORDER_STATUS_CONFIRMED => $hasShowroomSample
            ? [ORDER_STATUS_READY_FOR_SHIPMENT, ORDER_STATUS_CANCELLED]
            : [ORDER_STATUS_IN_PRODUCTION, ORDER_STATUS_CANCELLED],
        ORDER_STATUS_IN_PRODUCTION => [ORDER_STATUS_READY_FOR_SHIPMENT, ORDER_STATUS_CANCELLED],
        ORDER_STATUS_READY_FOR_SHIPMENT => [
            $fulfillment === FULFILLMENT_DELIVERY ? ORDER_STATUS_SHIPPING : ORDER_STATUS_DELIVERED,
        ],
        ORDER_STATUS_SHIPPING => [ORDER_STATUS_DELIVERED],
        default => [],
    };
}

function canTransitionOrder(string $from, string $to, bool $hasShowroomSample, string $fulfillment): bool
{
    return in_array($to, allowedOrderTransitions($from, $hasShowroomSample, $fulfillment), true);
}

/**
 * `FR-ORD-002` правило 4, `BR-007` — отмена по звонку доступна только
 * до отгрузки; из «Готов к отгрузке» и позже действует процедура
 * Возврата (раздел 6.3 ТЗ), не отмена.
 */
function canCancelOrder(string $status): bool
{
    return in_array($status, [ORDER_STATUS_NEW, ORDER_STATUS_CONFIRMED, ORDER_STATUS_IN_PRODUCTION], true);
}
