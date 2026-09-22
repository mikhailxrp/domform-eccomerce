<?php

declare(strict_types=1);

/** @var string|null $title */

$pageTitle   = isset($title) && $title !== '' ? $title . ' — ДомФорм' : 'Панель управления — ДомФорм';
$currentUser = currentUser();
$userName    = (string) ($currentUser['name'] ?? '');
$currentPath = requestPath();

require_once ROOT_PATH . '/src/Models/Review.php';
require_once ROOT_PATH . '/src/Models/CallbackRequest.php';
$pendingReviewsCount = countPendingReviews();
$newCallbacksCount   = countNewCallbacks();

// Баннер превышения месячного лимита ИИ (`BR-AI-001` правило 5, Таск 8
// Фазы 9) — виден только `admin` («Владелец» в терминах `prd.md`),
// запрос расхода за месяц не делается для `manager`, у кого раздела
// «ИИ» и так нет.
require_once ROOT_PATH . '/src/Core/Ai.php';
require_once ROOT_PATH . '/src/Core/Settings.php';
require_once ROOT_PATH . '/src/Models/Setting.php';
require_once ROOT_PATH . '/src/Models/AiUsage.php';
$aiLimitExceeded = ($currentUser['role'] ?? '') === 'admin'
    && isAiLimitExceeded(getAiSpendForMonth(date('Y-m')), setting('ai_monthly_limit_rub'));

// «Новый заказ» убран отсюда — кнопка «Новый заказ» перенесена на
// страницу `/admin/orders` (обычный паттерн создания записи, как у
// «Добавить категорию»/«Добавить баннер»), список пунктов меню короче
// на один. Пункты без прямого `url` — раскрывающиеся группы
// (`children`), меню собрано по итогам обсуждения с пользователем
// 23.09.2026: 18 плоских пунктов не помещались на экране, тема Valex
// поддерживает аккордеон из коробки (`has-sub`/`slide-menu` в
// `defaultmenu.min.js`) — своей реализации сворачивания не пишем.
$adminNavItems = [
    ['url' => '/admin',           'label' => 'Дашборд', 'icon' => 'bx bx-home-alt'],
    ['url' => '/admin/orders',    'label' => 'Заказы',  'icon' => 'bx bx-cart'],
    [
        'label'    => 'Каталог',
        'icon'     => 'bx bx-package',
        'children' => [
            ['url' => '/admin/products',   'label' => 'Товары',    'icon' => 'bx bx-package'],
            ['url' => '/admin/categories', 'label' => 'Категории', 'icon' => 'bx bx-category'],
        ],
    ],
    ['url' => '/admin/customers', 'label' => 'Клиенты', 'icon' => 'bx bx-group'],
    [
        'label'    => 'Контент',
        'icon'     => 'bx bx-file',
        'children' => [
            ['url' => '/admin/content',       'label' => 'Контент',               'icon' => 'bx bx-file'],
            ['url' => '/admin/banners',       'label' => 'Баннеры',               'icon' => 'bx bx-carousel'],
            ['url' => '/admin/about-gallery', 'label' => 'Галерея «О компании»',  'icon' => 'bx bx-images'],
        ],
    ],
    ['url' => '/admin/reviews',   'label' => 'Отзывы', 'icon' => 'bx bx-star', 'badge' => $pendingReviewsCount > 0 ? $pendingReviewsCount : null],
    ['url' => '/admin/callbacks', 'label' => 'Заявки', 'icon' => 'bx bx-phone-call', 'badge' => $newCallbacksCount > 0 ? $newCallbacksCount : null],
    [
        'label'    => 'Продажи',
        'icon'     => 'bx bx-bar-chart-alt-2',
        'children' => [
            ['url' => '/admin/reports',        'label' => 'Отчёты',       'icon' => 'bx bx-bar-chart-alt-2'],
            ['url' => '/admin/returns',        'label' => 'Возвраты',     'icon' => 'bx bx-undo'],
            ['url' => '/admin/sales-channels', 'label' => 'Каналы продаж', 'icon' => 'bx bx-chat'],
        ],
    ],
    [
        'label'    => 'Система',
        'icon'     => 'bx bx-cog',
        // Роли — по каждому пункту отдельно, как и раньше (`Интеграции`
        // остаются видны и Менеджеру, `Q-DEV-001`) — группа не сужает
        // видимость сама по себе, только группирует визуально.
        'children' => [
            ['url' => '/admin/integrations', 'label' => 'Интеграции',   'icon' => 'bx bx-plug'],
            ['url' => '/admin/ai',           'label' => 'ИИ-помощники', 'icon' => 'bx bx-bot', 'roles' => ['admin']],
            ['url' => '/admin/users',        'label' => 'Сотрудники',   'icon' => 'bx bx-user-check', 'roles' => ['admin']],
            ['url' => '/admin/settings',     'label' => 'Настройки',    'icon' => 'bx bx-cog', 'roles' => ['admin']],
        ],
    ],
];

