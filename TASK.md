# Current Task

## Фаза
Phase 2 — Корзина и оформление заказа (`.docs/phases/phase-2.md`, Таск 1)

**Статус:** ✅ Завершён — код реализован и проверен против реальной БД
(`mikhail700.beget.tech`): `install.php` дважды подряд без ошибок,
`SHOW CREATE TABLE cart_items` совпадает со схемой; изоляция владельца
(исключение при пустом/двойном ключе, чужая строка недоступна на
update/remove), `addCartItem()` (несуществующий Вариант, слияние
Вариант+цвет, clamp образца), `mergeGuestCart()` (суммирование,
переезд/удаление гостевых строк, идемпотентность повторного вызова) —
подтверждены временным скриптом в scratchpad (не в репозитории);
`composer test` 84/84, включая 14 тестов `CartTest` (детали ниже)

## Задача
`cart_items` получает `price_snapshot`; появляются чистые расчётные
функции корзины (`Core/Cart.php`) и Model корзины, работающая с
владельцем «пользователь либо cart-token» — без UI, проверка тестами и
прямым вызовом функций.

## Scope — что трогаем

- [x] `database/install.php` — изменено: `cart_items.price_snapshot
      DECIMAL(10,2) NOT NULL` (идемпотентная проверка через
      `information_schema`, как `categories.description`)
- [x] `.docs/database.md` — изменено: колонка `price_snapshot` (с
      оговоркой «только для уведомления `FR-CHK-003`, не для суммы»);
      примечание к `session_id` — теперь хранит cookie `cart_token`,
      не PHP session id
- [x] `.docs/planning-log.md` — изменено: `ADR-033` (`price_snapshot`),
      `ADR-034` (cookie `cart_token` в `cart_items.session_id`)
- [x] `config/config.php` — изменено: константа `CART_COOKIE_DAYS = 30`
      (`CART_MAX_QUANTITY` — см. «Отклонения от плана»)
- [x] `src/Core/Cart.php` — создан: `clampCartQuantity(int $qty, bool
      $isShowroomSample): int` (1..`CART_MAX_QUANTITY`, образец — ровно
      1), `calculateCartTotals(array $items): array` (сумма по строкам и
      итог через bcmath, без float; недоступные позиции исключаются из
      суммы, но остаются в списке), `findPriceChanges(array $items):
      array` (позиции, где текущая цена ≠ `price_snapshot`, через
      `bccomp`)
- [x] `tests/Unit/CartTest.php` — создан: 14 тестов (clamp, суммы без
      float, недоступные позиции, изменение цен)
- [x] `src/Models/Cart.php` — создан: `validateCartOwner()` (общий
      helper, бросает исключение при пустом/двойном ключе владельца),
      `getCartItems(array $owner): array` (текущая цена из
      `product_variants`, `price_snapshot`, `is_showroom_sample`,
      `is_reserved` = `EXISTS reserves WHERE status='active'`, название
      Товара, slug, фото по цвету с фолбэком на главное фото Варианта),
      `addCartItem(array $owner, int $variantId, ?string $color, int
      $qty): bool` (тот же Вариант+цвет — увеличивает количество с
      clamp), `updateCartItemQuantity(array $owner, int $itemId, int
      $qty): bool`, `removeCartItem(array $owner, int $itemId): bool`,
      `countCartItems(array $owner): int`, `clearCart(array $owner):
      void`, `mergeGuestCart(string $token, int $userId): void` (в
      транзакции) — все запросы с условием владельца в `WHERE`
- [x] `src/Core/functions.php` — изменено: `cartToken(): string`
      (чтение/выдача cookie `cart_token`, 64 hex, httponly/samesite=Lax/
      secure в проде), `cartOwner(): array` — `['user_id' => …]` для
      авторизованного, иначе `['session_id' => cartToken()]`
- [x] `tests/bootstrap.php` — изменено: подключён `src/Core/Cart.php`
      (по образцу остальных Core-файлов, нужных unit-тестам)

Отклонения от изначального Scope, обнаруженные в процессе реализации
(причина — ниже):
- [x] `CART_MAX_QUANTITY` определена в `src/Core/Cart.php`, а не в
      `config/config.php`, как было в изначальном плане

## Отклонения от плана

1. **`CART_MAX_QUANTITY` определена в `src/Core/Cart.php`, а не в
   `config/config.php`.** Причина: `tests/bootstrap.php` подключает
   только конкретные Core-файлы, не весь `config/config.php` (он
   требует `.env` и не идемпотентен по `define('ROOT_PATH', ...)` при
   повторном подключении) — тот же принцип, по которому
   `SEARCH_QUERY_MAX_LENGTH`/`SEARCH_BOOLEAN_OPERATORS` уже лежат в
   самом `CatalogFilters.php`, а не в `config.php`, хотя `SEARCH_SUGGEST_LIMIT`
   лежит в `config.php`: константа, нужная внутри тестируемой чистой
   функции, живёт рядом с этой функцией. `CART_COOKIE_DAYS`, нужная
   только `cartToken()` (не покрыта unit-тестами, использует cookie),
   осталась в `config.php` как и планировалось.

## Out of scope — не трогаем

- `CartController`, страница `/cart`, счётчик корзины в шапке, форма
  `POST /cart/add` — Таск 2
- Экран оформления, `Checkout*` — Таск 3
- Создание заказа, `Models/Order.php` — Таск 4
- Статусная модель заказа, `OrderStatus.php` — Таск 5
- `AuthController` (вызов `mergeGuestCart()` при входе/регистрации) —
  Таск 2, здесь только сама функция в Model
- Любая вёрстка/JS/CSS
- `FR-CART-004` (перенос в избранное), реальная оплата картой,
  интерфейс отмены заказа — перенесены в другие фазы (`phase-2.md`)

## Definition of Done

- [x] `install.php` выполняется дважды подряд без ошибок; `SHOW CREATE
      TABLE cart_items` совпадает с `database.md` по колонкам, типам,
      индексам (ручная сверка против реальной БД
      `mikhail700.beget.tech`)
- [x] Ровно одно из `session_id` / `user_id` заполнено — Model
      отклоняет владельца без ключа или с двумя ключами (исключение),
      не пишет строку — подтверждено вызовом `validateCartOwner()` и
      `addCartItem([], ...)` против реальной БД
- [x] `addCartItem()` с неактивным / несуществующим Вариантом → `false`,
      строка не создана; повторное добавление того же Варианта+цвета
      → одна строка с увеличенным количеством — подтверждено (variant
      999999 → `false`, 0 строк; variant 17 дважды → одна строка,
      2+3=5)
- [x] `mergeGuestCart()`: одинаковый Вариант+цвет суммируется, образец
      остаётся 1, гостевые строки удалены, повторный вызов ничего не
      ломает — подтверждено (variant 17: 1+5=6, образец variant 4
      остался 1, гостевая корзина пуста после переноса, повторный
      вызов — без изменений)
- [x] `composer test` зелёный (84/84), включая `CartTest`: clamp (0,
      отриц., >max, образец), суммы без float (`"1999.99" × 3` =
      `"5999.97"`), изменение цен (включая разницу в форматировании
      `"1999.9"` vs `"1999.90"` — не считается изменением через
      `bccomp`)
- [x] Проверить `.docs/dod-global.md` — новых записей в
      `storage/logs/app.log` за время проверки не появилось; изоляция
      владельца дополнительно проверена на чужой строке (`update`/
      `remove` от другого `user_id` → `false`, строка не изменена)

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
