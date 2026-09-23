<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Product.php';
require_once ROOT_PATH . '/src/Models/Banner.php';
require_once ROOT_PATH . '/src/Models/Review.php';
require_once ROOT_PATH . '/src/Models/Category.php';
require_once ROOT_PATH . '/src/Models/Favorite.php';

class HomeController
{
    public function index(): void
    {
        // Вкладки блока «Товары» (карусель) — вкладка скрывается, если её
        // подборка пуста; блок целиком скрывается во View, если пусты все три.
        $productTabs = array_values(array_filter([
            ['label' => 'Хиты продаж', 'products' => getFeaturedProducts(HOME_BLOCK_LIMIT)],
            ['label' => 'Новинки', 'products' => getNewestProducts(HOME_BLOCK_LIMIT)],
            ['label' => 'Товары со скидкой', 'products' => getDiscountedProducts(HOME_BLOCK_LIMIT)],
        ], static function (array $tab): bool {
            return $tab['products'] !== [];
        }));

        // Вкладки блока «Лидеры продаж» — в отличие от «Хитов продаж»
        // выше (ручная отметка `is_featured`), тут реальные суммы
        // `order_items.quantity` по периодам (`getBestsellingProducts()`).
        $bestsellerTabs = array_values(array_filter([
            ['label' => 'Весь период', 'products' => getBestsellingProducts('all', HOME_BLOCK_LIMIT)],
            ['label' => 'Этот год', 'products' => getBestsellingProducts('year', HOME_BLOCK_LIMIT)],
            ['label' => 'Этот месяц', 'products' => getBestsellingProducts('month', HOME_BLOCK_LIMIT)],
        ], static function (array $tab): bool {
            return $tab['products'] !== [];
        }));

        // Баннеры категорий (карусель под Benefit) — реальное число
        // видимых Товаров на баннер (`getCategoryProductCount()`), не
        // фиксированный текст темы («15 Products»); категория без
        // видимых Товаров сейчас — баннер просто не рисуется.
        $categoryBanners = array_values(array_filter(array_map(static function (array $item): array {
            return $item + ['count' => getCategoryProductCount($item['slug'])];
        }, [
            ['slug' => 'divany', 'name' => 'Диваны', 'image' => 'products-01.webp'],
            ['slug' => 'kresla', 'name' => 'Кресла', 'image' => 'products-02.webp'],
            ['slug' => 'krovati', 'name' => 'Кровати', 'image' => 'products-03.webp'],
            ['slug' => 'shkafy', 'name' => 'Шкафы', 'image' => 'products-04.webp'],
        ]), static function (array $banner): bool {
            return $banner['count'] > 0;
        }));

        $user = currentUser();

        render('home', [
            'title'           => 'Главная',
            'canonical'       => rtrim(APP_URL, '/') . '/',
            'banners'         => getActiveBanners(),
            'productTabs'     => $productTabs,
            'bestsellerTabs'  => $bestsellerTabs,
            'categoryBanners' => $categoryBanners,
            'storeReviews'    => getApprovedStoreReviews(HOME_BLOCK_LIMIT),
            'favoriteIds'     => $user !== null ? getFavoriteProductIds($user['id']) : [],
        ]);
    }
}
