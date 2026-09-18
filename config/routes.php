<?php

declare(strict_types=1);

/**
 * Все маршруты приложения.
 * Формат: 'МЕТОД' => ['/путь' => ['ИмяКонтроллера', 'метод']]
 */

return [
    'GET' => [
        '/'                        => ['HomeController', 'index'],
        '/catalog'                 => ['CatalogController', 'index'],
        '/catalog/{slug}'          => ['CatalogController', 'category'],
        '/product/{slug}'          => ['ProductController', 'show'],
        '/search'                  => ['SearchController', 'index'],
        '/search/suggest'          => ['SearchController', 'suggest'],
        '/cart'                    => ['CartController', 'index'],
        '/checkout'                => ['CheckoutController', 'index'],
        '/checkout/success'        => ['CheckoutController', 'success'],
        '/login'                   => ['AuthController', 'showLogin'],
        '/register'                => ['AuthController', 'showRegister'],
        '/admin'                   => ['AdminController', 'index'],
        '/forgot-password'         => ['AuthController', 'showForgot'],
        '/reset-password/{token}'  => ['AuthController', 'showReset'],
    ],
    'POST' => [
        '/login'                   => ['AuthController', 'login'],
        '/register'                => ['AuthController', 'register'],
        '/logout'                  => ['AuthController', 'logout'],
        '/forgot-password'         => ['AuthController', 'forgot'],
        '/reset-password/{token}'  => ['AuthController', 'reset'],
        '/cart/add'                => ['CartController', 'add'],
        '/cart/update'             => ['CartController', 'update'],
        '/cart/remove'             => ['CartController', 'remove'],
        '/cart/clear'              => ['CartController', 'clear'],
        '/checkout/accept-changes' => ['CheckoutController', 'acceptChanges'],
        '/checkout'                => ['CheckoutController', 'store'],
    ],
];
