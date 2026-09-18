# Phase 4 — Панель менеджера и каталог в админке

## Цель

Менеджер и Администратор в Панели управления видят все Заказы (сайт +
звонок/WhatsApp) в одном списке с фильтром по статусу, открывают Заказ
и ведут его по диаграмме раздела 6.3 (только разрешённые переходы),
отмечают предоплату/остаток и стоимость доставки, фиксируют отмену по
`BR-007`, редактируют состав до раскроя ткани, создают Заказ вручную
сразу в «Подтверждён» с привязкой к Покупателю по телефону; управляют
Категориями, Товарами, Вариантами и фото; видят клиентов с поиском по
телефону и отчёт «количество и сумма Заказов за период» —
`FR-MGR-001…004`, `FR-ADM-001, 002, 005, 008`, `FR-ORD-002` (UI
отмены), `FR-ORD-003`, `FR-ORD-004`, `FR-PAY-002` (UI отметки оплаты),
`BR-006`, `BR-007`.

Не входит: создание Резерва на переходе в «Подтверждён» и STOCK-эффекты
отмены — снятие Резерва, пометка изготовленного Варианта Выставочным
образцом, inline-переключатель образца в списке Товаров
(`FR-STOCK-001…005` → Фаза 5); отображение скидки на витрине и фильтр
«Со скидкой» (`FR-DISC-002`, `FR-CAT-010` → Фаза 6); модерация отзывов
(`FR-ADM-004` → Фаза 6); СМС на переходах статуса (`FR-NOTIF-001` →
Фаза 7); сотрудники, технические настройки, контент/баннеры
(`FR-ADM-003`, `FR-ADM-007` → Фаза 8); баг `hitRateLimit()` из
`dev-log.md` 17.09.2026 — отдельным таском.

## Статус

🔄 В работе

## Решения фазы

- **Отдельный layout Панели управления на ассетах шаблона Valex, не
  тема витрины.** В `public/assets/admin/` копируется куратированный
  минимум из `00-input/admin/assets/` (`styles.min.css`, `icons.css` +
  `icon-fonts`, `bootstrap.bundle`, `defaultmenu`, `sticky`,
  `simplebar`), не все 87 МБ. Витринные `header.php`/`footer.php` в
  админке не используются — у Valex собственная сборка Bootstrap и
  сайдбар. Никаких CDN (`general.md`).
- **Без DataTables.** Фильтры, сортировка и пагинация списков —
  серверные (`buildPagination()` из Фазы 1, PDO): клиентский плагин
  стал бы вторым источником истины по выборке и потянул бы jQuery-
  обвязку шаблона. Графики — Chart.js из
  `00-input/admin/assets/libs/chart.js` (в стеке `CLAUDE.md`), не
  ApexCharts.
- **Свой JS админки — `public/assets/js/admin.js`** (IIFE, без
  собственных глобальных переменных) — аналог `app.js` для второго
  layout: `app.js` завязан на `main.js` темы витрины, который в админке
  не грузится. Правило `CLAUDE.md` «свой код в `app.js`» трактуется как
  «один файл на layout», фиксируется ADR в Таске 1.
- **Схема: две колонки в `orders`** (Таск 3, ADR) —
  `cancel_note TEXT NULL` (комментарий отмены; обязателен для
  нестандартного размера, `FR-ORD-002` правило 3) и
  `prepayment_refunded TINYINT(1) NOT NULL DEFAULT 0` (`FR-ORD-002`
  правило 7: «форма не позволяет закрыть отмену без отметки о возврате
  предоплаты» — сейчас эту отметку негде хранить).
- **Форма отмены целиком в Фазе 4**, а не «стандартная ветка здесь,
  нестандартная — в Фазе 5», как записано в `_status.md`. Ветка — это
  радиокнопка в одной форме (`ord.md`: ветка определяется устно,
  Менеджер выбирает явно), резать её на две фазы дороже, чем сделать
  сразу. В Фазе 5 остаются только STOCK-эффекты отмены (снятие Резерва,
  пометка изготовленного Варианта образцом). Строка Фазы 5 в
  `_status.md` уточняется при закрытии фазы.
- **`is_showroom_sample` и `discount_percent` — в форме Варианта уже
  сейчас** (`FR-ADM-001` правило 2 перечисляет их явно). Это обычные
  колонки: образец уже отображается каталогом с Фазы 1 (`FR-CARD-003`),
  скидка — только сохраняется, её отображение/фильтр — Фаза 6, как и
  планировалось. Inline-переключатель образца прямо в списке Товаров
  (`FR-STOCK-001` правило 1) — Фаза 5.
- **Характеристики — свободные пары «название — значение»**
  (`product_specs`, `ADR-030`), а не «набор полей, специфичный для
  Категории» (`FR-ADM-001` правило 3): шаблонов характеристик по
  категориям в схеме нет, заводить их для ≈80 Товаров избыточно.
  Отклонение фиксируется в `tz-coverage.md` при закрытии фазы.
- **Категории — плоская таблица с колонкой «родитель»**, без
  treeview-плагина (оба варианта допущены `admin-assembly.md`; плоская
  — без нового JS). Правило «не более 2 уровней» — в Model.
- **Контроллеры — плоские `Admin*Controller`** (`AdminOrderController`,
  `AdminProductController`, …): `Router.php` резолвит
  `App\Controllers\{Name}` без подпапок.
