# Current Task

## Фаза
Phase 5 — Выставочный образец, резерв и возврат
(`.docs/phases/phase-5.md`), Таск 1 из 6.

**Статус:** ✅ Завершён 20.09.2026. Проверено на реальной БД сценарием
через Model-функции и живой HTTP-сессией (`php -S` + `curl`, логин
`admin`); `composer test` 232/232. Подробности — `.docs/dev-log.md`
20.09.2026.

## Задача
При фиксации предоплаты (`markOrderPrepaid()` — единый путь и для
карточки Заказа, и для ручного Заказа) в той же транзакции создаётся
`reserves.active` на каждую Позицию с Выставочным образцом; проигрыш
конкуренции (дубль `UNIQUE(active_variant_id)`, SQLSTATE 23000)
откатывает фиксацию предоплаты целиком и сообщается Менеджеру, а не
падает 500 — `BR-003`, `FR-STOCK-002`, `FR-STOCK-003`, UC-02 (2а).

## Scope — что трогаем

- [x] `src/Core/Reserve.php` — создать: константы
      `RESERVE_STATUS_ACTIVE/RELEASED/FULFILLED`,
      `RESERVE_STATUS_LABELS` + `reserveStatusLabel(string): string`,
      `isUniqueViolation(Throwable $e): bool` (только `PDOException` с
      SQLSTATE `23000`), константы результата
      `PREPAID_RESULT_OK / PREPAID_RESULT_ALREADY /
      PREPAID_RESULT_SAMPLE_TAKEN` — чистые функции без БД
- [x] `src/Models/Reserve.php` — создать:
      `createReservesForOrder(PDO $pdo, int $orderId): bool` — работает
      внутри транзакции вызывающего, `INSERT` по каждой Позиции, чей
      Вариант `is_showroom_sample = 1`, **без** предварительного
      `SELECT` активного резерва; `false` при `isUniqueViolation()`,
      любое другое исключение — наружу;
      `findCompetingReserveForOrder(int $orderId): ?array` — активный
      резерв другого Заказа на образец из состава этого + `order_id`
      держателя (для текста flash «занят Заказом №N»). **Отклонение от
      плана:** вместо `findActiveReserveByVariant()` — контроллеру
      известен Заказ, а не Вариант, иначе перебор позиций в контроллере
- [x] `src/Models/Order.php` — изменить: `markOrderPrepaid()`
      возвращает `string` (одна из `PREPAID_RESULT_*`) вместо `bool`;
      после `UPDATE payment_status` и до `transitionOrderStatus()`
      вызывает `createReservesForOrder()`; при `false` — `rollBack()`,
      `logWarning()` с `order_id` + `variant_id`,
      `PREPAID_RESULT_SAMPLE_TAKEN`; docblock — актуализировать
      («закрепление Резерва — здесь, снятие/списание — Таск 2»)
- [x] `src/Controllers/AdminOrderController.php` — изменить:
      `markPrepaid()` — `match` по результату: три разных flash
      (успех / «Предоплата уже отмечена» / «Выставочный образец уже
      закреплён за Заказом №N — предложите Покупателю такой же Вариант
      под заказ»); `store()` — результат `markOrderPrepaid()` больше не
      игнорируется: при `PREPAID_RESULT_SAMPLE_TAKEN` Заказ остаётся
      `new`/`unpaid`, flash `error` «Заказ №N создан без предоплаты.
      …» (ключа `warning` у `flash.php` нет, заводить — вне scope),
      редирект на созданный Заказ
- [x] `tests/Unit/ReserveTest.php` — создать: `isUniqueViolation()` —
      `PDOException` с SQLSTATE 23000 (в `errorInfo` и в `getCode()`) →
      `true`; `PDOException` с другим SQLSTATE → `false`;
      `RuntimeException` → `false`; `reserveStatusLabel()` для трёх
      статусов и неизвестного значения — 6 тестов
- [x] `tests/bootstrap.php` — изменить: подключён `Core/Reserve.php`
      (не было в Scope — без этого тесты не видят функции; одна строка)
- [x] `.docs/planning-log.md` — изменить: `ADR-040` — откат фиксации
      предоплаты при проигрыше конкуренции; альтернатива «оплата
      записана, резерв не создан» отклонена: в системе остался бы
      оплаченный Заказ на чужой образец, который Менеджер не заметит
