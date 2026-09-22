<?php

declare(strict_types=1);

/**
 * Классы задач ИИ и их маршрутизация к помощникам (`BR-AI-001`
 * правило 3, `ADR-048`) — конфигурация привязана к классу задачи, не к
 * конкретному помощнику: смена провайдера — правка `.env`, не кода
 * (`Services/Ai/ai.php::providerFor()`). Чистые функции без БД —
 * подключаются и в `tests/bootstrap.php`, и в `Services/Ai/*`.
 */

const AI_CLASS_ANONYMOUS  = 'anonymous';
const AI_CLASS_USER_INPUT = 'user_input';

/**
 * Помощник → класс задачи. `specs` (`FR-AI-001`) и `description`
 * (`FR-AI-002`) работают с обезличенными данными Товара — любой
 * провайдер; `consultant` (`FR-AI-003`) и `picker` (`FR-AI-004`) — с
 * пользовательским вводом Покупателя, поэтому российский провайдер по
 * умолчанию (`BR-AI-001` правило 2).
 */
const AI_ASSISTANTS = [
    'specs'       => AI_CLASS_ANONYMOUS,
    'description' => AI_CLASS_ANONYMOUS,
    'consultant'  => AI_CLASS_USER_INPUT,
    'picker'      => AI_CLASS_USER_INPUT,
];

function aiClassForAssistant(string $assistant): ?string
{
    return AI_ASSISTANTS[$assistant] ?? null;
}

/**
 * Whitelist формы `/admin/ai` (Таск 8) — по образцу `SETTING_KEYS`
 * (`Core/Settings.php`): ключ → подпись поля. Три тумблера, не четыре
 * — `picker` умер как отдельный помощник ещё в Таске 7 (`ADR-051`,
 * подбор товара работает внутри `consultant`), `aiComplete('picker',
 * ...)` в коде нигде не вызывается — заводить для него тумблер нечего
 * включать/выключать.
 */
const AI_SETTING_KEYS = [
    'ai_monthly_limit_rub'    => 'Месячный лимит расхода, ₽',
    'ai_usd_rate'             => 'Курс USD → RUB',
    'ai_yandex_price_per_1k'  => 'Цена YandexGPT за 1000 токенов, ₽',
    'ai_specs_enabled'        => 'Разбор характеристик',
    'ai_description_enabled' => 'Генератор описания',
    'ai_consultant_enabled'  => 'Консультант в чате',
];

/**
 * Помощник → ключ настройки-тумблера. Используется `aiAssistantEnabled()`
 * (`Services/Ai/ai.php` — там же, где `aiClassAvailable()`, а не здесь:
 * функция читает `setting()`, обращение к БД, а этот файл — чистые
 * функции без БД, подключаемые и в `tests/bootstrap.php`).
 */
const AI_ASSISTANT_TOGGLE_KEYS = [
    'specs'       => 'ai_specs_enabled',
    'description' => 'ai_description_enabled',
    'consultant'  => 'ai_consultant_enabled',
];

const AI_MONTHLY_LIMIT_MAX_RUB = 1000000;

/**
 * Форма `/admin/ai` (`BR-AI-001` правило 5, Таск 8) — лимит/курс/цена
 * обязательны и больше нуля (лимит = 0 не бывает штатной настройкой,
 * для полного отключения есть тумблеры помощников); тумблеры —
 * чекбоксы, невалидного значения у них не бывает, поэтому в проверке
 * не участвуют.
 */
function validateAiSettingsInput(array $input): array
{
    $limit       = trim((string) ($input['ai_monthly_limit_rub'] ?? ''));
    $usdRate     = trim((string) ($input['ai_usd_rate'] ?? ''));
    $yandexPrice = trim((string) ($input['ai_yandex_price_per_1k'] ?? ''));

    return [
        'ai_monthly_limit_rub' => !preg_match('/^\d+(\.\d{1,2})?$/', $limit)
            || (float) $limit <= 0
            || (float) $limit > AI_MONTHLY_LIMIT_MAX_RUB,
        'ai_usd_rate'            => !preg_match('/^\d+(\.\d{1,4})?$/', $usdRate) || (float) $usdRate <= 0,
        'ai_yandex_price_per_1k' => !preg_match('/^\d+(\.\d{1,4})?$/', $yandexPrice) || (float) $yandexPrice <= 0,
    ];
}

/**
 * `$spendRub`/`$limitRub` — строки-деньги (`bcmath`, не `float`),
 * сравниваются `bccomp()` как остальные денежные сравнения проекта
 * (`Core/Price.php`). Лимит исчерпан — не значит «заблокировано»:
 * решение отключить помощника вручную принимает Владелец (`Q-027`),
 * эта функция только определяет факт превышения для баннера/письма.
 */
