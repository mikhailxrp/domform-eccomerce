# Current Task

## Фаза
Phase 4 — Панель менеджера и каталог в админке (`.docs/phases/phase-4.md`, Таск 4)

**Статус:** ✅ Завершён — код реализован и проверен. `composer test`
143/143 (3 новых теста `canEditOrderItems()`). Проверено `php -S` +
`curl` против реальной БД (`mikhail700.beget.tech`) на двух временных
тестовых Заказах (оба и их позиции удалены после проверки, изменённая
для проверки снэпшота цена Варианта восстановлена): добавление позиции
по артикулу без JS пересчитывает `orders.total` (35000 → 111000 →
149000 верно по bcmath); изменение количества пересчитывает `total`
(149000 → тот же расчёт); удаление не последней позиции пересчитывает
`total` и оставляет запись; удаление последней оставшейся позиции
отклонено с flash «Нельзя удалить последнюю позицию — отмените Заказ.»,
запись не удалена; смена цены Варианта в БД после добавления позиции не
изменила `order_items.price`/`total` уже добавленной позиции;
статус `new` — блок редактирования не рендерится, ручной POST на
`items` отклонён без изменений (`item_count` не увеличился); чужой
`itemId` (Позиция другого Заказа) в `items/{itemId}` не изменён;
`GET /admin/variants/search` — Гостю редирект на `/login`, залогиненному
`customer` редирект на `/`, `manager`/`admin` получает JSON с ценой и
цветами. В процессе проверки найдена и исправлена реальная ошибка:
`AdminVariantController.php` вызывал `searchVariantsForAdmin()` →
`buildFulltextTerm()`, но не подключал `Core/CatalogFilters.php` (тот же
`require`, что уже стоит в `SearchController.php`) — падало 500
`Call to undefined function`; добавлен `require_once`, зафиксировано в
`storage/logs/app.log` (единственная ошибка за сессию, до фикса).
Не проверено вручную: откат при порче SQL внутри `recalculateOrderTotal()`
проверен только код-ревью (та же структура `try/rollBack/throw`, что уже
верифицирована живым тестом на `cancelOrder()` в Таске 3), фактическая
порча SQL на реальной БД не воспроизводилась — риск для чужих данных не
оправдан; реальная работа JS-подсказок `variant-picker` в браузере
(только код-ревью), вёрстка на 320px — тот же пробел, что в Тасках 1–3
(нет браузера в сессии).

## Задача
В статусах `confirmed` / `in_production` (`FR-ORD-003`, `FR-MGR-003`) Менеджер
на карточке Заказа добавляет позицию (поиск Варианта по названию Товара /
артикулу с подсказками → выбор цвета из `variant_images.color` →
количество), меняет количество, удаляет позицию; `orders.total`
пересчитывается в той же транзакции из `product_variants`, снэпшот полей —
как в `createOrder()`. Последнюю позицию удалить нельзя (это отмена,
`FR-ORD-002` правило 6). В других статусах блок редактирования не
показывается, ручной POST отклоняется. Границу «до раскроя ткани» система
не проверяет (`FR-ORD-003` правило 2).

## Scope — что трогаем

- [x] `src/Core/OrderActions.php` — изменён: `canEditOrderItems(string
      $status): bool` (`confirmed`, `in_production`)
- [x] `tests/Unit/OrderActionsTest.php` — изменён: тест
      `canEditOrderItems()` для всех 7 статусов + неизвестного
- [x] `src/Models/Order.php` — изменён: `addOrderItem(int $orderId, int
      $variantId, ?string $color, int $qty): bool` (активный Вариант,
      снэпшот, `clampCartQuantity()` для образца), `updateOrderItemQuantity(int
      $orderId, int $itemId, int $qty): bool`, `removeOrderItem(int
      $orderId, int $itemId): bool` (последняя позиция → `false`),
      `recalculateOrderTotal(PDO $pdo, int $orderId): void` — все три в
      одной транзакции с пересчётом, условие `order_id` в каждом `WHERE`
- [x] `src/Models/Product.php` — изменён: `searchVariantsForAdmin(string
      $q, int $limit): array` — по образцу `suggestProducts()` /
      `buildSearchConditions()`, по `products.name` (FULLTEXT/префикс) и
      `product_variants.sku` (префикс), только активные, с ценой и списком
      цветов из `variant_images`; заодно `findActiveVariantIdBySku(string
      $sku): ?int` (не входило дословно в Scope, но необходимо для формы
      без JS — `variant-picker.php` шлёт `sku`, а `addOrderItem()`
      принимает `variantId`)
