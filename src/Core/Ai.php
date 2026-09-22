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
