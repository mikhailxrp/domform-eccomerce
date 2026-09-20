<?php

declare(strict_types=1);

/**
 * Гарантия производителя — 18 месяцев с даты перехода Заказа в статус
 * «Доставлен/Собран» (`FR-RET-002`, раздел 6.1 ТЗ). Отдельной таблицей
 * не хранится — считается на лету от `orders.delivered_at` (`ADR-013`).
 * Чистые функции без обращения к БД.
 */

const WARRANTY_MONTHS = 18;

/**
 * `null` — Заказ ещё не доставлен, гарантия не считается. Иначе —
 * `delivered_at` + `WARRANTY_MONTHS`, с клампом на последний день
 * месяца: наивный `strtotime('+18 months')` на датах конца месяца
 * переполняется (31.12 → не 30.06 следующего года, а 1.07 или позже,
 * в зависимости от длины месяца) — здесь дата всегда остаётся внутри
 * целевого месяца.
 */
function warrantyExpiresAt(?string $deliveredAt): ?string
{
    if ($deliveredAt === null) {
        return null;
    }

    $delivered   = new DateTimeImmutable($deliveredAt);
    $originalDay = (int) $delivered->format('j');

    // Первое число целевого месяца — безопасная точка отсчёта, на ней
    // `modify('+N months')` не может переполниться.
    $firstOfTargetMonth = $delivered->modify('first day of this month')->modify('+' . WARRANTY_MONTHS . ' months');
    $lastDayOfMonth      = (int) $firstOfTargetMonth->format('t');
    $clampedDay          = min($originalDay, $lastDayOfMonth);

    return $firstOfTargetMonth->modify('+' . ($clampedDay - 1) . ' days')->format('Y-m-d');
}

/**
 * `$now` — `Y-m-d` (или любой формат, понятный `DateTimeImmutable`),
 * передаётся вызывающим кодом, не читается функцией самостоятельно —
 * чистая функция без обращения к системному времени напрямую. День
 * истечения ещё считается «в пределах гарантии» (сравнение `<=`).
 */
function isUnderWarranty(?string $deliveredAt, string $now): bool
{
    $expiresAt = warrantyExpiresAt($deliveredAt);
    if ($expiresAt === null) {
        return false;
    }

    return $expiresAt >= (new DateTimeImmutable($now))->format('Y-m-d');
}
