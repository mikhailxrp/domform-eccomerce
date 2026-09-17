<?php

declare(strict_types=1);

/** @var string|null $title */

require_once ROOT_PATH . '/src/Models/Category.php';

$pageTitle         = isset($title) && $title !== '' ? $title . ' — ДомФорм' : 'ДомФорм — мебель на заказ';
$isLoggedIn        = isAuthenticated();
$userName          = $isLoggedIn ? (string) ($_SESSION['user_name'] ?? '') : '';
$catalogCategories = getCategoryTree();
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="ДомФорм — мебель на заказ в Краснодаре и крае.">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <link rel="shortcut icon" type="image/x-icon" href="/assets/images/favicon.png">

    <link rel="stylesheet" href="/assets/css/plugins/pe-icon-7-stroke.css">
    <link rel="stylesheet" href="/assets/css/plugins/font-awesome.min.css">
    <link rel="stylesheet" href="/assets/css/plugins/animate.min.css">
    <link rel="stylesheet" href="/assets/css/plugins/swiper-bundle.min.css">
    <link rel="stylesheet" href="/assets/css/plugins/odometer.min.css">
    <link rel="stylesheet" href="/assets/css/plugins/nice-select.css">
    <link rel="stylesheet" href="/assets/css/plugins/select2.min.css">
    <link rel="stylesheet" href="/assets/css/plugins/ion.rangeSlider.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>

<body>

    <!-- Header Start -->
    <div class="header-area header-sticky d-none d-xl-block">
        <div class="container position-relative">
            <div class="row align-items-center">
                <div class="col-lg-3">
                    <div class="header-logo">
                        <a href="/"><img src="/assets/images/logo.png" alt="ДомФорм"></a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="header-menu">
                        <ul class="nav-menu">
                            <li><a href="/">Главная</a></li>
                            <li>
                                <a href="/catalog">Каталог</a>
                                <?php if ($catalogCategories !== []): ?>
                                    <ul class="mega-sub-menu">
                                        <?php foreach ($catalogCategories as $rootCategory): ?>
                                            <li>
                                                <a href="/catalog/<?= e($rootCategory['slug']) ?>" class="menu-title"><?= e($rootCategory['name']) ?></a>
                                                <?php if ($rootCategory['children'] !== []): ?>
                                                    <ul class="menu-item">
                                                        <?php foreach ($rootCategory['children'] as $childCategory): ?>
                                                            <li><a href="/catalog/<?= e($childCategory['slug']) ?>"><?= e($childCategory['name']) ?></a></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </li>
                            <li><a href="#">О компании</a></li>
                            <li><a href="#">Доставка и оплата</a></li>
                            <li><a href="#">Контакты</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="header-meta">
                        <div class="dropdown">
                            <a class="action" href="#" role="button" data-bs-toggle="dropdown"><i class="pe-7s-search"></i></a>
                            <div class="dropdown-menu dropdown-search">
                                <div class="header-search">
                                    <form action="#">
                                        <input type="text" placeholder="Поиск по каталогу...">
                                        <button type="submit"><i class="pe-7s-search"></i></button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="dropdown">
                            <a class="action<?= $isLoggedIn ? ' action--active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown"><i class="pe-7s-user"></i></a>
                            <ul class="dropdown-menu dropdown-profile">
                                <?php if ($isLoggedIn): ?>
                                    <li><span class="dropdown-item-text"><?= e($userName) ?></span></li>
                                    <li>
                                        <form method="post" action="/logout">
                                            <?= csrfField() ?>
                                            <button type="submit" class="dropdown-item">Выход</button>
                                        </form>
                                    </li>
                                <?php else: ?>
                                    <li><a href="/login">Вход</a></li>
                                    <li><a href="/register">Регистрация</a></li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <a class="action" href="#">
                            <i class="pe-7s-shopbag"></i>
                            <span class="number">0</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Header End -->

    <!-- Header Mobile Start -->
    <div class="header-mobile section d-xl-none">
        <div class="header-mobile-top header-sticky">
            <div class="container">
                <div class="row row-cols-3 gx-2 align-items-center">
                    <div class="col">
                        <div class="header-toggle">
                            <button class="mobile-menu-open" aria-label="Открыть меню">
                                <span></span>
                                <span></span>
                                <span></span>
                            </button>
                        </div>
                    </div>
                    <div class="col">
                        <div class="header-logo text-center">
                            <a href="/"><img src="/assets/images/logo.png" alt="ДомФорм"></a>
                        </div>
                    </div>
                    <div class="col">
                        <div class="header-meta">
                            <div class="dropdown">
                                <a class="action<?= $isLoggedIn ? ' action--active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown"><i class="pe-7s-user"></i></a>
                                <ul class="dropdown-menu dropdown-profile">
                                    <?php if ($isLoggedIn): ?>
                                        <li><span class="dropdown-item-text"><?= e($userName) ?></span></li>
                                        <li>
                                            <form method="post" action="/logout">
                                                <?= csrfField() ?>
                                                <button type="submit" class="dropdown-item">Выход</button>
                                            </form>
                                        </li>
                                    <?php else: ?>
                                        <li><a href="/login">Вход</a></li>
                                        <li><a href="/register">Регистрация</a></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <a class="action" href="#">
                                <i class="pe-7s-shopbag"></i>
                                <span class="number">0</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="header-mobile-bottom">
            <div class="container">
                <div class="header-search">
                    <form action="#">
                        <input type="text" placeholder="Поиск по каталогу...">
                        <button type="submit"><i class="pe-7s-search"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Header Mobile End -->

    <!-- off Canvas Start -->
    <div class="off-canvas-box">
        <div class="canvas-close-bar">
            <span>Меню</span>
            <a class="menu-close" href="javascript:;"><i class="pe-7s-angle-left"></i></a>
        </div>

        <div class="canvas-menu">
            <nav>
                <ul class="nav-menu">
                    <li><a href="/">Главная</a></li>
                    <li>
                        <a href="/catalog">Каталог</a>
                        <?php if ($catalogCategories !== []): ?>
                            <ul class="mega-sub-menu">
                                <?php foreach ($catalogCategories as $rootCategory): ?>
                                    <li>
                                        <a href="/catalog/<?= e($rootCategory['slug']) ?>" class="menu-title"><?= e($rootCategory['name']) ?></a>
                                        <?php if ($rootCategory['children'] !== []): ?>
                                            <ul class="menu-item">
                                                <?php foreach ($rootCategory['children'] as $childCategory): ?>
                                                    <li><a href="/catalog/<?= e($childCategory['slug']) ?>"><?= e($childCategory['name']) ?></a></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                    <li><a href="#">О компании</a></li>
                    <li><a href="#">Доставка и оплата</a></li>
                    <li><a href="#">Контакты</a></li>
                </ul>
            </nav>
        </div>
    </div>
    <!-- off Canvas End -->

    <div class="menu-overlay"></div>
