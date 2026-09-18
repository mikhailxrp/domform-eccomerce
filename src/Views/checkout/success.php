<?php

declare(strict_types=1);

/** @var string $title */
/** @var array $order строка orders */
/** @var array<int,array> $items строки order_items */
/** @var string $fulfillmentLabel */
/** @var string $paymentLabel */

include ROOT_PATH . '/src/Views/layout/header.php';

$breadcrumbs = [['name' => 'Заказ принят']];
?>

<main>
    <?php include ROOT_PATH . '/src/Views/components/flash.php'; ?>

    <div class="section page-banner-section page-banner-section--cart">
        <div class="container">
            <div class="page-banner-content">
                <h1 class="title">Заказ принят</h1>
                <?php include ROOT_PATH . '/src/Views/components/breadcrumbs.php'; ?>
            </div>
        </div>
    </div>

    <div class="section section-padding">
        <div class="container">
            <div class="checkout-info mt-30">
                <p class="info-header">
                    <i class="fa fa-check-circle"></i>
                    Заказ №<?= e((string) $order['id']) ?> принят, статус — «<?= e(orderStatusLabel($order['status'])) ?>».
                    Менеджер позвонит для согласования ткани, размера и стоимости доставки.
                </p>
                <?php if ($order['payment_method'] === 'card_online'): ?>
                    <p class="info-header">
                        <i class="fa fa-exclamation-circle"></i>
                        Вы выбрали оплату картой онлайн.
                    </p>
                <?php endif; ?>
            </div>

            <?php if ($order['payment_method'] === 'card_online'): ?>
                <div class="single-form">
                    <a href="/payment/stub" class="btn btn-dark btn-hover-primary">Перейти к оплате</a>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-7">
                    <div class="checkout-form">
                        <div class="checkout-title">
                            <h4 class="title">Детали заказа</h4>
                        </div>
                        <p><strong>Способ получения:</strong> <?= e($fulfillmentLabel) ?></p>
                        <?php if ($order['delivery_address'] !== null): ?>
                            <p><strong>Адрес доставки:</strong> <?= e($order['delivery_address']) ?></p>
                        <?php endif; ?>
                        <p><strong>Способ оплаты:</strong> <?= e($paymentLabel) ?></p>
                        <p><strong>Комментарий:</strong> <?= e($order['comment']) ?></p>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="checkout-order">
                        <div class="checkout-title">
                            <h4 class="title">Ваш заказ</h4>
                        </div>
                        <div class="checkout-order-table table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th class="Product-name">Товар</th>
                                        <th class="Product-price">Сумма</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td class="Product-name">
                                                <p><?= e($item['product_name']) ?> × <?= e((string) $item['quantity']) ?></p>
                                            </td>
                                            <td class="Product-price">
                                                <p><?= formatPrice($item['line_total']) ?></p>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td class="Product-name"><p>Итого</p></td>
                                        <td class="total-price"><p><?= formatPrice((string) $order['total']) ?></p></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <p class="cart-shipping-note">Стоимость доставки уточняется при подтверждении заказа.</p>
                        <div class="single-form">
                            <a href="/catalog" class="btn btn-dark btn-hover-primary d-block">Продолжить покупки</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
