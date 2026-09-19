<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Slug.php';

/**
 * Чистая нормализация и валидация формы Товара (`FR-ADM-001`, Таск 8
 * Фазы 4) — без обращения к БД. Уникальность `sku` в самой БД (занят
 * другим Товаром) сюда не входит — это заботa
 * `createProductWithVariants()`/`updateProductWithVariants()`
 * (перехват SQLSTATE 23000), эта функция проверяет только повтор
 * внутри одной отправленной формы.
 */

// Число строк Варианта/характеристики на форме по умолчанию — `dod-
// global.md`/`phase-4.md`: форма без JS должна работать с одной
// строкой каждого вида; кнопки «Добавить» (admin.js) добавляют ещё.
const PRODUCT_FORM_DEFAULT_VARIANT_ROWS = 1;
const PRODUCT_FORM_DEFAULT_SPEC_ROWS    = 1;

function normalizeProductInput(array $input): array
{
    $name    = trim((string) ($input['name'] ?? ''));
    $slugRaw = trim((string) ($input['slug'] ?? ''));

    return [
        'name'                => $name,
        'slug'                => slugify($slugRaw !== '' ? $slugRaw : $name),
        'description'         => trim((string) ($input['description'] ?? '')),
        'is_featured'         => (string) ($input['is_featured'] ?? '') === '1',
        'is_active'           => (string) ($input['is_active'] ?? '') === '1',
        'category_ids'        => normalizeProductCategoryIds($input['categories'] ?? []),
        'primary_category_id' => (int) ($input['primary_category_id'] ?? 0),
        'specs'               => normalizeProductSpecs(is_array($input['specs'] ?? null) ? $input['specs'] : []),
        'variants'            => normalizeProductVariants(is_array($input['variants'] ?? null) ? $input['variants'] : []),
    ];
}

function normalizeProductCategoryIds(mixed $value): array
{
    $items = is_array($value) ? $value : [];

    $ids = [];
    foreach ($items as $item) {
        if (is_numeric($item) && (int) $item > 0) {
            $ids[(int) $item] = (int) $item;
        }
    }

    return array_values($ids);
}

/**
 * Пустая строка (ни названия, ни значения — незаполненный шаблонный
 * ряд формы) отбрасывается здесь, не доходит до валидации/сохранения.
 */
function normalizeProductSpecs(array $rawSpecs): array
{
    $specs = [];

    foreach ($rawSpecs as $rawSpec) {
        if (!is_array($rawSpec)) {
            continue;
        }

        $name  = trim((string) ($rawSpec['name'] ?? ''));
        $value = trim((string) ($rawSpec['value'] ?? ''));

        if ($name === '' && $value === '') {
            continue;
        }

        $specs[] = ['name' => $name, 'value' => $value];
    }

    return $specs;
}

/**
 * Пустая строка — незаполненный шаблонный ряд (ни `id` существующего
 * Варианта, ни `sku` нового) — отбрасывается здесь, до валидации.
 */
function normalizeProductVariants(array $rawVariants): array
{
    $variants = [];

    foreach ($rawVariants as $rawVariant) {
        if (!is_array($rawVariant)) {
            continue;
        }

        $id  = (int) ($rawVariant['id'] ?? 0);
        $sku = trim((string) ($rawVariant['sku'] ?? ''));

        if ($id <= 0 && $sku === '') {
            continue;
        }

        $variants[] = [
            'id'                 => $id,
            'sku'                => $sku,
            'material'           => trim((string) ($rawVariant['material'] ?? '')),
            'mechanism_type'     => trim((string) ($rawVariant['mechanism_type'] ?? '')),
            'price'              => trim((string) ($rawVariant['price'] ?? '')),
            'production_time'    => trim((string) ($rawVariant['production_time'] ?? '')),
            'is_showroom_sample' => (string) ($rawVariant['is_showroom_sample'] ?? '') === '1',
            'discount_percent'   => trim((string) ($rawVariant['discount_percent'] ?? '')),
            'is_active'          => (string) ($rawVariant['is_active'] ?? '') === '1',
        ];
    }

    return $variants;
}

/**
 * `$input` — результат `normalizeProductInput()`. Публикация
 * (`is_active` Товара) требует хотя бы один Вариант, отмеченный
 * активным, с корректной положительной ценой — критерий приёмки
 * `FR-ADM-001`/ТЗ (`phase-4.md`, Таск 8); `sku` проверяется на повтор
 * только внутри формы (регистронезависимо) — совпадение с уже
 * существующим в БД артикулом другого Товара перехватывается Model'ю
 * через SQLSTATE 23000.
 */
function validateProductInput(array $input): array
{
    $skuCounts = [];
    foreach ($input['variants'] as $variant) {
        if ($variant['sku'] === '') {
            continue;
        }
        $key             = mb_strtolower($variant['sku']);
        $skuCounts[$key] = ($skuCounts[$key] ?? 0) + 1;
    }

    $hasActiveVariantWithPrice = false;
    $variantErrors             = [];

    foreach ($input['variants'] as $index => $variant) {
        $priceValid = preg_match('/^\d+(\.\d{1,2})?$/', $variant['price']) === 1
            && bccomp($variant['price'], '0.00', 2) > 0;

        $discountValid = $variant['discount_percent'] === ''
            || (
                preg_match('/^\d+(\.\d{1,2})?$/', $variant['discount_percent']) === 1
                && bccomp($variant['discount_percent'], '99.99', 2) <= 0
            );

        if ($variant['is_active'] && $priceValid) {
            $hasActiveVariantWithPrice = true;
        }

        $variantErrors[$index] = [
            'sku'              => $variant['sku'] === '',
            'sku_duplicate'    => $variant['sku'] !== '' && ($skuCounts[mb_strtolower($variant['sku'])] ?? 0) > 1,
            'material'         => $variant['material'] === '',
            'production_time'  => $variant['production_time'] === '',
            'price'            => !$priceValid,
            'discount_percent' => !$discountValid,
        ];
    }

    $specErrors = [];
    foreach ($input['specs'] as $index => $spec) {
        $specErrors[$index] = [
            'name'  => $spec['name'] === '' && $spec['value'] !== '',
            'value' => $spec['value'] === '' && $spec['name'] !== '',
        ];
    }

    return [
        'name'                  => $input['name'] === '',
        'category_ids'          => $input['category_ids'] === [],
        'primary_category_id'   => $input['primary_category_id'] <= 0
            || !in_array($input['primary_category_id'], $input['category_ids'], true),
        'publish_needs_variant' => $input['is_active'] && !$hasActiveVariantWithPrice,
        'variants'              => $variantErrors,
        'specs'                 => $specErrors,
    ];
}

/**
 * `$errors` может быть произвольно вложенным (`variants`/`specs` —
 * массивы массивов по индексу) — обычный `in_array(true, $errors,
 * true)`, которым Controllers Фазы 4 проверяют плоские массивы ошибок
 * (`AdminOrderController`, `ManualOrder`), вложенность не обходит.
 */
function productFormHasErrors(array $errors): bool
{
    foreach ($errors as $error) {
        if (is_array($error)) {
            if (productFormHasErrors($error)) {
                return true;
            }
        } elseif ($error === true) {
            return true;
        }
    }

    return false;
}
