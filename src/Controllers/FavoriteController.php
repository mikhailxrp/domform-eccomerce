<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Favorite.php';
require_once ROOT_PATH . '/src/Models/Product.php';

class FavoriteController
{
    public function toggle(): void
    {
        requireAuth();
        requireCsrf();

        $productId = (int) input('product_id');

        if (findActiveProductById($productId) === null) {
            abort404();
        }

        $userId = (int) currentUser()['id'];
        toggleFavorite($userId, $productId);

        $this->redirectBack();
    }

    public function index(): void
    {
        requireAuth();

        $userId = (int) currentUser()['id'];

        render('account/favorites', [
            'title'         => 'Избранное',
            'activeSection' => 'favorites',
            'products'      => getFavoriteProducts($userId),
        ]);
    }

    public function remove(): void
    {
        requireAuth();
        requireCsrf();

        $userId    = (int) currentUser()['id'];
        $productId = (int) input('product_id');

        removeFavorite($userId, $productId);

        redirect('/account/favorites');
    }

    /**
     * Назад туда, откуда пришла форма (карточка/мини-карточка) — тот
     * же приём, что `CartController::redirectBack()`: только путь из
     * Referer, без хоста (чужой/поддельный Referer не уводит на
     * внешний сайт). Фолбэк — `/`, не `/catalog`: клик по сердцу
     * возможен с Главной, из поиска, с карточки Товара — не только из
     * каталога.
     */
    private function redirectBack(): void
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $path    = parse_url($referer, PHP_URL_PATH);
        $query   = parse_url($referer, PHP_URL_QUERY);

        if (!is_string($path) || !str_starts_with($path, '/') || str_starts_with($path, '//')) {
            redirect('/');
        }

        redirect($query !== null ? $path . '?' . $query : $path);
    }
}
