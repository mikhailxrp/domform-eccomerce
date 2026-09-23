<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Product.php';
require_once ROOT_PATH . '/src/Models/Category.php';
require_once ROOT_PATH . '/src/Models/Review.php';
require_once ROOT_PATH . '/src/Models/User.php';
require_once ROOT_PATH . '/src/Models/Favorite.php';
require_once ROOT_PATH . '/src/Core/Review.php';

class ProductController
{
    private const RELATED_LIMIT = 4;

    /**
     * `$reviewOld`/`$reviewErrors` — только при повторном рендере после
     * неудачной отправки формы отзыва: `ReviewController::store()` при
     * ошибке валидации вызывает этот же метод напрямую, без редиректа
     * (`setFlash()` хранит только строку, не массив ошибок по полям —
     * тот же приём, что `CheckoutController::store()`/
     * `AdminOrderController::store()`). При обычном `GET` оба параметра
     * пусты — тогда имя/email подставляются из аккаунта авторизованного
     * Покупателя, как в чекауте.
     */
    public function show(string $slug, array $reviewOld = [], array $reviewErrors = []): void
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
                'sku'                => $variant['sku'],
                'material'           => $variant['material'],
                'mechanism_type'     => $variant['mechanism_type'],
                // Сырая DECIMAL-строка из БД, только для schema.org
                // (`buildProductSchema()`) — `data-variants` JS-блоку
                // хватает `price_formatted`, но лишнее поле в уже
                // существующем публичном JSON безопаснее, чем повторно
                // выводить деньги через `formatPrice()` и парсить назад.
                'price_raw'          => $variant['price'],
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

        $reviews = getApprovedProductReviews($productId);

        // Подстановка имени/email авторизованного Покупателя — только
        // на обычном заходе (форма ещё не заполнялась); после неудачной
        // отправки в `$reviewOld` уже то, что ввёл сам Покупатель, его
        // не перетираем. Email в сессии не хранится (`functions.php`,
        // `currentUser()` — только id/name/role), поэтому за ним отдельный
        // поход в `findUserById()`, тем же приёмом, что `CheckoutController`.
        if ($reviewOld === []) {
            $user = currentUser();
            if ($user !== null) {
                $account = findUserById($user['id']);
                if ($account !== null) {
                    $reviewOld = ['name' => $account['name'], 'email' => $account['email']];
                }
            }
        }

        $currentUserId = currentUser()['id'] ?? null;

        $canonical = rtrim(APP_URL, '/') . '/product/' . $slug;

        // Шаблон описания по `tz.md` §13.2: название, категория, краткая
        // характеристика, упоминание Краснодара — первая строка
        // характеристик (`$specs` уже отсортирована `sort_order` в
        // Model), не весь список, чтобы уложиться в 160 символов.
        $firstSpec     = $specs[0] ?? null;
        $specFragment  = $firstSpec !== null ? sprintf('%s: %s. ', $firstSpec['name'], $firstSpec['value']) : '';
        $description   = sprintf(
            '%s — %s на заказ в Краснодаре. %sЦена, фото и характеристики на сайте ДомФорм.',
            $product['name'],
            $category['name'],
            $specFragment,
        );

        render('product/show', [
            'title'         => $product['name'],
            'product'       => $product,
            'breadcrumbs'   => $breadcrumbs,
            'description'   => $description,
            'canonical'     => $canonical,
            'productSchema' => $this->buildProductSchema($product, $variantsData, $description, $canonical),
            'variants'      => $variantsData,
            'specs'         => $specs,
            'related'       => $related,
            'reviews'       => $reviews,
            'averageRating' => averageRating($reviews),
            'reviewOld'     => $reviewOld,
            'reviewErrors'  => $reviewErrors,
            'favoriteIds'   => $currentUserId !== null ? getFavoriteProductIds($currentUserId) : [],
        ]);
    }

    /**
     * `schema.org/Product` (`tz.md` §13.3) — один `Offer` на Вариант, а
     * не единая цена/наличие на Товар: у Вариантов разная цена и разный
     * статус (выставочный образец готов сразу — `InStock`, остальное —
     * `MadeToOrder`, обе даты — реальные значения `ItemAvailability`
     * schema.org). Цена берётся из того же `$variantsData`, что уже
     * показан покупателю (`price_raw`) — расхождение видимой цены и
     * разметки здесь структурно невозможно, не только по факту данных.
     */
    private function buildProductSchema(array $product, array $variantsData, string $fallbackDescription, string $canonical): array
    {
        $offers = array_map(static function (array $variant) use ($canonical): array {
            return [
                '@type'         => 'Offer',
                'sku'           => $variant['sku'],
                'price'         => $variant['price_raw'],
                'priceCurrency' => 'RUB',
                'availability'  => $variant['is_showroom_sample']
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/MadeToOrder',
                'url'           => $canonical,
            ];
        }, $variantsData);

        $primaryImagePath = $variantsData[0]['images'][0]['path'] ?? null;
        $productDescription = (string) ($product['description'] ?? '');

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => $product['name'],
            'description' => $productDescription !== '' ? $productDescription : $fallbackDescription,
            'sku'         => $variantsData[0]['sku'] ?? null,
            'category'    => $product['category_name'],
            'url'         => $canonical,
            'offers'      => $offers,
        ];

        if ($primaryImagePath !== null) {
            $schema['image'] = rtrim(APP_URL, '/') . $primaryImagePath;
        }

        return $schema;
    }
}
