<?php

declare(strict_types=1);

/**
 * Расчёт периода отчёта по продажам (`FR-ADM-005`, Таск 10 Фазы 4) —
 * чистая функция без обращения к БД, `$now` передаётся параметром для
 * тестируемости (`ReportTest`).
 */

// Определена здесь, а не в config/config.php: tests/bootstrap.php его
// не подключает (тот же приём, что UPLOAD_MAX_BYTES в Core/Upload.php,
// Таск 9). Ограничение не задано ТЗ — санитарный предел, чтобы
// произвольный диапазон не превратился в отчёт за всю историю магазина.
const REPORT_MAX_RANGE_DAYS = 366;

/**
 * `$preset` — `today`/`week`/`month`/`custom` (whitelist — на
 * Controller). `week`/`month` — скользящее окно в N дней до
 * сегодняшнего дня включительно (не календарная неделя/месяц — ТЗ
 * формат не задаёт, скользящее окно проще для пользователя и для
 * теста). Возвращает `['from' => ?string, 'to' => ?string, 'error' =>
 * ?string]` — `error !== null` ⇒ `from`/`to` всегда `null`.
 */
function resolveReportPeriod(string $preset, ?string $from, ?string $to, DateTimeImmutable $now): array
{
    return match ($preset) {
        'today' => ['from' => $now->format('Y-m-d'), 'to' => $now->format('Y-m-d'), 'error' => null],
        'week'  => ['from' => $now->modify('-6 days')->format('Y-m-d'), 'to' => $now->format('Y-m-d'), 'error' => null],
        'month' => ['from' => $now->modify('-29 days')->format('Y-m-d'), 'to' => $now->format('Y-m-d'), 'error' => null],
        'custom' => resolveCustomReportPeriod($from, $to),
        default  => ['from' => null, 'to' => null, 'error' => 'Неизвестный период.'],
    };
}

function resolveCustomReportPeriod(?string $from, ?string $to): array
{
    if ($from === null || $to === null || $from === '' || $to === '') {
        return ['from' => null, 'to' => null, 'error' => 'Укажите обе даты диапазона.'];
    }

    $fromDate = parseReportDate($from);
    $toDate   = parseReportDate($to);

    if ($fromDate === null || $toDate === null) {
        return ['from' => null, 'to' => null, 'error' => 'Некорректный формат даты — укажите ГГГГ-ММ-ДД.'];
    }

    if ($fromDate > $toDate) {
        return ['from' => null, 'to' => null, 'error' => 'Начальная дата позже конечной.'];
    }

    if ($fromDate->diff($toDate)->days > REPORT_MAX_RANGE_DAYS) {
        return ['from' => null, 'to' => null, 'error' => 'Слишком большой диапазон — не более ' . REPORT_MAX_RANGE_DAYS . ' дней.'];
    }

    return ['from' => $from, 'to' => $to, 'error' => null];
}

/**
 * `createFromFormat('!Y-m-d', ...)` сам по себе принимает
 * переполненные значения («2024-13-45» тихо перекатывается в другую
 * дату) — обратная проверка формата ловит несовпадение и отклоняет
 * такой ввод, а не молча использует перекатившуюся дату.
 */
function parseReportDate(string $value): ?DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if ($date === false || $date->format('Y-m-d') !== $value) {
        return null;
    }

    return $date;
}
