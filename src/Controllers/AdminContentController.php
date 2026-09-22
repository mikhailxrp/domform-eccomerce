<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/ContentPage.php';
require_once ROOT_PATH . '/src/Core/Content.php';
require_once ROOT_PATH . '/src/Services/FileUpload.php';

/**
 * `/admin/content` (`FR-ADM-003` п. 1, 3, Таск 6 Фазы 8) — `manager`/
 * `admin`, как остальное редактирование контента (не `admin`-only).
 */
class AdminContentController
{
    public function index(): void
    {
        requireRole(['manager', 'admin']);

        render('admin/content/index', [
            'title' => 'Контент',
            'pages' => getAllContentPages(),
        ]);
    }

    /**
     * `$old`/`$errors` — только после неудачного `update()`, прямой
     * рендер этой же страницы без редиректа (по образцу
     * `AdminReviewController::index()`).
     */
    public function edit(string $slug, array $old = [], array $errors = []): void
    {
        requireRole(['manager', 'admin']);

        if (!isContentPageSlug($slug)) {
            abort404();
        }

        $page = findContentPageBySlug($slug);
        if ($page === null) {
            abort404();
        }

        render('admin/content/edit', [
            'title'  => 'Редактирование: ' . $page['title'],
            'page'   => $page,
            'old'    => $old !== [] ? $old : ['title' => $page['title'], 'body' => $page['body']],
            'errors' => $errors,
        ]);
    }

    /**
     * Текст сохраняется независимо от результата загрузки фото — фото
     * необязательно (в отличие от `AdminAboutGalleryController::store()`,
     * где оно обязательно): ошибка загрузки не должна откатывать уже
     * введённый текст (по духу `AdminReviewController::storeShopReview()`,
     * где фото автора отзыва тоже необязательно).
     */
    public function update(string $slug): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        if (!isContentPageSlug($slug)) {
            abort404();
        }

        $page = findContentPageBySlug($slug);
        if ($page === null) {
            abort404();
        }

        $input = [
            'title' => trim((string) input('title')),
            'body'  => trim((string) input('body')),
        ];
        $errors = validateContentPageInput($input);

        if (in_array(true, $errors, true)) {
            $this->edit($slug, $input, $errors);
            return;
        }

        updateContentPage($slug, $input['title'], $input['body']);

        $file        = $_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE];
        $hasPhoto    = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $uploadError = null;

        if ($hasPhoto) {
            $detectedMime = detectUploadedMime((string) ($file['tmp_name'] ?? ''));
            $uploadError  = validateUploadedImage($file, $detectedMime);

            if ($uploadError === null) {
                $newPath = storeContentImage($file);
                if ($newPath === null) {
                    $uploadError = 'Не удалось сохранить файл.';
                } else {
                    updateContentPageImage($slug, $newPath);
                    if ($page['image_path'] !== null) {
                        deleteStoredFile($page['image_path']);
                    }
                }
            }
        }

        setFlash(
            $uploadError !== null ? 'error' : 'success',
            $uploadError !== null ? 'Текст сохранён, но фото не сохранилось: ' . $uploadError : 'Страница обновлена.'
        );
        redirect('/admin/content/' . $slug . '/edit');
    }

    public function deleteImage(string $slug): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        if (!isContentPageSlug($slug)) {
            abort404();
        }

        $page = findContentPageBySlug($slug);
        if ($page === null) {
            abort404();
        }

        if ($page['image_path'] !== null) {
            deleteStoredFile($page['image_path']);
            updateContentPageImage($slug, null);
        }

        setFlash('success', 'Фото удалено.');
        redirect('/admin/content/' . $slug . '/edit');
    }
}
