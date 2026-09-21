<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/ContentPage.php';
require_once ROOT_PATH . '/src/Models/AboutGallery.php';
require_once ROOT_PATH . '/src/Models/Review.php';
require_once ROOT_PATH . '/src/Core/Content.php';

class PageController
{
    /**
     * Общий шаблон текстовой страницы (`FR-CNT-004…006`). Slug сначала
     * сверяется с whitelist `CONTENT_PAGE_SLUGS`, потом ищется в БД —
     * оба промаха дают один и тот же 404, чтобы по ответу нельзя было
     * отличить «нет такой страницы» от «строка ещё не засидена».
     *
     * `about`/`showroom` — единственные slug из whitelist, у которых
     * есть свой маршрут и View (`about()`/`showroom()`, Таск 3 Фазы 8);
     * старый `/pages/{slug}` для них — постоянный редирект, а не 404
     * (страницы существовали меньше суток, но у кого-то могла успеть
     * сохраниться ссылка).
     */
    public function show(string $slug): void
    {
        if ($slug === 'about' || $slug === 'showroom') {
            header('Location: /' . $slug, true, 301);
            exit;
        }

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

    /**
     * `FR-CNT-002` — текст/фото страницы редактируются в Панели
     * (Таск 6), блок команды ниже статичен во View (`ADR` в
     * «Решения фазы» `phase-8.md`): состав задан ТЗ явно, это
     * структура страницы, а не редактируемое содержимое.
     *
     * `galleryImages`/`testimonials` — внеплановый редизайн (`ADR-046`):
     * галерея показывается только если загружено хотя бы одно фото,
     * слайдер цитат — только если есть хотя бы один одобренный отзыв о
     * магазине с фото (`[]` — секция отсутствует в HTML, не просто
     * пустая).
     */
    public function about(): void
    {
        $page = findContentPageBySlug('about');
        if ($page === null) {
            abort404();
        }

        render('pages/about', [
            'title'         => $page['title'],
            'page'          => $page,
            'bodyHtml'      => renderContentBody((string) $page['body']),
            'galleryImages' => getAboutGalleryImages(),
            'testimonials'  => getApprovedStoreReviewsWithPhoto(HOME_BLOCK_LIMIT),
        ]);
    }

    /**
     * `FR-CNT-003` — реквизиты (адрес, режим работы, ссылка на карту)
     * читаются View напрямую через `setting()`, как и на других
     * страницах витрины (`footer.php`, `checkout/index.php`) — здесь
     * не дублируются в массиве данных Controller.
     */
    public function showroom(): void
    {
        $page = findContentPageBySlug('showroom');
        if ($page === null) {
            abort404();
        }

        render('pages/showroom', [
            'title'    => $page['title'],
            'page'     => $page,
            'bodyHtml' => renderContentBody((string) $page['body']),
        ]);
    }
}