- **Все экраны фазы — `requireRole(['manager', 'admin'])`** первой
  строкой каждого метода: права ролей в этом объёме совпадают (раздел
  5.2 ТЗ); `role='admin'`-only появляется только в Фазе 8
  (`Q-DEV-001` в `tz-coverage.md`).
- **Ручной Заказ — через уже существующие `createOrder()` +
  `markOrderPrepaid()`**, не отдельный путь записи: `createOrder()`
  создаёт `new`, `markOrderPrepaid()` переводит в `confirmed` и
  фиксирует предоплату — ровно то, что требует `FR-MGR-002` правило 2
  («звонок и предоплата уже состоялись»). Сумма предоплаты в форме
  обязательна.
- **`orders.total` — только сумма позиций; `shipping_cost` хранится и
  показывается отдельно**, в `total` не вливается (`BR-006`: стоимость
  доставки согласуется по телефону, не считается сайтом). Пересчёт
  `total` при изменении состава — в той же транзакции из
  `product_variants`, снэпшот полей — как в `createOrder()`.
- **Деньги** — суммы предоплаты, цены Вариантов, отчёт — только
  строками/`DECIMAL`, сравнение через `bccomp()`, `formatPrice()`
  только во Views (`php.md`).

## Таски

| #  | Название                                                        | Статус     |
| -- | --------------------------------------------------------------- | ---------- |
| 1  | Каркас Панели управления: layout, ассеты, навигация, дашборд    | ✅ Завершён |
| 2  | Список Заказов с фильтрами и карточка Заказа (просмотр)         | ✅ Завершён |
| 3  | Действия над Заказом: смена статуса, оплата, доставка, отмена   | ✅ Завершён |
| 4  | Редактирование состава Заказа                                   | ✅ Завершён |
| 5  | Ручное создание Заказа по звонку/WhatsApp                       | ⏳ Ожидает |
| 6  | Клиенты: список, поиск по телефону, карточка                    | ⏳ Ожидает |
| 7  | Категории и список Товаров                                      | ⏳ Ожидает |
| 8  | Форма Товара: Варианты, характеристики, категории               | ⏳ Ожидает |
| 9  | Фото Вариантов: загрузка, цвет, образец ткани, главное          | ⏳ Ожидает |
| 10 | Отчёт по продажам                                               | ⏳ Ожидает |

---

## Таск 1 — Каркас Панели управления: layout, ассеты, навигация, дашборд

**Статус:** ✅ Завершён

**Цель таска:**
`/admin` открывается в собственном layout Панели управления (сайдбар:
Заказы, Новый заказ, Товары, Категории, Клиенты, Отчёты; имя текущего
пользователя и «Выйти»), показывает счётчики Заказов по 7 статусам со
ссылками на будущий фильтр списка (`/admin/orders?status=…`);
Покупатель → редирект на `/`, Гость → `/login` (уже делает
`requireRole()`). Пункты меню для ещё не построенных экранов ведут на
маршруты, которые появятся в следующих тасках — до этого 404 допустим.

**Что нужно создать/изменить:**
- `public/assets/admin/` — создать: куратированный минимум из
  `00-input/admin/assets/` — `css/styles.min.css`, `css/icons.css`,
  `icon-fonts/` (только те, на которые ссылается `icons.css`),
  `libs/bootstrap/`, `libs/@popperjs/`, `libs/simplebar/`,
  `js/defaultmenu.min.js`, `js/sticky.js`, `js/simplebar.js`,
  `js/main.js`; `custom-switcher`, `pickr`, `choices`, `flatpickr`,
  `node-waves`, `jsvectormap`, `apexcharts` — не копировать
- `src/Views/layout/admin-header.php` — создать: `<html>`, ассеты
  админки, сайдбар с навигацией (активный пункт по текущему URI),
  верхняя панель с именем пользователя и формой `POST /logout`
  (`csrfField()`), `flash.php`
- `src/Views/layout/admin-footer.php` — создать: подключение JS админки
  + `admin.js`
- `src/Views/admin/index.php` — переписать: дашборд — 7 карточек-
  счётчиков по статусам с бейджем и ссылкой на `/admin/orders?status=…`
- `src/Views/components/admin/order-status-badge.php` — создать:
  `$status` → `<span class="badge bg-…">Подпись</span>` через
  `orderStatusLabel()` + `orderStatusBadgeClass()`
- `src/Core/OrderStatus.php` — изменить: `orderStatusBadgeClass(string
  $status): string` — 7 статусов → 7 классов `bg-*`
  (`new`→`bg-secondary`, `confirmed`→`bg-info`,
  `in_production`→`bg-primary`, `ready_for_shipment`→`bg-warning
  text-dark`, `shipping`→`bg-warning`, `delivered`→`bg-success`,
  `cancelled`→`bg-danger`), неизвестный → `bg-light text-dark`
- `tests/Unit/OrderStatusTest.php` — изменить: класс для каждого из 7
  статусов и для неизвестного
- `src/Models/Order.php` — изменить: `countOrdersByStatus(): array` —
  `['new' => 3, …]`, все 7 ключей, 0 для отсутствующих
- `src/Controllers/AdminController.php` — изменить: `index()` передаёт
  счётчики
- `public/assets/js/admin.js` — создать: пустой IIFE
  (`'use strict'`), точка входа для следующих тасков
- `.docs/planning-log.md` — изменить: ADR — второй layout на ассетах
  Valex, `admin.js` как второй файл своего JS

**Definition of Done:**
- [x] `manager` и `admin` видят layout с сайдбаром и счётчиками;
      `customer` по `/admin` → редирект на `/`; Гость → `/login`
