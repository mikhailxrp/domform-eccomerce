<?php

declare(strict_types=1);

require_once __DIR__ . '/AiProvider.php';
require_once __DIR__ . '/AiHttp.php';

/**
 * Класс задачи «обезличенные данные» (`FR-AI-001`, `FR-AI-002`,
 * `BR-AI-001`) — Claude через OpenRouter, единый REST-эндпоинт на
 * множество моделей (`openrouter.ai/api/v1/chat/completions`, формат
 * запроса/ответа — OpenAI-совместимый). Модель — строка из `.env`
 * (`AI_OPENROUTER_MODEL`), смена версии Claude не требует правки кода.
 *
 * Стоимость запроса OpenRouter уже считает сам и отдаёт в
 * `usage.cost` (USD) — своя таблица тарифов не нужна; перевод в рубли
 * по курсу настройки — задача `Models/AiUsage.php`, не этого класса
 * (см. `AiProvider.php`).
 */
final class OpenRouterProvider implements AiProvider
{
    private const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {
    }

    public function complete(array $messages, array $options = []): array
    {
        $payload = array_merge([
            'model'    => $this->model,
            'messages' => $messages,
        ], $options);

        $response = aiHttpPostJson(
            self::ENDPOINT,
            [
                'Authorization: Bearer ' . $this->apiKey,
                'HTTP-Referer: ' . APP_URL,
                'X-Title: ДомФорм',
            ],
            $payload,
            AI_TIMEOUT_SECONDS
        );

        $text = $response['choices'][0]['message']['content'] ?? null;
        if (!is_string($text) || trim($text) === '') {
            throw new RuntimeException('OpenRouter вернул пустой ответ');
        }

        $usage = $response['usage'] ?? [];

        return [
            'text'       => $text,
            'tokens_in'  => (int) ($usage['prompt_tokens'] ?? 0),
            'tokens_out' => (int) ($usage['completion_tokens'] ?? 0),
            'cost_usd'   => isset($usage['cost']) ? (float) $usage['cost'] : null,
        ];
    }
}
