<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Product.php';
require_once ROOT_PATH . '/src/Models/Banner.php';
require_once ROOT_PATH . '/src/Models/Review.php';

class HomeController
{
    public function index(): void
    {
        render('home', [
            'title'              => 'Главная',
            'banners'            => getActiveBanners(),
            'featuredProducts'   => getFeaturedProducts(HOME_BLOCK_LIMIT),
            'storeReviews'       => getApprovedStoreReviews(HOME_BLOCK_LIMIT),
            'newestProducts'     => getNewestProducts(HOME_BLOCK_LIMIT),
            'discountedProducts' => getDiscountedProducts(HOME_BLOCK_LIMIT),
        ]);
    }
}