- [x] Счётчики совпадают с `SELECT status, COUNT(*) FROM orders GROUP
      BY status` (ручная сверка), статусы без Заказов показывают 0
- [x] Сайдбар сворачивается/раскрывается на 320px, контент читаем —
      баг с переносом текста шапки на 320–576px найден пользователем и
      исправлен, см. `TASK.md`/`dev-log.md` 18.09.2026
- [x] В отданном HTML нет ни одного `http(s)://`-адреса; вкладку
      Network браузера не открывал — тот же пробел, что пункт выше
- [x] «Выйти» — POST с CSRF, после выхода `/admin` недоступен
- [x] `composer test` зелёный, включая проверку `orderStatusBadgeClass()`
- [x] Проверить `.docs/dod-global.md`

---

## Таск 2 — Список Заказов с фильтрами и карточка Заказа (просмотр)

**Статус:** ✅ Завершён

**Цель таска:**
`/admin/orders` — все Заказы независимо от источника (`FR-ORD-004`,
`FR-MGR-001` правило 1) в одном списке: №, клиент (имя из `users`
либо `guest_name`), сумма, способ получения/оплаты, статус оплаты,
статус (бейдж), дата; фильтр по статусу (`FR-MGR-001` правило 2) +
поиск по № Заказа / телефону; сортировка по дате (новые сверху),
пагинация. `/admin/orders/{id}` — контакты (Покупатель: `users`, Гость:
`guest_*`), позиции со снэпшотом, `total`, `shipping_cost`, способ
получения/адрес, способ оплаты, `payment_status`/`prepaid_amount`,
комментарий Покупателя, `created_at`/`updated_at`/`delivered_at`.
Действия над Заказом — Таск 3, здесь только просмотр
(`FR-ADM-008`: у Администратора тот же экран).

**Что нужно создать/изменить:**
- `src/Models/Order.php` — изменить: `getAdminOrders(array $filters,
  int $page, int $perPage): array` (`LEFT JOIN users`, фильтр
  `status`, поиск по `id` / `guest_phone` / `users.phone` через
  `normalizePhone()`), `countAdminOrders(array $filters): int`,
  `findOrderForAdmin(int $id): ?array` (Заказ + контакт клиента —
  `customer_name`/`customer_phone`/`customer_email` из `users` либо
  `guest_*`, признак `is_guest`)
- `src/Controllers/AdminOrderController.php` — создать: `index()`
  (whitelist статуса через константы `ORDER_STATUS_*`, `page` —
  int-приведение, `buildPagination()`), `show()` (нет Заказа → 404
  через существующий обработчик, не 500)
- `src/Views/admin/orders/index.php` — создать: панель фильтра (GET-
  форма: статус, поиск), таблица, `pagination.php` (проверить, что
  компонент Фазы 1 работает вне витринного layout — иначе
  `components/admin/pagination.php`), пустое состояние
- `src/Views/admin/orders/show.php` — создать: блоки «Клиент»,
  «Получение и оплата», «Позиции», «Комментарий», «Хронология»
- `config/routes.php` — изменить: `GET /admin/orders`,
  `GET /admin/orders/{id}`

**Definition of Done:**
- [x] В списке есть и Заказ, оформленный на сайте, и созданный
      напрямую в БД с другим источником контактов (гость/Покупатель) —
      оба с текущим статусом (критерий приёмки `FR-MGR-001`)
- [x] Фильтр «Новый» показывает только `new`; неизвестное значение
      `status` в URL игнорируется, не 500
- [x] Поиск по телефону находит и Заказ Покупателя (по `users.phone`),
      и гостевой (по `guest_phone`); ввод `8 (000) …` и `+70000000000`
      дают один и тот же результат
- [x] Карточка гостевого Заказа показывает `guest_*` и пометку «гость»;
      Заказ Покупателя — данные из `users`; несуществующий id → 404
- [x] `customer` по прямому URL `/admin/orders` и `/admin/orders/{id}`
      → редирект, без утечки данных
- [x] Пагинация сохраняет фильтр/поиск в ссылках страниц; весь вывод
      через `e()`, `formatPrice()`
- [x] Проверить `.docs/dod-global.md`

---

## Таск 3 — Действия над Заказом: смена статуса, оплата, доставка, отмена

**Статус:** ✅ Завершён

**Цель таска:**
На карточке Заказа появляется панель действий: кнопки следующего
статуса — **только из `allowedOrderTransitions()`** для текущего
статуса, ветки образца и способа получения (`FR-MGR-001` правило 3,
`FR-ORD-001`); форма «Отметить предоплату» (сумма →
`validatePaymentAmount()` → `markOrderPrepaid()`, `new → confirmed`)
и кнопка «Остаток получен» (`markOrderPaidFull()`) — UI `FR-PAY-002`;
поле «Стоимость доставки» (`BR-006`, вносится вручную); форма отмены
(`FR-ORD-002`, `BR-007`): выбор ветки — стандартный / нестандартный
размер, комментарий (обязателен для нестандартного), отметка
«предоплата возвращена переводом на карту» (обязательна, если
`payment_status ≠ unpaid`). Из «Готов к отгрузке» и далее кнопки
«Отменить» нет (`canCancelOrder()`), ручной POST отклоняется.
Хуки Резерва (Фаза 5) и СМС (Фаза 7) — точки расширения, не
реализуются.

