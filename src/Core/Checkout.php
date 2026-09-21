<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Validation.php';
require_once ROOT_PATH . '/src/Core/Address.php';

/**
 * Чистая нормализация и валидация формы оформления заказа — без
 * обращения к БД/сессии (`tests/bootstrap.php` не поднимает сессию),
 * по образцу `Core/Cart.php`. Пересчёт цены/остатка из БД —
 * `CheckoutController` (Таск 4). Поля адреса доставки переиспользуют
 * `validateAddressInput()` (`Core/Address.php`, Таск 4) — тот же состав
 * адреса, что в книге адресов кабинета (Таск 5, `phase-7.md`).
 */

const FULFILLMENT_DELIVERY = 'delivery';
const FULFILLMENT_PICKUP   = 'pickup';

const FULFILLMENT_LABELS = [
    FULFILLMENT_DELIVERY => 'Доставка',
    FULFILLMENT_PICKUP   => 'Самовывоз',
];

const PAYMENT_CARD_ONLINE   = 'card_online';
const PAYMENT_CASH          = 'cash';
const PAYMENT_BANK_TRANSFER = 'bank_transfer';

/**
 * `BR-001` — все способы оплаты доступны при любом способе получения,
 * список не фильтруется по `fulfillment_method`.
 */
const PAYMENT_METHOD_LABELS = [
    PAYMENT_CARD_ONLINE   => 'Картой онлайн',
    PAYMENT_CASH          => 'Наличными при получении',
    PAYMENT_BANK_TRANSFER => 'Банковский перевод',
];

function normalizeCheckoutInput(array $input): array
{
    $savedAddressId = trim((string) ($input['saved_address_id'] ?? ''));

    return [
        'name'               => trim((string) ($input['name'] ?? '')),
        'phone'              => normalizePhone((string) ($input['phone'] ?? '')),
        'email'              => mb_strtolower(trim((string) ($input['email'] ?? '')), 'UTF-8'),
        'fulfillment_method' => trim((string) ($input['fulfillment_method'] ?? '')),
        'address_city'       => trim((string) ($input['address_city'] ?? '')),
        'address_street'     => trim((string) ($input['address_street'] ?? '')),
        'address_house'      => trim((string) ($input['address_house'] ?? '')),
        'address_apartment'  => trim((string) ($input['address_apartment'] ?? '')),
        'address_comment'    => trim((string) ($input['address_comment'] ?? '')),
        'save_address'       => (string) ($input['save_address'] ?? '') === '1',
        'saved_address_id'   => ctype_digit($savedAddressId) ? (int) $savedAddressId : null,
        'comment'            => trim((string) ($input['comment'] ?? '')),
        'payment_method'     => trim((string) ($input['payment_method'] ?? '')),
        'create_account'     => (string) ($input['create_account'] ?? '') === '1',
        'password'           => (string) ($input['password'] ?? ''),
    ];
}

/**
 * `$input` — результат `normalizeCheckoutInput()`. `$isAuthenticated`
 * покупатель не видит чекбокс «Создать аккаунт?» (`checkout/index.php`)
 * — поля `email`/`password` для создания аккаунта не проверяются.
 * Поля адреса проверяются только при `fulfillment_method === delivery`
 * — при самовывозе они скрыты в форме (`applyCheckoutFulfillmentVisibility()`,
 * `app.js`) и не обязательны, как раньше с одним `delivery_address`.
 */
function validateCheckoutInput(array $input, bool $isAuthenticated): array
{
    $requiresAccountFields = $input['create_account'] && !$isAuthenticated;
    $isDelivery            = $input['fulfillment_method'] === FULFILLMENT_DELIVERY;

    $addressErrors = validateAddressInput([
        'city'      => $input['address_city'],
        'street'    => $input['address_street'],
        'house'     => $input['address_house'],
        'apartment' => $input['address_apartment'],
        'comment'   => $input['address_comment'],
    ]);

    $errors = [
        'name'               => $input['name'] === '',
        'phone'              => !validatePhone($input['phone']),
        'email'              => $input['email'] !== '' && !validateEmail($input['email']),
        'fulfillment_method' => !array_key_exists($input['fulfillment_method'], FULFILLMENT_LABELS),
        'address_city'       => $isDelivery && $addressErrors['city'],
        'address_street'     => $isDelivery && $addressErrors['street'],
        'address_house'      => $isDelivery && $addressErrors['house'],
        'address_apartment'  => $isDelivery && $addressErrors['apartment'],
        'address_comment'    => $isDelivery && $addressErrors['comment'],
        'comment'            => $input['comment'] === '',
        'payment_method'     => !array_key_exists($input['payment_method'], PAYMENT_METHOD_LABELS),
    ];

    if ($requiresAccountFields) {
        $errors['email']    = $errors['email'] || $input['email'] === '';
        $errors['password'] = !validatePassword($input['password']);
    } else {
        $errors['password'] = false;
    }

    return $errors;
}
