<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<int,array> $items строки calculateCartTotals()+price_changed */
/** @var string $total */
/** @var bool $isBlocked есть неприменённое изменение цены или недоступный образец */
/** @var bool $isAuthenticated */
/** @var array{name:string,phone:string,email:string} $prefill */
/** @var array<int,array> $addresses сохранённые адреса авторизованного (Модель Address) */
/** @var array<string,string> $addressPrefill address_city/address_street/address_house/address_apartment/address_comment */
/** @var array<string,string> $fulfillmentOptions */
/** @var array<string,string> $paymentOptions */
/** @var string $checkoutToken */
/** @var array<string,string> $old */
/** @var array<string,bool> $errors */

include ROOT_PATH . '/src/Views/layout/header.php';

$breadcrumbs = [['name' => 'Оформление заказа']];
?>

<main>
    <?php include ROOT_PATH . '/src/Views/components/flash.php'; ?>

    <div class="section page-banner-section page-banner-section--cart">
        <div class="container">
            <div class="page-banner-content">
                <h1 class="title">Оформление заказа</h1>
                <?php include ROOT_PATH . '/src/Views/components/breadcrumbs.php'; ?>
            </div>
        </div>
    </div>

    <div class="section section-padding">
        <div class="container">
            <div class="checkout-call">
                <a href="tel:<?= e(SHOP_PHONE_TEL) ?>" class="btn btn-outline-dark"><i class="fa fa-phone"></i> <?= e(SHOP_PHONE) ?></a>
                <a href="<?= e(SHOP_WHATSAPP_URL) ?>" class="btn btn-outline-dark" target="_blank" rel="noopener"><i class="fa fa-whatsapp"></i> Написать в WhatsApp</a>
            </div>

            <div class="row">
                <div class="col-lg-7">
                    <form method="post" action="/checkout" id="checkout-form" novalidate>
                        <?= csrfField() ?>
                        <input type="hidden" name="checkout_token" value="<?= e($checkoutToken) ?>">
                        <div class="checkout-form">
                            <div class="checkout-title">
                                <h4 class="title">Контактные данные</h4>
                            </div>

                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="single-form">
                                        <input
                                            type="text"
                                            name="name"
                                            placeholder="Имя *"
                                            class="<?= !empty($errors['name']) ? 'is-invalid' : '' ?>"
                                            value="<?= e($old['name'] ?? $prefill['name']) ?>"
                                            required
                                        >
                                        <?php if (!empty($errors['name'])): ?>
                                            <div class="invalid-feedback">Введите имя.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="single-form">
                                        <input
                                            type="tel"
                                            name="phone"
                                            placeholder="Телефон *"
                                            class="<?= !empty($errors['phone']) ? 'is-invalid' : '' ?>"
                                            value="<?= e($old['phone'] ?? $prefill['phone']) ?>"
                                            required
                                        >
                                        <?php if (!empty($errors['phone'])): ?>
                                            <div class="invalid-feedback">Введите корректный телефон, например +7 900 123-45-67.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-sm-12">
                                    <div class="single-form">
                                        <input
                                            type="email"
                                            name="email"
                                            placeholder="Email"
                                            class="<?= !empty($errors['email']) ? 'is-invalid' : '' ?>"
                                            value="<?= e($old['email'] ?? $prefill['email']) ?>"
                                        >
                                        <?php if (!empty($errors['email'])): ?>
                                            <div class="invalid-feedback">Введите корректный email.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="checkout-title">
                                <h4 class="title">Способ получения</h4>
                            </div>
                            <ul class="checkout-fulfillment">
                                <?php foreach ($fulfillmentOptions as $value => $label): ?>
                                    <li class="radio">
                                        <input
                                            type="radio"
                                            name="fulfillment_method"
                                            id="fulfillment-<?= e($value) ?>"
                                            value="<?= e($value) ?>"
                                            data-checkout-fulfillment
                                            <?= ($old['fulfillment_method'] ?? FULFILLMENT_DELIVERY) === $value ? 'checked' : '' ?>
                                        >
                                        <label for="fulfillment-<?= e($value) ?>"><span></span> <?= e($label) ?></label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php if (!empty($errors['fulfillment_method'])): ?>
                                <div class="invalid-feedback d-block">Выберите способ получения.</div>
                            <?php endif; ?>

                            <div id="checkout-delivery-address">
                                <?php if ($addresses !== []): ?>
                                    <div class="single-form">
                                        <label class="form-label" for="checkout-saved-address">Выбрать сохранённый адрес</label>
                                        <select id="checkout-saved-address" data-checkout-address>
                                            <option value="">Свой адрес</option>
                                            <?php foreach ($addresses as $address): ?>
                                                <option
                                                    value="<?= e((string) $address['id']) ?>"
                                                    data-city="<?= e($address['city']) ?>"
                                                    data-street="<?= e($address['street']) ?>"
                                                    data-house="<?= e($address['house']) ?>"
                                                    data-apartment="<?= e((string) ($address['apartment'] ?? '')) ?>"
                                                    data-comment="<?= e((string) ($address['comment'] ?? '')) ?>"
                                                    <?= (int) $address['is_default'] === 1 && !isset($old['address_city']) ? 'selected' : '' ?>
                                                >
                                                    <?= e($address['title'] !== null ? $address['title'] : formatAddress($address)) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>

                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="single-form">
                                            <input
                                                type="text"
                                                name="address_city"
                                                placeholder="Город *"
                                                class="<?= !empty($errors['address_city']) ? 'is-invalid' : '' ?>"
                                                value="<?= e($old['address_city'] ?? $addressPrefill['address_city']) ?>"
                                                required
                                            >
                                            <?php if (!empty($errors['address_city'])): ?>
                                                <div class="invalid-feedback">Укажите город.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="single-form">
                                            <input
                                                type="text"
                                                name="address_street"
                                                placeholder="Улица *"
                                                class="<?= !empty($errors['address_street']) ? 'is-invalid' : '' ?>"
                                                value="<?= e($old['address_street'] ?? $addressPrefill['address_street']) ?>"
                                                required
                                            >
                                            <?php if (!empty($errors['address_street'])): ?>
                                                <div class="invalid-feedback">Укажите улицу.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="single-form">
                                            <input
                                                type="text"
                                                name="address_house"
                                                placeholder="Дом *"
                                                class="<?= !empty($errors['address_house']) ? 'is-invalid' : '' ?>"
                                                value="<?= e($old['address_house'] ?? $addressPrefill['address_house']) ?>"
                                                required
                                            >
                                            <?php if (!empty($errors['address_house'])): ?>
                                                <div class="invalid-feedback">Укажите дом.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="single-form">
                                            <input
                                                type="text"
                                                name="address_apartment"
                                                placeholder="Квартира/офис"
                                                class="<?= !empty($errors['address_apartment']) ? 'is-invalid' : '' ?>"
                                                value="<?= e($old['address_apartment'] ?? $addressPrefill['address_apartment']) ?>"
                                            >
                                            <?php if (!empty($errors['address_apartment'])): ?>
                                                <div class="invalid-feedback">Слишком длинное значение.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-sm-12">
                                        <div class="single-form">
                                            <textarea
                                                name="address_comment"
                                                placeholder="Подъезд, этаж, домофон"
                                                class="<?= !empty($errors['address_comment']) ? 'is-invalid' : '' ?>"
                                            ><?= e($old['address_comment'] ?? $addressPrefill['address_comment']) ?></textarea>
                                            <?php if (!empty($errors['address_comment'])): ?>
                                                <div class="invalid-feedback">Слишком длинный комментарий.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($isAuthenticated): ?>
                                    <div class="single-form checkbox-checkbox">
                                        <input type="checkbox" id="save_address" name="save_address" value="1" <?= ($old['save_address'] ?? false) === true ? 'checked' : '' ?>>
                                        <label for="save_address"> <span></span> Сохранить адрес в кабинете</label>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="single-form checkout-note">
                                <label class="form-label">Комментарий к заказу *</label>
                                <textarea
                                    name="comment"
                                    placeholder="Ткань, размер, пожелания по доставке — уточнит менеджер"
                                    class="<?= !empty($errors['comment']) ? 'is-invalid' : '' ?>"
                                    required
                                ><?= e($old['comment'] ?? '') ?></textarea>
                                <?php if (!empty($errors['comment'])): ?>
                                    <div class="invalid-feedback">Оставьте комментарий к заказу.</div>
                                <?php endif; ?>
                            </div>

                            <?php if (!$isAuthenticated): ?>
                                <div class="single-form checkbox-checkbox">
                                    <input type="checkbox" id="account" name="create_account" value="1" <?= ($old['create_account'] ?? '') === '1' ? 'checked' : '' ?>>
                                    <label for="account"> <span></span> Создать аккаунт?</label>
                                </div>

                                <div class="checkout-account">
                                    <?php
                                    $fieldName         = 'password';
                                    $fieldPlaceholder  = 'Пароль *';
                                    $fieldHasError     = !empty($errors['password']);
                                    $fieldErrorMessage = 'Пароль должен быть не короче 8 символов.';
                                    include ROOT_PATH . '/src/Views/components/password-field.php';
                                    ?>
                                </div>
                            <?php endif; ?>

                            <div class="checkout-title">
                                <h4 class="title">Способ оплаты</h4>
                            </div>
                            <div class="checkout-payment">
                                <ul>
                                    <?php foreach ($paymentOptions as $value => $label): ?>
                                        <li>
                                            <div class="single-payment">
                                                <div class="payment-radio radio">
                                                    <input
                                                        type="radio"
                                                        name="payment_method"
                                                        id="payment-<?= e($value) ?>"
                                                        value="<?= e($value) ?>"
                                                        <?= ($old['payment_method'] ?? PAYMENT_CASH) === $value ? 'checked' : '' ?>
                                                    >
                                                    <label for="payment-<?= e($value) ?>"><span></span> <?= e($label) ?></label>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php if (!empty($errors['payment_method'])): ?>
                                <div class="invalid-feedback d-block">Выберите способ оплаты.</div>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="col-lg-5">
                    <?php include ROOT_PATH . '/src/Views/components/checkout-summary.php'; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
