# Current Task

## Фаза
Phase 1 — Каталог и карточка товара (`.docs/phases/phase-1.md`, Таск 5)

**Статус:** ✅ Завершён — код реализован и проверен против реальной БД
(`mikhail700.beget.tech`): FULLTEXT-поиск (≥3 симв.), префиксный `LIKE`
(2 симв., материал, цвет), подсказки, пустое состояние, сортировка,
сохранение `q` в URL, rate-limit — подтверждены `curl`; `composer test`
70/70 (детали — `dev-log.md`, 17.09.2026)

## Задача
Поле поиска в шапке ведёт на `GET /search?q=` (результаты — в сетке
каталога, тот же `product-card.php`); подсказки с 2 символов, до 5
позиций (фото+название) через `GET /search/suggest` с debounce и
rate-limit.

## Scope — что трогаем

- [x] `src/Models/Product.php` — изменено: `searchProducts()`,
      `countSearchProducts()`, `suggestProducts()`, плюс общий
      `buildSearchConditions()`/`bindSearchParams()`/`escapeLikeValue()`
      (по образцу `getCatalogProducts`/`countCatalogProducts`):
      `MATCH...AGAINST` для `mb_strlen($q) >= 3` по `products(name,
      description)`, `LIKE 'q%'` по `products.name` для 2 символов, плюс
      `LIKE 'q%'` по `product_variants.material`/`variant_images.color`
      в обоих случаях; `suggestProducts()` переиспользует
      `attachCheapestVariant()`
- [x] `src/Core/CatalogFilters.php` — изменено: добавлены
      `normalizeSearchQuery(string $q): string` (trim, схлопывание
      пробелов, обрезка длины — для отображения и как основа LIKE),
      `buildFulltextTerm(string $q): string` (вырезает операторы BOOLEAN
      MODE `+ - * " ( ) < > ~ @` из каждого слова, добавляет `*`)
- [x] `tests/Unit/CatalogFiltersTest.php` — изменено: 7 новых тестов на
      `normalizeSearchQuery`/`buildFulltextTerm` (пробелы, длина, пустая
      строка, спецсимволы, запрос из одних операторов)
- [x] `src/Controllers/SearchController.php` — создан: `index()`,
      `suggest()` (JSON, `tooManyAttempts('suggest', 20, 10)` +
      `hitRateLimit('suggest')`)
- [x] `src/Views/layout/header.php` — изменено: обе формы поиска
      (desktop-дропдаун и мобильная) → `method="get" action="/search"`,
      `name="q"`, значение сохраняется из `$_GET['q']`
      (`normalizeSearchQuery()`); добавлен `require_once
      CatalogFilters.php`
- [x] `src/Views/components/search-suggest.php` — создан: пустой
      контейнер подсказок (`.search-suggest[hidden]`), заполняется JS
- [x] `public/assets/js/app.js` — изменено: `initSearchSuggest()`
      (debounce 250мс, рендер подсказок, ↑↓/Enter/Esc, закрытие по клику
      вне — делегирование на `.header-search__input`/`.search-suggest`),
      `initSearchSortSubmit()` — в существующем IIFE, без новых
      глобальных переменных
- [x] `public/assets/css/app.css` — изменено: стили `.search-suggest`/
      `.search-suggest__item`
- [x] `config/routes.php` — изменено: `GET /search`, `GET /search/suggest`
- [x] `config/config.php` — изменено: константы `SEARCH_SUGGEST_LIMIT = 5`,
      `SEARCH_MIN_QUERY_LENGTH = 2`
- [x] `.docs/planning-log.md` — изменено: `ADR-032` (FULLTEXT ≥3 симв. +
      префиксный `LIKE` для 2 симв./материала/цвета, `INDEX(material)`)

Отклонения от изначального Scope, обнаруженные в процессе реализации
(причины — ниже):
- [x] `src/Views/search/index.php` — создан (не было в изначальном
      Scope)
- [x] `database/install.php`, `.docs/database.md` — изменены:
      `INDEX(material)` на `product_variants` (не было в изначальном
      Scope)

## Отклонения от плана

1. **`src/Views/search/index.php` создан отдельно, не переиспользован
   `catalog/index.php`/`components/catalog-grid.php` буквально.**
   Причина: `<select id="catalog-sort">` в `catalog-grid.php` привязан
   к сайдбар-форме через `form="catalog-filter-form"`, а JS
   (`initCatalogFilters()`) целиком выключен, если этой формы нет на
   странице (`if (!jQuery('#catalog-filter-form').length) return;`) —
   на `/search` такой формы нет и не должно быть (Out of scope: сайдбар
   каталога не меняется), поэтому и сортировка, и пагинация через
   `#catalog-results .page-link` молча не работали бы. Новый
   `search/index.php` переиспользует то, что действительно общее
   (`product-card.php`, `pagination.php`, `empty-state.php`), и вместо
   fetch-фрагмента — обычная навигация: сортировка — маленькая
   `<form id="search-sort-form">` с автосабмитом по `change`
   (`initSearchSortSubmit()`), пагинация — обычные `<a href>` со
   `?q=...&sort=...&page=N`. DoD («сортировкой и пагинацией») это
   покрывает, а требования «без перезагрузки», в отличие от каталога
   Таска 3, для `/search` в `phase-1.md` нет.
