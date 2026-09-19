<?php

declare(strict_types=1);

/** @var string $title */
/** @var string $lookupPhone */
/** @var array<string, mixed>|null $foundCustomer */
/** @var array<string, string> $fulfillmentOptions */
/** @var array<string, string> $paymentOptions */
/** @var string $manualOrderToken */
/** @var array<string, mixed> $old */
/** @var array<string, bool> $errors */
/** @var int $itemRows */

include ROOT_PATH . '/src/Views/layout/admin-header.php';

$customerMode  = $old['customer_mode'] ?? ($foundCustomer !== null ? MANUAL_ORDER_CUSTOMER_USER : MANUAL_ORDER_CUSTOMER_GUEST);
$userId        = (int) ($old['user_id'] ?? ($foundCustomer['id'] ?? 0));
$guestName     = (string) ($old['guest_name'] ?? '');
$guestPhone    = (string) ($old['guest_phone'] ?? $lookupPhone);
$guestEmail    = (string) ($old['guest_email'] ?? '');
$fulfillment   = (string) ($old['fulfillment_method'] ?? FULFILLMENT_PICKUP);
$address       = (string) ($old['delivery_address'] ?? '');
$paymentMethod = (string) ($old['payment_method'] ?? PAYMENT_CASH);
$comment       = (string) ($old['comment'] ?? '');
$prepaidAmount = (string) ($old['prepaid_amount'] ?? '');
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0">Новый заказ</h4>
        <p class="mb-0"><a href="/admin/orders">← К списку заказов</a></p>
    </div>
</div>

