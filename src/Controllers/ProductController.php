<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Product.php';
require_once ROOT_PATH . '/src/Models/Category.php';

class ProductController
{
    private const RELATED_LIMIT = 4;

    public function show(string $slug): void
    {
        $product = findProductBySlug($slug);
        if ($product === null) {
            abort404();
        }

        $productId = (int) $product['id'];
        $categoryId = (int) $product['category_id'];

        $variants   = getProductVariants($productId);
        $variantIds = array_map(static fn (array $variant): int => (int) $variant['id'], $variants);
        $images     = getVariantImages($variantIds);
        $specs      = getProductSpecs($productId);
        $related    = getRelatedProducts($productId, $categoryId, self::RELATED_LIMIT);

        $imagesByVariant = [];
        foreach ($images as $image) {
            $imagesByVariant[(int) $image['product_variant_id']][] = $image;
        }

        $variantsData = array_map(static function (array $variant) use ($imagesByVariant): array {
            $variantId = (int) $variant['id'];

            return [
                'id'                 => $variantId,
                'material'           => $variant['material'],
                'mechanism_type'     => $variant['mechanism_type'],
                'price_formatted'    => formatPrice($variant['price']),
                // Старая цена и процент скидки (`FR-DISC-002`, Таск 2
                // Фазы 6) — `old_price` уже сырая цена Варианта
                // (`getProductVariants()`, Таск 1), `discount_percent`
                // читает и `show.php` (SSR), и `app.js` (JSON), решают
                // показывать ли `old_price_formatted` через `hasDiscount()`.
                'old_price_formatted' => formatPrice($variant['old_price']),
                'discount_percent'   => $variant['discount_percent'],
                'production_time'    => $variant['production_time'],
                // Резервированный образец показывается как обычный
                // Вариант под заказ (`BR-003`, `BR-004`, Таск 5 Фазы 5)
                // — единственная точка, где `is_showroom_sample`
                // становится «эффективным» статусом, а не сырым
                // значением из БД; `variant-selector.php` и `app.js`
                // читают уже это готовое значение под тем же именем.
                'is_showroom_sample' => (bool) $variant['is_showroom_sample'] && !(bool) $variant['has_active_reserve'],
                'images'             => array_map(static fn (array $image): array => [
                    'color'     => $image['color'],
                    'is_swatch' => (bool) $image['is_swatch'],
                    'path'      => '/' . ltrim($image['path'], '/'),
                ], $imagesByVariant[$variantId] ?? []),
            ];
        }, $variants);

        $category = [
            'id'        => $categoryId,
            'parent_id' => $product['category_parent_id'] !== null ? (int) $product['category_parent_id'] : null,
            'name'      => $product['category_name'],
            'slug'      => $product['category_slug'],
        ];
        $breadcrumbs = array_merge(getCategoryPath($category), [['name' => $product['name']]]);

        render('product/show', [
            'title'       => $product['name'],
            'product'     => $product,
            'breadcrumbs' => $breadcrumbs,
            'variants'    => $variantsData,
            'specs'       => $specs,
            'related'     => $related,
        ]);
    }
}
