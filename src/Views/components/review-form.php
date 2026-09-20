<?php

declare(strict_types=1);

/** @var string $productSlug */
/** @var array $reviewOld значения после неудачной отправки — из ReviewController::store(),
 *  либо имя/email авторизованного Покупателя на обычном заходе */
/** @var array $reviewErrors */

$old    = $reviewOld;
$errors = $reviewErrors;
?>
<div class="reviews-form">
    <h3 class="reviews-title">Оставить отзыв</h3>

    <form method="post" action="/product/<?= e($productSlug) ?>/reviews#reviews" novalidate>
        <?= csrfField() ?>
        <div class="row">
            <div class="col-md-6">
                <div class="single-form">
                    <label for="review-name" class="visually-hidden">Ваше имя</label>
                    <input
                        type="text"
                        id="review-name"
                        name="name"
                        placeholder="Ваше имя"
                        class="<?= !empty($errors['name']) ? 'is-invalid' : '' ?>"
                        value="<?= e($old['name'] ?? '') ?>"
                        required
                    >
                    <?php if (!empty($errors['name'])): ?>
                        <div class="invalid-feedback">Имя от 2 до 150 символов.</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="single-form">
                    <label for="review-email" class="visually-hidden">Email</label>
                    <input
                        type="email"
                        id="review-email"
                        name="email"
                        placeholder="john.smith@example.com"
                        class="<?= !empty($errors['email']) ? 'is-invalid' : '' ?>"
                        value="<?= e($old['email'] ?? '') ?>"
                        required
                    >
                    <?php if (!empty($errors['email'])): ?>
                        <div class="invalid-feedback">Введите корректный email.</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-12">
                <div class="review-rating">
                    <span class="title" id="review-rating-label">Оценка:</span>
                    <div
                        class="review-form__stars<?= !empty($errors['rating']) ? ' review-form__stars--invalid' : '' ?>"
                        role="radiogroup"
                        aria-labelledby="review-rating-label"
                    >
                        <?php for ($value = 5; $value >= 1; $value--): ?>
                            <input
                                type="radio"
                                name="rating"
                                id="review-rating-<?= $value ?>"
                                value="<?= $value ?>"
                                class="review-form__star-input"
                                <?= ((string) $value === (string) ($old['rating'] ?? '')) ? 'checked' : '' ?>
                                required
                            >
                            <label for="review-rating-<?= $value ?>" class="review-form__star-label" aria-label="<?= $value ?> из 5">★</label>
                        <?php endfor; ?>
                    </div>
                    <?php if (!empty($errors['rating'])): ?>
                        <div class="invalid-feedback d-block">Выберите оценку от 1 до 5.</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-12">
                <div class="single-form">
                    <label for="review-text" class="visually-hidden">Ваш отзыв</label>
                    <textarea
                        id="review-text"
                        name="text"
                        placeholder="Ваш отзыв"
                        class="<?= !empty($errors['text']) ? 'is-invalid' : '' ?>"
                        required
                    ><?= e($old['text'] ?? '') ?></textarea>
                    <?php if (!empty($errors['text'])): ?>
                        <div class="invalid-feedback">Напишите отзыв (не длиннее 2000 символов).</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-12">
                <div class="single-form">
                    <button class="btn btn-dark btn-hover-primary" type="submit">Отправить отзыв</button>
                </div>
            </div>
        </div>
    </form>
</div>
