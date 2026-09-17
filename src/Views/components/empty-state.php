<?php

declare(strict_types=1);

/** @var string $resetUrl */
?>
<div class="empty-state">
    <p class="empty-state__title">Ничего не найдено</p>
    <p>Попробуйте изменить фильтры или сбросить их.</p>
    <a class="btn btn-primary btn-hover-dark catalog-reset-link" href="<?= e($resetUrl) ?>">Сбросить фильтры</a>
</div>
