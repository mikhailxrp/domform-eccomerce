<?php

declare(strict_types=1);

/** @var array $item строка из calculateCartTotals(): getCartItems() + line_total/available */

$isAvailable       = $item['available'];
$isShowroomSample  = (bool) $item['is_showroom_sample'];
$metaText          = trim(
    ($item['material'] ?? '') . ($item['material'] !== null && $item['color'] !== null ? ', ' : '') . ($item['color'] ?? '')
);
$productUrl    = '/product/' . $item['product_slug'];
$imageUrl      = $item['image_path'] !== null ? '/' . ltrim($item['image_path'], '/') : '/assets/images/product/product-01.jpg';
$itemHasDiscount = hasDiscount($item['discount_percent'] ?? null);
?>
<tr class="cart-row<?= $isAvailable ? '' : ' cart-row--unavailable' ?>">
    <td class="product-thumb">
        <img src="<?= e($imageUrl) ?>" alt="<?= e($item['product_name']) ?>">
    </td>
    <td class="product-info">
        <h6 class="name"><a href="<?= e($productUrl) ?>"><?= e($item['product_name']) ?></a></h6>
        <?php if ($metaText !== ''): ?>
            <div class="product-size-color"><p><?= e($metaText) ?></p></div>
        <?php endif; ?>
        <?php if ($itemHasDiscount): ?>
            <div class="product-prices">
                <span class="old-price"><?= formatPrice($item['old_price']) ?></span>
                <span class="sale-price"><?= formatPrice($item['price']) ?></span>
            </div>
        <?php else: ?>
            <p class="price"><?= formatPrice($item['price']) ?></p>
        <?php endif; ?>
        <?php if (!$isAvailable): ?>
            <p class="cart-row__unavailable-note">Недоступен для заказа — товар зарезервирован</p>
        <?php endif; ?>
    </td>
    <td class="quantity" data-label="Количество">
        <form method="post" action="/cart/update" class="cart-row__quantity-form">
            <?= csrfField() ?>
            <input type="hidden" name="item_id" value="<?= e((string) $item['id']) ?>">
            <div class="product-quantity d-inline-flex">
                <button type="button" class="sub" aria-label="Уменьшить количество">-</button>
                <input type="text" name="quantity" value="<?= e((string) $item['quantity']) ?>" inputmode="numeric">
                <button type="button" class="add" aria-label="Увеличить количество"<?= $isShowroomSample ? ' disabled' : '' ?>>+</button>
            </div>
        </form>
    </td>
    <td class="product-total-price" data-label="Сумма">
        <span class="price"><?= formatPrice($item['line_total']) ?></span>
    </td>
    <td class="product-action">
        <form method="post" action="/cart/remove">
            <?= csrfField() ?>
            <input type="hidden" name="item_id" value="<?= e((string) $item['id']) ?>">
            <button type="submit" class="remove" aria-label="Удалить товар"><i class="pe-7s-trash"></i></button>
        </form>
    </td>
</tr>
