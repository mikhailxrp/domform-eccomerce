<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Product.php';
require_once ROOT_PATH . '/src/Models/Category.php';
require_once ROOT_PATH . '/src/Core/Pagination.php';
require_once ROOT_PATH . '/src/Core/CatalogFilters.php';
require_once ROOT_PATH . '/src/Core/ProductForm.php';
require_once ROOT_PATH . '/src/Core/Upload.php';
require_once ROOT_PATH . '/src/Core/Ai.php';
require_once ROOT_PATH . '/src/Core/AiDescription.php';
require_once ROOT_PATH . '/src/Services/FileUpload.php';
require_once ROOT_PATH . '/src/Services/Ai/ai.php';

class AdminProductController
{
    public function index(): void
    {
        requireRole(['manager', 'admin']);

        $categoryId = (int) input('category', 0);
        $status     = (string) input('status', '');
        if (!in_array($status, ['active', 'hidden'], true)) {
            $status = '';
        }
        $search = trim((string) input('search', ''));
        $onlySamples = (string) input('only_samples', '') === '1';

        $page = (int) input('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $filters = [
            'category_id'  => $categoryId > 0 ? $categoryId : null,
            'status'       => $status !== '' ? $status : null,
            'search'       => $search !== '' ? $search : null,
            'only_samples' => $onlySamples,
        ];

        $total      = countAdminProducts($filters);
        $pagination = buildPagination($total, $page, ADMIN_PRODUCTS_PER_PAGE);
        $products   = getAdminProducts($filters, $pagination['page'], ADMIN_PRODUCTS_PER_PAGE);

        $queryParams = array_filter(
            [
                'category'     => $categoryId > 0 ? (string) $categoryId : '',
                'status'       => $status,
                'search'       => $search,
                'only_samples' => $onlySamples ? '1' : '',
            ],
            static fn (string $value): bool => $value !== ''
        );

        $paginationLinks = [];
        for ($i = 1; $i <= $pagination['total_pages']; $i++) {
            $paginationLinks[$i] = buildPaginationUrl('/admin/products', $queryParams, $i);
        }

        render('admin/products/index', [
            'title'            => 'Товары',
            'products'         => $products,
            'categories'       => getCategoriesFlat(),
            'categoryFilter'   => $categoryId,
            'statusFilter'     => $status,
            'searchQuery'      => $search,
            'onlySamplesFilter' => $onlySamples,
            'pagination'       => $pagination,
            'paginationLinks'  => $paginationLinks,
            'prevUrl'          => $pagination['has_prev'] ? buildPaginationUrl('/admin/products', $queryParams, $pagination['prev_page']) : null,
            'nextUrl'          => $pagination['has_next'] ? buildPaginationUrl('/admin/products', $queryParams, $pagination['next_page']) : null,
        ]);
    }

    public function toggle(string $id): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        $product = findProductForToggle((int) $id);
        if ($product === null) {
            abort404();
        }

        setProductActive((int) $id, !(bool) $product['is_active']);