**Что нужно создать/изменить:**
- `database/install.php` — изменить: `orders.cancel_note TEXT NULL`,
  `orders.prepayment_refunded TINYINT(1) NOT NULL DEFAULT 0`
- `.docs/database.md` — изменить: две колонки с назначением
- `.docs/planning-log.md` — изменить: ADR (колонки отмены; почему не
  отдельная таблица — отмена одна на Заказ, статусной модели у неё нет)
- `src/Core/OrderActions.php` — создать: `validateCancelInput(array
  $input, string $paymentStatus): array` — ошибки по полям: `branch`
  ∈ {`standard`, `non_standard`}, `note` обязателен при
  `non_standard`, `refund_confirmed` обязателен при `paymentStatus ≠
  unpaid`; чистая, без БД
- `tests/Unit/OrderActionsTest.php` — создать
- `src/Models/Order.php` — изменить: `cancelOrder(int $orderId, string
  $note = '', bool $prepaymentRefunded = false): bool` — в той же
  транзакции пишет `cancel_note`/`prepayment_refunded` и вызывает
  `transitionOrderStatus(...'cancelled')`; `setOrderShippingCost(int
  $orderId, ?string $cost): void`
- `src/Controllers/AdminOrderController.php` — изменить: `transition()`
  (`to` из whitelist, `transitionOrderStatus()` → `false` → flash-
  ошибка), `markPrepaid()` (`validatePaymentAmount()` с
  `orders.total` и `0`), `markPaidFull()`, `setShipping()` (число ≥ 0
  или пусто → NULL), `cancel()` (`validateCancelInput()` → ошибки в
  сессию, редирект назад) — все `requireCsrf()`, POST → `redirect()`
- `src/Views/admin/orders/show.php` — изменить: панель действий —
  кнопки переходов (по одной форме на переход), форма предоплаты /
  остатка (скрыта, если `paid_full`), форма доставки, форма отмены
  (модальное окно Bootstrap через `data-bs-*`; без JS — обычная форма
  в блоке страницы), блок «Отмена» с `cancel_note` и отметкой возврата
  на отменённом Заказе
- `config/routes.php` — изменить: `POST /admin/orders/{id}/transition`,
  `/prepaid`, `/paid-full`, `/shipping`, `/cancel`

**Definition of Done:**
- [x] `install.php` дважды подряд без ошибок; `SHOW CREATE TABLE
      orders` содержит обе новые колонки (ручная сверка с `database.md`)
- [x] Заказ `new` с обычным Вариантом: кнопки «Подтверждён» и
      «Отменить»; `confirmed` с образцом — «Готов к отгрузке», без «В
      производстве»; `ready_for_shipment` самовывоз — «Доставлен/
      Собран», доставка — «В доставке»
- [x] Ручной POST `transition` с `to=shipping` на Заказе `new` →
      flash-ошибка, статус в БД не изменён;
      `grep -r "UPDATE orders SET status" src/` — по-прежнему одно
      вхождение в `transitionOrderStatus()`
- [x] Предоплата: `0`, отрицательная, больше `total`, `12.345` →
      ошибка формы; корректная → `payment_status=prepaid`,
      `prepaid_amount` записана, статус `confirmed`; повторная отправка
      → сообщение «уже отмечена», данные не изменились; «Остаток
      получен» → `paid_full`, `status` не тронут
- [x] Стоимость доставки сохраняется и показывается отдельно от
      `total`; пустое поле → `NULL`
- [x] Отмена: `non_standard` без комментария → ошибка; `prepaid` без
      отметки возврата → ошибка; с отметкой → `cancelled`,
      `prepayment_refunded=1`, `cancel_note` сохранён; на Заказе
      `shipping`/`ready_for_shipment` кнопки «Отменить» нет, ручной
      POST → отклонён, статус не изменён
- [x] `composer test` зелёный, включая `OrderActionsTest`
- [x] Проверить `.docs/dod-global.md`

---

## Таск 4 — Редактирование состава Заказа

**Статус:** ✅ Завершён

**Цель таска:**
В статусах `confirmed` / `in_production` (`FR-ORD-003`, `FR-MGR-003`)
Менеджер на карточке Заказа добавляет позицию (поиск Варианта по
названию Товара / артикулу с подсказками → выбор цвета из
`variant_images.color` → количество), меняет количество, удаляет
позицию; `orders.total` пересчитывается в той же транзакции из
`product_variants`, снэпшот полей — как в `createOrder()`. Последнюю
позицию удалить нельзя (это отмена, `FR-ORD-002` правило 6). В других
статусах блок редактирования не показывается, ручной POST отклоняется.
Границу «до раскроя ткани» система не проверяет (`FR-ORD-003`
правило 2).

**Что нужно создать/изменить:**
- `src/Core/OrderActions.php` — изменить: `canEditOrderItems(string
  $status): bool` (`confirmed`, `in_production`)
- `tests/Unit/OrderActionsTest.php` — изменить
- `src/Models/Order.php` — изменить: `addOrderItem(int $orderId, int
  $variantId, ?string $color, int $qty): bool` (активный Вариант,
  снэпшот, `clampCartQuantity()` для образца), `updateOrderItemQuantity(int
  $orderId, int $itemId, int $qty): bool`, `removeOrderItem(int
  $orderId, int $itemId): bool` (последняя позиция → `false`),
  `recalculateOrderTotal(PDO $pdo, int $orderId): void` — все три в
  одной транзакции с пересчётом, условие `order_id` в каждом `WHERE`
