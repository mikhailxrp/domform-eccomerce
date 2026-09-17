<?php

declare(strict_types=1);

/** @var array<int,array> $breadcrumbs категории от родителя к текущей, [] — сама страница каталога */
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Главная</a></li>
        <?php if ($breadcrumbs === []): ?>
            <li class="breadcrumb-item active" aria-current="page">Каталог</li>
        <?php else: ?>
            <?php $lastIndex = array_key_last($breadcrumbs); ?>
            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                <?php if ($index === $lastIndex): ?>
                    <li class="breadcrumb-item active" aria-current="page"><?= e($crumb['name']) ?></li>
                <?php else: ?>
                    <li class="breadcrumb-item"><a href="/catalog/<?= e($crumb['slug']) ?>"><?= e($crumb['name']) ?></a></li>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </ol>
</nav>
