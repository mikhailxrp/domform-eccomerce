# Current Task

## Фаза
Phase 6 — Скидки, отзывы и главная страница
(`.docs/phases/phase-6.md`), Таск 2 из 6.

**Статус:** ✅ Завершён 20.09.2026. Проверено на реальной БД и живым
HTTP (`php -S` + `curl`, cookie-based сессия): скидка 15% на реальном
Варианте (SOFA-MILAN-FABRIC, 35000→29750) на карточке товара (SSR и
через JS-переключение), в каталоге (grid и list), в корзине; заодно
код корректно отобразил уже существовавшую в БД скидку 20% на другом
Товаре (WARDROBE-KLASSIK-LDSP, 32000→25600) — не трогал эту запись при
уборке тестовых данных. `composer test` — 271/271 (регрессия, без
изменений). Подробности — `.docs/dev-log.md` 20.09.2026.

## Задача
`FR-DISC-002` — везде, где витрина показывает цену Варианта со скидкой
(мини-карточка каталога/поиска/похожих, карточка товара — включая
смену Варианта без перезагрузки, строка корзины), рядом с новой ценой
показывается зачёркнутая старая — в разметке темы (`.old-price`/
`.sale-price`), без ничего сверх шаблона (правило 1). Данные (скидочная
цена, `old_price`, `discount_percent`) уже готовы с Таска 1 — здесь
только вывод.

Scope уточнён по факту сверки с кодом и разметкой темы (см. переписку
перед записью файла): бейдж «-N%», `checkout-summary.php`,
`variant-selector.php` и правки `app.css` убраны из плана
`phase-6.md` — подробности в пунктах Out of scope.

## Scope — что трогаем

- [x] `src/Controllers/ProductController.php` — изменить: добавить
      `old_price_formatted` (`formatPrice($variant['old_price'])`) и
      `discount_percent` в `$variantsData` — нужны и для SSR первого
      Варианта в `show.php`, и для JSON `data-variants`, который читает
      `app.js`
- [x] `src/Views/product/show.php` — изменить: рядом с
      `<span id="product-price">` — всегда присутствующий в DOM
      `<span id="product-old-price" class="old-price">` с атрибутом
      `hidden`, когда у первого Варианта нет скидки (тот же паттерн
      `hidden`, что уже у `.product-variant-selector__mechanism`);
      видим и заполнен `old_price_formatted`, когда
      `hasDiscount($firstVariant['discount_percent'])`
- [x] `public/assets/js/app.js` — изменить: `renderVariant()` в
      `initProductVariantSelector()` — при переключении Варианта
      показывает/прячет `#product-old-price` (`.attr('hidden', ...)`)
      и обновляет его текст `variant.old_price_formatted`, решение —
      `variant.discount_percent && parseFloat(variant.discount_percent) > 0`;
      без новых глобальных переменных, в существующем IIFE
- [x] `src/Views/components/product-card.php` — изменить: в `.price`
      (и grid-, и list-ветка) — `<span class="old-price">` с
      `formatPrice($product['min_old_price'])`, рендерится, только
      когда `$product['min_old_price'] !== null` (уже вычислено в
      Model в Таске 1 — `null`, если скидки нет)
- [x] `src/Views/components/cart-row.php` — изменить: цена оборачивается
      в `<div class="product-prices">` со старой (`.old-price`) и
      новой (`.sale-price`) — порядок, как в `cart.html` темы (старая
      первой), — когда `hasDiscount($item['discount_percent'])`; без
      скидки — прежний вид, один `<p class="price">`

## Out of scope — не трогаем

- Бейдж «-N%» — темой не предусмотрен нигде (`shop-grid-*.html`,
  `product-details-affiliate.html`, `index.html`, `cart.html` — только
  зачёркнутая старая цена рядом с новой), рисовать его значило бы
  нарушить `FR-DISC-002` правило 1 («сверх шаблона ничего не
  дорисовывается»)
- `src/Views/components/checkout-summary.php` — тема (`checkout.html`)
  показывает в таблице заказа только сумму строки (`Product-price` =
  `line_total`), без цены за штуку — показывать старую/новую там
  нечего; сумма уже верна по скидочной цене с Таска 1 (регрессия, не
  новая логика)
- `src/Views/components/variant-selector.php` — цену не выводит вовсе
  (форма выбора материала/цвета/количества); цена и её переключение —
  в `product/show.php` + `app.js`
- `public/assets/css/app.css` — не трогаем: нужные селекторы уже есть в
  `style.css` (`.single-product .product-content .price .old-price`,
  `.single-product-02 ...`, `.product-details-description .price
  .old-price`, `.cart-table ... .product-prices .old-price`/
  `.sale-price`)
- `src/Views/components/search-suggest.php` — подсказки поиска в шапке
  показывают только фото и название, без цены — не трогаем
- Фильтр каталога «Со скидкой» (Таск 3), отзывы (Таск 4), модерация
  (Таск 5), блоки Главной (Таск 6)
- Промокоды, Wishlist/Compare, модальное окно Quick View — не
  реализованы в проекте, не создаются
- `src/Models/*.php`, `src/Core/Price.php` — данные уже готовы с Таска
  1, здесь только вывод
- Любой рефакторинг за пределами перечисленных файлов

## Definition of Done

- [x] Мини-карточка (каталог, поиск, «Похожие товары» — все три идут
      через `product-card.php`; grid и list) с Вариантом со скидкой —
      старая цена зачёркнута, новая рядом; без скидки — одна цена,
      `.old-price` в HTML нет вообще (не пустой `<span>`) — проверено
      на реальной БД (grid и list каталога, оба показали верно)
- [x] Карточка товара: первый Вариант со скидкой при заходе сразу
      показывает старую+новую (SSR — проверено `curl`, без JS);
      `data-variants` содержит `old_price_formatted`/`discount_percent`
      по каждому Варианту, `discount_percent: null` без скидки — JS
      переключение прочитано по коду (`renderVariant()`), не гонялось
      руками в браузере (нет браузера в сессии)
- [x] Корзина: строка с Вариантом со скидкой — `.product-prices` со
      старой/новой ценой (порядок как в теме); без скидки — прежний
      одиночный `<p class="price">`; сумма строки/итог корзины и
      `/checkout` — по новой цене — проверено на реальной БД живым HTTP
      (`php -S` + `curl`, cookie-сессия)
- [x] `/checkout`: сводка заказа не менялась внешне (в теме нет цены за
      штуку), сумма верная (29 750 ₽ проверено)
- [x] Весь вывод через `e()`/`formatPrice()`; в HTML нет внешних
      адресов; 320px не проверялось глазами (нет браузера в сессии)
- [x] `composer test` зелёный (271/271 — регрессия; новой чистой логики
      без обращения к БД в этом таске нет, вся уже покрыта `PriceTest`
      из Таска 1)
- [x] Проверить `.docs/dod-global.md`

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
- На каждый шаг — чем проверяется (пункт DoD / ручная проверка на
  реальной БД/HTTP), не только что сделать