- `src/Models/Product.php` — изменить: `searchVariantsForAdmin(string
  $q, int $limit): array` — по `products.name` (FULLTEXT / префикс) и
  `product_variants.sku` (префикс), только активные, с ценой и
  списком цветов
- `src/Controllers/AdminOrderController.php` — изменить: `addItem()`,
  `updateItem()`, `removeItem()` — `requireCsrf()`,
  `canEditOrderItems()`, редирект на карточку с flash
- `src/Controllers/AdminVariantController.php` — создать: `search()` —
  JSON-подсказки (по образцу `SearchController::suggest()`)
- `src/Views/components/admin/variant-picker.php` — создать: поле
  поиска + скрытый `variant_id` + `color` + `quantity`; без JS — поле
  «Артикул» и кнопка «Добавить» обычным POST
- `src/Views/admin/orders/show.php` — изменить: таблица позиций с
  формами ± / удалить, блок добавления через `variant-picker`
- `public/assets/js/admin.js` — изменить: подсказки к `variant-picker`
  (`fetch`, `async/await`), подстановка цветов выбранного Варианта
- `config/routes.php` — изменить: `POST /admin/orders/{id}/items`,
  `POST /admin/orders/{id}/items/{itemId}`,
  `POST /admin/orders/{id}/items/{itemId}/remove`,
  `GET /admin/variants/search`

**Definition of Done:**
- [x] Добавление / изменение количества / удаление меняют
      `order_items` и `orders.total` атомарно; временная порча SQL
      пересчёта → откат, позиция не добавлена, `total` прежний, запись
      в `storage/logs/app.log`
- [x] В `new`, `ready_for_shipment`, `delivered`, `cancelled` блока
      нет, ручной POST → flash-ошибка без изменений
- [x] Неактивный / несуществующий Вариант нельзя добавить; образец —
      количество 1; чужой `itemId` (другого Заказа) → без изменений
- [x] Удаление последней позиции → ошибка «отмените Заказ»
- [x] Снэпшот: после добавления позиции изменение цены Варианта в БД
      не меняет `order_items.price` и `total`
- [x] Без JS: артикул + количество + «Добавить» работают обычным POST;
      `GET /admin/variants/search` недоступен `customer`
- [x] `composer test` зелёный
- [x] Проверить `.docs/dod-global.md`

---

## Таск 5 — Ручное создание Заказа по звонку/WhatsApp

**Статус:** ⏳ Ожидает

**Цель таска:**
`/admin/orders/create` (`FR-MGR-002`, закрывает `Q-DEV-006`): телефон →
«Найти» → если Покупатель есть, предлагается привязать Заказ к его
учётной записи (`user_id`), иначе — гостевые контакты (имя*, телефон*,
email); способ получения + адрес (обязателен при доставке), способ
оплаты, **сумма предоплаты (обязательна)**, комментарий, позиции через
`variant-picker` (Таск 4). Заказ создаётся через `createOrder()` и
сразу `markOrderPrepaid()` → `confirmed` / `prepaid` — появляется в
общем списке наравне с сайтовыми (`FR-ORD-004`).

**Что нужно создать/изменить:**
- `src/Core/ManualOrder.php` — создать: `normalizeManualOrderInput()`,
  `validateManualOrderInput(array $input): array` — переиспользует
  `validatePhone()`/`validateEmail()`/`validatePaymentAmount()`,
  ≥1 позиция, `user_id` либо контакты (не оба), whitelist способов
- `tests/Unit/ManualOrderTest.php` — создать
- `src/Models/User.php` — изменить: `findCustomerByPhone(string
  $phone): ?array` (только `role='customer'`, нормализованный телефон)
- `src/Controllers/AdminOrderController.php` — изменить: `create()`
  (форма, одноразовый токен против двойной отправки — как
  `checkout_token`), `store()` (`requireCsrf()`, валидация → ошибки и
  введённые значения в сессию, `createOrder()` + `markOrderPrepaid()`,
  редирект на карточку), `lookupCustomer()` — JSON по телефону
- `src/Views/admin/orders/create.php` — создать: блок клиента (телефон
  + «Найти», результат — карточка Покупателя с «Привязать» / поля
  гостя), получение/оплата, предоплата, позиции (повторяющиеся
  `variant-picker`)
- `public/assets/js/admin.js` — изменить: поиск по телефону (`fetch`),
  добавление строки позиции
- `config/routes.php` — изменить: `GET /admin/orders/create`,
  `POST /admin/orders`, `GET /admin/customers/lookup`

**Definition of Done:**
- [ ] Телефон существующего Покупателя → предложение привязать; Заказ
      создан с `user_id`, `guest_*` NULL, вторая запись в `users` не
      создана (критерий приёмки `FR-MGR-002`)
- [ ] Новый телефон → Заказ с `guest_*`, `user_id` NULL
- [ ] Созданный Заказ в БД: `status=confirmed`,
      `payment_status=prepaid`, `prepaid_amount` записана, `total` =
      Σ цен из `product_variants` × qty, снэпшот позиций (критерий
      приёмки: «сразу в „Подтверждён“, не „Новый“»)
- [ ] Без предоплаты / без позиций / доставка без адреса → форма не
      отправлена, поля подсвечены, значения сохранены
- [ ] Двойная отправка → один Заказ; сбой на `order_items` → откат, в
      списке Заказа нет
- [ ] Без JS: телефон + «Найти» обычным POST перерисовывает форму с
      результатом
