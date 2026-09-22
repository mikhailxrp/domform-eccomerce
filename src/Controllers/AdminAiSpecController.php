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
            'classAvailable'  => aiClassAvailable(aiClassForAssistant('specs')),
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

        $ids = array_slice(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            array_filter((array) input('product_ids', []), static fn (mixed $id): bool => (int) $id > 0)
        )), 0, AI_SPECS_BATCH_MAX);

        if ($ids === []) {
            setFlash('error', 'Выберите хотя бы один Товар.');
            redirect('/admin/ai/specs');
        }

        // Общий таймаут пакета — по числу Товаров и таймауту одного
        // вызова провайдера, с запасом; молча игнорируется, если
        // `set_time_limit()` запрещён на хостинге (`disable_functions`).
        @set_time_limit(count($ids) * (AI_TIMEOUT_SECONDS + 5));

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
}
