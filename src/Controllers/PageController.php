<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/ContentPage.php';
require_once ROOT_PATH . '/src/Models/AboutGallery.php';
require_once ROOT_PATH . '/src/Models/Review.php';
require_once ROOT_PATH . '/src/Models/User.php';
require_once ROOT_PATH . '/src/Models/CallbackRequest.php';
require_once ROOT_PATH . '/src/Core/Content.php';
require_once ROOT_PATH . '/src/Core/Callback.php';

class PageController
{
    /**
     * Общий шаблон текстовой страницы (`FR-CNT-004…006`). Slug сначала
     * сверяется с whitelist `CONTENT_PAGE_SLUGS`, потом ищется в БД —
     * оба промаха дают один и тот же 404, чтобы по ответу нельзя было
     * отличить «нет такой страницы» от «строка ещё не засидена».
     *
     * `about`/`showroom`/`contacts` — единственные slug из whitelist, у
     * которых есть свой маршрут и View (`about()`/`showroom()` — Таск 3,
     * `contacts()` — Таск 4 Фазы 8); старый `/pages/{slug}` для них —
     * постоянный редирект, а не 404 (страницы существовали меньше
     * суток на момент первого редиректа, но у кого-то могла успеть
     * сохраниться ссылка).
     */
    public function show(string $slug): void
    {
        if ($slug === 'about' || $slug === 'showroom' || $slug === 'contacts') {
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
            'title'       => $page['title'],
            'description' => defaultPageDescription($page['title']),
            'canonical'   => rtrim(APP_URL, '/') . publicUrlForContentSlug($slug),
            'page'        => $page,
            'bodyHtml'    => renderContentBody((string) $page['body']),
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
            'description'   => defaultPageDescription($page['title']),
            'canonical'     => rtrim(APP_URL, '/') . '/about',
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
            'title'       => $page['title'],
            'description' => defaultPageDescription($page['title']),
            'canonical'   => rtrim(APP_URL, '/') . '/showroom',
            'page'        => $page,
            'bodyHtml'    => renderContentBody((string) $page['body']),
        ]);
    }

    /**
     * `FR-CNT-001` — реквизиты магазина из `settings` (читаются View
     * напрямую, как на `/showroom`), редактируемый текст
     * `content_pages.contacts`, форма «Перезвоните мне». `$old`/`$errors`
     * — от `storeCallback()` при ошибке валидации; на обычном заходе
     * (`$old === []`) авторизованному Покупателю подставляются
     * сохранённые имя/телефон, как на `/checkout`
     * (`CheckoutController::renderCheckoutPage()`).
     */
    public function contacts(array $old = [], array $errors = []): void
    {
        $page = findContentPageBySlug('contacts');
        if ($page === null) {
            abort404();
        }

        if ($old === []) {
            $user = currentUser();
            if ($user !== null) {
                $account = findUserById($user['id']);
                if ($account !== null) {
                    $old = [
                        'name'    => $account['name'],
                        'phone'   => (string) ($account['phone'] ?? ''),
                        'comment' => '',
                    ];
                }
            }
        }

        render('pages/contacts', [
            'title'       => $page['title'],
            'description' => defaultPageDescription($page['title']),
            'canonical'   => rtrim(APP_URL, '/') . '/contacts',
            'page'        => $page,
            'bodyHtml'    => renderContentBody((string) $page['body']),
            'old'         => $old,
            'errors'      => $errors,
        ]);
    }

    /**
     * Форма «Перезвоните мне» (`FR-CNT-001`) — тот же rate-limit
     * (3 / 600 сек), что `ReviewController::store()`, лимит не
     * снимается успешной отправкой (защита от спама количеством).
     * Ошибка валидации — прямой рендер `contacts()` с `$old`/`$errors`,
     * без редиректа (по образцу `CheckoutController::store()`).
     */
    public function storeCallback(): void
    {
        requireCsrf();

        if (tooManyAttempts('callback', 3, 600)) {
            setFlash('error', 'Слишком много заявок подряд. Попробуйте позже.');
            redirect('/contacts');
        }
        hitRateLimit('callback');

        $input  = normalizeCallbackInput([
            'name'    => input('name'),
            'phone'   => input('phone'),
            'comment' => input('comment'),
        ]);
        $errors = validateCallbackInput($input);

        if (in_array(true, $errors, true)) {
            $this->contacts($input, $errors);
            return;
        }

        createCallbackRequest($input);

        setFlash('success', 'Спасибо! Мы перезвоним в ближайшее время.');
        redirect('/contacts');
    }
}
