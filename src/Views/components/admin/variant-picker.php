<?php

declare(strict_types=1);

/**
 * Добавление позиции в состав Заказа (Таск 4 Фазы 4). Видимое поле —
 * артикул/название (`sku`): без JS Менеджер вводит точный артикул и
 * форма уходит обычным POST (`findActiveVariantIdBySku()` на сервере);
 * с JS поле превращается в подсказки (`admin.js`), которые заполняют
 * скрытый `variant_id` и список цветов Варианта.
 */

/** @var string $orderId */
?>
<form method="post" action="/admin/orders/<?= e($orderId) ?>/items" class="row g-2 align-items-end position-relative" data-variant-picker>
    <?= csrfField() ?>
    <input type="hidden" name="variant_id" value="" data-variant-picker-id>
    <div class="col-md-4 position-relative">
        <label for="variant-picker-sku" class="form-label">Артикул или название товара</label>
        <input type="text" id="variant-picker-sku" name="sku" class="form-control" autocomplete="off" required data-variant-picker-query>
        <div class="list-group position-absolute w-100 variant-picker__suggestions" data-variant-picker-suggestions></div>
    </div>
    <div class="col-md-3">
        <label for="variant-picker-color" class="form-label">Цвет</label>
        <select id="variant-picker-color" name="color" class="form-select" data-variant-picker-color>
            <option value="">—</option>
        </select>
    </div>
    <div class="col-md-2">
        <label for="variant-picker-qty" class="form-label">Количество</label>
        <input type="number" id="variant-picker-qty" name="quantity" class="form-control" value="1" min="1" required>
    </div>
    <div class="col-md-3">
        <button type="submit" class="btn btn-primary w-100">Добавить</button>
    </div>
</form>
