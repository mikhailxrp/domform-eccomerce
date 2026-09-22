<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Core/Ai.php';
require_once ROOT_PATH . '/src/Core/Settings.php';
require_once ROOT_PATH . '/src/Models/Setting.php';
require_once ROOT_PATH . '/src/Models/AiUsage.php';

/**
 * Расход, месячный лимит и тумблеры помощников (`BR-AI-001` правило 5,
 * Таск 8 Фазы 9) — только `admin`, как остальные ИИ-разделы Панели
 * (`.docs/phases/phase-9.md`, «Решения фазы»).
 */
class AdminAiController
{
    private const TOGGLE_KEYS = ['ai_specs_enabled', 'ai_description_enabled', 'ai_consultant_enabled'];

    public function index(): void
    {
        requireRole(['admin']);

        $this->renderForm($this->currentValues(), []);
    }

    public function update(): void
    {
        requireRole(['admin']);
        requireCsrf();

        $input = [
            'ai_monthly_limit_rub'   => trim((string) input('ai_monthly_limit_rub', '')),
            'ai_usd_rate'            => trim((string) input('ai_usd_rate', '')),
            'ai_yandex_price_per_1k' => trim((string) input('ai_yandex_price_per_1k', '')),
        ];
        foreach (self::TOGGLE_KEYS as $key) {
            $input[$key] = input($key, '') === '1' ? '1' : '0';
        }

        $errors = validateAiSettingsInput($input);
        if (in_array(true, $errors, true)) {
            $this->renderForm($input, $errors);
            return;
        }

        updateSettings($input, AI_SETTING_KEYS);

        setFlash('success', 'Настройки ИИ обновлены.');
        redirect('/admin/ai');
    }

    private function currentValues(): array
    {
        $values = ['ai_monthly_limit_rub' => setting('ai_monthly_limit_rub'), 'ai_usd_rate' => setting('ai_usd_rate'), 'ai_yandex_price_per_1k' => setting('ai_yandex_price_per_1k')];
        foreach (self::TOGGLE_KEYS as $key) {
            $values[$key] = setting($key) !== '0' ? '1' : '0';
        }

        return $values;
    }

    /**
     * Баннер превышения сверяется с уже сохранённым лимитом
     * (`setting()`), не с тем, что сейчас в поле формы — на экране с
     * ошибкой валидации `$values['ai_monthly_limit_rub']` может быть
     * произвольной нечисловой строкой, `isAiLimitExceeded()` не должна
     * получать её как есть.
     */
    private function renderForm(array $values, array $errors): void
    {
        $month = date('Y-m');
        $spend = getAiSpendForMonth($month);

        render('admin/ai/index', [
            'title'         => 'ИИ — расход и настройки',
            'month'         => $month,
            'spend'         => $spend,
            'limitExceeded' => isAiLimitExceeded($spend, setting('ai_monthly_limit_rub')),
            'summary'       => summarizeAiRequestStats(getAiRequestStats($month)),
            'values'        => $values,
            'errors'        => $errors,
        ]);
    }
}
