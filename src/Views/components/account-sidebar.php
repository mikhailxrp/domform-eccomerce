<?php

declare(strict_types=1);

/** @var string $activeSection одно из: dashboard, orders, addresses, favorites, details */

/**
 * Меню разделов кабинета (`my-account.html`, `account-menu-list`) —
 * обычные ссылки на отдельные маршруты, не Bootstrap pills-табы
 * (`.docs/phases/phase-7.md`, «Решения фазы»: серверные формы с
 * ошибками и пагинация не требуют «вспомнить открытую вкладку»).
 * Разделы «Заказы»/«Адреса»/«Избранное»/«Личные данные» появятся в
 * Тасках 2–4, 6–7 — маршруты уже закреплены решением фазы, дальше
 * этот файл не меняется. «Выход» здесь не дублируется — уже есть в
 * дропдауне шапки на каждой странице (`layout/header.php`). Вкладки
 * «Download»/«Payment Method» из макета не переносятся — демо-
 * артефакты темы (`screens.md`).
 */
$accountMenuItems = [
    'dashboard' => ['url' => '/account',          'icon' => 'fa-tachometer',    'label' => 'Обзор'],
    'orders'    => ['url' => '/account/orders',    'icon' => 'fa-shopping-cart', 'label' => 'Заказы'],
    'addresses' => ['url' => '/account/addresses', 'icon' => 'fa-map-marker',    'label' => 'Адреса'],
    'favorites' => ['url' => '/account/favorites', 'icon' => 'fa-heart-o',       'label' => 'Избранное'],
    'details'   => ['url' => '/account/details',   'icon' => 'fa-user',          'label' => 'Личные данные'],
];
?>
<div class="my-account-menu mt-6">
    <ul class="nav account-menu-list flex-column">
        <?php foreach ($accountMenuItems as $section => $item): ?>
            <li>
                <a class="<?= $section === $activeSection ? 'active' : '' ?>" href="<?= e($item['url']) ?>">
                    <i class="fa <?= e($item['icon']) ?>"></i> <?= e($item['label']) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
