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
    ],
];
