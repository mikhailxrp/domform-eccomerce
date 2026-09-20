<?php

declare(strict_types=1);

/**
 * Резерв Выставочного образца (`BR-003`, `FR-STOCK-002…003`,
 * `modules/stock.md`) — чистые константы и хелперы без обращения к БД.
 * Сама запись в `reserves` — `Models/Reserve.php`.
 */

const RESERVE_STATUS_ACTIVE    = 'active';
const RESERVE_STATUS_RELEASED  = 'released';
const RESERVE_STATUS_FULFILLED = 'fulfilled';

const RESERVE_STATUS_LABELS = [
    RESERVE_STATUS_ACTIVE    => 'Активен',
    RESERVE_STATUS_RELEASED  => 'Снят',
    RESERVE_STATUS_FULFILLED => 'Списан',
];

function reserveStatusLabel(string $status): string
{
    return RESERVE_STATUS_LABELS[$status] ?? $status;
}

/**
 * Результат `markOrderPrepaid()` (`Models/Order.php`): контроллеру нужно
 * отличать «предоплата уже отмечена» от «образец закреплён за другим
 * Заказом» — `bool` этого не даёт, а доменные исключения из
 * Model-функций в проекте не используются (`transitionOrderStatus()` →
 * `false`, `createOrder()` → `null`).
 */
const PREPAID_RESULT_OK           = 'ok';
const PREPAID_RESULT_ALREADY      = 'already';
const PREPAID_RESULT_SAMPLE_TAKEN = 'sample_taken';

/**
 * Дубль уникального ключа — SQLSTATE 23000 (integrity constraint
 * violation). Единственный ресурс, за который конкурируют параллельные
 * запросы, — Выставочный образец: `UNIQUE(reserves.active_variant_id)`
 * даёт второму `INSERT` именно эту ошибку, и Model трактует её как
 * проигрыш конкуренции, а не как системный сбой (`dod-global.md`,
 * `ADR-007`). Любое другое исключение — не наше, уходит наружу.
 */
function isUniqueViolation(Throwable $e): bool
{
    if (!$e instanceof PDOException) {
        return false;
    }

    // Драйвер MySQL кладёт SQLSTATE в getCode() строкой; errorInfo[0] —
    // тот же код для случаев, когда getCode() отдаёт числовой код драйвера.
    $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());

    return $sqlState === '23000';
}

const RESERVE_STATUS_BADGE_CLASSES = [
    RESERVE_STATUS_ACTIVE    => 'bg-info',
    RESERVE_STATUS_RELEASED  => 'bg-secondary',
    RESERVE_STATUS_FULFILLED => 'bg-success',
];

function reserveStatusBadgeClass(string $status): string
{
    return RESERVE_STATUS_BADGE_CLASSES[$status] ?? 'bg-light text-dark';
}

/**
 * Устно согласованный срок Резерва (`FR-STOCK-002` правило 2) — поле
 * `<input type="date">`, формат `Y-m-d`. Пустая строка — очистка срока
 * (`value = null`, без ошибки). Любая корректная календарная дата
 * принимается, в том числе прошедшая: срок вносится для справки
 * Менеджеру, система его не интерпретирует и по нему не действует.
 * Возвращает `['value' => ?string, 'error' => ?string]`.
 */
function validateAgreedUntil(string $raw): array
{
    $raw = trim($raw);

    if ($raw === '') {
        return ['value' => null, 'error' => null];
    }

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m) !== 1
        || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])
    ) {
        return ['value' => null, 'error' => 'Введите корректную дату срока резерва.'];
    }

    return ['value' => $raw, 'error' => null];
}
