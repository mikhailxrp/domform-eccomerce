<?php

declare(strict_types=1);

/** @var array $review одобренный отзыв о магазине — getApprovedStoreReviews() */

$rating = (int) $review['rating'];
?>
<div class="col-lg-4 col-md-6">
    <div class="store-review-card">
        <div class="store-review-card__header">
            <span class="store-review-card__name"><?= e($review['name']) ?></span>
            <span class="review-list__stars" aria-hidden="true">
                <span class="review-list__stars-fill" style="width: <?= e((string) ($rating * 20)) ?>%"></span>
            </span>
            <span class="visually-hidden">Оценка <?= e((string) $rating) ?> из 5</span>
        </div>
        <p class="store-review-card__text"><?= e($review['text']) ?></p>
        <span class="store-review-card__date"><?= e(date('d.m.Y', strtotime((string) $review['created_at']))) ?></span>
    </div>
</div>
