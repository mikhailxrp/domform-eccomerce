<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<string, mixed>|null $product */
/** @var array<int, array<string, mixed>> $categories */
/** @var array<string, mixed> $old */
/** @var array<string, mixed> $errors */
/** @var int $variantRows */
/** @var int $specRows */
/** @var bool $aiDescriptionAvailable */

include ROOT_PATH . '/src/Views/layout/admin-header.php';

$isEdit        = $product !== null;
$formAction    = $isEdit ? '/admin/products/' . $product['id'] : '/admin/products';
$name          = (string) ($old['name'] ?? '');
$slug          = (string) ($old['slug'] ?? '');
$description   = (string) ($old['description'] ?? '');
$isFeatured    = (bool) ($old['is_featured'] ?? false);
$isActive      = (bool) ($old['is_active'] ?? false);
$categoryIds   = $old['category_ids'] ?? [];
$primaryId     = (int) ($old['primary_category_id'] ?? 0);
$variantErrors = $errors['variants'] ?? [];
$specErrors    = $errors['specs'] ?? [];
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0"><?= e($title) ?></h4>
        <p class="mb-0"><a href="/admin/products">← К списку товаров</a></p>
    </div>
</div>

<?php if (!empty($errors['publish_needs_variant'])): ?>
    <div class="alert alert-danger">Для публикации добавьте хотя бы один активный Вариант с ценой — иначе снимите отметку «Опубликован».</div>
<?php endif; ?>

