# Current Task

## Фаза
Phase 4 — Панель менеджера и каталог в админке (`.docs/phases/phase-4.md`, Таск 7)

**Статус:** ✅ Завершён — код реализован и проверен `php -S` + `curl`
против реальной БД (`mikhail700.beget.tech`). `composer test` 174/174
(+7 `SlugTest`). Подробности — `.docs/dev-log.md`, 19.09.2026.

При живой проверке найдены и исправлены три реальные ошибки:
1. Форма категории при пустом `name` одновременно показывала «Введите
   название» и ложное «Slug уже занят другой категорией» (каскад от
   пустого имени, не конфликт БД) — разделено на `errors['slug']`
   (структурная пустота) и `errors['slug_taken']` (реальный дубликат
   из `createCategory()`/`updateCategory()`).
2. `AdminProductController.php` не подключал `Core/CatalogFilters.php`
   — падало 500 `Call to undefined function buildFulltextTerm()` на
   поиске от 3 символов; та же ошибка класса, что уже чинили в
   `AdminVariantController.php` (Таск 4). Добавлен `require_once`.
3. Диапазон цен считался по всем Вариантам, а `phase-4.md` явно
   требует «из активных Вариантов» — найдено при сверке с DoD перед
   закрытием таска, не в момент проверки основного сценария.
   Исправлено на условную агрегацию (`MIN/MAX(CASE WHEN is_active = 1
   ...)`), количество Вариантов при этом осталось по всем — это
   управленческая информация о структуре Товара, не о витрине.
   Перепроверено на Товаре с временно деактивированными Вариантами,
   состояние в БД восстановлено после проверки.

## Задача
`/admin/categories` — плоский список (название, родитель, slug,
порядок, кол-во Товаров) + создание/редактирование/удаление.
`/admin/products` — список с фото, названием, основной категорией,
кол-вом Вариантов, диапазоном цен, бейджами «скрыт»/«образец»,
фильтром по категории/статусу, поиском по названию/артикулу,
переключателем «Скрыть/Показать».

## Scope — что трогаем

- [x] `src/Core/Slug.php` — создать: `slugify(string $text): string` —
      транслитерация кириллицы в латиницу, затем только `[a-z0-9-]`,
      схлопывание повторных/краевых дефисов
- [x] `tests/Unit/SlugTest.php` — создать
- [x] `src/Models/Category.php` — изменить: `getCategoriesFlat():
      array` (JOIN самой на себя для имени родителя + `COUNT`
      уникальных Товаров через `product_categories`),
      `findCategoryById(int $id): ?array`, `createCategory(array
      $data): ?int` / `updateCategory(int $id, array $data): bool` —
      сначала проверка глубины (если указан `parent_id`, у него самого
      `parent_id` должен быть `NULL`, иначе это уже 3-й уровень), затем
      `INSERT`/`UPDATE`, перехват SQLSTATE 23000 (дубликат `slug`,
      UNIQUE в БД) → `null`/`false` — тот же паттерн, что `createUser()`
      в `Models/User.php`; `deleteCategory(int $id): bool` — перехват
      SQLSTATE 23000 (FK RESTRICT из `product_categories`) → `false`;
      дополнительно — `isValidCategoryParent(?int $parentId): bool`
- [x] `src/Models/Product.php` — изменить: `getAdminProducts(array
      $filters, int $page, int $perPage): array` — LEFT JOIN основной
      категории (`is_primary=1`), подзапросы `COUNT`/`MIN`/`MAX` цены
      по `product_variants` (цена — условная агрегация только по
      активным Вариантам, количество — по всем), флаг
      `is_showroom_sample` (любой у Товара), главное фото
      (`variant_images.is_main`); фильтр по категории — `EXISTS` на
      `product_categories` (не только основная), по статусу —
      `is_active`; поиск — `buildFulltextTerm()`/`LIKE` по
      `products.name` и `product_variants.sku`; `countAdminProducts()`
      — те же условия; `setProductActive(int $id, bool $active): void`;
      дополнительно — `findProductForToggle(int $id): ?array`
      (не входило дословно в Scope, но нужно для 404/текста
      уведомления в `toggle()`)
- [x] `src/Controllers/AdminCategoryController.php` — создать:
      `index()`, `create()`, `store()`, `edit(string $id)`,
      `update(string $id)`, `delete(string $id)` — `requireCsrf()` на
      всех POST, ошибки через `setFlash()` + редирект
