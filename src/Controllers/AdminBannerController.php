<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Banner.php';
require_once ROOT_PATH . '/src/Core/BannerForm.php';
require_once ROOT_PATH . '/src/Services/FileUpload.php';

/**
 * `/admin/banners` (`FR-ADM-003` п. 2, `FR-HOME-001`, Таск 7 Фазы 8) —
 * `manager`/`admin`, как остальное редактирование контента (не
 * `admin`-only). Физического удаления нет — только `toggle()`, тот же
 * паттерн `is_active`, что у Товаров.
 */
class AdminBannerController
{
    public function index(): void
    {
        requireRole(['manager', 'admin']);

        render('admin/banners/index', [
            'title'   => 'Баннеры',
            'banners' => getAllBanners(),
        ]);
    }

    public function create(): void
    {
        requireRole(['manager', 'admin']);

        $this->renderForm(null, ['title' => '', 'link' => '', 'sort_order' => '0'], []);
    }

    public function store(): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        $input                            = $this->bannerInputFromRequest();
        [$hasFile, $file, $uploadError]   = $this->validateUpload();
        $hasImage                         = $hasFile && $uploadError === null;

        $errors = validateBannerInput($input, $hasImage);
        if ($uploadError !== null) {
            $errors['image'] = true;
        }

        if (in_array(true, $errors, true)) {
            $this->renderForm(null, $input, $errors, $uploadError);
            return;
        }

        $imagePath = storeContentImage($file);
        if ($imagePath === null) {
            $this->renderForm(null, $input, ['image' => true], 'Не удалось сохранить файл.');
            return;
        }

        createBanner($input + ['image_path' => $imagePath]);

        setFlash('success', 'Баннер создан.');
        redirect('/admin/banners');
    }

    public function edit(string $id): void
    {
        requireRole(['manager', 'admin']);

        $banner = findBannerById((int) $id);
        if ($banner === null) {
            abort404();
        }

        $this->renderForm($banner, $banner, []);
    }

    /**
     * Попытка заменить фото невалидным файлом блокирует сохранение
     * целиком (`$hasImage = false`, даже если старое изображение ещё
     * есть) — та же логика, что при создании, а не «текст сохранён,
     * фото — нет» (`AdminContentController::update()`, Таск 6): здесь
     * изображение обязательный атрибут баннера, а не необязательное
     * украшение страницы.
     */
    public function update(string $id): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        $banner = findBannerById((int) $id);
        if ($banner === null) {
            abort404();
        }

        $input                          = $this->bannerInputFromRequest();
        [$hasFile, $file, $uploadError] = $this->validateUpload();
        $hasImage                       = ($hasFile && $uploadError === null) || (!$hasFile && $banner['image_path'] !== null);

        $errors = validateBannerInput($input, $hasImage);
        if ($uploadError !== null) {
            $errors['image'] = true;
        }

        if (in_array(true, $errors, true)) {
            $this->renderForm($banner, $input, $errors, $uploadError);
            return;
        }

        $newImagePath = null;
        if ($hasFile) {
            $newImagePath = storeContentImage($file);
            if ($newImagePath === null) {
                $this->renderForm($banner, $input, ['image' => true], 'Не удалось сохранить файл.');
                return;
            }
        }

        updateBanner((int) $id, $input + ['image_path' => $newImagePath]);

        if ($newImagePath !== null && $banner['image_path'] !== null) {
            deleteStoredFile($banner['image_path']);
        }

        setFlash('success', 'Баннер обновлён.');
        redirect('/admin/banners');
    }

    public function toggle(string $id): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        if (!toggleBanner((int) $id)) {
            setFlash('error', 'Баннер не найден.');
            redirect('/admin/banners');
        }

        setFlash('success', 'Статус баннера изменён.');
        redirect('/admin/banners');
    }

    private function bannerInputFromRequest(): array
    {
        return [
            'title'      => trim((string) input('title', '')),
            'link'       => trim((string) input('link', '')),
            'sort_order' => (int) input('sort_order', 0),
        ];
    }

    /**
     * Только проверка MIME/размера — `storeContentImage()` (реальная
     * запись на диск) сюда намеренно не входит и вызывается отдельно,
     * только после того, как `validateBannerInput()` подтвердит, что
     * форма целиком валидна. Иначе при невалидном, например, поле
     * `link` файл успел бы физически сохраниться раньше проверки и
     * остался бы сиротой на диске при отклонённой форме.
     * Возвращает `[есть ли загруженный файл, сам файл из $_FILES,
     * текст ошибки MIME/размера или null]`.
     */
    private function validateUpload(): array
    {
        $file    = $_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE];
        $hasFile = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if (!$hasFile) {
            return [false, null, null];
        }

        $detectedMime = detectUploadedMime((string) ($file['tmp_name'] ?? ''));
        $uploadError  = validateUploadedImage($file, $detectedMime);

        return [true, $file, $uploadError];
    }

    private function renderForm(?array $banner, array $old, array $errors, ?string $uploadError = null): void
    {
        render('admin/banners/form', [
            'title'       => $banner === null ? 'Новый баннер' : 'Редактирование баннера',
            'banner'      => $banner,
            'old'         => $old,
            'errors'      => $errors,
            'uploadError' => $uploadError,
        ]);
    }
}
