# Current Task

## Фаза
Phase 3 — Онлайн-оплата и касса (`.docs/phases/phase-3.md`, Таск 2)

**Статус:** ✅ Завершён — последний таск фазы, фаза 3 целиком закрыта.
Код реализован и проверен: `composer test` 125/125 (6 новых тестов
`PaymentTest`); `grep -r "SET payment_status" src/` — ровно 2
вхождения; ручная проверка временным скриптом в scratchpad против
реальной БД (`mikhail700.beget.tech`): `markOrderPrepaid()` на
`new`/`unpaid` → `prepaid`/`confirmed`, `prepaid_amount` записана;
повторный вызов → `false`, без изменений; `markOrderPaidFull()` →
`paid_full`, `status` не тронут, повторный вызов → `false`; отменённый
Заказ → `markOrderPrepaid()` фиксирует оплату, но не переоткрывает
`cancelled`-статус. `storage/logs/app.log` — только ожидаемая `INFO`
запись из `markOrderPaidFull()`. Тестовые заказы удалены после
проверки.

## Задача
Единственный путь пометить оплату Заказа полученной — `markOrderPrepaid()`
(предоплата: `payment_status → prepaid`, `prepaid_amount` заполнена,
статус Заказа `new → confirmed` через существующий
`transitionOrderStatus()`) и `markOrderPaidFull()` (остаток:
`payment_status → paid_full`, `orders.status` не трогается) — обе
атомарны (одна транзакция) и идемпотентны (повторный вызов на уже
оплаченном Заказе не меняет данные, не бросает исключение). Сумма
проверяется чистой функцией без БД. UI вызова — Фаза 4, здесь не
реализуется.

## Scope — что трогаем

- [x] `src/Core/Payment.php` — создан: `validatePaymentAmount(string
      $amount, string $orderTotal, string $alreadyPaid): ?string` —
      `null`, если сумма корректна (число > 0, не превышает
      `orderTotal - alreadyPaid`), иначе текст ошибки; сравнение
      только через `bccomp()`
- [x] `tests/Unit/PaymentTest.php` — создан: 6 тестов (0/отрицательная
      сумма, сумма больше остатка, сумма равна остатку, корректная
      сумма, сумма с лишними знаками после запятой, некорректный формат)
- [x] `tests/bootstrap.php` — изменён: добавлен
      `require_once ROOT_PATH . '/src/Core/Payment.php';`
- [x] `src/Models/Order.php` — изменён: `markOrderPrepaid(int
      $orderId, string $amount): bool` — транзакция, `UPDATE orders
      SET payment_status = :prepaid, prepaid_amount = :amount WHERE
      id = :id AND payment_status = :unpaid` (константы
      `PAYMENT_STATUS_*`), `rowCount() === 0` → `false` без изменений,
      иначе `transitionOrderStatus($orderId, ORDER_STATUS_CONFIRMED)`;
      `markOrderPaidFull(int $orderId, string $amount): bool` — тот же
      паттерн, `payment_status='prepaid' → 'paid_full'`, `orders.status`
      и `prepaid_amount` не трогаются (в схеме нет колонки под остаток
      — `orders.total` уже содержит полную сумму); `$amount` уходит в
      `logInfo()` как аудиторский след, не как запись в БД

## Out of scope — не трогаем

- UI Менеджера для вызова этих функций (кнопка «Отметить оплату» и
  т.п.) — Фаза 4, когда появится Панель менеджера
- Реальная интеграция с ЮMoney, вебхук, запись в `payment_logs` — не
  реализуется (`ADR-018`)
- Фискализация Атол (`FR-PAY-005`) — не реализуется (`Q-007` снят)
- `PaymentController`, `/payment/stub` — уже сделаны Таском 1, не
  трогаем
- Диапазон 30–50% предоплаты — не валидируется системой (`FR-PAY-002`:
  процент вводит Менеджер вручную, не расчёт сайта)

## Definition of Done

- [x] `composer test` зелёный (125/125), включая `PaymentTest`:
      `validatePaymentAmount()` отклоняет 0/отрицательную/превышающую
      остаток/некорректно отформатированную сумму, принимает корректную
- [x] `grep -r "SET payment_status" src/` — единственные два вхождения,
      в `markOrderPrepaid()`/`markOrderPaidFull()`
- [x] Ручная проверка временным скриптом в scratchpad (не в
      репозитории) против реальной БД (`mikhail700.beget.tech`): Заказ
      `new`/`unpaid` → `markOrderPrepaid()` → `prepaid`/`confirmed`,
      `prepaid_amount` записана; повторный вызов → `false`, данные не
      изменились; `markOrderPaidFull()` после этого → `paid_full`,
      `orders.status` не тронут, повторный вызов → `false`; отменённый
      Заказ (`cancelled`) → `markOrderPrepaid()` помечает оплату, но не
      переоткрывает статус (переход запрещён таблицей 6.3, функция не
      бросает исключение)
- [x] Деньги нигде как `float`
- [x] Проверить `.docs/dod-global.md` — новых записей в
      `storage/logs/app.log`, кроме ожидаемой `INFO` из
      `markOrderPaidFull()`, нет

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
