<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/AiSpec.php';
require_once ROOT_PATH . '/src/Core/AiSpecs.php';
require_once ROOT_PATH . '/src/Core/Pagination.php';
require_once ROOT_PATH . '/src/Services/Ai/ai.php';

/**
 * Разбор характеристик Товара (`FR-AI-001`) — только `admin`
 * (`.docs/phases/phase-9.md`, «Решения фазы»: роль в требовании —
 * Администратор, Менеджер этот раздел не видит и не открывает).
 */
class AdminAiSpecController
{
    private const STATUS_OPTIONS = ['pending', 'confirmed'];

    public function index(): void
    {
        requireRole(['admin']);

        $status = (string) input('status', 'pending');
        if ($status !== '' && !in_array($status, self::STATUS_OPTIONS, true)) {
            $status = 'pending';
        }

        $page = (int) input('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $total      = countProductsForSpecsQueue($status);
        $pagination = buildPagination($total, $page, ADMIN_AI_SPECS_PER_PAGE);
        $products   = getProductsForSpecsQueue($status, $pagination['page'], ADMIN_AI_SPECS_PER_PAGE);

        $queryParams = ['status' => $status];

        $paginationLinks = [];
        for ($i = 1; $i <= $pagination['total_pages']; $i++) {
            $paginationLinks[$i] = buildPaginationUrl('/admin/ai/specs', $queryParams, $i);
        }

        render('admin/ai/specs/index', [
            'title'           => 'ИИ — Разбор характеристик',
            'products'        => $products,
            'statusFilter'    => $status,
            'pagination'      => $pagination,
            'paginationLinks' => $paginationLinks,
            'prevUrl'         => $pagination['has_prev'] ? buildPaginationUrl('/admin/ai/specs', $queryParams, $pagination['prev_page']) : null,
            'nextUrl'         => $pagination['has_next'] ? buildPaginationUrl('/admin/ai/specs', $queryParams, $pagination['next_page']) : null,
            'classAvailable'  => aiClassAvailable(aiClassForAssistant('specs')) && aiAssistantEnabled('specs'),
        ]);
    }

    /**
     * Пакетный запуск (`FR-AI-001` правило 1) — до `AI_SPECS_BATCH_MAX`
     * Товаров за один POST: каждый вызов провайдера — блокирующий HTTP-
     * запрос, больший пакет рискует упереться в `max_execution_time`
     * shared-хостинга. Товар без Категории или не найденный — молча
     * пропускается (не должно случаться штатно, но не повод падать
     * всему пакету). Неответившие по одному Товару не прерывают разбор
     * остальных — только уменьшают счётчик успеха во flash-сообщении.
     */
    public function run(): void
    {
        requireRole(['admin']);
        requireCsrf();

        if (!aiClassAvailable(aiClassForAssistant('specs')) || !aiAssistantEnabled('specs')) {
            setFlash('error', 'Разбор характеристик сейчас недоступен.');
            redirect('/admin/ai/specs');
        }

        $ids = array_slice(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            array_filter((array) input('product_ids', []), static fn (mixed $id): bool => (int) $id > 0)
        )), 0, AI_SPECS_BATCH_MAX);

        if ($ids === []) {
            setFlash('error', 'Выберите хотя бы один Товар.');
            redirect('/admin/ai/specs');
        }

        // Общий таймаут пакета — по числу Товаров и таймауту одного
        // вызова провайдера плюс запас на БД/сборку промпта на Товар
        // (`AI_SPECS_BATCH_OVERHEAD_SECONDS`); молча игнорируется, если
        // `set_time_limit()` запрещён на хостинге (`disable_functions`).
        @set_time_limit(count($ids) * (AI_TIMEOUT_SECONDS + AI_SPECS_BATCH_OVERHEAD_SECONDS));

        $succeeded = 0;

        foreach ($ids as $id) {
            $product = findProductForSpecsRun($id);
            if ($product === null || $product['specs_status'] !== 'pending') {
                continue;
            }

            $knownValues = getKnownSpecValues($product['category_ids']);
            $messages    = buildSpecsPrompt($product, $knownValues);
            $result      = aiComplete('specs', $messages);

            if ($result === null) {
                continue;
            }

            $decoded = decodeAiJson($result['text']);
            $rows    = normalizeSpecSuggestions($decoded, $knownValues);
            replaceSpecSuggestions($id, $rows);
            $succeeded++;
        }

        setFlash(
            $succeeded > 0 ? 'success' : 'error',
            "Разобрано {$succeeded} из " . count($ids) . ' Товаров.'
        );

        redirect('/admin/ai/specs');
    }

    public function review(string $id): void
    {
        requireRole(['admin']);

        $product = findProductForSpecsReview((int) $id);
        if ($product === null) {
            abort404();
        }

        $this->renderReview($product, [], []);
    }

    /**
     * Ошибка валидации → прямой рендер той же страницы с `$old`/
     * `$errors` (не редирект — как форма Товара, `dod-global.md`).
     * Ничего не отмечено и ошибок нет → это не ошибка формата, а
     * отсутствие выбора: flash + редирект обратно на ревью, чтобы
     * попробовать снова («Подтвердить вручную» — для случая «нечего
     * применять»).
     */
    public function apply(string $id): void
    {
        requireRole(['admin']);
        requireCsrf();

        $productId = (int) $id;
        $product   = findProductForSpecsReview($productId);
        if ($product === null) {
            abort404();
        }

        $validVariantIds = array_map(static fn (array $v): int => (int) $v['id'], $product['variants']);
        $result = validateSpecReviewInput($product['suggestions'], $validVariantIds, [
            'accepted'   => input('accepted', []),
            'value'      => input('value', []),
            'variant_id' => input('variant_id', []),
        ]);

        if ($result['errors'] !== []) {
            $this->renderReview($product, (array) input('value', []), $result['errors']);
            return;
        }

        if ($result['accepted'] === []) {
            setFlash('error', 'Отметьте хотя бы одно предложение — или используйте «Подтвердить вручную».');
            redirect('/admin/ai/specs/' . $productId);
        }

        applySpecSuggestions($productId, $result['accepted']);
        setFlash('success', 'Характеристики применены, Товар подтверждён.');
        redirect('/admin/ai/specs');
    }

    /**
     * Допускает Товар в подбор диалогом (`FR-AI-004`) без единого
     * предложения ИИ — для случая, когда провайдер выключен или
     * Администратор ввёл характеристики руками в форме Товара.
     */
    public function confirmManually(string $id): void
    {
        requireRole(['admin']);
        requireCsrf();

        $productId = (int) $id;
        if (findProductForSpecsRun($productId) === null) {
            abort404();
        }

        setProductSpecsStatus($productId, 'confirmed');
        setFlash('success', 'Товар подтверждён без ИИ.');
        redirect('/admin/ai/specs');
    }

    private function renderReview(array $product, array $old, array $errors): void
    {
        render('admin/ai/specs/review', [
            'title'        => 'ИИ — Ревью характеристик',
            'product'      => $product,
            'old'          => $old,
            'errors'       => $errors,
            'targetLabels' => AI_SPEC_TARGET_LABELS,
        ]);
    }
}
