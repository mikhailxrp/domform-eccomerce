<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<int, array<string, mixed>> $categories */

include ROOT_PATH . '/src/Views/layout/admin-header.php';
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0">Категории</h4>
    </div>
    <div>
        <a href="/admin/categories/create" class="btn btn-primary">Добавить категорию</a>
    </div>
</div>

<div class="card custom-card">
    <div class="card-body">
        <?php if ($categories === []): ?>
            <p class="text-muted text-center py-5 mb-0">Категорий пока нет.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered text-nowrap w-100">
                    <thead>
                        <tr>
                            <th>Название</th>
                            <th>Родитель</th>
                            <th>Slug</th>
                            <th>Порядок</th>
                            <th>Товаров</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?= e($category['name']) ?></td>
                                <td><?= $category['parent_name'] !== null ? e($category['parent_name']) : '—' ?></td>
                                <td><?= e($category['slug']) ?></td>
                                <td><?= e((string) $category['sort_order']) ?></td>
                                <td><?= e((string) $category['product_count']) ?></td>
                                <td class="d-flex gap-1">
                                    <a href="/admin/categories/<?= e((string) $category['id']) ?>/edit" class="btn btn-sm btn-outline-primary">Изменить</a>
                                    <form method="post" action="/admin/categories/<?= e((string) $category['id']) ?>/delete">
                                        <?= csrfField() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include ROOT_PATH . '/src/Views/layout/admin-footer.php'; ?>
