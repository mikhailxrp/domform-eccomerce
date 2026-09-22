<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<string, mixed>|null $banner */
/** @var array<string, mixed> $old */
/** @var array<string, bool> $errors */
/** @var string|null $uploadError */

include ROOT_PATH . '/src/Views/layout/admin-header.php';

$isEdit     = $banner !== null;
$formAction = $isEdit ? '/admin/banners/' . $banner['id'] : '/admin/banners';
$bannerTitle = (string) ($old['title'] ?? '');
$link        = (string) ($old['link'] ?? '');
$sortOrder   = (string) ($old['sort_order'] ?? '0');
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0"><?= e($title) ?></h4>
        <p class="mb-0"><a href="/admin/banners">← К списку баннеров</a></p>
    </div>
</div>

<div class="card custom-card">
    <div class="card-body">
        <form method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data">
            <?= csrfField() ?>

            <div class="mb-3">
                <label for="banner-title" class="form-label">Текст (необязательно)</label>
                <input type="text" id="banner-title" name="title" class="form-control <?= !empty($errors['title']) ? 'is-invalid' : '' ?>" value="<?= e($bannerTitle) ?>">
                <?php if (!empty($errors['title'])): ?>
                    <div class="invalid-feedback">Не длиннее 200 символов.</div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="banner-link" class="form-label">Ссылка (необязательно)</label>
                <input type="text" id="banner-link" name="link" class="form-control <?= !empty($errors['link']) ? 'is-invalid' : '' ?>" value="<?= e($link) ?>" placeholder="/catalog или https://...">
                <?php if (!empty($errors['link'])): ?>
                    <div class="invalid-feedback">Только относительный путь («/catalog») или полный адрес («https://...»).</div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="banner-sort-order" class="form-label">Порядок</label>
                <input type="number" id="banner-sort-order" name="sort_order" class="form-control <?= !empty($errors['sort_order']) ? 'is-invalid' : '' ?>" value="<?= e($sortOrder) ?>" min="0">
                <?php if (!empty($errors['sort_order'])): ?>
                    <div class="invalid-feedback">Целое число, не меньше 0.</div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <?php if ($isEdit && $banner['image_path'] !== null): ?>
                    <label class="form-label d-block">Текущее фото</label>
                    <div class="border rounded overflow-hidden mb-2" style="max-width: 320px;">
                        <img src="<?= e('/' . ltrim((string) $banner['image_path'], '/')) ?>" alt="" class="w-100">
                    </div>
                <?php endif; ?>
                <label for="banner-image" class="form-label">
                    <?= $isEdit ? 'Заменить фото (необязательно)' : 'Фото' ?> (JPG, PNG или WEBP, до 5 МБ)
                </label>
                <input type="file" id="banner-image" name="image" class="form-control <?= !empty($errors['image']) ? 'is-invalid' : '' ?>" accept="image/jpeg,image/png,image/webp">
                <?php if (!empty($errors['image'])): ?>
                    <div class="invalid-feedback"><?= e($uploadError ?? 'Загрузите изображение.') ?></div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary mb-4"><?= $isEdit ? 'Сохранить' : 'Создать' ?></button>
        </form>
    </div>
</div>

<?php include ROOT_PATH . '/src/Views/layout/admin-footer.php'; ?>
