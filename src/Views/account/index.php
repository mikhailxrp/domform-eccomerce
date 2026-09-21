<?php

declare(strict_types=1);

/** @var string $title */
/** @var string $activeSection */
/** @var string $userName */
/** @var int $ordersCount */
/** @var int $addressesCount */
/** @var int $favoritesCount */

include ROOT_PATH . '/src/Views/layout/header.php';

$breadcrumbs = [['name' => 'Личный кабинет']];
?>

<main>
    <?php include ROOT_PATH . '/src/Views/components/flash.php'; ?>

    <div class="section page-banner-section page-banner-section--cart">
        <div class="container">
            <div class="page-banner-content">
                <h1 class="title">Личный кабинет</h1>
                <?php include ROOT_PATH . '/src/Views/components/breadcrumbs.php'; ?>
            </div>
        </div>
    </div>

    <div class="section section-padding mt-n6">
        <div class="container">
            <div class="row">
                <div class="col-xl-3 col-md-4">
                    <?php include ROOT_PATH . '/src/Views/components/account-sidebar.php'; ?>
                </div>
                <div class="col-xl-9 col-md-8">
                    <div class="my-account-tab mt-6">
                        <div class="my-account-dashboard account-wrapper">
                            <h4 class="account-title">Обзор</h4>
                            <div class="welcome-dashboard">
                                <p>Здравствуйте, <strong><?= e($userName) ?></strong>!</p>
                            </div>
                            <p class="mt-25">Здесь собраны ваши Заказы, сохранённые адреса, Избранное и личные данные.</p>
                            <ul class="list-unstyled mt-25">
                                <li><a href="/account/orders">Заказы</a> — <?= e((string) $ordersCount) ?></li>
                                <li><a href="/account/addresses">Адреса</a> — <?= e((string) $addressesCount) ?></li>
                                <li><a href="/account/favorites">Избранное</a> — <?= e((string) $favoritesCount) ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
