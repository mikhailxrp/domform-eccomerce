<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Review.php';
require_once ROOT_PATH . '/src/Core/Review.php';
require_once ROOT_PATH . '/src/Core/Pagination.php';
require_once ROOT_PATH . '/src/Services/FileUpload.php';

class AdminReviewController
{
    private const STATUS_OPTIONS = [REVIEW_STATUS_PENDING, REVIEW_STATUS_APPROVED, REVIEW_STATUS_REJECTED];

    /**
     * Фильтр по умолчанию — `pending` (очередь модерации, `FR-ADM-004`).
     * Пустая строка — явный выбор «Все статусы» в форме (`?status=`, не
     * отсутствие параметра); любое другое значение — откат к `pending`,
     * а не 500 (по образцу `AdminOrderController::index()`).
     * `$storeReviewOld`/`$storeReviewErrors` — только после неудачной
     * `storeShopReview()`, прямой рендер этой же страницы без редиректа
     * (`setFlash()` хранит только строку, не массив ошибок по полям).
     */
    public function index(array $storeReviewOld = [], array $storeReviewErrors = []): void
    {
        requireRole(['manager', 'admin']);

        $status = (string) input('status', REVIEW_STATUS_PENDING);
        if ($status !== '' && !in_array($status, self::STATUS_OPTIONS, true)) {
            $status = REVIEW_STATUS_PENDING;
        }

        $page = (int) input('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $filters = ['status' => $status !== '' ? $status : null];

        $total      = countAdminReviews($filters);
        $pagination = buildPagination($total, $page, ADMIN_REVIEWS_PER_PAGE);
        $reviews    = getAdminReviews($filters, $pagination['page'], ADMIN_REVIEWS_PER_PAGE);

        $queryParams = ['status' => $status];

        $paginationLinks = [];
        for ($i = 1; $i <= $pagination['total_pages']; $i++) {
            $paginationLinks[$i] = buildPaginationUrl('/admin/reviews', $queryParams, $i);
        }

        render('admin/reviews/index', [
            'title'             => 'Отзывы',
            'reviews'           => $reviews,
            'statusFilter'      => $status,
            'pagination'        => $pagination,
            'paginationLinks'   => $paginationLinks,
            'prevUrl'           => $pagination['has_prev'] ? buildPaginationUrl('/admin/reviews', $queryParams, $pagination['prev_page']) : null,
            'nextUrl'           => $pagination['has_next'] ? buildPaginationUrl('/admin/reviews', $queryParams, $pagination['next_page']) : null,
            'storeReviewOld'    => $storeReviewOld,
            'storeReviewErrors' => $storeReviewErrors,
        ]);
    }

    public function approve(string $id): void
    {
        $this->transition($id, REVIEW_STATUS_APPROVED, 'Отзыв одобрен.');
    }

    public function reject(string $id): void
    {
        $this->transition($id, REVIEW_STATUS_REJECTED, 'Отзыв отклонён.');
    }

    /**
     * Идемпотентно: повторное «Одобрить» на уже `approved` — тоже успех
     * (`setReviewStatus()` отличает это от несуществующего `id`).
     * Автор не уведомляется в любом случае (`FR-ADM-004` правило 3).
     */
    private function transition(string $id, string $status, string $successMessage): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        if (!setReviewStatus((int) $id, $status)) {
            setFlash('error', 'Отзыв не найден.');
            redirect('/admin/reviews');
        }

        setFlash('success', $successMessage);
        redirect('/admin/reviews');
    }

    /**
     * Ручное добавление отзыва о магазине (`FR-HOME-007` правило 5) —
     * тот же валидатор, что у витринной формы Товара (Таск 4);
     * `product_id` в проверке не участвует. Ошибка — прямой рендер
     * `index()` с введёнными значениями, без редиректа (см. `index()`).
     * Фото (`ADR-046`, внеплановый редизайн `/about`) — необязательно:
     * отсутствие файла (`UPLOAD_ERR_NO_FILE`) не ошибка, отзыв просто
     * не подойдёт для цитаты на `/about` без фото
     * (`findLatestApprovedStoreReviewWithPhoto()`).
     */
    public function storeShopReview(): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        $input  = normalizeReviewInput([
            'name'   => input('name'),
            'email'  => input('email'),
            'rating' => input('rating'),
            'text'   => input('text'),
        ]);
        $errors = validateReviewInput($input);

        $file       = $_FILES['photo'] ?? ['error' => UPLOAD_ERR_NO_FILE];
        $hasPhoto   = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $photoPath  = null;
        $photoError = null;

        if ($hasPhoto) {
            $detectedMime = detectUploadedMime((string) ($file['tmp_name'] ?? ''));
            $photoError   = validateUploadedImage($file, $detectedMime);
        }

        if ($photoError !== null) {
            $errors['photo'] = true;
        }

        if (in_array(true, $errors, true)) {
            $this->index($input, $errors);
            return;
        }

        if ($hasPhoto) {
            $photoPath = storeReviewImage($file);
        }

        createStoreReview($input + ['photo_path' => $photoPath]);

        setFlash(
            $hasPhoto && $photoPath === null ? 'error' : 'success',
            $hasPhoto && $photoPath === null
                ? 'Отзыв добавлен, но фото не сохранилось.'
                : 'Отзыв о магазине добавлен.'
        );
        redirect('/admin/reviews');
    }
}