- [x] `src/Controllers/AdminProductController.php` — создать:
      `index()` (фильтр/поиск/пагинация, по образцу
      `AdminOrderController::index()`), `toggle(string $id)`
      (`requireCsrf()`, инвертирует `is_active`)
- [x] `src/Views/admin/categories/index.php` — создать: таблица, кнопки
      редактировать/удалить (удаление — обычная POST-форма, без JS)
- [x] `src/Views/admin/categories/form.php` — создать: общая для
      create/edit (`$category = null` для создания), `name`, `slug`
      (необязателен — пустой автозаполняется `slugify($name)` на
      сервере), `parent_id` (select только из корневых), `description`,
      `sort_order`
- [x] `src/Views/admin/products/index.php` — создать: таблица с
      фильтром (категория + статус) и поиском, бейджи, кнопка
      «Скрыть»/«Показать»; ссылка «Редактировать» — на ещё не
      существующий `/admin/products/{id}/edit` (Таск 8, до этого —
      штатный 404, как уже было с «Новый заказ» в Таске 1)
- [x] `config/config.php` — изменить: `ADMIN_PRODUCTS_PER_PAGE` (по
      образцу `ADMIN_ORDERS_PER_PAGE`)
- [x] `config/routes.php` — изменить: `GET/POST /admin/categories`,
      `GET /admin/categories/create`, `GET /admin/categories/{id}/edit`,
      `POST /admin/categories/{id}`, `POST /admin/categories/{id}/delete`,
      `GET /admin/products`, `POST /admin/products/{id}/toggle`

`src/Views/layout/admin-header.php` — без изменений: пункты
«Категории»/«Товары» уже вели на нужные адреса с Таска 1.

## Out of scope — не трогаем

- Форма Товара (создание/редактирование, Варианты, характеристики) —
  Таск 8; в этом таске «Редактировать» у Товара ведёт на ещё не
  существующий маршрут
- Фото Вариантов, отчёт по продажам (Таски 9–10)
- Срок изготовления (`production_time`) как отдельная колонка списка
  Товаров — у Товара может быть несколько Вариантов с разными сроками,
  отдельное агрегирование ради второстепенного поля не делаем
- Пагинация списка Категорий — список из ~10–20 строк, `dod-global.md`
  требует пагинацию явно только для «товаров/заказов»
- Изменение/удаление Варианта из списка Товаров — только счётчик и
  диапазон цен для чтения

## Definition of Done

- [x] `slugify('Диван «Модерн» 2-х местный')` → `divan-modern-2-h-mestnyy`
      (собственная таблица транслитерации, зафиксирована в `SlugTest`);
      пустой результат (вход из одних спецсимволов) —
      `createCategory()`/`updateCategory()` отклоняют с ошибкой поля —
      проверено на реальной БД
- [x] Родитель второго уровня недоступен в select при создании/
      редактировании; ручной POST с `parent_id` подкатегории (в т.ч. в
      обход `<select>`) → ошибка, строка не создана/не изменена —
      проверено
- [x] Дубликат `slug` → отдельная ошибка поля, ничего не создано/не
      изменено; категория с Товарами не удаляется (дружелюбное
      сообщение, проверено на «Диваны»); пустая — удаляется —
      проверено
- [x] Скрытый Товар (`is_active=0`) пропадает из `/catalog`
      (`404` на `/product/{slug}`), остаётся в `/admin/products` с
      бейджем «скрыт»; `order_items` не затронуты (`setProductActive()`
      меняет только `products.is_active`) — проверено циклом скрыть→
      показать на реальном Товаре
- [x] Фильтр по категории в `/admin/products` учитывает
      `product_categories` целиком — проверено
- [x] Диапазон цен в списке считается только по активным Вариантам
      конкретного Товара (условная агрегация); Товар со всеми
      неактивными Вариантами показывает «—», не 0 и не ошибку —
      проверено на реальных данных, найдено и исправлено расхождение
      с исходной реализацией
- [x] `customer`/Гость по `/admin/categories*` и `/admin/products*` →
      редирект — Гость проверен; `customer` — тот же
      `requireRole(['manager','admin'])`, что и везде в Фазе 4
- [x] `composer test` зелёный, включая `SlugTest` (174/174)
- [x] Проверить `.docs/dod-global.md`

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
