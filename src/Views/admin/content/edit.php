<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<string, mixed> $page строка content_pages */
/** @var array<string, mixed> $old */
/** @var array<string, bool> $errors */

include ROOT_PATH . '/src/Views/layout/admin-header.php';
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0"><?= e($title) ?></h4>
        <p class="mb-0"><a href="/admin/content">← К списку страниц</a></p>
    </div>
</div>

<div class="row">
    <div class="col-xl-8">
        <div class="card custom-card">
            <div class="card-header">
                <div class="card-title">Текст страницы</div>
            </div>
            <div class="card-body">
                <form method="post" action="/admin/content/<?= e($page['slug']) ?>">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label for="content-title" class="form-label">Заголовок</label>
                        <input type="text" id="content-title" name="title" class="form-control<?= !empty($errors['title']) ? ' is-invalid' : '' ?>" value="<?= e((string) $old['title']) ?>">
                        <?php if (!empty($errors['title'])): ?>
                            <div class="invalid-feedback">Заголовок от 1 до 200 символов.</div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-2">
                        <label for="content-body" class="form-label">Текст</label>
                        <textarea id="content-body" name="body" class="form-control<?= !empty($errors['body']) ? ' is-invalid' : '' ?>" rows="16"><?= e((string) $old['body']) ?></textarea>
                        <?php if (!empty($errors['body'])): ?>
                            <div class="invalid-feedback">Текст не может быть пустым.</div>
                        <?php endif; ?>
                    </div>
                    <p class="text-muted small mb-3">
                        Разметка: пустая строка между абзацами; строка «<code>## Заголовок</code>» — подзаголовок;
                        строки «<code>- Пункт</code>» — список. HTML-теги не поддерживаются и выводятся как текст.
                    </p>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card custom-card">
            <div class="card-header">
                <div class="card-title">Фото</div>
            </div>
            <div class="card-body">
                <?php if ($page['slug'] === 'about'): ?>
                    <!-- На «О компании» одиночное фото страницы не выводится
                         — вместо него отдельная галерея из 4 фото
                         (`about_gallery_images`), правка по скриншоту
                         пользователя (`dev-log.md` 22.09.2026). -->
                    <p class="text-muted small mb-3">
                        У страницы «О компании» вместо одного фото — галерея
                        из нескольких фото под текстом.
                    </p>
                    <a href="/admin/about-gallery" class="btn btn-outline-primary w-100">Управлять галереей</a>
                <?php else: ?>
                    <?php if ($page['image_path'] !== null): ?>
                        <div class="border rounded overflow-hidden mb-3">
                            <img src="<?= e((string) $page['image_path']) ?>" alt="" class="w-100">
                        </div>
                        <form method="post" action="/admin/content/<?= e($page['slug']) ?>/image/remove" class="mb-3">
                            <?= csrfField() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger w-100">Удалить фото</button>
                        </form>
                    <?php else: ?>
                        <p class="text-muted small">Фото не загружено.</p>
                    <?php endif; ?>

                    <form method="post" action="/admin/content/<?= e($page['slug']) ?>" enctype="multipart/form-data">
                        <?= csrfField() ?>
                        <div class="mb-3">
                            <label for="content-image" class="form-label">Заменить фото (JPG, PNG или WEBP, до 5 МБ)</label>
                            <input type="file" id="content-image" name="image" class="form-control" accept="image/jpeg,image/png,image/webp">
                        </div>
                        <input type="hidden" name="title" value="<?= e((string) $old['title']) ?>">
                        <input type="hidden" name="body" value="<?= e((string) $old['body']) ?>">
                        <button type="submit" class="btn btn-outline-primary">Загрузить</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include ROOT_PATH . '/src/Views/layout/admin-footer.php'; ?>
