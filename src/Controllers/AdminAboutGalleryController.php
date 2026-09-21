<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/AboutGallery.php';
require_once ROOT_PATH . '/src/Services/FileUpload.php';

/**
 * Галерея фото на `/about` (`about_gallery_images`, `ADR-046`) —
 * `manager`/`admin`, как остальное редактирование контента
 * (`FR-ADM-003`), не `admin`-only.
 */
class AdminAboutGalleryController
{
    public function index(): void
    {
        requireRole(['manager', 'admin']);

        $this->renderIndex(null);
    }

    public function store(): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        if (countAboutGalleryImages() >= ABOUT_GALLERY_MAX) {
            $this->renderIndex('Уже загружено максимум фото (' . ABOUT_GALLERY_MAX . ').');
            return;
        }

        $file         = $_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE];
        $detectedMime = detectUploadedMime((string) ($file['tmp_name'] ?? ''));
        $error        = validateUploadedImage($file, $detectedMime);

        if ($error !== null) {
            $this->renderIndex($error);
            return;
        }

        $path = storeContentImage($file);
        if ($path === null) {
            $this->renderIndex('Не удалось сохранить файл.');
            return;
        }

        createAboutGalleryImage($path);

        setFlash('success', 'Фото добавлено.');
        redirect('/admin/about-gallery');
    }

    public function delete(string $id): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        $image = findAboutGalleryImage((int) $id);
        if ($image === null) {
            setFlash('error', 'Фото не найдено.');
            redirect('/admin/about-gallery');
        }

        deleteAboutGalleryImage((int) $id);
        deleteStoredFile($image['path']);

        setFlash('success', 'Фото удалено.');
        redirect('/admin/about-gallery');
    }

    private function renderIndex(?string $uploadError): void
    {
        render('admin/about-gallery/index', [
            'title'       => 'Галерея «О компании»',
            'images'      => getAboutGalleryImages(),
            'maxReached'  => countAboutGalleryImages() >= ABOUT_GALLERY_MAX,
            'uploadError' => $uploadError,
        ]);
    }
}