// `roles` — необязательный ключ на листовом пункте; пункт без него
// виден и Менеджеру, и Администратору (как раньше), с ключом — только
// перечисленным ролям (`FR-ADM-007`, `Q-DEV-001`). Группа фильтрует
// только своих детей и исчезает целиком, если детей не осталось —
// сама по себе роли не задаёт.
$currentRole = (string) ($currentUser['role'] ?? '');
$roleFilter  = static fn (array $item): bool => !isset($item['roles']) || in_array($currentRole, $item['roles'], true);

$adminNavItems = array_values(array_filter(array_map(
    static function (array $navItem) use ($roleFilter): ?array {
        if (!isset($navItem['children'])) {
            return $navItem;
        }

        $navItem['children'] = array_values(array_filter($navItem['children'], $roleFilter));

        return $navItem['children'] !== [] ? $navItem : null;
    },
    array_filter($adminNavItems, $roleFilter)
)));

// Активный пункт — самый длинный совпавший `url` среди ЛИСТЬЕВ (внутри
// групп тоже), как и раньше; группа, содержащая активный лист,
// получает класс `open`, чтобы при заходе на её страницу список был
// сразу развёрнут, а не спрятан за лишним кликом.
$activeNavUrl    = null;
$activeUrlLength = -1;
foreach ($adminNavItems as $navItem) {
    $leaves = $navItem['children'] ?? [$navItem];
    foreach ($leaves as $leaf) {
        $isMatch = $currentPath === $leaf['url'] || str_starts_with($currentPath, $leaf['url'] . '/');
        if ($isMatch && strlen($leaf['url']) > $activeUrlLength) {
            $activeNavUrl    = $leaf['url'];
            $activeUrlLength = strlen($leaf['url']);
        }
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
                            <?php if (isset($navItem['children'])): ?>
                                <?php $isGroupOpen = in_array($activeNavUrl, array_column($navItem['children'], 'url'), true); ?>
                                <li class="slide has-sub<?= $isGroupOpen ? ' open' : '' ?>">
                                    <a href="javascript:void(0);" class="side-menu__item">
                                        <i class="<?= e($navItem['icon']) ?> side-menu__icon"></i>
                                        <span class="side-menu__label"><?= e($navItem['label']) ?></span>
                                        <i class="fe fe-chevron-right side-menu__angle"></i>
                                    </a>
                                    <ul class="slide-menu">
                                        <?php foreach ($navItem['children'] as $child): ?>
                                            <li class="slide">
                                                <a href="<?= e($child['url']) ?>" class="side-menu__item<?= $child['url'] === $activeNavUrl ? ' active' : '' ?>">
                                                    <i class="<?= e($child['icon']) ?> side-menu__icon"></i>
                                                    <span class="side-menu__label"><?= e($child['label']) ?></span>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </li>
                            <?php else: ?>
                                <li class="slide">
                                    <a href="<?= e($navItem['url']) ?>" class="side-menu__item<?= $navItem['url'] === $activeNavUrl ? ' active' : '' ?>">
                                        <i class="<?= e($navItem['icon']) ?> side-menu__icon"></i>
                                        <span class="side-menu__label"><?= e($navItem['label']) ?></span>
                                        <?php if (!empty($navItem['badge'])): ?>
                                            <span class="badge bg-danger rounded-pill ms-1"><?= e((string) $navItem['badge']) ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            </div>
        </aside>
        <!-- End::app-sidebar -->

        <div class="main-content app-content">
            <div class="container-fluid">
                <?php include ROOT_PATH . '/src/Views/components/flash.php'; ?>
                <?php if ($aiLimitExceeded): ?>
                    <div class="alert alert-warning" role="alert">
                        Расход на ИИ-помощников за этот месяц превысил лимит.
                        Помощники продолжают работать — отключить вручную можно в разделе
                        <a href="/admin/ai">«ИИ»</a>.
                    </div>
                <?php endif; ?>
