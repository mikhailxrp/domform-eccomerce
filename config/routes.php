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
        '/payment/stub'            => ['PaymentController', 'stub'],
        '/login'                   => ['AuthController', 'showLogin'],
        '/register'                => ['AuthController', 'showRegister'],
        '/admin'                   => ['AdminController', 'index'],
        '/admin/orders'            => ['AdminOrderController', 'index'],
        '/admin/orders/create'     => ['AdminOrderController', 'create'],
        '/admin/orders/{id}'       => ['AdminOrderController', 'show'],
        '/admin/variants/search'   => ['AdminVariantController', 'search'],
        '/admin/customers/lookup'  => ['AdminOrderController', 'lookupCustomer'],
        '/admin/customers'         => ['AdminCustomerController', 'index'],
        '/admin/customers/{type}/{key}' => ['AdminCustomerController', 'show'],
        '/admin/categories'        => ['AdminCategoryController', 'index'],
        '/admin/categories/create' => ['AdminCategoryController', 'create'],
        '/admin/categories/{id}/edit' => ['AdminCategoryController', 'edit'],
        '/admin/products'          => ['AdminProductController', 'index'],
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
        '/admin/orders'                  => ['AdminOrderController', 'store'],
        '/admin/orders/{id}/transition'  => ['AdminOrderController', 'transition'],
        '/admin/orders/{id}/prepaid'     => ['AdminOrderController', 'markPrepaid'],
        '/admin/orders/{id}/paid-full'   => ['AdminOrderController', 'markPaidFull'],
        '/admin/orders/{id}/shipping'    => ['AdminOrderController', 'setShipping'],
        '/admin/orders/{id}/cancel'      => ['AdminOrderController', 'cancel'],
        '/admin/orders/{id}/items'                 => ['AdminOrderController', 'addItem'],
        '/admin/orders/{id}/items/{itemId}'        => ['AdminOrderController', 'updateItem'],
        '/admin/orders/{id}/items/{itemId}/remove' => ['AdminOrderController', 'removeItem'],
        '/admin/categories'             => ['AdminCategoryController', 'store'],
        '/admin/categories/{id}'        => ['AdminCategoryController', 'update'],
        '/admin/categories/{id}/delete' => ['AdminCategoryController', 'delete'],
        '/admin/products/{id}/toggle'   => ['AdminProductController', 'toggle'],
    ],
];
