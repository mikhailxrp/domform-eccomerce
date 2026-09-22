<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Ai.php';
require_once ROOT_PATH . '/src/Core/Logger.php';
require_once ROOT_PATH . '/src/Core/functions.php';
require_once ROOT_PATH . '/src/Core/Settings.php';
require_once ROOT_PATH . '/src/Models/AiUsage.php';
require_once ROOT_PATH . '/src/Models/Setting.php';
require_once ROOT_PATH . '/src/Models/User.php';
require_once ROOT_PATH . '/src/Services/Mailer.php';
require_once __DIR__ . '/AiProvider.php';
require_once __DIR__ . '/OpenRouterProvider.php';
require_once __DIR__ . '/YandexGptProvider.php';

/**
 * Провайдер по классу задачи, не по помощнику (`BR-AI-001` правило 3)
 * — читает конфигурацию только из `.env`. Ключ класса не задан → `null`
 * без исключения: запрос к провайдеру другого класса/юрисдикции не
 * уходит молча (`AC-06`, `phase-9.md` «Решения фазы»).
 */
function providerFor(string $taskClass): ?AiProvider
{
    return match ($taskClass) {
        AI_CLASS_ANONYMOUS  => createOpenRouterProvider(),
        AI_CLASS_USER_INPUT => createYandexGptProvider(),
        default             => null,
    };
}

function createOpenRouterProvider(): ?AiProvider
{
    $key   = trim(env('AI_OPENROUTER_KEY', ''));
    $model = trim(env('AI_OPENROUTER_MODEL', ''));

    if ($key === '' || $model === '') {
        return null;
    }

    return new OpenRouterProvider($key, $model);
}

function createYandexGptProvider(): ?AiProvider
{
    $key      = trim(env('AI_YANDEX_KEY', ''));
    $folderId = trim(env('AI_YANDEX_FOLDER_ID', ''));
    $model    = trim(env('AI_YANDEX_MODEL', ''));

    if ($key === '' || $folderId === '' || $model === '') {
        return null;
    }

    return new YandexGptProvider($key, $folderId, $model);
}

function aiClassAvailable(string $taskClass): bool
{
    return providerFor($taskClass) !== null;
}

/**
 * Тумблер конкретного помощника (`Таск 8 Фазы 9`, `AI_SETTING_KEYS`)
 * — гасит помощника так же, как отсутствие ключа провайдера в `.env`:
 * вызывающий код (`AiChatController`, `AdminAiSpecController`,
 * `AdminProductController`) обязан проверять её рядом с
 * `aiClassAvailable()`, а не полагаться на одну только настройку
 * класса. Помощник без ключа в `AI_ASSISTANT_TOGGLE_KEYS` (сейчас
 * только исторический `picker`, мёртвый с `ADR-051`) тумблера не
 * имеет — считается включённым. Отсутствующая в БД настройка (ключ ещё
 * не засеян) трактуется как «включено», выключает только явный `'0'`
 * — по умолчанию новый деплой не должен молча гасить помощников.
 */
function aiAssistantEnabled(string $assistant): bool
{
    $key = AI_ASSISTANT_TOGGLE_KEYS[$assistant] ?? null;

    return $key === null || setting($key) !== '0';
}

function providerNameForClass(string $taskClass): string
{
    return match ($taskClass) {
        AI_CLASS_ANONYMOUS  => 'openrouter',
        AI_CLASS_USER_INPUT => 'yandexgpt',
        default             => 'unknown',
    };
}

/**
 * Единственная точка вызова модели во всём проекте. `$assistant` —
 * ключ `AI_ASSISTANTS` (`specs`/`description`/`consultant`/`picker`),
 * определяет класс задачи и подписывает строку журнала `ai_requests`.
 * Неизвестный помощник, недоступный класс, сетевая ошибка или таймаут
 * → `null`; исключение никогда не покидает эту функцию — недоступность
 * провайдера отключает конкретного помощника, а не сайт (`FR-AI-*`).
 *
 * @param array<int, array{role: string, content: string}> $messages
 * @return array{text: string, tokens_in: int, tokens_out: int}|null
 */
function aiComplete(string $assistant, array $messages, array $options = []): ?array
{
    $taskClass = aiClassForAssistant($assistant);
    if ($taskClass === null) {
        logError('Неизвестный помощник ИИ', ['assistant' => $assistant]);
        return null;
    }

    $provider = providerFor($taskClass);
    if ($provider === null) {
        return null;
    }

    $startedAt = microtime(true);

    try {
        $result = $provider->complete($messages, $options);

        logAiRequest([
            'provider'    => providerNameForClass($taskClass),
            'task_class'  => $taskClass,
            'assistant'   => $assistant,
            'tokens_in'   => $result['tokens_in'],
            'tokens_out'  => $result['tokens_out'],
            'cost_usd'    => $result['cost_usd'],
            'status'      => 'ok',
            'error'       => null,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        notifyIfAiLimitExceeded();

        return [
            'text'       => $result['text'],
            'tokens_in'  => $result['tokens_in'],
            'tokens_out' => $result['tokens_out'],
        ];
    } catch (Throwable $e) {
        logError('Вызов ИИ-провайдера завершился ошибкой', [
            'assistant'  => $assistant,
            'task_class' => $taskClass,
            'error'      => $e->getMessage(),
        ]);

        logAiRequest([
            'provider'    => providerNameForClass($taskClass),
            'task_class'  => $taskClass,
            'assistant'   => $assistant,
            'tokens_in'   => 0,
            'tokens_out'  => 0,
            'cost_usd'    => null,
            'status'      => 'error',
            'error'       => mb_substr($e->getMessage(), 0, 255),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return null;
    }
}

/**
 * Проверка месячного лимита после каждого успешного вызова
 * (`BR-AI-001` правило 5, Таск 8 Фазы 9). Превышение не отключает
 * помощников (`Q-027`) — только баннер в Панели (читает
 * `isAiLimitExceeded()` напрямую при рендере, не отсюда) и одно
 * письмо Владельцу за календарный месяц: метка `ai_limit_notified_month`
 * в `settings` сравнивается с текущим месяцем, чтобы второе и
 * последующие превышения того же месяца не дублировали письмо.
 */
function notifyIfAiLimitExceeded(): void
{
    $month = date('Y-m');
    $spend = getAiSpendForMonth($month);
    $limit = setting('ai_monthly_limit_rub');

    if (!isAiLimitExceeded($spend, $limit)) {
        return;
    }

    if (setting('ai_limit_notified_month') === $month) {
        return;
    }

    logWarning('Превышен месячный лимит расхода на ИИ', [
        'month' => $month,
        'spend' => $spend,
        'limit' => $limit,
    ]);

    foreach (getAdminEmails() as $email) {
        sendMail(
            $email,
            'Превышен месячный лимит расхода на ИИ — ДомФорм',
            renderEmailBody('ai-limit-exceeded', ['month' => $month, 'spend' => $spend, 'limit' => $limit])
        );
    }

    updateSettings(
        ['ai_limit_notified_month' => $month],
        ['ai_limit_notified_month' => 'Отметка последнего уведомления о лимите']
    );
}
