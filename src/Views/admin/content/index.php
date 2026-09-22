<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<int, array<string, mixed>> $pages */

include ROOT_PATH . '/src/Views/layout/admin-header.php';
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0">Контент</h4>
        <p class="mb-0 text-muted">Тексты и фото статических страниц (`FR-ADM-003`)</p>
    </div>
</div>

<div class="card custom-card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered text-nowrap w-100">
                <thead>
                    <tr>
                        <th>Заголовок</th>
                        <th>Slug</th>
                        <th>Обновлено</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pages as $page): ?>
                        <tr>
                            <td><?= e($page['title']) ?></td>
                            <td><code><?= e($page['slug']) ?></code></td>
                            <td><?= e(date('d.m.Y H:i', strtotime((string) $page['updated_at']))) ?></td>
                            <td class="d-flex gap-2">
                                <a href="/admin/content/<?= e($page['slug']) ?>/edit" class="btn btn-sm btn-outline-primary">Редактировать</a>
                                <a href="<?= e(publicUrlForContentSlug($page['slug'])) ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">Открыть на сайте</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include ROOT_PATH . '/src/Views/layout/admin-footer.php'; ?>
