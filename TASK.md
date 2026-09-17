# Current Task

## Фаза
Phase 1 — Каталог и карточка товара (`.docs/phases/phase-1.md`, Таск 1)

## Задача
`php database/install.php` содержит `categories.description` и таблицу
`product_specs` (1:1 с `database.md`); `php database/seed-catalog.php`
наполняет БД тестовым каталогом, достаточным для проверки всех кейсов
фазы (`_status.md`: «каталог на данных, занесённых напрямую в БД»).

## Scope — что трогаем

- [x] `database/install.php` — изменить: `categories.description TEXT
      NULL`; таблица `product_specs` (`id`, `product_id` FK →
      `products.id` ON DELETE CASCADE, `name VARCHAR(100)`, `value
      VARCHAR(255)`, `sort_order INT DEFAULT 0`, `INDEX(product_id)`)
- [x] `.docs/database.md` — изменить: колонка `categories.description`,
      раздел `product_specs`, карта связей
- [x] `.docs/planning-log.md` — изменить: `ADR-029`
      (`categories.description`), `ADR-030` (`product_specs`)
- [x] `database/seed-catalog.php` — создать: 6 категорий в 2 уровня (по
      брифу), ~12 товаров, у части — 2–3 Варианта с разной ценой и
      сроком (ткань / экокожа), `variant_images` с `color` и
      `is_swatch` (пути на фото темы), ровно один
      `is_showroom_sample = 1`, один товар в двух категориях
      (`is_primary` у одной), `product_specs`; идемпотентно —
      повторный запуск не дублирует
- [x] `composer.json` — изменить: скрипт `seed:catalog`

## Out of scope — не трогаем

- Таски 2–5 Фазы 1 (листинг каталога, фильтры без перезагрузки,
  карточка товара, поиск) — отдельные таски после этого
- Обработчик `POST /cart/add` и сама корзина — Фаза 2
- CRUD товаров/вариантов для Менеджера/Администратора — Фаза 4; в этом
  таске данные заносятся напрямую сидами
- Реальная загрузка фото в `public/uploads/products/` — Фаза 4; сиды
  используют существующие пути на фото темы
  (`public/assets/images/product/*.jpg`)
- Скидки (`product_variants.discount_percent`), отзывы, избранное —
  Фазы 6/7, схемы не касаются `categories`/`product_specs`
- Любые Controllers/Views/routes — эта фаза начинает их только с
  Таска 2

## Definition of Done

- [x] `install.php` выполняется дважды подряд без ошибок; `SHOW CREATE
      TABLE categories` / `product_specs` совпадают с `database.md` по
      колонкам, типам, FK и индексам (ручная сверка) — проверено
      против `mikhail700.beget.tech`; MySQL 8.4 там не поддерживает
      `ADD COLUMN IF NOT EXISTS`, добавление колонки на уже
      развёрнутую с Фазы 0 таблицу `categories` сделано проверкой
      через `information_schema.COLUMNS` перед `ALTER`
- [x] `seed-catalog.php` дважды подряд — без дублей (`COUNT(*)` не
      растёт) — сверено по всем 6 таблицам каталога до/после
- [x] В данных есть: Вариант-образец (`SOFA-VERONA-ECO`); цвет со
      `is_swatch = 1` без реального фото («Коричневый» того же
      Варианта); товар в 2 категориях («Осло» — Диваны + Кровати);
      товар с `mechanism_type = NULL` (6 Вариантов); категория с
      заполненным `description` (Диваны, Кровати); неактивный товар
      (`is_active = 0`, «Шкаф «Классик»») для проверки скрытия
- [x] Ровно одна `is_primary = 1` на товар в `product_categories`
- [x] Проверить `.docs/dod-global.md`

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
