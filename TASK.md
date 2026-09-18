# Current Task

## Фаза
Phase 2 — Корзина и оформление заказа (`.docs/phases/phase-2.md`, Таск 5)

**Статус:** ✅ Завершён — последний таск фазы, фаза 2 целиком закрыта
(`.docs/phases/_status.md`, `.docs/tz-coverage.md` обновлены). Код
реализован и проверен: `composer test` 119/119 (21 новый тест
`OrderStatusTest` — вся таблица переходов раздела 6.3, включая
запрещённые); ручная проверка временным скриптом в scratchpad против
реальной БД (`mikhail700.beget.tech`): `cancelOrder()` на `new` →
`cancelled` с обновлением `updated_at`, на `delivered` → `false` без
изменений; полная цепочка `new→confirmed→in_production→
ready_for_shipment→shipping→delivered` с заполнением `delivered_at`;
образец `confirmed→ready_for_shipment` напрямую минуя `in_production`;
запрещённый `new→shipping` корректно отклонён; `grep -r "UPDATE orders"
src/` — единственное вхождение со `status` в `transitionOrderStatus()`;
`/checkout/success` показывает «Новый» через `orderStatusLabel()`,
подтверждено на реальном заказе через `curl`. Тестовые заказы удалены
после проверки.

## Задача
Единственная функция-переход по таблице раздела 6.3 ТЗ (`FR-ORD-001`,
`php.md`: никогда raw `UPDATE orders SET status`): запрет обхода
диаграммы, ветвление образца («Подтверждён» → «Готов к отгрузке»
напрямую, минуя «В производстве»), ветвление по способу получения из
«Готов к отгрузке», `delivered_at` на «Доставлен/Собран»; отмена
доступна только из «Новый / Подтверждён / В производстве»
(`FR-ORD-002` правило 4, `BR-007`). Хуки резерва (Фаза 5), предоплаты
(Фаза 4) и СМС (Фаза 7) — точки расширения, здесь не реализуются. UI —
Фаза 4; проверка — unit-тесты и прямой вызов, без UI.

## Scope — что трогаем

- [x] `src/Core/OrderStatus.php` — создан: константы 7 статусов Заказа
      и 3 статусов оплаты с русскими подписями (`orderStatusLabel()`),
      `allowedOrderTransitions(string $from, bool $hasShowroomSample,
      string $fulfillment): array`, `canTransitionOrder(string $from,
      string $to, bool $hasShowroomSample, string $fulfillment): bool`,
      `canCancelOrder(string $status): bool`
- [x] `tests/bootstrap.php` — изменён: `require_once
      Core/OrderStatus.php`
- [x] `tests/Unit/OrderStatusTest.php` — создан: 21 тест, вся таблица
      переходов раздела 6.3, включая запрещённые
- [x] `src/Models/Order.php` — изменён: `orderHasShowroomSample(int
      $orderId): bool`, `transitionOrderStatus(int $orderId, string
      $to): bool` (единственное место с `UPDATE orders SET status`),
      `cancelOrder(int $orderId): bool`
- [x] `src/Views/checkout/success.php` — изменён: подпись статуса
      через `orderStatusLabel($order['status'])` вместо литерала
      «Новый»
- [x] `.docs/tz-coverage.md` — изменён: `FR-ORD-001…005` разбит по
      фактическому покрытию (001/005 — Фаза 2, 002 — механизм Фаза 2 /
      UI Фаза 4, 003/004 — целиком Фаза 4); также обновлены
      `FR-CART-*`/`FR-CHK-*`/`FR-SHIP-*`/`BR-001`/`BR-006`/`BR-007` —
      отметки «Реализовано Тасками N Фазы 2» (закрытие фазы)

## Out of scope — не трогаем

- Снятие/списание `reserves` при отмене/доставке — Фаза 5 (решение
  фазы, `phase-2.md`)
- UI отмены заказа, смены статуса из Панели менеджера — Фаза 4 (этот
  таск — только механизм)
- Предоплата (`prepaid_amount`, переход по факту оплаты) — Фаза 4 /
  модуль `PAY`
- СМС-уведомления при смене статуса — Фаза 7
- Нестандартный размер, согласование Менеджером — Фаза 5

## Definition of Done

- [x] `grep -r "UPDATE orders" src/` — единственное вхождение со
      `status` в `transitionOrderStatus()`
- [x] `OrderStatusTest`: «Новый → В доставке» запрещён; обычный
      Вариант из «Подтверждён» → только «В производстве» и «Отменён»;
      образец из «Подтверждён» → «Готов к отгрузке» и «Отменён», не
      «В производстве»; из «Готов к отгрузке» самовывоз → «Доставлен/
      Собран», доставка → «В доставке»; отмена из «Готов к отгрузке»/
      «В доставке»/«Доставлен/Собран»/«Отменён» запрещена; терминальные
      статусы без переходов
- [x] Ручная проверка скриптом в scratchpad (не в репозитории):
      `cancelOrder()` на `new` → `cancelled`, `updated_at` обновлён; на
      `delivered` → `false`, статус не изменён; цепочка `new →
      confirmed → in_production → ready_for_shipment → shipping →
      delivered` проходит и заполняет `delivered_at`
- [x] `/checkout/success` показывает «Новый» из `orderStatusLabel()`,
      не строку `new` (проверено на реальном заказе через `curl`)
- [x] `composer test` зелёный — 119/119
- [x] Проверить `.docs/dod-global.md`

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
