<?php

declare(strict_types=1);

require_once __DIR__ . '/AiProvider.php';
require_once __DIR__ . '/AiHttp.php';

/**
 * Класс задачи «пользовательский ввод» (`FR-AI-003`, `FR-AI-004`,
 * `BR-AI-001` правило 2) — YandexGPT, российский провайдер по
 * умолчанию. `$folderId` кодируется прямо в `modelUri`
 * (`gpt://<folder_id>/<model>`) — так же определяет каталог, что и
 * отдельный заголовок `x-folder-id`, второй не нужен.
 *
 * Стоимости в ответе нет — только токены; перевод в рубли по цене за
 * 1000 токенов (настройка `ai_yandex_price_per_1k`) — задача
 * `Models/AiUsage.php`, не этого класса (см. `AiProvider.php`).
 */
final class YandexGptProvider implements AiProvider
{
    private const ENDPOINT = 'https://ai.api.cloud.yandex.net/foundationModels/v1/completion';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $folderId,
        private readonly string $model,
    ) {
    }

    public function complete(array $messages, array $options = []): array
    {
        $payload = array_merge([
            'modelUri'          => "gpt://{$this->folderId}/{$this->model}",
            'completionOptions' => [
                'stream'      => false,
                'temperature' => 0.3,
                'maxTokens'   => '2000',
            ],
            'messages' => array_map(
                static fn (array $message): array => [
                    'role' => $message['role'],
                    'text' => $message['content'],
                ],
                $messages
            ),
        ], $options);

        $response = aiHttpPostJson(
            self::ENDPOINT,
            ['Authorization: Api-Key ' . $this->apiKey],
            $payload,
            AI_TIMEOUT_SECONDS
        );

        $text = $response['result']['alternatives'][0]['message']['text'] ?? null;
        if (!is_string($text) || trim($text) === '') {
            throw new RuntimeException('YandexGPT вернул пустой ответ');
        }

        $usage = $response['result']['usage'] ?? [];

        return [
            'text'       => $text,
            'tokens_in'  => (int) ($usage['inputTextTokens'] ?? 0),
            'tokens_out' => (int) ($usage['completionTokens'] ?? 0),
            'cost_usd'   => null,
        ];
    }
}
