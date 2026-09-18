<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Cart.php';
require_once ROOT_PATH . '/src/Core/Cart.php';

class CartController
{
    public function index(): void
    {
        $items  = getCartItems(cartOwner());
        $totals = calculateCartTotals($items);

        // Самоисцеление кэша счётчика (functions.php:cacheCartCount()) —
        // сумма количеств уже известна из только что прочитанных строк,
        // лишнего запроса нет.
        cacheCartCount((int) array_sum(array_column($items, 'quantity')));

        render('cart/index', [
            'title' => 'Корзина',
            'items' => $totals['items'],
            'total' => $totals['total'],
        ]);
    }

    public function add(): void
    {
        requireCsrf();

        $owner     = cartOwner();
        $variantId = (int) input('variant_id');
        $color     = trim((string) input('color', ''));
        $quantity  = (int) input('quantity', 1);

        $added = addCartItem($owner, $variantId, $color !== '' ? $color : null, $quantity);
        refreshCartCount($owner);

        setFlash(
            $added ? 'success' : 'error',
            $added ? 'Товар добавлен в корзину.' : 'Не удалось добавить товар в корзину.'
        );

        $this->redirectBack();
    }

    public function update(): void
    {
        requireCsrf();

        $owner    = cartOwner();
        $itemId   = (int) input('item_id');
        $quantity = (int) input('quantity');

        updateCartItemQuantity($owner, $itemId, $quantity);
        refreshCartCount($owner);

        redirect('/cart');
    }

    public function remove(): void
    {
        requireCsrf();

        $owner  = cartOwner();
        $itemId = (int) input('item_id');
        removeCartItem($owner, $itemId);
        refreshCartCount($owner);

        setFlash('success', 'Товар удалён из корзины.');
        redirect('/cart');
    }

    public function clear(): void
    {
        requireCsrf();

        $owner = cartOwner();
        clearCart($owner);
        refreshCartCount($owner);

        setFlash('success', 'Корзина очищена.');
        redirect('/cart');
    }

    /**
     * Назад туда, откуда пришла форма (карточка/мини-карточка) — берём
     * только путь из Referer, без хоста: чужой/поддельный Referer не
     * может увести редирект на внешний сайт (open redirect). Нет
     * валидного Referer — на каталог.
     */
    private function redirectBack(): void
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $path    = parse_url($referer, PHP_URL_PATH);
        $query   = parse_url($referer, PHP_URL_QUERY);

        if (!is_string($path) || !str_starts_with($path, '/') || str_starts_with($path, '//')) {
            redirect('/catalog');
        }

        redirect($query !== null ? $path . '?' . $query : $path);
    }
}
