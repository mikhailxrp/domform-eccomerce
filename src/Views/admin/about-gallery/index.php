<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<int, array<string, mixed>> $images */
/** @var bool $maxReached */
/** @var string|null $uploadError */

include ROOT_PATH . '/src/Views/layout/admin-header.php';
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0"><?= e($title) ?></h4>
        <p class="text-muted mb-0">До <?= (int) ABOUT_GALLERY_MAX ?> фото интерьера/цеха для страницы «О компании»</p>
    </div>
</div>

<div class="row">
    <div class="col-xl-8">
        <div class="card custom-card">
            <div class="card-header">
                <div class="card-title">Фото в галерее</div>
            </div>
            <div class="card-body">
                <?php if ($images === []): ?>
                    <p class="text-muted mb-0">Пока ни одного фото — секция галереи на сайте не показывается.</p>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($images as $image): ?>
                            <div class="col-md-4">
                                <div class="border rounded overflow-hidden mb-2">
                                    <img src="<?= e($image['path']) ?>" alt="" class="about-gallery-admin__thumb">
                                </div>
                                <form method="post" action="/admin/about-gallery/<?= e((string) $image['id']) ?>/delete">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger w-100">Удалить</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card custom-card">
            <div class="card-header">
                <div class="card-title">Загрузить фото</div>
            </div>
            <div class="card-body">
                <?php if ($maxReached): ?>
                    <p class="text-muted mb-0">Достигнут максимум (<?= (int) ABOUT_GALLERY_MAX ?>) — удалите фото, чтобы загрузить новое.</p>
                <?php else: ?>
                    <form method="post" action="/admin/about-gallery" enctype="multipart/form-data">
                        <?= csrfField() ?>
                        <div class="mb-3">
                            <label for="gallery-image" class="form-label">Файл (JPG, PNG или WEBP, до 5 МБ)</label>
                            <input type="file" id="gallery-image" name="image" class="form-control<?= $uploadError !== null ? ' is-invalid' : '' ?>" accept="image/jpeg,image/png,image/webp">
                            <?php if ($uploadError !== null): ?>
                                <div class="invalid-feedback"><?= e($uploadError) ?></div>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="btn btn-primary">Загрузить</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include ROOT_PATH . '/src/Views/layout/admin-footer.php'; ?>
