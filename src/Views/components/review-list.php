<?php

declare(strict_types=1);

/** @var array<int,array> $reviews одобренные отзывы — getApprovedProductReviews() */
/** @var ?string $averageRating null, если отзывов ещё нет */
?>
<div class="reviews-comment">
    <?php if ($reviews === []): ?>
        <p class="reviews-empty">Отзывов пока нет — станьте первым.</p>
    <?php else: ?>
        <p class="reviews-summary">
            Средняя оценка: <strong><?= e($averageRating) ?></strong> из 5
            (<?= e((string) count($reviews)) ?>)
        </p>

        <?php foreach ($reviews as $review): ?>
            <?php $rating = (int) $review['rating']; ?>
            <div class="single-reviews">
                <div class="comment-content">
                    <div class="author-name-rating">
                        <h6 class="name"><?= e($review['name']) ?></h6>
                        <span class="review-list__stars" aria-hidden="true">
                            <span class="review-list__stars-fill" style="width: <?= e((string) ($rating * 20)) ?>%"></span>
                        </span>
                        <span class="visually-hidden">Оценка <?= e((string) $rating) ?> из 5</span>
                    </div>
                    <span class="date"><?= e(date('d.m.Y', strtotime((string) $review['created_at']))) ?></span>
                    <p><?= e($review['text']) ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
