<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<int, array<string, mixed>> $customers */
/** @var string $searchQuery */
/** @var array $pagination */
/** @var array<int, string> $paginationLinks */
/** @var string|null $prevUrl */
/** @var string|null $nextUrl */

include ROOT_PATH . '/src/Views/layout/admin-header.php';
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0">Клиенты</h4>
        <p class="mb-0 text-muted">Покупатели с аккаунтом и гости, оформлявшие Заказ без регистрации</p>
    </div>
</div>

<div class="card custom-card">
    <div class="card-body">
        <form method="get" action="/admin/customers" class="row g-2 align-items-end mb-3">
            <div class="col-sm-8 col-md-6">
                <label for="customer-search" class="form-label">Имя или телефон</label>
                <input type="text" id="customer-search" name="search" class="form-control" value="<?= e($searchQuery) ?>" placeholder="Например, Иван или +7 918…">
            </div>
            <div class="col-sm-2 col-md-2">
                <button type="submit" class="btn btn-primary w-100">Найти</button>
            </div>
            <?php if ($searchQuery !== ''): ?>
                <div class="col-sm-2 col-md-2">
                    <a href="/admin/customers" class="btn btn-outline-secondary w-100">Сбросить</a>
                </div>
            <?php endif; ?>
        </form>

        <?php if ($customers === []): ?>
            <p class="text-muted text-center py-5 mb-0">Клиенты не найдены.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered text-nowrap w-100">
                    <thead>
                        <tr>
                            <th>Имя</th>
                            <th>Телефон</th>
                            <th>Тип</th>
                            <th>Заказов</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                            <?php $customerUrl = '/admin/customers/' . $customer['type'] . '/' . rawurlencode((string) $customer['key']); ?>
                            <tr class="admin-orders-table__row" data-href="<?= e($customerUrl) ?>">
                                <td><a href="<?= e($customerUrl) ?>"><?= e($customer['name'] ?? '—') ?></a></td>
                                <td><?= e($customer['phone'] ?? '—') ?></td>
                                <td>
                                    <span class="badge <?= $customer['type'] === 'user' ? 'bg-primary' : 'bg-secondary' ?>">
                                        <?= $customer['type'] === 'user' ? 'Аккаунт' : 'Гость' ?>
                                    </span>
                                </td>
                                <td><?= e((string) $customer['order_count']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include ROOT_PATH . '/src/Views/components/pagination.php'; ?>

<?php include ROOT_PATH . '/src/Views/layout/admin-footer.php'; ?>