- [ ] `composer test` зелёный, включая `ManualOrderTest`
- [ ] Проверить `.docs/dod-global.md`

---

## Таск 6 — Клиенты: список, поиск по телефону, карточка

**Статус:** ⏳ Ожидает

**Цель таска:**
`/admin/customers` (`FR-ADM-002`, `FR-MGR-004`) — Покупатели
(`users.role='customer'`) и Гости (уникальный `guest_phone` из
`orders`, у которых нет учётной записи с этим телефоном) в одном
списке с признаком «аккаунт / гость», количеством Заказов, поиском по
телефону (`FR-ADM-002` правило 2) и имени, пагинация. Карточка —
контакты + список Заказов клиента (по `user_id` либо `guest_phone`)
со ссылками на карточки Заказов. История переписки/звонков не строится
(`Q-016`).

**Что нужно создать/изменить:**
- `src/Models/Customer.php` — создать: `getCustomers(string $search,
  int $page, int $perPage): array` (`UNION` Покупателей и Гостей,
  `normalizePhone()` для поиска), `countCustomers(string $search): int`,
  `findCustomer(string $type, string|int $key): ?array`
  (`user:{id}` / `guest:{phone}`), `getCustomerOrders(...)`
- `src/Controllers/AdminCustomerController.php` — создать: `index()`,
  `show()`
- `src/Views/admin/customers/index.php` — создать: по мотивам
  `contacts.html`, поиск, таблица, пагинация, пустое состояние
- `src/Views/admin/customers/show.php` — создать: контакты, Заказы с
  бейджами статуса
- `src/Views/layout/admin-header.php` — изменить: активный пункт
- `config/routes.php` — изменить: `GET /admin/customers`,
  `GET /admin/customers/{type}/{key}`

**Definition of Done:**
- [ ] Поиск по телефону находит и Покупателя с аккаунтом, и гостевые
      Заказы с этим телефоном (критерий приёмки `FR-ADM-002`)
- [ ] Гость с 3 Заказами — одна строка с «3 заказа»; Покупатель, у
      которого были гостевые Заказы до регистрации, привязанные
      `linkGuestOrdersToUser()`, — одна строка «аккаунт»
- [ ] `8 (918) …` и `+7918…` дают один результат
- [ ] Карточка гостя показывает только его Заказы; чужой `key` → 404
- [ ] Список читаем на 320px; весь вывод через `e()`
- [ ] Проверить `.docs/dod-global.md`

---

## Таск 7 — Категории и список Товаров

**Статус:** ⏳ Ожидает

**Цель таска:**
`/admin/categories` — плоская таблица (название, родитель, slug,
порядок, кол-во Товаров), создание/редактирование (`name`, `slug` с
автогенерацией через `slugify()`, `parent_id` — только корневые в
списке родителей, `description`, `sort_order`), не более 2 уровней
(Model), удаление запрещено при Товарах (RESTRICT → дружелюбная ошибка).
`/admin/products` — колонки по `admin-assembly.md`: фото, название,
основная категория, кол-во Вариантов, диапазон цен, бейджи «скрыт» /
«образец»; фильтр по категории и статусу, поиск по названию/артикулу;
«Скрыть / Показать» (`products.is_active`, физически не удаляется).
Форма Товара — Таск 8.

**Что нужно создать/изменить:**
- `src/Core/Slug.php` — создать: `slugify(string $text): string` —
  транслитерация кириллицы, `[a-z0-9-]`, без повторных дефисов
- `tests/Unit/SlugTest.php` — создать
- `src/Models/Category.php` — изменить: `getCategoriesFlat(): array`
  (с именем родителя и `COUNT` Товаров), `findCategoryById()`,
  `createCategory(array $data): ?int`, `updateCategory(int $id, array
  $data): bool`, `deleteCategory(int $id): bool` (перехват
  SQLSTATE 23000 → `false`), проверка глубины ≤ 2 и уникальности `slug`
- `src/Models/Product.php` — изменить: `getAdminProducts(array
  $filters, int $page, int $perPage): array` (мин/макс цена,
  `COUNT` Вариантов, главное фото, основная категория, флаг образца),
  `countAdminProducts()`, `setProductActive(int $id, bool $active)`
- `src/Controllers/AdminCategoryController.php` — создать: `index`,
  `create`, `store`, `edit`, `update`, `delete`
- `src/Controllers/AdminProductController.php` — создать: `index()`,
  `toggle()`
- `src/Views/admin/categories/index.php`, `form.php` — создать
- `src/Views/admin/products/index.php` — создать
- `config/routes.php` — изменить: маршруты категорий и списка Товаров

**Definition of Done:**
- [ ] `slugify('Диван «Модерн» 2-х местный')` → `divan-modern-2-h-mestnyj`
      (или эквивалент по принятой таблице транслитерации — зафиксировать
      в тесте); пустой результат → ошибка формы
- [ ] Родитель второго уровня недоступен в выборе; ручной POST с
      `parent_id` подкатегории → ошибка, строка не создана
- [ ] Дубликат `slug` → ошибка поля; категория с Товарами не
      удаляется, показано сообщение; пустая — удаляется
- [ ] Скрытый Товар пропадает из `/catalog` и поиска, остаётся в
      `/admin/products` с бейджем «скрыт»; `order_items` целы
- [ ] Фильтр по категории учитывает `product_categories` (Товар в двух
      категориях виден в обеих); диапазон цен — из активных Вариантов