function isAiLimitExceeded(string $spendRub, string $limitRub): bool
{
    return bccomp($spendRub, $limitRub, 2) >= 0;
}

/**
 * Готовит `getAiRequestStats()` (строки по `assistant`/`task_class`/
 * `provider`) для показа на `/admin/ai` — итог и две разбивки, суммы
 * через `bcmath` (`dod-global.md`: деньги нигде не float). Аггрегация
 * вынесена сюда, а не в View (`php.md`: View — только HTML + echo, без
 * бизнес-логики).
 *
 * @param array<int, array{assistant: string, task_class: string, provider: string, requests_count: int|string, errors_count: int|string, spend: string}> $rows
 */
function summarizeAiRequestStats(array $rows): array
{
    $totalSpend    = '0';
    $totalRequests = 0;
    $totalErrors   = 0;
    $byAssistant   = [];
    $byClass       = [];

    foreach ($rows as $row) {
        $totalSpend    = bcadd($totalSpend, $row['spend'], 2);
        $totalRequests += (int) $row['requests_count'];
        $totalErrors   += (int) $row['errors_count'];

        $assistant = $row['assistant'];
        $byAssistant[$assistant]['spend']          = bcadd($byAssistant[$assistant]['spend'] ?? '0', $row['spend'], 2);
        $byAssistant[$assistant]['requests_count'] = ($byAssistant[$assistant]['requests_count'] ?? 0) + (int) $row['requests_count'];
        $byAssistant[$assistant]['errors_count']   = ($byAssistant[$assistant]['errors_count'] ?? 0) + (int) $row['errors_count'];

        $class = $row['task_class'];
        $byClass[$class]['spend']          = bcadd($byClass[$class]['spend'] ?? '0', $row['spend'], 2);
        $byClass[$class]['requests_count'] = ($byClass[$class]['requests_count'] ?? 0) + (int) $row['requests_count'];
    }

    return [
        'total_spend'    => $totalSpend,
        'total_requests' => $totalRequests,
        'total_errors'   => $totalErrors,
        'by_assistant'   => $byAssistant,
        'by_class'       => $byClass,
    ];
}

/**
 * Модель нередко оборачивает JSON в ```` ```json ... ``` ```` вместо
 * чистого объекта — снимаем обрамление перед `json_decode()`. Текст
 * без JSON, пустая строка или JSON-скаляр (не объект/массив) → `null`;
 * вызывающий код обязан считать это отказом разбора, не крашем
 * (`FR-AI-001` правило 3, `FR-AI-004` правило 2 — сервер не доверяет
 * форме ответа модели).
 */
function decodeAiJson(string $text): ?array
{
    $trimmed = trim($text);
    if ($trimmed === '') {
        return null;
    }

    if (str_starts_with($trimmed, '```')) {
        $trimmed = (string) preg_replace('/^```[a-zA-Z]*\s*/', '', $trimmed);
        $trimmed = (string) preg_replace('/```\s*$/', '', $trimmed);
        $trimmed = trim($trimmed);
    }

    $decoded = json_decode($trimmed, true);

    return is_array($decoded) ? $decoded : null;
}

/**
 * `$usdCost` — как отдаёт OpenRouter (`usage.cost` в ответе, JSON-
 * float), не наше внутреннее денежное значение: единственное место
 * проекта, где сумма ненадолго проходит через `float`, — на входе
 * внешнего API. Сразу переводится в строку и дальше считается только
 * `bcmath`, тем же приёмом округления half-up, что `discountedPrice()`
 * (`Core/Price.php`) — `bcadd(..., '0.005', $scale)`.
 */
function costRubFromUsd(float $usdCost, string $rubPerUsd): string
{
    $usd = sprintf('%.6f', $usdCost);

    return bcadd(bcmul($usd, $rubPerUsd, 6), '0.005', 2);
}

/**
 * YandexGPT не возвращает стоимость в ответе — только токены; цена
 * задаётся настройкой `ai_yandex_price_per_1k` (руб. за 1000 токенов,
 * вход и выход по единой цене — отдельного тарифа на токены генерации
 * ТЗ не задаёт).
 */
function costRubFromTokens(int $tokensIn, int $tokensOut, string $rubPer1kTokens): string
{
    $totalTokens = (string) ($tokensIn + $tokensOut);

    return bcadd(bcdiv(bcmul($totalTokens, $rubPer1kTokens, 6), '1000', 6), '0.005', 2);
}