<form method="post" action="<?= e($formAction) ?>" id="product-form">
    <?= csrfField() ?>

    <div class="card custom-card">
        <div class="card-header">
            <div class="card-title">Товар</div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="product-name" class="form-label">Название</label>
                    <input type="text" id="product-name" name="name" class="form-control <?= !empty($errors['name']) ? 'is-invalid' : '' ?>" value="<?= e($name) ?>">
                    <?php if (!empty($errors['name'])): ?>
                        <div class="invalid-feedback">Введите название.</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label for="product-slug" class="form-label">Slug (необязательно — сгенерируется из названия)</label>
                    <input type="text" id="product-slug" name="slug" class="form-control" value="<?= e($slug) ?>">
                </div>
                <div class="col-12">
                    <label for="product-description" class="form-label">Описание</label>
                    <textarea id="product-description" name="description" class="form-control" rows="4"><?= e($description) ?></textarea>
                </div>
                <?php if ($isEdit && (string) (currentUser()['role'] ?? '') === 'admin'): ?>
                    <div class="col-12">
                        <?php if ($aiDescriptionAvailable): ?>
                            <div class="border rounded p-3" data-ai-description data-product-id="<?= e((string) $product['id']) ?>">
                                <p class="fw-semibold mb-2">Черновик описания от ИИ</p>
                                <div class="row g-2 mb-2">
                                    <div class="col-md-3">
                                        <input type="text" class="form-control form-control-sm" placeholder="Категория" data-ai-description-field="category">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" class="form-control form-control-sm" placeholder="Материал" data-ai-description-field="material">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" class="form-control form-control-sm" placeholder="Размер" data-ai-description-field="size">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" class="form-control form-control-sm" placeholder="Механизм" data-ai-description-field="mechanism">
                                    </div>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm" data-ai-description-generate>Сгенерировать черновик</button>
                                <div class="mt-2" data-ai-description-message></div>
                                <div class="mt-2" data-ai-description-result <?= ((string) ($product['description_draft'] ?? '')) === '' ? 'hidden' : '' ?>>
                                    <textarea class="form-control form-control-sm" rows="3" readonly data-ai-description-draft><?= e((string) ($product['description_draft'] ?? '')) ?></textarea>
                                    <button type="button" class="btn btn-outline-success btn-sm mt-2" data-ai-description-apply>Применить к описанию</button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-secondary mb-0">Генератор черновика описания недоступен — провайдер ИИ не настроен.</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="product-featured" <?= $isFeatured ? 'checked' : '' ?>>
                        <label class="form-check-label" for="product-featured">Хит продаж (блок на Главной)</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="product-active" <?= $isActive ? 'checked' : '' ?>>
                        <label class="form-check-label" for="product-active">Опубликован (виден на витрине)</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card custom-card">
        <div class="card-header">
            <div class="card-title">Категории</div>
        </div>
        <div class="card-body">
            <?php if (!empty($errors['category_ids'])): ?>
                <div class="alert alert-danger">Отметьте хотя бы одну категорию.</div>
            <?php endif; ?>
            <?php if (!empty($errors['primary_category_id'])): ?>
                <div class="alert alert-danger">Выберите ровно одну основную категорию среди отмеченных.</div>
            <?php endif; ?>
            <div class="row g-2">
                <?php foreach ($categories as $category): ?>
                    <?php
                    $categoryLabel  = $category['parent_name'] !== null ? $category['parent_name'] . ' / ' . $category['name'] : $category['name'];
                    $categoryId     = $category['id'];
                    $isChecked      = in_array($categoryId, $categoryIds, true);
                    ?>
                    <div class="col-md-4 d-flex align-items-center gap-2" data-category-option>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="categories[]" value="<?= e((string) $categoryId) ?>" id="category-<?= e((string) $categoryId) ?>" <?= $isChecked ? 'checked' : '' ?> data-category-checkbox>
                            <label class="form-check-label" for="category-<?= e((string) $categoryId) ?>"><?= e($categoryLabel) ?></label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="primary_category_id" value="<?= e((string) $categoryId) ?>" id="category-primary-<?= e((string) $categoryId) ?>" <?= $primaryId === $categoryId ? 'checked' : '' ?> data-category-primary>
                            <label class="form-check-label" for="category-primary-<?= e((string) $categoryId) ?>">основная</label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card custom-card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div class="card-title mb-0">Характеристики</div>
            <?php if ($isEdit): ?>
                <?php $specsStatus = (string) ($product['specs_status'] ?? 'pending'); ?>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge <?= $specsStatus === 'confirmed' ? 'bg-success' : 'bg-warning' ?>"><?= $specsStatus === 'confirmed' ? 'Подтверждены' : 'Требует разбора' ?></span>
                    <?php if ((string) (currentUser()['role'] ?? '') === 'admin'): ?>
                        <a href="/admin/ai/specs/<?= e((string) $product['id']) ?>" class="btn btn-outline-primary btn-sm">Разбор ИИ</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($isEdit && ($old['specs'] ?? []) !== []): ?>
                <p class="text-muted small mb-2">Ниже — уже сохранённые характеристики этого товара (<?= count($old['specs']) ?>). Их можно изменить, убрать или добавить ещё.</p>
            <?php else: ?>
                <p class="text-muted small mb-2">Необязательно. Например: «Ширина» — «80 см».</p>
            <?php endif; ?>
            <div data-spec-row-list>
                <?php for ($i = 0; $i < $specRows; $i++):
                    $index     = $i;
                    $specName  = (string) ($old['specs'][$i]['name'] ?? '');
                    $specValue = (string) ($old['specs'][$i]['value'] ?? '');
                    $rowErrors = $specErrors[$i] ?? [];
                    include ROOT_PATH . '/src/Views/components/admin/spec-row.php';
                ?>
                <?php endfor; ?>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" data-spec-row-add>Добавить характеристику</button>

            <template data-spec-row-template>
                <?php
                $index     = '__INDEX__';
                $specName  = '';
                $specValue = '';
                $rowErrors = [];
                include ROOT_PATH . '/src/Views/components/admin/spec-row.php';
                ?>
            </template>
        </div>
    </div>

    <div class="card custom-card">
        <div class="card-header">
            <div class="card-title">Варианты</div>
        </div>
        <div class="card-body">
            <?php if ($isEdit && ($old['variants'] ?? []) !== []): ?>
                <p class="text-muted small mb-2">Ниже — уже сохранённые Варианты этого товара (<?= count($old['variants']) ?>), включая ранее скрытые (галочка «Активен» снята). «Убрать Вариант» снимает его с формы — после сохранения он не удалится, а станет неактивным и снова появится здесь при следующем открытии, с той же снятой галочкой.</p>
            <?php else: ?>
                <p class="text-muted small mb-2">Добавьте хотя бы один Вариант с ценой.</p>
            <?php endif; ?>
            <div data-variant-row-list>
                <?php for ($i = 0; $i < $variantRows; $i++):
                    $index            = $i;
                    $id               = (int) ($old['variants'][$i]['id'] ?? 0);
                    $sku              = (string) ($old['variants'][$i]['sku'] ?? '');
                    $material         = (string) ($old['variants'][$i]['material'] ?? '');
                    $mechanismType    = (string) ($old['variants'][$i]['mechanism_type'] ?? '');
                    $price            = (string) ($old['variants'][$i]['price'] ?? '');
                    $productionTime   = (string) ($old['variants'][$i]['production_time'] ?? '');
                    $isShowroomSample = (bool) ($old['variants'][$i]['is_showroom_sample'] ?? false);
                    $discountPercent  = (string) ($old['variants'][$i]['discount_percent'] ?? '');
                    $variantIsActive  = isset($old['variants'][$i]['is_active']) ? (bool) $old['variants'][$i]['is_active'] : true;
                    $rowErrors        = $variantErrors[$i] ?? [];
                    include ROOT_PATH . '/src/Views/components/admin/variant-row.php';
                ?>
                <?php endfor; ?>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" data-variant-row-add>Добавить Вариант</button>

            <template data-variant-row-template>
                <?php
                $index            = '__INDEX__';
                $id               = 0;
                $sku              = '';
                $material         = '';
                $mechanismType    = '';
                $price            = '';
                $productionTime   = '';
                $isShowroomSample = false;
                $discountPercent  = '';
                $variantIsActive  = true;
                $rowErrors        = [];
                include ROOT_PATH . '/src/Views/components/admin/variant-row.php';
                ?>
            </template>
        </div>
    </div>

    <?php if (!$isEdit): ?>
        <button type="submit" class="btn btn-primary mb-4">Создать</button>
    <?php endif; ?>
</form>

<?php if ($isEdit): ?>
    <div class="card custom-card">
        <div class="card-header">
            <div class="card-title">Фото по Вариантам</div>
        </div>
        <div class="card-body">
            <?php if (($product['variants'] ?? []) === []): ?>
                <p class="text-muted small mb-0">У Товара пока нет Вариантов — добавьте и сохраните хотя бы один выше.</p>
            <?php else: ?>
                <?php $productId = (int) $product['id']; ?>
                <?php foreach ($product['variants'] as $variant): ?>
                    <?php include ROOT_PATH . '/src/Views/components/admin/variant-photos.php'; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php
    // Кнопка сохранения полей Товара вынесена сюда — визуально последним
    // шагом после фото, хотя физически отправляет форму `#product-form`
    // (атрибут `form`, HTML5): вложить саму форму сюда нельзя — блок
    // фото Варианта уже содержит свои `<form>` (`variant-photos.php`), а
    // вложенные `<form>` в HTML недопустимы.
    ?>
    <button type="submit" form="product-form" class="btn btn-primary mb-4">Сохранить</button>
<?php endif; ?>

<?php include ROOT_PATH . '/src/Views/layout/admin-footer.php'; ?>
