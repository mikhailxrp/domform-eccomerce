<?php

declare(strict_types=1);

/**
 * Единый источник скидочной цены Варианта (`FR-DISC-001` правило 3,
 * `FR-DISC-002` правило 3, `ADR-041`) — одна PHP-функция на `bcmath` и
 * одно эквивалентное SQL-выражение (`discountedPriceSql()`), чтобы
 * сортировка/фильтр по цене в SQL (`Models/Product.php`) и расчёт в
 * PHP (`Models/Cart.php`, `Models/Order.php`) никогда не разошлись.
 * `discount_percent = NULL` или `0` — скидки нет (`hasDiscount()`):
 * форма Варианта (Таск 8 Фазы 4) пропускает `0` при сохранении,
 * повторно нормализовать данные в БД не требуется.
 */

/**
 * Цена Варианта минус процент скидки, округление до копеек half-up —
 * как `ROUND()` в MySQL для неотрицательных сумм (цена и скидка в этом
 * проекте всегда ≥ 0). `bcadd(..., '0.005', 2)` — стандартный приём
 * `bcmath`-округления: `bcadd()` с заданным `scale` отбрасывает лишние
 * разряды (не округляет сам по себе), а добавленные «пол-копейки»
 * превращают отбрасывание в округление половины вверх.
 */
function discountedPrice(string $price, ?string $percent): string
{
    $price = bcadd($price, '0.00', 2);

    if (!hasDiscount($percent)) {
        return $price;
    }

    $factor  = bcsub('100', $percent, 6);
    $product = bcdiv(bcmul($price, $factor, 6), '100', 6);

    return bcadd($product, '0.005', 2);
}

/**
 * `NULL` или `0` (в любом представлении — `'0'`, `'0.00'`) — скидки
 * нет. Форма Варианта нормализует пустое поле в `NULL` уже на входе
 * (`ProductForm.php`), но `0` в БД не исключён (ручная правка,
 * будущие импорты) — трактуется так же, как отсутствие скидки.
 */
function hasDiscount(?string $percent): bool
{
    return $percent !== null && bccomp($percent, '0', 2) > 0;
}

/**
 * SQL-эквивалент `discountedPrice()` для запросов каталога/поиска —
 * `$alias` — алиас таблицы `product_variants` в запросе (`pv`, `pv3`,
 * …). `IFNULL(..., 0)` — тот же случай «скидки нет», что
 * `hasDiscount()`; `ROUND()` в MySQL 8 округляет `.5` от нуля, что для
 * неотрицательных денежных сумм совпадает с half-up.
 */
function discountedPriceSql(string $alias): string
{
    return "ROUND({$alias}.price * (1 - IFNULL({$alias}.discount_percent, 0) / 100), 2)";
}