- [ ] `composer test` зелёный, включая `SlugTest`
- [ ] Проверить `.docs/dod-global.md`

---

## Таск 8 — Форма Товара: Варианты, характеристики, категории

**Статус:** ⏳ Ожидает

**Цель таска:**
`/admin/products/create` и `/admin/products/{id}/edit` (`FR-ADM-001`):
`name`, `slug` (автогенерация), `description`, `is_featured`,
категории (мультивыбор чекбоксами + радио «основная» → `is_primary`,
ровно одна), характеристики (повторяющиеся строки «название —
значение», `product_specs`), Варианты (повторяющийся блок: `sku`,
`material`, `mechanism_type`, `price`, `production_time`,
`is_showroom_sample`, `discount_percent`, `is_active`). **Публикация
(`is_active=1`) невозможна без ≥1 активного Варианта с ценой** —
критерий приёмки ТЗ. Варианты не удаляются — деактивируются. Фото —
Таск 9.

**Что нужно создать/изменить:**
- `src/Core/ProductForm.php` — создать: `normalizeProductInput(array
  $input): array`, `validateProductInput(array $input): array` —
  ошибки по полям и по индексу Варианта: `price` формат
  `\d+(\.\d{1,2})?` > 0, `discount_percent` пусто или 0–99.99, `sku`
  непустой и уникален внутри формы, ≥1 категория и ровно одна
  основная, публикация только с активным Вариантом
- `tests/Unit/ProductFormTest.php` — создать
- `src/Models/Product.php` — изменить: `findProductForAdmin(int $id):
  ?array` (Товар + Варианты + характеристики + категории),
  `createProductWithVariants(array $product, array $variants, array
  $specs, array $categoryIds, int $primaryId): ?int`,
  `updateProductWithVariants(...)` — одна транзакция: `products`,
  `product_categories` (пересборка), `product_specs` (пересборка),
  `product_variants` (`INSERT` новых / `UPDATE` существующих по `id`,
  принадлежащих этому Товару; отсутствующие в форме →
  `is_active=0`, не `DELETE`); дубль `sku` в БД (SQLSTATE 23000) →
  откат + ошибка поля
- `src/Controllers/AdminProductController.php` — изменить: `create()`,
  `store()`, `edit()`, `update()` — `requireCsrf()`, ошибки и
  введённые значения в сессию, редирект
- `src/Views/admin/products/form.php` — создать: по мотивам
  `mail-compose.html`; блоки Товар / Категории / Характеристики /
  Варианты
- `src/Views/components/admin/variant-row.php` — создать: один блок
  Варианта (используется для существующих и как шаблон для нового)
- `public/assets/js/admin.js` — изменить: добавить/убрать строку
  характеристики и блок Варианта (клонирование `<template>`), «основная»
  доступна только среди отмеченных категорий
- `config/routes.php` — изменить: `GET /admin/products/create`,
  `POST /admin/products`, `GET /admin/products/{id}/edit`,
  `POST /admin/products/{id}`

**Definition of Done:**
- [ ] Товар без Варианта с `is_active=1` → ошибка «добавьте Вариант с
      ценой», не сохранён; тот же Товар со снятой публикацией →
      сохранён и не виден в `/catalog` (критерий приёмки `FR-ADM-001`)
- [ ] Изменена цена Варианта → `/product/{slug}` показывает новую
      (критерий приёмки `FR-ADM-001`)
- [ ] `sku`, уже занятый другим Товаром → ошибка поля, транзакция
      откатана (ни `products`, ни `product_specs` не изменены)
- [ ] `discount_percent` = `150` → ошибка; `= 15` → сохранён (на
      витрине пока не отображается — Фаза 6); `is_showroom_sample`
      включён → карточка показывает «Выставочный образец»
- [ ] Вариант, убранный из формы, → `is_active=0`, строка и
      `order_items` на неё целы; `variant_id` чужого Товара в POST →
      отклонён
- [ ] Две категории, основная — вторая → `is_primary` ровно у одной;
      без основной → ошибка
- [ ] Без JS форма с одним блоком Варианта и одной строкой
      характеристики работает
- [ ] `composer test` зелёный, включая `ProductFormTest`; деньги нигде
      как `float`
- [ ] Проверить `.docs/dod-global.md`

---

## Таск 9 — Фото Вариантов: загрузка, цвет, образец ткани, главное

**Статус:** ⏳ Ожидает

**Цель таска:**
На форме редактирования Товара у каждого Варианта — блок фото
(`FR-ADM-001` правило 1, `variant_images`): загрузка (jpg/png/webp,
≤ `UPLOAD_MAX_BYTES`, тип по `finfo`, не по расширению; имя файла —
случайное), `color`, `is_swatch` (образец ткани), «главное» (ровно одно
на Вариант), `sort_order`, удаление (файл + строка). Файлы — в
`public/uploads/products/` (PHP там не выполняется — `.htaccess` уже
есть). Загрузка доступна только у сохранённого Товара (нужен
`variant_id`).

**Что нужно создать/изменить:**
- `config/config.php` — изменить: `UPLOAD_MAX_BYTES`,
  `UPLOAD_ALLOWED_MIME`, `UPLOAD_PRODUCTS_DIR`
- `src/Core/Upload.php` — создать: `validateUploadedImage(array $file,
  string $detectedMime): ?string` — чистая часть (код ошибки PHP,
  размер, MIME из whitelist) под unit-тест; `uploadExtensionForMime()`
