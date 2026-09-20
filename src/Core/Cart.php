<?php

declare(strict_types=1);

/**
 * Чистые расчёты корзины — без обращения к БД/сессии. Суммы считаются
 * строковой арифметикой bcmath (`php.md`: деньги никогда не float).
 */

// Санитарный предел количества в строке корзины — ТЗ ограничения не
// задаёт (`phase-2.md`, «Решения фазы»). Определена здесь, а не в
// config.php: нужна внутри тестируемой clampCartQuantity(), а
// tests/bootstrap.php не подключает config.php (по тому же образцу,
// что SEARCH_QUERY_MAX_LENGTH/SEARCH_BOOLEAN_OPERATORS в
// CatalogFilters.php).
const CART_MAX_QUANTITY = 99;

function clampCartQuantity(int $qty, bool $isShowroomSample): int
{
    if ($isShowroomSample) {
        return 1;
    }

    return max(1, min($qty, CART_MAX_QUANTITY));
}

/**
 * Можно ли добавить Вариант в корзину (`BR-003`, `BR-004`, Таск 5
 * Фазы 5) — только Выставочный образец с активным Резервом недоступен:
 * физический экземпляр уже закреплён за другим Покупателем, добавлять
 * его в новую корзину нет смысла — `createOrder()` всё равно откажет
 * при оформлении (`Models/Order.php`). Обычный Вариант под заказ и
 * свободный образец — доступны всегда.
 */
function isReservedSampleUnavailable(bool $isShowroomSample, bool $hasActiveReserve): bool
{
    return $isShowroomSample && $hasActiveReserve;
}

/**
 * `$items` — строки `getCartItems()` (`Models/Cart.php`): `price`
 * (текущая цена Варианта из `product_variants`), `quantity`,
 * `is_reserved` (образец занят конкуренцией — проверяется в Таске 3,
 * исключается из суммы, но остаётся в списке для отображения).
 * Возвращает исходные строки с добавленными `line_total`/`available` и
 * общий `total` — всё строками через bcmath, без float.
 */
function calculateCartTotals(array $items): array
{
    $total = '0.00';
    $lines = [];

    foreach ($items as $item) {
        $available = empty($item['is_reserved']);
        $lineTotal = $available
            ? bcmul((string) $item['price'], (string) $item['quantity'], 2)
            : '0.00';

        if ($available) {
            $total = bcadd($total, $lineTotal, 2);
        }

        $lines[] = $item + ['line_total' => $lineTotal, 'available' => $available];
    }

    return ['items' => $lines, 'total' => $total];
}

/**
 * Позиции, где текущая цена Варианта (`price`) разошлась со снэпшотом
 * на момент добавления в корзину (`price_snapshot`, `FR-CHK-003`
 * правило 1) — для уведомления «было / стало» на экране оформления
 * (Таск 3), не влияет на сумму.
 */
function findPriceChanges(array $items): array
{
    $changed = [];
    foreach ($items as $item) {
        if (bccomp((string) $item['price'], (string) $item['price_snapshot'], 2) !== 0) {
            $changed[] = $item;
        }
    }

    return $changed;
}