<div class="card custom-card">
    <div class="card-header">
        <div class="card-title">Найти покупателя по телефону</div>
    </div>
    <div class="card-body">
        <form method="get" action="/admin/orders/create" class="row g-2 align-items-end" data-customer-lookup-form>
            <div class="col-md-4">
                <label for="lookup-phone" class="form-label">Телефон</label>
                <input type="text" id="lookup-phone" name="phone" class="form-control" value="<?= e($lookupPhone) ?>" data-customer-lookup-input>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">Найти</button>
            </div>
            <div class="col-md-6" data-customer-lookup-result>
                <?php if ($foundCustomer !== null): ?>
                    <div class="alert alert-info mb-0">Найден покупатель: <?= e($foundCustomer['name']) ?> (<?= e((string) $foundCustomer['phone']) ?>)</div>
                <?php elseif ($lookupPhone !== ''): ?>
                    <div class="alert alert-secondary mb-0">Покупатель не найден — заполните контакты гостя ниже.</div>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<form method="post" action="/admin/orders" id="manual-order-form">
    <?= csrfField() ?>
    <input type="hidden" name="manual_order_token" value="<?= e($manualOrderToken) ?>">
    <input type="hidden" name="phone" value="<?= e($lookupPhone) ?>">

    <div class="card custom-card">
        <div class="card-header">
            <div class="card-title">Клиент</div>
        </div>
        <div class="card-body">
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="customer_mode" id="customer-mode-user" value="<?= e(MANUAL_ORDER_CUSTOMER_USER) ?>" <?= $customerMode === MANUAL_ORDER_CUSTOMER_USER ? 'checked' : '' ?> data-customer-mode-user>
                <label class="form-check-label" for="customer-mode-user">Оформить на найденного покупателя</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="customer_mode" id="customer-mode-guest" value="<?= e(MANUAL_ORDER_CUSTOMER_GUEST) ?>" <?= $customerMode === MANUAL_ORDER_CUSTOMER_GUEST ? 'checked' : '' ?> data-customer-mode-guest>
                <label class="form-check-label" for="customer-mode-guest">Оформить как гостя</label>
            </div>
            <?php if (!empty($errors['user_id'])): ?>
                <div class="invalid-feedback d-block">Сначала найдите покупателя по телефону.</div>
            <?php endif; ?>

            <input type="hidden" name="user_id" value="<?= $userId > 0 ? e((string) $userId) : '' ?>" data-customer-mode-user-id>

            <div class="row g-2 mt-2">
                <div class="col-md-4">
                    <label for="guest-name" class="form-label">Имя гостя</label>
                    <input type="text" id="guest-name" name="guest_name" class="form-control <?= !empty($errors['guest_name']) ? 'is-invalid' : '' ?>" value="<?= e($guestName) ?>">
                    <?php if (!empty($errors['guest_name'])): ?>
                        <div class="invalid-feedback">Введите имя.</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label for="guest-phone" class="form-label">Телефон гостя</label>
                    <input type="text" id="guest-phone" name="guest_phone" class="form-control <?= !empty($errors['guest_phone']) ? 'is-invalid' : '' ?>" value="<?= e($guestPhone) ?>" data-guest-phone-input>
                    <?php if (!empty($errors['guest_phone'])): ?>
                        <div class="invalid-feedback">Введите корректный телефон, например +7 900 123-45-67.</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label for="guest-email" class="form-label">Email гостя (необязательно)</label>
                    <input type="email" id="guest-email" name="guest_email" class="form-control <?= !empty($errors['guest_email']) ? 'is-invalid' : '' ?>" value="<?= e($guestEmail) ?>">
                    <?php if (!empty($errors['guest_email'])): ?>
                        <div class="invalid-feedback">Введите корректный email.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card custom-card">
                <div class="card-header">
                    <div class="card-title">Получение и оплата</div>
                </div>
                <div class="card-body">
                    <label class="form-label d-block">Способ получения</label>
                    <?php foreach ($fulfillmentOptions as $value => $label): ?>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="fulfillment_method" id="fulfillment-<?= e($value) ?>" value="<?= e($value) ?>" <?= $fulfillment === $value ? 'checked' : '' ?>>
                            <label class="form-check-label" for="fulfillment-<?= e($value) ?>"><?= e($label) ?></label>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!empty($errors['fulfillment_method'])): ?>
                        <div class="invalid-feedback d-block">Выберите способ получения.</div>
                    <?php endif; ?>

                    <div class="mb-3 mt-2">
                        <label for="delivery-address" class="form-label">Адрес доставки</label>
                        <textarea id="delivery-address" name="delivery_address" class="form-control <?= !empty($errors['delivery_address']) ? 'is-invalid' : '' ?>" rows="2"><?= e($address) ?></textarea>
                        <?php if (!empty($errors['delivery_address'])): ?>
                            <div class="invalid-feedback">Укажите адрес доставки.</div>
                        <?php endif; ?>
                    </div>

                    <label class="form-label d-block">Способ оплаты</label>
                    <?php foreach ($paymentOptions as $value => $label): ?>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="payment_method" id="payment-<?= e($value) ?>" value="<?= e($value) ?>" <?= $paymentMethod === $value ? 'checked' : '' ?>>
                            <label class="form-check-label" for="payment-<?= e($value) ?>"><?= e($label) ?></label>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!empty($errors['payment_method'])): ?>
                        <div class="invalid-feedback d-block">Выберите способ оплаты.</div>
                    <?php endif; ?>

                    <div class="mb-3 mt-3">
                        <label for="prepaid-amount" class="form-label">Сумма предоплаты</label>
                        <input type="text" id="prepaid-amount" name="prepaid_amount" class="form-control <?= !empty($errors['prepaid_amount']) ? 'is-invalid' : '' ?>" value="<?= e($prepaidAmount) ?>" placeholder="Например, 10500.00">
                        <?php if (!empty($errors['prepaid_amount'])): ?>
                            <div class="invalid-feedback">Введите сумму предоплаты больше нуля.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card custom-card">
                <div class="card-header">
                    <div class="card-title">Комментарий</div>
                </div>
                <div class="card-body">
                    <textarea name="comment" class="form-control" rows="4" placeholder="Ткань, размер, пожелания по доставке"><?= e($comment) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card custom-card">
        <div class="card-header">
            <div class="card-title">Позиции</div>
        </div>
        <div class="card-body">
            <?php if (!empty($errors['items'])): ?>
                <div class="alert alert-danger">Добавьте хотя бы одну позицию — проверьте, что артикул найден.</div>
            <?php endif; ?>

            <div data-variant-picker-list>
                <?php for ($i = 0; $i < $itemRows; $i++):
                    $index     = $i;
                    $sku       = (string) ($old['items'][$i]['sku'] ?? '');
                    $color     = (string) ($old['items'][$i]['color'] ?? '');
                    $quantity  = (int) ($old['items'][$i]['quantity'] ?? 1);
                    $variantId = (int) ($old['items'][$i]['variant_id'] ?? 0);
                ?>
                    <div class="row g-2 align-items-end position-relative mb-2" data-variant-picker data-variant-picker-row>
                        <?php include ROOT_PATH . '/src/Views/components/admin/variant-picker-fields.php'; ?>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-outline-danger w-100" data-variant-picker-remove>Убрать</button>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

            <button type="button" class="btn btn-outline-primary btn-sm" data-variant-picker-add>Добавить позицию</button>

            <template data-variant-picker-template>
                <div class="row g-2 align-items-end position-relative mb-2" data-variant-picker data-variant-picker-row>
                    <?php
                    $index     = '__INDEX__';
                    $sku       = '';
                    $color     = '';
                    $quantity  = 1;
                    $variantId = 0;
                    include ROOT_PATH . '/src/Views/components/admin/variant-picker-fields.php';
                    ?>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-outline-danger w-100" data-variant-picker-remove>Убрать</button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <button type="submit" class="btn btn-primary mb-4">Создать заказ</button>
</form>

<?php include ROOT_PATH . '/src/Views/layout/admin-footer.php'; ?>