- [x] `src/Controllers/AdminOrderController.php` — изменён: `addItem()`,
      `updateItem()`, `removeItem()` (`requireCsrf()`,
      `canEditOrderItems()`, редирект на карточку с flash); `show()` —
      передаёт `canEditItems` во View
- [x] `src/Controllers/AdminVariantController.php` — создан: `search()` —
      JSON-подсказки по образцу `SearchController::suggest()`,
      `requireRole(['manager', 'admin'])`
- [x] `src/Views/components/admin/variant-picker.php` — создан: поле
      «Артикул» (`name="sku"`) + скрытые `variant_id`/`color`/`quantity`;
      без JS — обычный POST кнопкой «Добавить»
- [x] `src/Views/admin/orders/show.php` — изменён: таблица позиций с
      формами количества/удаления, блок добавления через `variant-picker`
      — виден только когда `canEditItems`
- [x] `public/assets/js/admin.js` — изменён: подсказки к `variant-picker`
      (`fetch`, async/await), подстановка цветов выбранного Варианта
- [x] `config/routes.php` — изменён: `GET /admin/variants/search`,
      `POST /admin/orders/{id}/items`,
      `POST /admin/orders/{id}/items/{itemId}`,
      `POST /admin/orders/{id}/items/{itemId}/remove`
- [x] `config/config.php` — изменён: `ADMIN_VARIANT_SEARCH_LIMIT` (не
      входило дословно в Scope, но нужна константа лимита подсказок — по
      образцу `SEARCH_SUGGEST_LIMIT`)

## Out of scope — не трогаем

- Список Заказов, карточка на просмотр, панель действий статуса/оплаты/
  доставки/отмены (Таски 2–3) — не трогаем повторно
- Ручное создание Заказа по звонку (Таск 5) — `variant-picker` создаётся
  здесь, повторно используется там
- Клиенты, Категории/Товары, форма Товара, фото Вариантов, отчёт по
  продажам (Таски 6–10)
- Резерв, Выставочный образец, STOCK-эффекты (`FR-STOCK-001…005` →
  Фаза 5): при удалении позиции с активным Резервом строка `reserves`
  каскадно удалится по существующему `FK ON DELETE CASCADE` — штатное
  поведение схемы, не новая логика этого таска
- Граница «до раскроя ткани» — не проверяется системой (`FR-ORD-003`
  правило 2)
- `createOrder()`, `transitionOrderStatus()`, `markOrderPrepaid()` и
  остальные функции Тасков 1–3 — переиспользуются как есть

## Definition of Done

- [x] Добавление / изменение количества / удаление меняют `order_items` и
      `orders.total` атомарно (проверено на реальной БД: 35000 → 111000 →
      149000 → 114000 — все шаги сошлись с ручным пересчётом); откат при
      порче SQL внутри общей транзакции проверен код-ревью (та же
      структура `try/rollBack/throw`, что и в уже протестированных
      `createOrder()`/`cancelOrder()`), живая порча SQL на реальной БД не
      воспроизводилась
- [x] В `new` блока нет (проверено), ручной POST → flash-ошибка без
      изменений (проверено); `ready_for_shipment`/`delivered`/`cancelled`
      — та же проверка `canEditOrderItems()`, что и `new`, отдельно не
      гонялась (тождественный код-путь)
- [x] Неактивный / несуществующий Вариант нельзя добавить (проверка
      `pv.is_active = 1 AND p.is_active = 1` в `addOrderItem()`/
      `findActiveVariantIdBySku()` — код-ревью); образец — количество 1
      (`clampCartQuantity()`, переиспользуется как есть); чужой `itemId`
      (другого Заказа) → без изменений (проверено на реальной БД)
- [x] Удаление последней позиции → ошибка «Нельзя удалить последнюю
      позицию — отмените Заказ.» (проверено на реальной БД)
- [x] Снэпшот: после добавления позиции изменение цены Варианта в БД не
      меняет `order_items.price` и `total` (проверено на реальной БД)
- [x] Без JS: артикул + количество + «Добавить» работают обычным POST
      (проверено — вся ручная проверка велась через `sku`, без
      `variant_id`); `GET /admin/variants/search` недоступен Гостю
      (редирект `/login`) и залогиненному `customer` (редирект `/`)
- [x] `composer test` зелёный (143/143)
- [x] Проверить `.docs/dod-global.md`

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
