<?php

declare(strict_types=1);

/**
 * Добавление позиции в состав уже существующего Заказа (Таск 4 Фазы 4)
 * — самостоятельная форма вокруг `variant-picker-fields.php` (Таск 5:
 * тот же partial переиспользуется как повторяющийся блок без своей
 * `<form>` на странице создания Заказа). Видимое поле — артикул/
 * название (`sku`): без JS Менеджер вводит точный артикул и форма
 * уходит обычным POST (`findActiveVariantIdBySku()` на сервере); с JS
 * поле превращается в подсказки (`admin.js`), которые заполняют
 * скрытый `variant_id` и список цветов Варианта.
 */

/** @var string $orderId */

$index     = null;
$sku       = '';
$color     = '';
$quantity  = 1;
$variantId = 0;
?>
<form method="post" action="/admin/orders/<?= e($orderId) ?>/items" class="row g-2 align-items-end position-relative" data-variant-picker>
    <?= csrfField() ?>
    <?php include ROOT_PATH . '/src/Views/components/admin/variant-picker-fields.php'; ?>
    <div class="col-md-3">
        <button type="submit" class="btn btn-primary w-100">Добавить</button>
    </div>
</form>
