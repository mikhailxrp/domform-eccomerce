<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';
require_once ROOT_PATH . '/src/Core/Ai.php';
require_once ROOT_PATH . '/src/Core/Settings.php';
require_once ROOT_PATH . '/src/Models/Setting.php';

/**
 * Журнал вызовов ИИ (`ai_requests`, `ADR-048`) — расход считается
 * отсюда, не с провайдера (`BR-AI-001` правило 5, Таск 8 Фазы 9).
 * Перевод стоимости в рубли — здесь, а не в `Services/Ai/*`: у
 * провайдеров нет доступа к БД/настройкам (`phase-9.md`, «Решения
 * фазы») — только эта Model читает курс/цену и пишет итоговую строку.
 */

/**
 * `$entry['cost_usd']` заполнен — значит стоимость уже посчитал
 * провайдер (OpenRouter), переводим по курсу `ai_usd_rate`; `null` —
 * значит провайдер отдал только токены (YandexGPT), считаем по цене
 * `ai_yandex_price_per_1k`. Обе настройки сидятся в `install.php` —
 * пустая строка (настройка ещё не создана) трактуется как курс/цена
 * `0`, чтобы запись журнала не падала из-за отсутствующей настройки.
 */
function logAiRequest(array $entry): void
{
    $costRub = $entry['cost_usd'] !== null
        ? costRubFromUsd((float) $entry['cost_usd'], settingOrZero('ai_usd_rate'))
        : costRubFromTokens((int) $entry['tokens_in'], (int) $entry['tokens_out'], settingOrZero('ai_yandex_price_per_1k'));

    $stmt = getPdo()->prepare(
        'INSERT INTO ai_requests
            (provider, task_class, assistant, tokens_in, tokens_out, cost_rub, status, error, duration_ms)
         VALUES
            (:provider, :task_class, :assistant, :tokens_in, :tokens_out, :cost_rub, :status, :error, :duration_ms)'
    );

    $stmt->execute([
        'provider'    => $entry['provider'],
        'task_class'  => $entry['task_class'],
        'assistant'   => $entry['assistant'],
        'tokens_in'   => $entry['tokens_in'],
        'tokens_out'  => $entry['tokens_out'],
        'cost_rub'    => $costRub,
        'status'      => $entry['status'],
        'error'       => $entry['error'],
        'duration_ms' => $entry['duration_ms'],
    ]);
}

function settingOrZero(string $key): string
{
    $value = setting($key);

    return $value !== '' ? $value : '0';
}

function getAiSpendForMonth(string $month): string
{
    $stmt = getPdo()->prepare(
        "SELECT COALESCE(SUM(cost_rub), 0) AS spend
         FROM ai_requests
         WHERE DATE_FORMAT(created_at, '%Y-%m') = :month"
    );
    $stmt->execute(['month' => $month]);

    return (string) $stmt->fetchColumn();
}

/**
 * Разбивка расхода за месяц по помощникам/классам/провайдерам — для
 * экрана `/admin/ai` (Таск 8 Фазы 9); в этом таске только читается для
 * ручной проверки через `SELECT`, UI ещё нет.
 */
function getAiRequestStats(string $month): array
{
    $stmt = getPdo()->prepare(
        "SELECT assistant, task_class, provider,
                COUNT(*) AS requests_count,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) AS errors_count,
                COALESCE(SUM(cost_rub), 0) AS spend
         FROM ai_requests
         WHERE DATE_FORMAT(created_at, '%Y-%m') = :month
         GROUP BY assistant, task_class, provider"
    );
    $stmt->execute(['month' => $month]);

    return $stmt->fetchAll();
}
