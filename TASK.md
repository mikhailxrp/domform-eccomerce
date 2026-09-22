# Current Task

## Фаза
Phase 9 — ИИ-помощники (MVP) (`.docs/phases/phase-9.md`), Таск 2 из 8.

**Статус:** ✅ Завершён 22.09.2026 — проверено живым HTTP на реальной
БД (`php -S` + `curl`), реальным вызовом Claude через OpenRouter (не
моком). Ролевой доступ: `admin` → 200 на `/admin/ai/specs`; временный
`manager` (создан и удалён после проверки, включая `remember_tokens`)
→ редирект `/admin`, пункта «ИИ-помощники» в сайдбаре нет; Гость →
`/login`. Реальный разбор на живом Товаре (описание временно
проставлено на существующий Товар и возвращено в `NULL` после
проверки): «Угловой диван раскладной, механизм еврокнижка. Обивка —
вельвет тёмно-синий. Ширина 320 см, глубина 180 см.» → 6 предложений
(`variant_material`/`variant_mechanism`/`color` — все `needs_decision`,
верно: этих значений не было в каталоге категории; 3× `spec` — `Форма`/
`Ширина`/`Глубина` — все `ok`, спецификация без словаря по конструкции
`ADR-049`). Второй такой же товар без упоминания размера в описании →
предложение только по материалу (совпало со словарём → `ok`) и одна
`spec`-характеристика из текста; поля «размер» нет вовсе — не пустое
значение (правило 2). Повторный запуск того же Товара — новые 5 строк
вместо старых 6 (замена, не накопление, `SELECT` до/после). На
протяжении обеих проверок `products.description`, `product_specs`
(4 предпосуществующие строки Товара) и `product_variants` не
изменились ни на байт (`SELECT` до/после). Ключ класса `specs`
временно затёрт в `.env` (после — восстановлен, сверено `diff`):
кнопка «Разобрать» пропала из HTML, алерт «недоступно» показан, прямой
POST на `/admin/ai/specs/run` в обход UI не создал ни одной строки в
`ai_spec_suggestions` (проверка на уровне сервера, не только UI). POST
без CSRF → 419. `php database/install.php` дважды подряд —
`products.specs_status`/её индекс/таблица `ai_spec_suggestions` без
дублей; 34 существующих Товара получили `specs_status='pending'`.
`composer test` — 454/454 (+25 `AiSpecsTest`). Все временные данные
(описания тестовых Товаров, тестовые предложения, тестовый Менеджер)
удалены после проверки. Реализовано по плану ниже без отклонений в
scope; уточнены детали `normalizeSpecSuggestions()` (словарная проверка
`needs_decision` только для `variant_material`/`variant_mechanism`/
`color`, не для `spec`) — см. `ADR-049`.

## Задача
Разбор характеристик Товара (`FR-AI-001` правила 1, 2, 5, 6):
`/admin/ai/specs` показывает очередь Товаров со `specs_status='pending'`,
Администратор отмечает несколько чекбоксами и запускает разбор
**пакетом**. Предложения (материал/механизм/цвет/произвольная
характеристика) сохраняются в новую таблицу `ai_spec_suggestions` со
статусом `ok`/`needs_decision` (значение вне известных в каталоге).
Ничего не пишется в карточку Товара — `product_specs`/
`product_variants`/`description` не меняются. Экран подтверждения —
следующий таск.

## Что проверено в коде перед планом
- `src/Models/Product.php::findProductForAdmin()` уже отдаёт Товар с
  `description`, `category_ids`, Вариантами, `specs` — переиспользуется
  для сборки промпта и подсчёта известных значений, отдельную
  fetch-функцию под это не заводим.
- `src/Models/Product.php::syncProductSpecs()`/`getProductSpecs()` —
  образец «удалить все строки Товара → вставить заново», тот же приём
  для `replaceSpecSuggestions()`.
- `database/install.php` — идемпотентные `$columnExists`/`$indexExists`
  (`information_schema.COLUMNS`/`STATISTICS`) уже объявлены один раз и
  переиспользуются по всему файлу (`ADR-031`/`ADR-037`) — для
  `products.specs_status` и её индекса используем те же переменные, не
  заводим новые `->prepare()`.
- `src/Models/Product.php::getAdminProducts()`/`countAdminProducts()` —
  образец пагинированного списка с фильтром для
  `getProductsForSpecsQueue()`/`countProductsForSpecsQueue()`.
- `src/Views/layout/admin-header.php` — `$adminNavItems` с ключом
  `roles => ['admin']` (готовый образец — «Сотрудники», «Настройки»);
  новый пункт «ИИ-помощники» добавляется тем же способом.
- `config/routes.php` — секции GET/POST плоские, без вложенности;
  `/admin/ai/specs` и `/admin/ai/specs/run` встают рядом с
  `/admin/reviews`-подобными маршрутами.
- `src/Services/Ai/ai.php::aiComplete('specs', $messages)` (Таск 1) —
  уже возвращает `null` при недоступном классе без исключений, ядро
  не дорабатывается.
