# Current Task

## Фаза
Phase 1 — Каталог и карточка товара (`.docs/phases/phase-1.md`, Таск 3)

## Задача
Сайдбар с 4 фильтрами (цена, категория, цвет, «В наличии» =
`is_showroom_sample`) применяется через `fetch` без перезагрузки
страницы, состояние — в URL («назад»/«вперёд» браузера восстанавливают
фильтры); пустое состояние «Ничего не найдено» + «Сбросить фильтры»;
переключатель плитка/список на `sessionStorage`.

## Scope — что трогаем

- [x] `src/Core/CatalogFilters.php` — создать: чистая
      `normalizeCatalogFilters(array $query): array` (границы цены,
      whitelist сортировки, int-приведение id категорий, обрезка
      цветов), `buildCatalogQueryString(array $filters): string`
- [x] `tests/Unit/CatalogFiltersTest.php` — создать
- [x] `src/Models/Product.php` — изменить: фильтры в
      `getCatalogProducts()` / `countCatalogProducts()`
      (`category_ids`/`colors`/`price_min`/`price_max`/`in_stock`,
      общий `buildCatalogFilterConditions()` — список и счётчик не
      могут разойтись по условиям); `getFilterOptions(?int
      $categoryId)` — min/max цена, список цветов
- [x] `src/Controllers/CatalogController.php` — изменить: при
      `X-Requested-With: fetch` рендерит только фрагмент
      (`components/catalog-grid`); категория из пути `/catalog/{slug}`
      всегда перекрывает чекбоксы (см. Out of scope)
- [x] `src/Views/components/catalog-sidebar.php`,
      `components/catalog-grid.php` (фрагмент: счётчик + сортировка +
      плитка/список + пагинация или пустое состояние),
      `components/empty-state.php` — создать
- [x] `src/Views/catalog/index.php` — изменить: подключить компоненты
      (сайдбар статичен, `#catalog-results` — то, что подменяет fetch)
- [x] `public/assets/js/app.js`, `public/assets/css/app.css` —
      изменить: `fetch` + `pushState`/`popstate`, переключатель вида
      через Bootstrap Tab API + `sessionStorage`, `[aria-busy]` —
      состояние загрузки

Не было в исходном плане, добавлено по ходу (необходимые зависимости
перечисленного выше, не отдельная функциональность):
- [x] `src/Views/components/product-card.php` — изменить: второй режим
      разметки (`$viewMode` grid/list) — без этого переключатель
      плитка/список (сам по себе часть Scope Таска 3) нечем было бы
      наполнить
- [x] `src/Core/Request.php` — изменить: `isFetchRequest()` — тот же
      паттерн, что `isPost()`/`isGet()`, читает
      `X-Requested-With: fetch`
- [x] `tests/bootstrap.php` — изменить: подключить
      `CatalogFilters.php` для `CatalogFiltersTest`
- [x] `database/install.php`, `.docs/database.md`,
      `.docs/planning-log.md` (`ADR-031`) — изменить:
      `INDEX(color)` на `variant_images` — новый фильтруемый запрос
      (цвет) требует индекса по `dod-global.md`, идемпотентно через
      `information_schema` (тот же приём, что `categories.description`
      в Таске 1)

## Out of scope — не трогаем

- Карточка товара (`/product/{slug}`), выбор Варианта — Таск 4
- Поиск и подсказки (`/search`) — Таск 5
- Обработчик `POST /cart/add` и сама корзина — Фаза 2
- CRUD категорий/товаров для Менеджера/Администратора — Фаза 4
- Скидки, отзывы, избранное — Фазы 6/7
- Чекбоксы категории в сайдбаре на `/catalog/{slug}` — виджет
  показывается только на `/catalog`; на странице категории она уже
  зафиксирована путём (осознанное упрощение — иначе пришлось бы
  различать «фильтр не тронут» и «пользователь снял единственную
  галочку» у чекбоксов в обычной GET-форме, а это не требуется ни одним
  пунктом DoD)

## Definition of Done

- [x] Смена фильтра → URL обновлён без перезагрузки; F5 воспроизводит
      ту же выдачу; «назад» восстанавливает предыдущие фильтры и
      чекбоксы в сайдбаре — серверная часть (fetch-фрагмент, query,
      pre-checked чекбоксы) проверена `curl`; сам `pushState`/
      `popstate` в `app.js` — синтаксически проверен (`node --check`),
      не воспроизведён руками (нет браузера в сессии)
- [x] Комбинация без результата → «Ничего не найдено» + «Сбросить
      фильтры»; после сброса — полный список Категории — проверено
      `curl`: `price_min=999999&price_max=999999` → пустое состояние,
      `catalog-reset-link` ведёт на `/catalog` или `/catalog/divany`
      (путь без query) в зависимости от страницы
- [x] «В наличии» отбирает только товары с Вариантом-образцом (1–2
      позиции на сидах) — `in_stock=1` → 1 из 1
- [x] Фильтр цены по диапазону Вариантов: товар с Вариантом за 30 000
      и 45 000 попадает в диапазон 40 000–50 000 — `divan-verona`
      (30000/45000) найден в `price_min=40000&price_max=50000`
- [x] Вид плитка/список переживает переход между страницами каталога,
      но не новую вкладку — `sessionStorage` в `app.js`; серверная
      часть (обе разметки — `single-product`/`single-product-02` —
      рендерятся одновременно в двух `tab-pane`) проверена `curl`, сам
      переход между вкладками — нет браузера в сессии
- [x] Без JS форма сайдбара отправляется обычным GET и даёт ту же
      выдачу — форма `method="get"`, все поля — обычные `name`/
      `value`, `sort` через HTML `form="catalog-filter-form"`;
      проверено `curl` без заголовка `X-Requested-With`
- [x] `page` сбрасывается на 1 при смене фильтров — форма сайдбара не
      содержит поле `page`, сервер по умолчанию отдаёт страницу 1
- [x] Нет собственных глобальных переменных в `app.js` (IIFE) — все
      новые функции/константы объявлены внутри существующего IIFE
- [x] `composer test` зелёный, включая `CatalogFiltersTest` — 63/63
      (было 49, +14 новых)
- [x] Проверить `.docs/dod-global.md` — индекс `idx_variant_images_color`
      добавлен под новый фильтр (`ADR-031`); `storage/logs/app.log` не
      создан за всю сессию проверки — ошибок и warning'ов не было

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