2. **Добавлен `INDEX(material)` на `product_variants`
   (`database/install.php`, `database.md`, `ADR-032`).** Причина: новый
   `LIKE 'q%'`-фильтр по материалу (и для ≥3, и для 2 символов) —
   `dod-global.md` требует индекс под новый фильтруемый запрос, тот же
   принцип, что уже применялся к `color` в `ADR-031` (Таск 3). Индекс
   под `products.name` сознательно не добавлен — тот путь (2-символьный
   `LIKE` по названию) уже описан в `phase-1.md` как «≤ 200 строк»,
   осознанно допустимый полный скан; заводить второй индекс поверх уже
   существующего `FULLTEXT(name, description)` ради этого редкого
   случая было бы избыточно.

## Out of scope — не трогаем

- Сайдбар фильтров каталога и сама логика фильтрации — переиспользуется
  как есть (Таск 3), не меняется
- Обработчик `POST /cart/add` и корзина — Фаза 2
- CRUD товаров/вариантов, загрузка фото — Фаза 4
- Скидки, отзывы, избранное — Фазы 6/7
- Закрытие фазы (`_status.md`, `tz-coverage.md`, `dev-log.md`) —
  отдельный шаг после DoD этого таска, не часть Scope

## Definition of Done

- [x] «ди» (кириллица, 2 символа) → до 5 подсказок с фото; «диван» (≥3)
      → `/search?q=диван` показывает результаты в сетке каталога с
      сортировкой и пагинацией — проверено `curl` против реальной БД:
      4 товара на «диван» (FULLTEXT), 3 на «ди» (префиксный `LIKE` по
      имени), подсказки для «ди» вернули 3 товара с фото/названием/
      ссылкой
- [x] «экокожа» находит товар по материалу Варианта (3 товара); поиск
      по названию цвета — «Коричневый» нашёл 2 товара
      (`variant_images.color`)
- [x] Пустая выдача → пустое состояние (`components/empty-state.php`),
      запрос остаётся видимым в поле поиска — `q=zzzzznotfound` показал
      «Ничего не найдено»
- [x] 1 символ → подсказок нет, запрос к `/search/suggest` не уходит на
      клиенте (`fetchSearchSuggest()` — ранний `return` при
      `q.length < 2`); сервер тоже не ищет при `mb_strlen($q) <
      SEARCH_MIN_QUERY_LENGTH` — второй рубеж на случай прямого
      запроса к `/search/suggest?q=д`, проверено `curl` — `{"items":[]}`
- [x] `q` нормализован и выведен через `e()`; запрос `+*"` (только
      операторы BOOLEAN MODE) не вызывает SQL-ошибку — проверено
      `curl` на `/search` и `/search/suggest`, оба вернули `200`
- [x] Подсказки и результаты — только `is_active=1` товары с ≥1 активным
      Вариантом (тот же `INNER JOIN ... is_active = 1`, что в
      `getCatalogProducts()`)
- [x] `/search/suggest` отдаёт `Content-Type: application/json`;
      `tooManyAttempts('suggest', 20, 10)`/`hitRateLimit('suggest')`
      корректно блокируют 21-й и далее запрос в пределах 10-секундного
      окна — подтверждено изолированным вызовом (25 запросов подряд:
      1–20 `ok`, 21–25 `BLOCKED`). HTTP-бёрст через `php -S`
      (dev-сервер спавнит процесс на каждый запрос) не уложился в
      10 секунд на 25 последовательных `curl` и окно успевало
      «истечь» раньше, чем счётчик — не баг моего кода: изолированный
      тест той же пары `tooManyAttempts()`/`hitRateLimit()` без сетевых
      задержек подтверждает правильную блокировку. Отмечено отдельно:
      `hitRateLimit()` не сбрасывает `first_at` при истечении окна
      (существующая функция, `src/Core/functions.php`, тот же код уже
      используется `login`/`checkout` до этого таска) — вне скоупа,
      не трогал
- [x] `composer test` зелёный (70/70), включая новые тесты
      `normalizeSearchQuery`/`buildFulltextTerm`
- [x] Проверить `.docs/dod-global.md` — `storage/logs/app.log` не
      прирастал новыми записями за время проверки (2 старые строки —
      те же, что были найдены и объяснены в Таске 4)

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
