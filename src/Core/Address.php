<?php

declare(strict_types=1);

/**
 * Книга сохранённых адресов (`FR-ACC-002`, `.docs/phases/phase-7.md`,
 * Таск 4) — чистая валидация и форматирование без обращения к БД,
 * по образцу `Core/Checkout.php`. Правило «ровно один основной адрес»
 * требует БД (см. `Models/Address.php`), здесь не проверяется.
 */

const ADDRESS_CITY_MAX_LENGTH      = 100;
const ADDRESS_STREET_MAX_LENGTH    = 150;
const ADDRESS_HOUSE_MAX_LENGTH     = 20;
const ADDRESS_TITLE_MAX_LENGTH     = 100;
const ADDRESS_APARTMENT_MAX_LENGTH = 20;
const ADDRESS_COMMENT_MAX_LENGTH   = 255;

/**
 * Обрезает пробелы сама (по образцу `validateProfileInput()`,
 * `Core/Account.php`) — не полагается на то, что вызывающий код уже
 * обрезал строки. Пустая строка у необязательных полей
 * (`title`/`apartment`/`comment`) — норма, не ошибка. Город/улица/дом
 * обязательны — состав адреса `FR-ACC-002`.
 */
function validateAddressInput(array $input): array
{
    $title     = trim((string) ($input['title'] ?? ''));
    $city      = trim((string) ($input['city'] ?? ''));
    $street    = trim((string) ($input['street'] ?? ''));
    $house     = trim((string) ($input['house'] ?? ''));
    $apartment = trim((string) ($input['apartment'] ?? ''));
    $comment   = trim((string) ($input['comment'] ?? ''));

    return [
        'title'     => mb_strlen($title, 'UTF-8') > ADDRESS_TITLE_MAX_LENGTH,
        'city'      => $city === '' || mb_strlen($city, 'UTF-8') > ADDRESS_CITY_MAX_LENGTH,
        'street'    => $street === '' || mb_strlen($street, 'UTF-8') > ADDRESS_STREET_MAX_LENGTH,
        'house'     => $house === '' || mb_strlen($house, 'UTF-8') > ADDRESS_HOUSE_MAX_LENGTH,
        'apartment' => mb_strlen($apartment, 'UTF-8') > ADDRESS_APARTMENT_MAX_LENGTH,
        'comment'   => mb_strlen($comment, 'UTF-8') > ADDRESS_COMMENT_MAX_LENGTH,
    ];
}

/**
 * «г. Краснодар, ул. Красная, д. 10, кв. 5 (домофон 25)» — квартира и
 * комментарий добавляются только когда заполнены. Используется и для
 * карточки адреса в кабинете, и для готовой строки в
 * `orders.delivery_address` при выборе сохранённого адреса на
 * оформлении (Таск 5).
 */
function formatAddress(array $address): string
{
    $parts = [
        'г. ' . $address['city'],
        'ул. ' . $address['street'],
        'д. ' . $address['house'],
    ];

    if (!empty($address['apartment'])) {
        $parts[] = 'кв. ' . $address['apartment'];
    }

    $result = implode(', ', $parts);

    if (!empty($address['comment'])) {
        $result .= ' (' . $address['comment'] . ')';
    }

    return $result;
}
