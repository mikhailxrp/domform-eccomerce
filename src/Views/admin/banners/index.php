<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<int, array<string, mixed>> $banners */

include ROOT_PATH . '/src/Views/layout/admin-header.php';
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0">Баннеры</h4>
        <p class="mb-0 text-muted">Слайдер Главной страницы (`FR-HOME-001`)</p>
    </div>
    <div>
        <a href="/admin/banners/create" class="btn btn-primary">Добавить баннер</a>
    </div>
</div>

<div class="card custom-card">
    <div class="card-body">
        <?php if ($banners === []): ?>
            <p class="text-muted text-center py-5 mb-0">Баннеров пока нет.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered text-nowrap w-100">
                    <thead>
                        <tr>
                            <th>Фото</th>
                            <th>Текст</th>
                            <th>Ссылка</th>
                            <th>Порядок</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($banners as $banner): ?>
                            <tr>
                                <td>
                                    <img src="<?= e('/' . ltrim((string) $banner['image_path'], '/')) ?>" alt="" class="banners-admin__thumb">
                                </td>
                                <td class="text-wrap"><?= $banner['title'] !== null ? e($banner['title']) : '—' ?></td>
                                <td class="text-wrap"><?= $banner['link'] !== null ? e($banner['link']) : '—' ?></td>
                                <td><?= e((string) $banner['sort_order']) ?></td>
                                <td>
                                    <span class="badge <?= (bool) $banner['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= (bool) $banner['is_active'] ? 'Активен' : 'Выключен' ?>
                                    </span>
                                </td>
                                <td class="d-flex gap-1">
                                    <a href="/admin/banners/<?= e((string) $banner['id']) ?>/edit" class="btn btn-sm btn-outline-primary">Изменить</a>
                                    <form method="post" action="/admin/banners/<?= e((string) $banner['id']) ?>/toggle">
                                        <?= csrfField() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            <?= (bool) $banner['is_active'] ? 'Выключить' : 'Включить' ?>
                                        </button>
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