        setFlash('success', (bool) $product['is_active'] ? 'Товар скрыт.' : 'Товар снова виден на витрине.');
        redirect('/admin/products');
    }

    /**
     * Переключатель «Выставочный образец» прямо в списке Товаров
     * (`FR-STOCK-001` правило 1, Таск 4 Фазы 5) — без перехода в форму
     * Товара. Снять отметку у Варианта с активным Резервом нельзя
     * (`BR-003`) — `setVariantShowroomSample()` отклоняет и подделанный
     * POST, не только задизейбленный элемент во View.
     */
    public function toggleShowroom(string $productId, string $variantId): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        $variant = findVariantForShowroomToggle((int) $variantId);
        if ($variant === null || (int) $variant['product_id'] !== (int) $productId) {
            abort404();
        }

        $on = (string) input('on', '') === '1';

        if (!setVariantShowroomSample((int) $variantId, $on)) {
            setFlash('error', $on
                ? 'Не удалось включить отметку — попробуйте ещё раз.'
                : 'Нельзя снять отметку: на Вариант оформлен активный Резерв.');
            redirect('/admin/products');
        }

        setFlash('success', $on ? 'Вариант отмечен Выставочным образцом.' : 'Отметка Выставочного образца снята.');
        redirect('/admin/products');
    }

    public function create(): void
    {
        requireRole(['manager', 'admin']);

        $this->renderForm(null, [], []);
    }

    public function store(): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        $input  = normalizeProductInput($this->collectRawInput());
        $errors = validateProductInput($input);
        $this->markConflictingSkus($input, $errors, 0);

        if (productFormHasErrors($errors)) {
            $this->renderForm(null, $input, $errors);
            return;
        }

        $id = createProductWithVariants($input, $input['variants'], $input['specs'], $input['category_ids'], $input['primary_category_id']);

        if ($id === null) {
            setFlash('error', 'Один из артикулов уже занят другим товаром — проверьте Варианты.');
            $this->renderForm(null, $input, []);
            return;
        }

        setFlash('success', 'Товар создан.');
        redirect('/admin/products');
    }

    public function edit(string $id): void
    {
        requireRole(['manager', 'admin']);

        $product = findProductForAdmin((int) $id);
        if ($product === null) {
            abort404();
        }

        $this->renderForm($product, $product, []);
    }

    public function update(string $id): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        $product = findProductForAdmin((int) $id);
        if ($product === null) {
            abort404();
        }

        $input  = normalizeProductInput($this->collectRawInput());
        $errors = validateProductInput($input);
        $this->markConflictingSkus($input, $errors, (int) $id);

        if (productFormHasErrors($errors)) {
            $this->renderForm($product, $input, $errors);
            return;
        }

        $updated = updateProductWithVariants(
            (int) $id,
            $input,
            $input['variants'],
            $input['specs'],
            $input['category_ids'],
            $input['primary_category_id']
        );

        if (!$updated) {
            setFlash('error', 'Один из артикулов уже занят другим товаром — проверьте Варианты.');
            $this->renderForm($product, $input, []);
            return;
        }

        setFlash('success', 'Товар обновлён.');
        redirect('/admin/products');
    }

    public function uploadImage(string $productId, string $variantId): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        if (findProductForToggle((int) $productId) === null) {
            abort404();
        }

        $file         = $_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE];
        $detectedMime = detectUploadedMime((string) ($file['tmp_name'] ?? ''));
        $dimensions   = detectImageDimensions((string) ($file['tmp_name'] ?? ''));
        $error        = validateUploadedImage($file, $detectedMime, $dimensions);

        if ($error !== null) {
            $this->respondImageError($productId, $error);
            return;
        }

        $path = storeProductImage($file);
        if ($path === null) {
            $this->respondImageError($productId, 'Не удалось сохранить файл.');
            return;
        }

        $color = trim((string) input('color', ''));

        $imageId = addVariantImage((int) $productId, (int) $variantId, [
            'color'      => $color !== '' ? $color : null,
            'is_swatch'  => (bool) input('is_swatch', false),
            'path'       => $path,
            'sort_order' => (int) input('sort_order', 0),
        ]);

        if ($imageId === null) {
            deleteStoredFile($path);
            abort404();
        }

        $this->respondImageSuccess($productId, $variantId, 'Фото добавлено.');
    }

    public function updateImage(string $productId, string $variantId, string $imageId): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        $color   = trim((string) input('color', ''));
        $updated = updateVariantImage((int) $productId, (int) $variantId, (int) $imageId, [
            'color'      => $color !== '' ? $color : null,
            'is_swatch'  => (bool) input('is_swatch', false),
            'sort_order' => (int) input('sort_order', 0),
        ]);

        if (!$updated) {
            $this->respondImageError($productId, 'Не удалось обновить фото.', 404);
            return;
        }

        $this->respondImageSuccess($productId, $variantId, 'Фото обновлено.');
    }

    public function deleteImage(string $productId, string $variantId, string $imageId): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        $path = deleteVariantImage((int) $productId, (int) $variantId, (int) $imageId);
        if ($path === null) {
            $this->respondImageError($productId, 'Не удалось удалить фото.', 404);
            return;
        }

        deleteStoredFile($path);
        $this->respondImageSuccess($productId, $variantId, 'Фото удалено.');
    }

    public function setMainImage(string $productId, string $variantId, string $imageId): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        $updated = setMainVariantImage((int) $productId, (int) $variantId, (int) $imageId);
        if (!$updated) {
            $this->respondImageError($productId, 'Не удалось изменить главное фото.', 404);
            return;
        }

        $this->respondImageSuccess($productId, $variantId, 'Главное фото изменено.');
    }

    /**
     * Общий выход из всех 4 действий над фото Варианта — обычной
     * отправке формы (`redirect()`, было изначально) и fetch-запросу
     * `admin.js` (JSON с готовым HTML сетки, чтобы её не дублировать в
     * JS — тот же `variant-photo-grid.php`, что при обычном рендере
     * формы Товара). `wantsJson()` различает их по заголовку
     * `X-Requested-With`, который сам проставляет только JS.
     */
    private function respondImageSuccess(string $productId, string $variantId, string $message): void
    {
        if (wantsJson()) {
            jsonResponse([
                'success' => true,
                'html'    => $this->renderVariantPhotoGrid((int) $productId, (int) $variantId),
            ]);
            return;
        }

        setFlash('success', $message);
        redirect('/admin/products/' . $productId . '/edit');
    }

    private function respondImageError(string $productId, string $message, int $status = 422): void
    {
        if (wantsJson()) {
            jsonResponse(['success' => false, 'error' => $message], $status);
            return;
        }

        setFlash('error', $message);
        redirect('/admin/products/' . $productId . '/edit');
    }

    private function renderVariantPhotoGrid(int $productId, int $variantId): string
    {
        $images = getVariantImagesForAdmin($variantId);

        ob_start();
        include ROOT_PATH . '/src/Views/components/admin/variant-photo-grid.php';
        return (string) ob_get_clean();
    }

    /**
     * Черновик описания от ИИ (`FR-AI-002`, Таск 4 Фазы 9) — только
     * `admin`, как остальные ИИ-действия Панели (`phase-9.md`, «Решения
     * фазы»). В промпт уходят только краткие данные из этого запроса —
     * ни `name`, ни существующий `description` Товара не читаются.
     */
    public function generateDescription(string $id): void
    {
        requireRole(['admin']);
        requireCsrf();

        header('Content-Type: application/json; charset=utf-8');

        $productId = (int) $id;
        if (findProductForToggle($productId) === null) {
            abort404();
        }

        if (tooManyAttempts('ai_description', 10, 60)) {
            http_response_code(429);
            echo json_encode(['error' => 'Слишком много запросов — попробуйте через минуту.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        hitRateLimit('ai_description');

        $validated = validateDescriptionBrief([
            'category'  => input('category', ''),
            'material'  => input('material', ''),
            'size'      => input('size', ''),
            'mechanism' => input('mechanism', ''),
        ]);

        if ($validated['error'] !== null) {
            echo json_encode(['error' => $validated['error']], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!aiClassAvailable(aiClassForAssistant('description')) || !aiAssistantEnabled('description')) {
            echo json_encode(['unavailable' => true], JSON_UNESCAPED_UNICODE);
            return;
        }

        $result = aiComplete('description', buildDescriptionPrompt($validated['brief']));
        if ($result === null) {
            echo json_encode(['unavailable' => true], JSON_UNESCAPED_UNICODE);
            return;
        }

        $draft = normalizeDraft($result['text']);
        if ($draft === '') {
            echo json_encode(['error' => 'Не удалось получить черновик — попробуйте ещё раз.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        saveDescriptionDraft($productId, $draft);

        echo json_encode(['draft' => $draft], JSON_UNESCAPED_UNICODE);
    }

    private function collectRawInput(): array
    {
        return [
            'name'                => input('name'),
            'slug'                => input('slug'),
            'description'         => input('description'),
            'is_featured'         => input('is_featured'),
            'is_active'           => input('is_active'),
            'categories'          => input('categories', []),
            'primary_category_id' => input('primary_category_id'),
            'specs'               => input('specs', []),
            'variants'            => input('variants', []),
        ];
    }

    /**
     * `sku`, занятый Вариантом другого Товара в БД (не только внутри
     * формы, это уже проверил `validateProductInput()`) — точечная
     * ошибка поля через предварительный `SELECT`, а не только перехват
     * SQLSTATE 23000 при записи: так конкретный ряд Варианта
     * подсвечивается, а не общее сообщение «что-то не так». Сама
     * попытка записи всё равно защищена перехватом 1062 в
     * `createProductWithVariants()`/`updateProductWithVariants()` — на
     * случай гонки между этой проверкой и сохранением.
     */
    private function markConflictingSkus(array $input, array &$errors, int $excludeProductId): void
    {
        $skus = array_values(array_filter(array_column($input['variants'], 'sku'), static fn (string $sku): bool => $sku !== ''));
        if ($skus === []) {
            return;
        }

        $conflicting = findConflictingSkus($skus, $excludeProductId);
        if ($conflicting === []) {
            return;
        }

        foreach ($input['variants'] as $index => $variant) {
            if (in_array(mb_strtolower($variant['sku']), $conflicting, true)) {
                $errors['variants'][$index]['sku_taken'] = true;
            }
        }
    }

    private function renderForm(?array $product, array $old, array $errors): void
    {
        $variantRows = max(PRODUCT_FORM_DEFAULT_VARIANT_ROWS, count($old['variants'] ?? []));
        $specRows    = max(PRODUCT_FORM_DEFAULT_SPEC_ROWS, count($old['specs'] ?? []));

        render('admin/products/form', [
            'title'                   => $product === null ? 'Новый товар' : 'Редактирование товара',
            'product'                 => $product,
            'categories'              => getCategoriesFlat(),
            'old'                     => $old,
            'errors'                  => $errors,
            'variantRows'             => $variantRows,
            'specRows'                => $specRows,
            'aiDescriptionAvailable'  => aiClassAvailable(aiClassForAssistant('description')) && aiAssistantEnabled('description'),
        ]);
    }
}