- [x] `.docs/dev-log.md` — изменить: запись по таску
- [x] `.docs/phases/phase-5.md` — изменить: статус Таска 1;
      `getOrderReserves()` переносится из Таска 1 в Таск 3 — там она
      впервые читается, в этом таске была бы мёртвым кодом

## Out of scope — не трогаем

- Списание при «Доставлен/Собран» и снятие при отмене
  (`transitionOrderStatus()` на `delivered`, `cancelOrder()`) — Таск 2
- Любой UI Резерва в карточке Заказа: блок, срок `agreed_until`, кнопка
  «Снять резерв» — Таск 3; в этом таске Менеджер видит резерв только
  через flash и в БД
- Переключатель образца в списке Товаров — Таск 4; витринные эффекты
  резерва (каталог/карточка/корзина/чекаут) — Таск 5; возвраты и
  гарантия — Таск 6
- `createOrder()` — его существующая проверка активного резерва
  остаётся как есть (первый барьер при оформлении), не переписывается
  на `isUniqueViolation()` и не удаляется
- Схема БД / `database/install.php` / `database.md` — не меняются,
  `reserves` заведена в Фазе 0 (`ADR-007`)
- `agreed_until` — системой не заполняется (`FR-STOCK-002` правило 2)
- Никакой cron/таймер по резерву; никакие СМС (Фаза 7)
- Другие Model/Controller/View, не перечисленные в Scope; попутный
  рефакторинг `markOrderPaidFull()` и соседних функций

## Definition of Done

- [x] Заказ с образцом + предоплата из карточки → строка `reserves`:
      `status='active'`, `order_item_id` = Позиция образца,
      `product_variant_id`, `agreed_until IS NULL`,
      `released_at IS NULL`; Заказ → `confirmed` / `prepaid`
- [x] Заказ без образца + предоплата → `reserves` не пополняется,
      поведение как до таска
- [x] Заказ с двумя образцами (разные Варианты) → две строки `reserves`
- [x] Конкуренция: Заказ А и Заказ Б на один образец, предоплата по Б →
      резерв у Б; затем предоплата по А → flash с номером Заказа Б, у А
      `payment_status='unpaid'`, `status='new'`, `prepaid_amount IS
      NULL` (откат целиком), в `storage/logs/app.log` — `WARNING`,
      HTTP не 500, PHP-warnings нет
- [x] Ручной Заказ (`/admin/orders/create`) на занятый образец → Заказ
      создан в `new`/`unpaid`, flash-предупреждение, редирект на его
      карточку — не молчаливый «Заказ создан». **Живым HTTP не
      прогонялся** — только чтение кода `store()`; Model-путь тот же,
      что у проверенного `markPrepaid()`
- [x] Повторная предоплата по уже `prepaid` Заказу → «Предоплата уже
      отмечена», второй строки `reserves` нет
- [x] После снятия резерва Б прямо в БД (`UPDATE reserves SET
      status='released'` — временно, UI появится в Таске 3) предоплата
      по А проходит и создаёт новый `active` — подтверждает, что
      `UNIQUE` держит только активные резервы
- [x] В `createReservesForOrder()` нет `SELECT ... FROM reserves WHERE
      status='active'` перед `INSERT` — защита от гонки только
      `UNIQUE(active_variant_id)` (`dod-global.md`, «Данные»)
- [x] `composer test` зелёный, включая `tests/Unit/ReserveTest.php`;
      `grep markOrderPrepaid` — ни одного вызова с `bool`-проверкой
      (`!markOrderPrepaid(`) не осталось
- [x] Формы предоплаты/ручного Заказа не тронуты: `csrfField()` в HTML
      и `requireCsrf()` в контроллере на месте
- [x] Проверить `.docs/dod-global.md`

## Решения таска

- **`markOrderPrepaid()` возвращает строковый результат, не `bool` и не
  исключение.** Контроллеру нужно отличать «уже оплачено» от «образец
  занят»; доменные исключения в Model-функциях в проекте не
  используются (`transitionOrderStatus()` → `false`, `createOrder()` →
  `null`), поэтому — константы результата.
- **Ручной Заказ на занятый образец всё равно создаётся** в `new` без
  предоплаты: `createOrder()` пропустит его, только если резерва не
  было в момент вставки, а `markOrderPrepaid()` откатится. Сейчас
  `store()` игнорирует результат — исправляется здесь, иначе Менеджер
  получит «Заказ создан» без предупреждения.

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
- На каждый шаг — чем проверяется (пункт DoD / unit-тест / ручная
  проверка в браузере), не только что сделать
