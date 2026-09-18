<?php

declare(strict_types=1);

/** @var array<int,array> $items строки calculateCartTotals()+price_changed */
/** @var string $total */
/** @var bool $isBlocked */
?>
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
                    <tr<?= $item['available'] ? '' : ' class="checkout-summary__row--unavailable"' ?>>
                        <td class="Product-name">
                            <p><?= e($item['product_name']) ?> × <?= e((string) $item['quantity']) ?></p>
                            <?php if (!$item['available']): ?>
                                <p class="checkout-summary__note">Недоступен для заказа — предложим такой же Вариант под заказ</p>
                            <?php elseif ($item['price_changed']): ?>
                                <p class="checkout-summary__note">Цена изменилась: было <?= formatPrice($item['price_snapshot']) ?>, стало <?= formatPrice($item['price']) ?></p>
                            <?php endif; ?>
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
                    <td class="total-price"><p><?= formatPrice($total) ?></p></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <p class="cart-shipping-note">Стоимость доставки уточняется при подтверждении заказа.</p>

    <?php if ($isBlocked): ?>
        <div class="checkout-info mt-30">
            <p class="info-header error"><i class="fa fa-exclamation-circle"></i> Цена или доступность позиций изменились. Примите изменения, чтобы продолжить оформление.</p>
        </div>
        <form method="post" action="/checkout/accept-changes" class="single-form">
            <?= csrfField() ?>
            <button type="submit" class="btn btn-outline-dark btn-block">Принять изменения</button>
        </form>
    <?php else: ?>
        <div class="single-form">
            <button type="submit" class="btn btn-primary btn-hover-dark d-block" form="checkout-form">Оформить заказ</button>
        </div>
    <?php endif; ?>
</div>
