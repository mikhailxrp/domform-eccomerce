<?php

declare(strict_types=1);

/** @var array $pagination */
/** @var array<int,string> $paginationLinks */
/** @var string|null $prevUrl */
/** @var string|null $nextUrl */

if ($pagination['total_pages'] <= 1) {
    return;
}
?>
<div class="page-pagination">
    <ul class="pagination justify-content-center">
        <li class="page-item<?= $prevUrl === null ? ' disabled' : '' ?>">
            <a class="page-link" href="<?= e($prevUrl ?? '#') ?>" aria-label="Предыдущая страница"><i class="fa fa-angle-left"></i></a>
        </li>
        <?php foreach ($paginationLinks as $pageNumber => $url): ?>
            <li class="page-item">
                <a class="page-link<?= $pageNumber === $pagination['page'] ? ' active' : '' ?>" href="<?= e($url) ?>"><?= e((string) $pageNumber) ?></a>
            </li>
        <?php endforeach; ?>
        <li class="page-item<?= $nextUrl === null ? ' disabled' : '' ?>">
            <a class="page-link" href="<?= e($nextUrl ?? '#') ?>" aria-label="Следующая страница"><i class="fa fa-angle-right"></i></a>
        </li>
    </ul>
</div>
