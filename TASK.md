# Current Task

## Фаза
Phase 4 — Панель менеджера и каталог в админке (`.docs/phases/phase-4.md`, Таск 3)

**Статус:** ⏳ Ожидает

## Задача
На карточке Заказа появляется панель действий: кнопки следующего
статуса — только из `allowedOrderTransitions()` для текущего статуса,
ветки образца и способа получения (`FR-MGR-001` правило 3,
`FR-ORD-001`); форма «Отметить предоплату» (сумма →
`validatePaymentAmount()` → `markOrderPrepaid()`, `new → confirmed`) и
кнопка «Остаток получен» (`markOrderPaidFull()`) — UI `FR-PAY-002`;
поле «Стоимость доставки» (`BR-006`, вносится вручную); форма отмены
(`FR-ORD-002`, `BR-007`): выбор ветки — стандартный / нестандартный
размер, комментарий (обязателен для нестандартного), отметка
«предоплата возвращена переводом на карту» (обязательна, если
`payment_status ≠ unpaid`). Из «Готов к отгрузке» и далее кнопки
«Отменить» нет (`canCancelOrder()`), ручной POST отклоняется. Хуки
Резерва (Фаза 5) и СМС (Фаза 7) — точки расширения, не реализуются.

## Scope — что трогаем

- [ ] `database/install.php` — изменить: `orders.cancel_note TEXT
      NULL`, `orders.prepayment_refunded TINYINT(1) NOT NULL DEFAULT
      0` — новая колонка в `CREATE TABLE orders` для свежих установок
      + идемпотентная проверка через `information_schema` для уже
      развёрнутых БД (тот же приём, что `comment`/индексы из Тасков
      1–2)
- [ ] `.docs/database.md` — изменить: обе колонки с назначением
- [ ] `.docs/planning-log.md` — изменить: ADR — колонки отмены; почему
      не отдельная таблица (отмена одна на Заказ, статусной модели у
      неё нет)
- [ ] `src/Core/OrderActions.php` — создать: `validateCancelInput(array
      $input, string $paymentStatus): array` — ошибки по полям:
      `branch` ∈ {`standard`, `non_standard`}, `note` обязателен при
      `non_standard`, `refund_confirmed` обязателен при
      `paymentStatus ≠ unpaid`; чистая функция, без БД
- [ ] `tests/Unit/OrderActionsTest.php` — создать
- [ ] `src/Models/Order.php` — изменить: `cancelOrder(int $orderId,
      string $note = '', bool $prepaymentRefunded = false): bool` — в
      той же транзакции пишет `cancel_note`/`prepayment_refunded` и
      вызывает `transitionOrderStatus(...'cancelled')` (сейчас
      `cancelOrder()` не вызывается ни из одного контроллера — менять
      сигнатуру безопасно); `setOrderShippingCost(int $orderId,
      ?string $cost): void`
- [ ] `src/Controllers/AdminOrderController.php` — изменить:
      `transition()` (`to` из whitelist статусов, `transitionOrder
      Status()` → `false` → flash-ошибка), `markPrepaid()`
      (`validatePaymentAmount()` с `orders.total` и `0`),
      `markPaidFull()`, `setShipping()` (число ≥ 0 или пусто → NULL),
      `cancel()` (`validateCancelInput()` → ошибки в сессию, редирект
      назад) — все методы: `requireCsrf()`, POST → `redirect()`
- [ ] `src/Views/admin/orders/show.php` — изменить: панель действий —
      кнопки переходов (по одной форме на переход), форма предоплаты /
      остатка (скрыта, если `paid_full`), форма стоимости доставки,
      форма отмены (модальное окно Bootstrap через `data-bs-*`; без JS
      — обычная форма в блоке страницы), блок «Отмена» с `cancel_note`
      и отметкой возврата на уже отменённом Заказе
- [ ] `config/routes.php` — изменить: `POST /admin/orders/{id}/transition`,
      `/prepaid`, `/paid-full`, `/shipping`, `/cancel`

## Out of scope — не трогаем

- Список Заказов, карточка на просмотр (без действий) — уже сделаны
  Таском 2, не трогаем повторно (кроме добавления панели действий в
  `show.php`, которая в Таске 2 сознательно не строилась)
- Редактирование состава Заказа (Таск 4), ручное создание Заказа по
  звонку (Таск 5)
- Клиенты, Категории/Товары, форма Товара, фото Вариантов, отчёт по
  продажам (Таски 6–10 этой же фазы)
- Резерв, Выставочный образец, STOCK-эффекты отмены — снятие Резерва,
  пометка изготовленного Варианта образцом (`FR-STOCK-001…005` →
  Фаза 5); хуки для них — только точки расширения, без реализации
- СМС на переходах статуса (`FR-NOTIF-001` → Фаза 7) — хук без
  реализации
- Каркас Панели управления, дашборд, список/просмотр Заказов —
  сделаны Тасками 1–2, не трогаем повторно
- `markOrderPrepaid()`/`markOrderPaidFull()`/`transitionOrderStatus()`
  (Фаза 3, Таск 1 Фазы 2) — переиспользуются как есть, сигнатуры не
  меняются

## Definition of Done

- [ ] `install.php` дважды подряд без ошибок; `SHOW CREATE TABLE
      orders` содержит обе новые колонки (ручная сверка с
      `database.md`)
- [ ] Заказ `new` с обычным Вариантом: кнопки «Подтверждён» и
      «Отменить»; `confirmed` с образцом — «Готов к отгрузке», без «В
      производстве»; `ready_for_shipment` самовывоз — «Доставлен/
      Собран», доставка — «В доставке»
- [ ] Ручной POST `transition` с `to=shipping` на Заказе `new` →
      flash-ошибка, статус в БД не изменён;
      `grep -r "UPDATE orders SET status" src/` — по-прежнему одно
      вхождение в `transitionOrderStatus()`
- [ ] Предоплата: `0`, отрицательная, больше `total`, `12.345` →
      ошибка формы; корректная → `payment_status=prepaid`,
      `prepaid_amount` записана, статус `confirmed`; повторная
      отправка → сообщение «уже отмечена», данные не изменились;
      «Остаток получен» → `paid_full`, `status` не тронут
- [ ] Стоимость доставки сохраняется и показывается отдельно от
      `total`; пустое поле → `NULL`
- [ ] Отмена: `non_standard` без комментария → ошибка; `prepaid` без
      отметки возврата → ошибка; с отметкой → `cancelled`,
      `prepayment_refunded=1`, `cancel_note` сохранён; на Заказе
      `shipping` кнопки «Отменить» нет, ручной POST → отклонён, статус
      не изменён
- [ ] `composer test` зелёный, включая `OrderActionsTest`
- [ ] Проверить `.docs/dod-global.md`

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
