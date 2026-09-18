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
 * unpaid`) — иначе возвращать нечего.
 */
function validateCancelInput(array $input, string $paymentStatus): array
{
    $branch = $input['branch'] ?? '';

    return [
        'branch'           => !in_array($branch, [CANCEL_BRANCH_STANDARD, CANCEL_BRANCH_NON_STANDARD], true),
        'note'             => $branch === CANCEL_BRANCH_NON_STANDARD && trim((string) ($input['note'] ?? '')) === '',
        'refund_confirmed' => $paymentStatus !== PAYMENT_STATUS_UNPAID && !($input['refund_confirmed'] ?? false),
    ];
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
