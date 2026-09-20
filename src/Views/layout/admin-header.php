<?php

declare(strict_types=1);

/** @var string|null $title */

$pageTitle   = isset($title) && $title !== '' ? $title . ' — ДомФорм' : 'Панель управления — ДомФорм';
$currentUser = currentUser();
$userName    = (string) ($currentUser['name'] ?? '');
$currentPath = requestPath();

$adminNavItems = [
    ['url' => '/admin',               'label' => 'Дашборд',     'icon' => 'bx bx-home-alt'],
    ['url' => '/admin/orders',        'label' => 'Заказы',      'icon' => 'bx bx-cart'],
    ['url' => '/admin/orders/create', 'label' => 'Новый заказ', 'icon' => 'bx bx-cart-add'],
    ['url' => '/admin/products',      'label' => 'Товары',      'icon' => 'bx bx-package'],
    ['url' => '/admin/categories',    'label' => 'Категории',   'icon' => 'bx bx-category'],
    ['url' => '/admin/customers',     'label' => 'Клиенты',     'icon' => 'bx bx-group'],
    ['url' => '/admin/reports',       'label' => 'Отчёты',      'icon' => 'bx bx-bar-chart-alt-2'],
    ['url' => '/admin/returns',       'label' => 'Возвраты',    'icon' => 'bx bx-undo'],
    ['url' => '/admin/sales-channels', 'label' => 'Каналы продаж', 'icon' => 'bx bx-chat'],
    ['url' => '/admin/integrations',  'label' => 'Интеграции',  'icon' => 'bx bx-plug'],
];

$activeNavUrl    = null;
$activeUrlLength = -1;
foreach ($adminNavItems as $navItem) {
    $isMatch = $currentPath === $navItem['url'] || str_starts_with($currentPath, $navItem['url'] . '/');
    if ($isMatch && strlen($navItem['url']) > $activeUrlLength) {
        $activeNavUrl    = $navItem['url'];
        $activeUrlLength = strlen($navItem['url']);
    }
}
?>
<!DOCTYPE html>
<html lang="ru" dir="ltr" data-nav-layout="vertical" data-theme-mode="light" data-header-styles="light" data-menu-styles="light" data-toggled="close">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= e($pageTitle) ?></title>

    <link rel="shortcut icon" type="image/x-icon" href="/assets/images/favicon.png">

    <script src="/assets/admin/js/main.js"></script>

    <link href="/assets/admin/libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/admin/css/styles.min.css" rel="stylesheet">
    <link href="/assets/admin/css/icons.css" rel="stylesheet">
    <link href="/assets/admin/libs/simplebar/simplebar.min.css" rel="stylesheet">

    <!-- Свои переопределения -->
    <link href="/assets/css/admin.css" rel="stylesheet">
</head>

<body>
    <div class="page">

        <!-- Start::app-header -->
        <header class="app-header">
            <div class="main-header-container container-fluid">
                <div class="header-content-left">
                    <div class="header-element">
                        <a aria-label="Показать/скрыть меню" class="sidemenu-toggle header-link animated-arrow hor-toggle horizontal-navtoggle" data-bs-toggle="sidebar" href="javascript:void(0);">
                            <i class="header-icon fe fe-align-left"></i>
                        </a>
                    </div>
                    <div class="header-element">
                        <a href="/admin" class="header-link fw-semibold text-nowrap text-decoration-none">ДомФорм</a>
                    </div>
                </div>

                <div class="header-content-right">
                    <div class="header-element headerProfile-dropdown">
                        <a href="javascript:void(0);" class="header-link dropdown-toggle" id="mainHeaderProfile" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                            <i class="fe fe-user header-link-icon"></i>
                            <span class="ms-2"><?= e($userName) ?></span>
                        </a>
                        <ul class="main-header-dropdown dropdown-menu pt-0 header-profile-dropdown dropdown-menu-end main-profile-menu" aria-labelledby="mainHeaderProfile">
                            <li><span class="dropdown-item-text"><?= e($userName) ?></span></li>
                            <li class="px-3 py-2">
                                <form method="post" action="/logout">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger w-100">Выйти</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </header>
        <!-- End::app-header -->

        <!-- Start::app-sidebar -->
        <aside class="app-sidebar sticky" id="sidebar">
            <div class="main-sidebar-header">
                <a href="/admin" class="header-logo fw-semibold fs-16 text-decoration-none">ДомФорм</a>
            </div>
            <div class="main-sidebar" id="sidebar-scroll">
                <nav class="main-menu-container nav nav-pills flex-column sub-open">
                    <ul class="main-menu">
                        <?php foreach ($adminNavItems as $navItem): ?>
                            <li class="slide">
                                <a href="<?= e($navItem['url']) ?>" class="side-menu__item<?= $navItem['url'] === $activeNavUrl ? ' active' : '' ?>">
                                    <i class="<?= e($navItem['icon']) ?> side-menu__icon"></i>
                                    <span class="side-menu__label"><?= e($navItem['label']) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            </div>
        </aside>
        <!-- End::app-sidebar -->

        <div class="main-content app-content">
            <div class="container-fluid">
                <?php include ROOT_PATH . '/src/Views/components/flash.php'; ?>