- `src/Controllers/AdminReviewController::index()` — образец
  `requireRole()` + пагинация + `render()` с `paginationLinks`.
- `config/config.php` пока не содержит `ADMIN_AI_SPECS_PER_PAGE`/
  `AI_SPECS_BATCH_MAX` — добавляются этим таском.

## Scope — что трогаем
- [ ] `database/install.php` — изменить: `products.specs_status
      ENUM('pending','confirmed') NOT NULL DEFAULT 'pending'` +
      `INDEX(specs_status)` (идемпотентно, через уже существующие
      `$columnExists`/`$indexExists`); новая таблица
      `ai_spec_suggestions` (`product_id` FK CASCADE, `target
      ENUM('spec','variant_material','variant_mechanism','color')`,
      `name VARCHAR(100)`, `value VARCHAR(255)`,
      `status ENUM('ok','needs_decision')`, `created_at`,
      `INDEX(product_id)`)
- [ ] `.docs/database.md` — изменить: разделы `products.specs_status` и
      `ai_spec_suggestions`
- [ ] `.docs/planning-log.md` — изменить: ADR-049 (справочник известных
      значений из существующих данных каталога, цели предложений,
      `specs_status`)
- [ ] `src/Core/AiSpecs.php` — создать, чистые функции:
      `buildSpecsPrompt(array $product, array $knownValues): array`,
      `normalizeSpecSuggestions(array $decoded, array $knownValues):
      array` (отбрасывает пустые значения, режет длину, помечает
      `needs_decision` для значений вне известных, схлопывает дубли)
- [ ] `src/Models/AiSpec.php` — создать:
      `getProductsForSpecsQueue(string $status, int $page, int
      $perPage): array`, `countProductsForSpecsQueue(string $status):
      int`, `getKnownSpecValues(array $categoryIds): array` (`DISTINCT`
      по `product_specs`, `product_variants.material`/
      `mechanism_type`, `variant_images.color`),
      `replaceSpecSuggestions(int $productId, array $rows): void`
      (транзакция)
- [ ] `src/Controllers/AdminAiSpecController.php` — создать: `index()`
      (очередь + пагинация), `run()` (POST, `requireCsrf()`, до
      `AI_SPECS_BATCH_MAX` Товаров за раз, по Товару — `aiComplete()`,
      неответившие пропускаются с flash «разобрано N из M»), оба —
      `requireRole(['admin'])`
- [ ] `src/Views/admin/ai/specs/index.php` — создать: список Товаров
      (чекбоксы, статус, число предложений), кнопка «Разобрать
      выбранные»; при недоступном классе — алерт вместо кнопки
- [ ] `src/Views/layout/admin-header.php` — изменить: пункт
      «ИИ-помощники» с `roles => ['admin']`
- [ ] `config/routes.php` — изменить: `GET /admin/ai/specs`,
      `POST /admin/ai/specs/run`
- [ ] `config/config.php` — изменить: `ADMIN_AI_SPECS_PER_PAGE`,
      `AI_SPECS_BATCH_MAX`
- [ ] `tests/Unit/AiSpecsTest.php` — создать: юнит-тесты
      `normalizeSpecSuggestions()`; `tests/bootstrap.php` — изменить:
      подключить `src/Core/AiSpecs.php`

## Out of scope — не трогаем
- Экран подтверждения предложений, запись в `product_specs`/
  `product_variants`, установка `specs_status='confirmed'` — Таск 3
- Бейдж/ссылка на форме Товара (`src/Views/admin/products/form.php`) —
  Таск 3
- Генератор описания, консультант, подбор товара, бюджет/лимит —
  Таски 4–8
- Любые изменения в `src/Services/Ai/*` и `src/Core/Ai.php` — ядро
  Таска 1 используется как есть, не рефакторится

## Definition of Done
- [ ] Описание реального Товара «диван раскладной, обивка — рогожка
      бежевая» → в `ai_spec_suggestions` появились предложения по
      механизму и цвету (проверено `SELECT` на реальной БД)
- [ ] Описание без размера → строки «размер» нет вовсе (не пустое
      значение), Товар не помечен ошибочным
- [ ] Значение вне `getKnownSpecValues()` → `status='needs_decision'`
      (юнит-тест `normalizeSpecSuggestions()` + проверка на живом
      разборе)
- [ ] `products.description`, `product_specs`, `product_variants` не
      изменились после разбора (`SELECT` до и после)
- [ ] Повторный разбор того же Товара заменяет прежние предложения, не
      дублирует их
- [ ] Класс `specs` недоступен (ключ снят) → кнопка «Разобрать» не
      активна/алерт, очередь по-прежнему открывается, характеристики
      вводятся вручную в форме Товара
- [ ] POST `/admin/ai/specs/run` без CSRF → 419; `manager` на
      `/admin/ai/specs` → редирект на `/admin`, пункта в сайдбаре нет
- [ ] `php database/install.php` дважды подряд — без дублей колонки
      `specs_status`/индекса/таблицы `ai_spec_suggestions`
- [ ] `composer test` зелёный
- [ ] Проверить `.docs/dod-global.md`

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
