<?php

declare(strict_types=1);

/**
 * Контракт провайдера ИИ (`BR-AI-001` правило 3) — несколько
 * взаимозаменяемых провайдеров за одним интерфейсом, случай, который
 * `php.md` прямо разрешает оформлять классом, а не набором функций.
 *
 * Реализация получает только текст переписки и опции вызова — ни
 * `PDO`, ни любых других DB-credentials ни в конструкторе, ни в
 * `complete()` нет и не должно появиться: модель не может ни
 * прочитать, ни записать что-либо в БД напрямую (`phase-9.md`,
 * «Решения фазы»). Перевод стоимости в рубли и запись журнала —
 * задача `Models/AiUsage.php`, не провайдера: `cost_usd` в возврате —
 * то, что прислал сам провайдер (или `null`, если не прислал), без
 * какого-либо чтения курса/цены.
 */
interface AiProvider
{
    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     * @return array{text: string, tokens_in: int, tokens_out: int, cost_usd: ?float}
     */
    public function complete(array $messages, array $options = []): array;
}
