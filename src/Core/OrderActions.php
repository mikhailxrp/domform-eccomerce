<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/OrderStatus.php';

/**
 * Чистая валидация форм действий над Заказом (Таск 3 Фазы 4) — без
 * обращения к БД.
 */

const CANCEL_BRANCH_STANDARD     = 'standard';
const CANCEL_BRANCH_NON_STANDARD = 'non_standard';

/**
 * Отмена (`FR-ORD-002`, `BR-007`): ветка нестандартного размера
 * требует комментарий, а возврат предоплаты подтверждается явной
 * отметкой, если оплата уже была внесена (`$paymentStatus !==
 * unpaid`) — иначе возвращать нечего. Отметка «Вариант уже изготовлен →
 * Выставочный образец» (`mark_as_sample`) допустима только в
 * стандартной ветке — образцом становится Вариант стандартного размера
 * (`ord.md`, «Отмена — двухветочная»).
 */
function validateCancelInput(array $input, string $paymentStatus): array
{
    $branch = $input['branch'] ?? '';

    return [
        'branch'           => !in_array($branch, [CANCEL_BRANCH_STANDARD, CANCEL_BRANCH_NON_STANDARD], true),
        'note'             => $branch === CANCEL_BRANCH_NON_STANDARD && trim((string) ($input['note'] ?? '')) === '',
        'refund_confirmed' => $paymentStatus !== PAYMENT_STATUS_UNPAID && !($input['refund_confirmed'] ?? false),
        'mark_as_sample'   => $branch !== CANCEL_BRANCH_STANDARD && ($input['mark_as_sample'] ?? false),
    ];
}

/**
 * Редактирование состава Заказа (`FR-ORD-003`, Таск 4 Фазы 4) доступно
 * только после подтверждения и до отгрузки — в `new` состав ещё
 * согласовывается по телефону и правится через сам чекаут/повторный
 * звонок, а с `ready_for_shipment` уже поздно (сборка/раскрой начаты).
 */
function canEditOrderItems(string $status): bool
{
    return in_array($status, [ORDER_STATUS_CONFIRMED, ORDER_STATUS_IN_PRODUCTION], true);
}

/**
 * Стоимость доставки (`BR-006`) — вносится Менеджером вручную; пустая
 * строка (сброс в `NULL`) обрабатывается отдельно в Controller, сюда
 * попадает только непустое значение. Формат — тот же, что деньги везде
 * в проекте: строка, не `float` (`php.md`).
 */
function validateShippingCost(string $raw): ?string
{
    return preg_match('/^\d+(\.\d{1,2})?$/', $raw) === 1
        ? null
        : 'Введите корректную стоимость доставки.';
}
