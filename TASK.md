# Current Task

## Фаза
Phase 6 — Скидки, отзывы и главная страница
(`.docs/phases/phase-6.md`), Таск 1 из 6.

**Статус:** ✅ Завершён 20.09.2026. Проверено на реальной БД
(`mikhail700.beget.tech`, временная скидка на реальном Варианте,
данные очищены после проверки) и `composer test` (271/271, было
257/257). Подробности — `.docs/dev-log.md` 20.09.2026.

## Задача
Ввести единый источник скидочной цены (одна PHP-функция на bcmath +
одно эквивалентное SQL-выражение) и завести его во все Model-функции,
которые читают цену Варианта — каталог, поиск, похожие товары,
корзина, Заказ. Сортировка и фильтр по цене в каталоге — по скидочной.
Внешне (UI) ничего не меняется — зачёркнутая старая цена появится в
Таске 2; здесь только цифры, которые видит/считает система.

## Scope — что трогаем

- [x] `src/Core/Price.php` — создать: `discountedPrice(string $price,
      ?string $percent): string` (bcmath, округление до копеек),
      `hasDiscount(?string $percent): bool` (`NULL`/`0` → нет скидки),
      `discountedPriceSql(string $alias): string` — SQL-выражение
      `ROUND(alias.price * (1 - IFNULL(alias.discount_percent, 0) /
      100), 2)` — чистые функции без обращения к БД
- [x] `tests/Unit/PriceTest.php` — создать: `40000.00`/`15` →
      `34000.00`; `NULL` и `0` → цена без изменений; `99.99`;
      округление копеек на границе (например `1999.99`/`33`)
- [x] `tests/bootstrap.php` — изменить: добавить `require_once
      ROOT_PATH . '/src/Core/Price.php'`
- [x] `src/Models/Product.php` — изменить: `getCatalogProducts()`,
      `countCatalogProducts()`, `buildCatalogFilterConditions()`
      (условие `price_min`/`price_max` — по скидочной),
      `getFilterOptions()` (границы слайдера цены), `searchProducts()`,
      `suggestProducts()`, `getRelatedProducts()`,
      `attachCheapestVariant()` (самый дешёвый Вариант — по скидочной
      цене), `getProductVariants()`, `searchVariantsForAdmin()` —
      возвращают `price` как скидочную, плюс `old_price` (сырая) и
      `discount_percent`; `min_price` каталога — минимум скидочных,
      плюс `min_old_price`
- [x] `src/Models/Cart.php` — изменить: `getCartItems()` (`price` =
      скидочная, `old_price`, `discount_percent` в выборке),
      `addCartItem()` — снэпшот (`price_snapshot`) пишется по
      скидочной цене. **Дополнительно (отклонение от плана):**
      `acceptCartPriceChanges()` — синхронизация снэпшота на
      скидочную, не была в исходном списке (см. ниже)
- [x] `src/Models/Order.php` — изменить: `createOrder()`,
      `addOrderItem()` — снэпшот `order_items.price` по скидочной
      цене. `recalculateOrderTotal()` правки не потребовалось — она
      суммирует уже снэпшотнутую `order_items.price`, ничего не
      перечитывает из `product_variants`
- [x] `.docs/planning-log.md` — изменить: `ADR-041` — единое SQL/PHP-
      выражение скидочной цены, `0` трактуется как «скидки нет», скидка
      как изменение цены закрывает `FR-CHK-003` автоматически
- [x] `.docs/dev-log.md` — изменить: запись по тасκу

## Out of scope — не трогаем

- UI старой/новой цены, бейдж «-N%» на мини-карточке/карточке/корзине/
  чекауте — Таск 2 этой же фазы
- Фильтр каталога «Со скидкой» (`on_sale`) — Таск 3
- Отзывы о Товаре, модерация, блоки Главной — Таски 4–6
- Изменение схемы БД / `database/install.php` — `discount_percent`
  заведена с Фазы 0, колонки не меняются
- Форма Варианта в админке (`AdminProductController`, `ProductForm.php`,
  `Views/admin/products/form.php`) — уже сохраняет `discount_percent`
  с Фазы 4, не трогаем
- `getAllProductVariants()` (админ-список Вариантов Товара,
  используется формой редактирования) — должна показывать сырую цену
  для редактирования, не скидочную; не меняем
- Любой рефакторинг `Product.php`/`Cart.php`/`Order.php` за пределами
  перечисленных функций

## Definition of Done

- [x] `PriceTest`: `40000.00`/`15` → `34000.00`; `NULL` и `0` → цена
      без изменений; округление копеек совпадает с `SELECT ROUND(...)`
      в MySQL — ручная сверка одной выборкой (4 кейса, включая
      `99.99`/`50%` — граница ровно `.5` копейки)
- [x] Вариант 40 000 ₽ (реально проверено на `SOFA-MILAN-FABRIC`,
      35 000 ₽ со скидкой 15% → 29 750 ₽) со скидкой 15%: каталог
      сортирует по цене и фильтрует по диапазону как скидочную; карточка
      Товара показывает эффективную цену Варианта в данных; корзина —
      строка и `total` по скидочной цене — проверено на реальной БД
- [x] Оформленный Заказ: `order_items.price` и `orders.total` посчитаны
      по скидочной цене — проверено `createOrder()` и `addOrderItem()`
      на реальной БД
- [x] Скидка снята после оформления Заказа → `order_items.price` и
      `orders.total` не изменились (снэпшот, критерий `FR-DISC-002`) —
      проверено на реальной БД
- [x] Изменение скидки у Варианта, уже лежащего в корзине → снэпшот
      корзины (`price_snapshot`) отличается от текущей цены,
      `acceptCartPriceChanges()` подтягивает снэпшот к новой скидочной
      — существующий механизм `findPriceChanges()` в `Core/Cart.php` не
      менялся, работает на новых полях без правок
- [x] `grep -rn "pv.price" src/` — сырая цена читается только в
      `getAllProductVariants()` (админ-форма), `attachAdminProductPhoto()`
      (выбор фото для списка Товаров — не про деньги) и внутри/рядом с
      `discountedPriceSql()`
- [x] `composer test` зелёный (271/271, было 257/257), включая
      `PriceTest`
- [x] Проверить `.docs/dod-global.md`

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
- На каждый шаг — чем проверяется (пункт DoD / unit-тест / ручная
  проверка на реальной БД), не только что сделать
