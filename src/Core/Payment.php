<?php

declare(strict_types=1);

/**
 * Чистая валидация суммы оплаты (предоплата/остаток, `FR-PAY-002`) —
 * без обращения к БД. Диапазон предоплаты 30-50% не проверяется:
 * процент вводит Менеджер вручную, сайт его не считает (`pay.md`).
 * Сравнение денег только через `bccomp()`, никогда `float` (`php.md`).
 */

function validatePaymentAmount(string $amount, string $orderTotal, string $alreadyPaid): ?string
{
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
        return 'Введите корректную сумму.';
    }

    if (bccomp($amount, '0.00', 2) <= 0) {
        return 'Сумма должна быть больше нуля.';
    }

    $remaining = bcsub($orderTotal, $alreadyPaid, 2);

    if (bccomp($amount, $remaining, 2) > 0) {
        return 'Сумма превышает остаток к оплате.';
    }

    return null;
}
