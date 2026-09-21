<?php

declare(strict_types=1);

/** @var string $title */
/** @var string $activeSection */
/** @var array<int,array> $orders */
/** @var array $pagination */
/** @var array<int,string> $paginationLinks */
/** @var string|null $prevUrl */
/** @var string|null $nextUrl */

include ROOT_PATH . '/src/Views/layout/header.php';

$breadcrumbs = [['name' => 'Мои заказы']];
?>

<main>
    <?php include ROOT_PATH . '/src/Views/components/flash.php'; ?>

    <div class="section page-banner-section page-banner-section--cart">
        <div class="container">
            <div class="page-banner-content">
                <h1 class="title">Мои заказы</h1>
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
                        <div class="my-account-order account-wrapper">
                            <h4 class="account-title">Заказы</h4>

                            <?php if ($orders === []): ?>
                                <div class="text-center mt-30">
                                    <p>У вас пока нет заказов.</p>
                                    <a href="/catalog" class="btn btn-primary btn-hover-dark">Перейти в каталог</a>
                                </div>
                            <?php else: ?>
                                <div class="account-table text-center mt-30 table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th class="no">№</th>
                                                <th class="date">Дата</th>
                                                <th class="status">Статус</th>
                                                <th class="total">Сумма</th>
                                                <th class="action">Действие</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($orders as $order): ?>
                                                <tr>
                                                    <td><?= e((string) $order['id']) ?></td>
                                                    <td><?= e(date('d.m.Y', strtotime((string) $order['created_at']))) ?></td>
                                                    <td><span class="badge <?= e(orderStatusBadgeClass($order['status'])) ?>"><?= e(orderStatusLabel($order['status'])) ?></span></td>
                                                    <td><?= formatPrice($order['total']) ?></td>
                                                    <td><a href="/account/orders/<?= e((string) $order['id']) ?>">Подробнее</a></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php include ROOT_PATH . '/src/Views/components/pagination.php'; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
