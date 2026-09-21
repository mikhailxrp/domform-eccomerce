<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<int, array<string, mixed>> $reviews */
/** @var string $statusFilter */
/** @var array $pagination */
/** @var array<int, string> $paginationLinks */
/** @var string|null $prevUrl */
/** @var string|null $nextUrl */
/** @var array $storeReviewOld */
/** @var array $storeReviewErrors */

include ROOT_PATH . '/src/Views/layout/admin-header.php';

$old    = $storeReviewOld;
$errors = $storeReviewErrors;
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0">Отзывы</h4>
        <p class="mb-0 text-muted">Модерация отзывов о Товарах и о магазине (`FR-ADM-004`)</p>
    </div>
</div>

<div class="card custom-card">
    <div class="card-body">
        <form method="get" action="/admin/reviews" class="row g-2 align-items-end mb-3">
            <div class="col-sm-4 col-md-3">
                <label for="review-status-filter" class="form-label">Статус</label>
                <select id="review-status-filter" name="status" class="form-select">
                    <option value=""<?= $statusFilter === '' ? ' selected' : '' ?>>Все статусы</option>
                    <?php foreach ([REVIEW_STATUS_PENDING, REVIEW_STATUS_APPROVED, REVIEW_STATUS_REJECTED] as $statusValue): ?>
                        <option value="<?= e($statusValue) ?>"<?= $statusFilter === $statusValue ? ' selected' : '' ?>><?= e(reviewStatusLabel($statusValue)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-2 col-md-2">
                <button type="submit" class="btn btn-primary w-100">Показать</button>
            </div>
        </form>

        <?php if ($reviews === []): ?>
            <p class="text-muted text-center py-5 mb-0">Отзывов не найдено.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered text-nowrap w-100">
                    <thead>
                        <tr>
                            <th>Автор</th>
                            <th>Оценка</th>
                            <th>О чём</th>
                            <th>Дата</th>
                            <th>Текст</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reviews as $review): ?>
                            <?php
                            $statusBadgeClass = match ($review['status']) {
                                REVIEW_STATUS_APPROVED => 'bg-success',
                                REVIEW_STATUS_REJECTED  => 'bg-danger',
                                default                  => 'bg-warning',
                            };
                            $isAboutProduct = $review['product_id'] !== null;
                            ?>
                            <tr>
                                <td><?= e($review['name']) ?></td>
                                <td><?= e((string) $review['rating']) ?>/5</td>
                                <td>
                                    <?php if ($isAboutProduct): ?>
                                        <a href="/product/<?= e((string) $review['product_slug']) ?>"><?= e((string) $review['product_name']) ?></a>
                                    <?php else: ?>
                                        О магазине
                                    <?php endif; ?>
                                </td>
                                <td><?= e(date('d.m.Y H:i', strtotime((string) $review['created_at']))) ?></td>
                                <td class="text-wrap"><?= nl2br(e($review['text'])) ?></td>
                                <td><span class="badge <?= $statusBadgeClass ?>"><?= e(reviewStatusLabel($review['status'])) ?></span></td>
                                <td class="d-flex gap-1">
                                    <form method="post" action="/admin/reviews/<?= e((string) $review['id']) ?>/approve">
                                        <?= csrfField() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-success">Одобрить</button>
                                    </form>
                                    <form method="post" action="/admin/reviews/<?= e((string) $review['id']) ?>/reject">
                                        <?= csrfField() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Отклонить</button>
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

<?php include ROOT_PATH . '/src/Views/components/pagination.php'; ?>

<div class="card custom-card">
    <div class="card-header">
        <h5 class="card-title mb-0">Добавить отзыв о магазине</h5>
        <p class="mb-0 text-muted">Перенос отзыва из Instagram/WhatsApp (`FR-HOME-007`) — публикуется сразу, без модерации</p>
    </div>
    <div class="card-body">
        <form method="post" action="/admin/reviews" class="row g-3" enctype="multipart/form-data">
            <?= csrfField() ?>
            <div class="col-md-4">
                <label for="store-review-name" class="form-label">Имя</label>
                <input type="text" id="store-review-name" name="name" class="form-control<?= !empty($errors['name']) ? ' is-invalid' : '' ?>" value="<?= e($old['name'] ?? '') ?>">
                <?php if (!empty($errors['name'])): ?>
                    <div class="invalid-feedback">Имя от 2 до 150 символов.</div>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <label for="store-review-email" class="form-label">Email</label>
                <input type="email" id="store-review-email" name="email" class="form-control<?= !empty($errors['email']) ? ' is-invalid' : '' ?>" value="<?= e($old['email'] ?? '') ?>">
                <?php if (!empty($errors['email'])): ?>
                    <div class="invalid-feedback">Введите корректный email.</div>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <label for="store-review-rating" class="form-label">Оценка</label>
                <select id="store-review-rating" name="rating" class="form-select<?= !empty($errors['rating']) ? ' is-invalid' : '' ?>">
                    <option value="">—</option>
                    <?php for ($value = 5; $value >= 1; $value--): ?>
                        <option value="<?= $value ?>"<?= (string) $value === (string) ($old['rating'] ?? '') ? ' selected' : '' ?>><?= $value ?></option>
                    <?php endfor; ?>
                </select>
                <?php if (!empty($errors['rating'])): ?>
                    <div class="invalid-feedback">Выберите оценку от 1 до 5.</div>
                <?php endif; ?>
            </div>
            <div class="col-md-12">
                <label for="store-review-text" class="form-label">Текст</label>
                <textarea id="store-review-text" name="text" class="form-control<?= !empty($errors['text']) ? ' is-invalid' : '' ?>" rows="3"><?= e($old['text'] ?? '') ?></textarea>
                <?php if (!empty($errors['text'])): ?>
                    <div class="invalid-feedback">Напишите отзыв (не длиннее 2000 символов).</div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <label for="store-review-photo" class="form-label">Фото автора (необязательно)</label>
                <input type="file" id="store-review-photo" name="photo" class="form-control<?= !empty($errors['photo']) ? ' is-invalid' : '' ?>" accept="image/jpeg,image/png,image/webp">
                <?php if (!empty($errors['photo'])): ?>
                    <div class="invalid-feedback">Только JPG, PNG или WEBP, до 5 МБ.</div>
                <?php endif; ?>
                <div class="form-text">Одобренный отзыв о магазине с фото может быть показан цитатой на странице «О компании».</div>
            </div>
            <div class="col-md-12">
                <button type="submit" class="btn btn-primary">Добавить отзыв</button>
            </div>
        </form>
    </div>
</div>

<?php include ROOT_PATH . '/src/Views/layout/admin-footer.php'; ?>
