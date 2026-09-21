<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/ContentPage.php';
require_once ROOT_PATH . '/src/Core/Content.php';

class PageController
{
    /**
     * Общий шаблон текстовой страницы (`FR-CNT-004…006`). Slug сначала
     * сверяется с whitelist `CONTENT_PAGE_SLUGS`, потом ищется в БД —
     * оба промаха дают один и тот же 404, чтобы по ответу нельзя было
     * отличить «нет такой страницы» от «строка ещё не засидена».
     */
    public function show(string $slug): void
    {
        if (!isContentPageSlug($slug)) {
            abort404();
        }

        $page = findContentPageBySlug($slug);
        if ($page === null) {
            abort404();
        }

        render('pages/show', [
            'title'    => $page['title'],
            'page'     => $page,
            'bodyHtml' => renderContentBody((string) $page['body']),
        ]);
    }
}