- `tests/Unit/UploadTest.php` — создать
- `src/Services/FileUpload.php` — создать: `storeProductImage(array
  $file): ?string` (`finfo`, `random_bytes` → имя,
  `move_uploaded_file`, возвращает относительный путь),
  `deleteStoredFile(string $path): void` (только внутри
  `UPLOAD_PRODUCTS_DIR` — защита от `..`)
- `src/Models/Product.php` — изменить: `addVariantImage(int $variantId,
  array $data): ?int`, `updateVariantImage(int $variantId, int
  $imageId, array $data): bool`, `deleteVariantImage(int $variantId,
  int $imageId): ?string` (возвращает путь для удаления файла),
  `setMainVariantImage(int $variantId, int $imageId): void` (снять
  `is_main` с остальных в транзакции)
- `src/Controllers/AdminProductController.php` — изменить:
  `uploadImage()`, `updateImage()`, `deleteImage()`, `setMainImage()` —
  `requireCsrf()`, Вариант принадлежит Товару из URL
- `src/Views/admin/products/form.php` — изменить: блок фото в
  `variant-row.php` (миниатюры, цвет, образец, главное, удалить,
  форма загрузки `multipart/form-data`)
- `config/routes.php` — изменить: маршруты фото

**Definition of Done:**
- [ ] `.php`, переименованный в `.jpg`, отклонён по `finfo`; файл
      больше лимита → дружелюбная ошибка, не 500; `UPLOAD_ERR_*` ≠ OK
      → ошибка с записью в лог
- [ ] Загруженное фото с `color` видно на витрине: мини-карточка
      (главное фото) и галерея карточки при выборе этого цвета
- [ ] Главное фото ровно одно на Вариант после любого переключения
- [ ] Удаление стирает файл с диска и строку; путь с `..` в
      подделанном запросе не выходит за `UPLOAD_PRODUCTS_DIR`
- [ ] `imageId` чужого Варианта / Вариант чужого Товара → без изменений
- [ ] `composer test` зелёный, включая `UploadTest`
- [ ] Проверить `.docs/dod-global.md`

---

## Таск 10 — Отчёт по продажам

**Статус:** ⏳ Ожидает

**Цель таска:**
`/admin/reports` (`FR-ADM-005`): выбор периода — сегодня / неделя /
месяц / произвольный диапазон дат; количество и сумма Заказов за
период (без `cancelled`), таблица по дням и столбчатая диаграмма
Chart.js (self-hosted). Сверх двух метрик — ничего (`Q-017`).

**Что нужно создать/изменить:**
- `src/Core/Report.php` — создать: `resolveReportPeriod(string
  $preset, ?string $from, ?string $to, DateTimeImmutable $now):
  array` — границы `[from, to]` включительно, валидация дат,
  `from ≤ to`, ограничение диапазона (например, 366 дней); чистая
- `tests/Unit/ReportTest.php` — создать
- `src/Models/Report.php` — создать: `getSalesSummary(string $from,
  string $to): array` (`COUNT`, `SUM(total)` как строка),
  `getSalesByDay(string $from, string $to): array`
- `src/Controllers/AdminReportController.php` — создать: `index()`
- `src/Views/admin/reports/index.php` — создать: форма периода (GET),
  две карточки-метрики, таблица по дням, `<canvas>` с данными в
  `data-*`-атрибутах (JSON через `e()`), пустое состояние
- `public/assets/admin/libs/chart.js/` — создать: копия из
  `00-input/admin/assets/libs/chart.js/` (только `chart.umd.js`)
- `public/assets/js/admin.js` — изменить: инициализация диаграммы из
  `data-*`
- `config/routes.php` — изменить: `GET /admin/reports`

**Definition of Done:**
- [ ] Сумма и количество за период совпадают с `SELECT COUNT(*),
      SUM(total) FROM orders WHERE status <> 'cancelled' AND created_at
      BETWEEN …` (ручная сверка); отменённые не входят
- [ ] Пресеты «сегодня/неделя/месяц» дают ожидаемые границы
      (`ReportTest` с фиксированным `$now`); `from > to` и мусор в датах
      → ошибка формы, не 500
- [ ] Пустой период → пустое состояние, диаграмма не падает
- [ ] Суммы приходят из Model строками, `formatPrice()` только во View;
      диаграмма грузится из `public/assets/admin/`, без CDN
- [ ] `composer test` зелёный, включая `ReportTest`
- [ ] Проверить `.docs/dod-global.md`

---

## Закрытие фазы

- [ ] `.docs/phases/_status.md` — Фаза 4 → ✅ Завершена; строка
      Фазы 5 уточнена: форма отмены (обе ветки, отметка возврата
      предоплаты) сделана в Фазе 4, в Фазе 5 — только STOCK-эффекты
      отмены (снятие Резерва, пометка изготовленного Варианта образцом)
- [ ] `.docs/tz-coverage.md` — `FR-MGR-001…004`, `FR-ADM-001, 002,
      005, 008`, `FR-ORD-002` (UI, обе ветки), `FR-ORD-003`,
      `FR-ORD-004`, `FR-PAY-002` (UI) — реализовано; отклонение
      `FR-ADM-001` правило 3 (свободные характеристики вместо набора
      по Категории); `FR-DISC-001` — поле сохраняется с Фазы 4,
      отображение — Фаза 6; `Q-DEV-006` закрыт (Таск 5)
- [ ] `.docs/admin-assembly.md` — `Q-DEV-006` помечен закрытым
- [ ] `.docs/dev-log.md` — запись сессии
