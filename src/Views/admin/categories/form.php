<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<string, mixed>|null $category */
/** @var array<int, array<string, mixed>> $rootCategories */
/** @var array<string, mixed> $old */
/** @var array<string, bool> $errors */

include ROOT_PATH . '/src/Views/layout/admin-header.php';

$isEdit     = $category !== null;
$formAction = $isEdit ? '/admin/categories/' . $category['id'] : '/admin/categories';
$name        = (string) ($old['name'] ?? '');
$slug        = (string) ($old['slug'] ?? '');
$parentId    = $old['parent_id'] ?? null;
$description = (string) ($old['description'] ?? '');
$sortOrder   = (string) ($old['sort_order'] ?? '0');
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0"><?= e($title) ?></h4>
        <p class="mb-0"><a href="/admin/categories">← К списку категорий</a></p>
    </div>
</div>

<div class="card custom-card">
    <div class="card-body">
        <form method="post" action="<?= e($formAction) ?>">
            <?= csrfField() ?>

            <div class="mb-3">
                <label for="category-name" class="form-label">Название</label>
                <input type="text" id="category-name" name="name" class="form-control <?= !empty($errors['name']) ? 'is-invalid' : '' ?>" value="<?= e($name) ?>">
                <?php if (!empty($errors['name'])): ?>
                    <div class="invalid-feedback">Введите название.</div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="category-slug" class="form-label">Slug (необязательно — сгенерируется из названия)</label>
                <input type="text" id="category-slug" name="slug" class="form-control <?= (!empty($errors['slug']) || !empty($errors['slug_taken'])) ? 'is-invalid' : '' ?>" value="<?= e($slug) ?>" placeholder="divany">
                <?php if (!empty($errors['slug'])): ?>
                    <div class="invalid-feedback">Не удалось сформировать адрес — укажите название или slug вручную.</div>
                <?php elseif (!empty($errors['slug_taken'])): ?>
                    <div class="invalid-feedback">Такой slug уже занят другой категорией.</div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="category-parent" class="form-label">Родительская категория</label>
                <select id="category-parent" name="parent_id" class="form-select <?= !empty($errors['parent_id']) ? 'is-invalid' : '' ?>">
                    <option value="">— Корневая категория —</option>
                    <?php foreach ($rootCategories as $root): ?>
                        <option value="<?= e((string) $root['id']) ?>"<?= $parentId === $root['id'] ? ' selected' : '' ?>><?= e($root['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['parent_id'])): ?>
                    <div class="invalid-feedback">Родителем может быть только корневая категория.</div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="category-description" class="form-label">Описание</label>
                <textarea id="category-description" name="description" class="form-control" rows="3"><?= e($description) ?></textarea>
            </div>

            <div class="mb-3">
                <label for="category-sort-order" class="form-label">Порядок</label>
                <input type="number" id="category-sort-order" name="sort_order" class="form-control" value="<?= e($sortOrder) ?>">
            </div>

            <button type="submit" class="btn btn-primary mb-4"><?= $isEdit ? 'Сохранить' : 'Создать' ?></button>
        </form>
    </div>
</div>

<?php include ROOT_PATH . '/src/Views/layout/admin-footer.php'; ?>
