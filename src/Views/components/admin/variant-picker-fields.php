<?php

declare(strict_types=1);

/**
 * Поля позиции без обёртки `<form>` — переиспользуются и в
 * самостоятельной форме `variant-picker.php` (добавление позиции в уже
 * существующий Заказ, Таск 4), и как повторяющийся блок внутри общей
 * формы создания Заказа (Таск 5), где обёртывающую `<form>` даёт сама
 * страница. `$index` — `null` для одиночного использования (плоские
 * имена `sku`/`variant_id`/`color`/`quantity`, как ждёт
 * `AdminOrderController::addItem()`), иначе целое число или строка
 * `__INDEX__` (шаблон для JS-клонирования) — имена полей становятся
 * `items[{index}][...]`.
 */

/** @var int|string|null $index */
/** @var string $sku */
/** @var string $color */
/** @var int $quantity */
/** @var int $variantId */

$fieldName = static fn (string $field): string => $index === null ? $field : "items[{$index}][{$field}]";
$fieldId   = static fn (string $field): string => $index === null ? "variant-picker-{$field}" : "variant-picker-{$field}-{$index}";
?>
<input type="hidden" name="<?= e($fieldName('variant_id')) ?>" value="<?= $variantId > 0 ? e((string) $variantId) : '' ?>" data-variant-picker-id>
<div class="col-md-4 position-relative">
    <label for="<?= e($fieldId('sku')) ?>" class="form-label">Артикул или название товара</label>
    <input type="text" id="<?= e($fieldId('sku')) ?>" name="<?= e($fieldName('sku')) ?>" class="form-control" autocomplete="off" value="<?= e($sku) ?>" data-variant-picker-query>
    <div class="list-group position-absolute w-100 variant-picker__suggestions" data-variant-picker-suggestions></div>
</div>
<div class="col-md-3">
    <label for="<?= e($fieldId('color')) ?>" class="form-label">Цвет</label>
    <select id="<?= e($fieldId('color')) ?>" name="<?= e($fieldName('color')) ?>" class="form-select" data-variant-picker-color>
        <option value="">—</option>
        <?php if ($color !== ''): ?>
            <option value="<?= e($color) ?>" selected><?= e($color) ?></option>
        <?php endif; ?>
    </select>
</div>
<div class="col-md-2">
    <label for="<?= e($fieldId('quantity')) ?>" class="form-label">Количество</label>
    <input type="number" id="<?= e($fieldId('quantity')) ?>" name="<?= e($fieldName('quantity')) ?>" class="form-control" value="<?= e((string) $quantity) ?>" min="1">
</div>
