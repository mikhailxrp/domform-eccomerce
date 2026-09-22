# Current Task

## Фаза
Phase 9 — ИИ-помощники (MVP) (`.docs/phases/phase-9.md`), Таск 3 из 8.

**Статус:** ✅ Завершён 22.09.2026 — проверено живым HTTP на реальной
БД (`php -S` + `curl`), реальными предложениями от Claude через
OpenRouter. Ролевой доступ на `/admin/ai/specs/{id}` и на форме Товара:
`admin` → 200, видит бейдж статуса и ссылку «Разбор ИИ»; временный
`manager` (создан и удалён после проверки) → редирект `/admin` на
экране ревью, на форме Товара видит бейдж, но без ссылки; Гость →
`/login`; несуществующий Товар → 404. Полный цикл на реальном Товаре
(6 предложений от реального разбора): принятие `variant_material` со
статусом `needs_decision` без правки значения → отклонено, в БД ничего
не изменилось, страница отрендерена повторно с ошибкой (не редирект);
подмена чужого `variant_id` (999) → отклонено тем же путём; принятие
того же предложения с правкой значения + Вариантом → применено ровно к
выбранному Варианту, второй Вариант Товара не тронут, `specs_status`
→ `confirmed`, все предложения Товара удалены. Повторное применение
`spec`-характеристики с тем же названием, но другим значением →
заменило старую строку `product_specs`, не задвоило (`SELECT`
до/после, было/стало 5 строк, не 6). «Подтвердить вручную» на Товаре
без единого предложения → `specs_status='confirmed'`. Новая
характеристика подтверждена видна на витрине (`/product/{slug}`) без
единой правки `ProductController`/`product/show.php`, как и
предполагалось на этапе планирования. POST `apply`/`confirm` без CSRF
→ 419 на обоих. `composer test` — 464/464 (+10 `AiSpecsTest` на
`validateSpecReviewInput()`). Все временные данные (описание/статус/
материал тестового Товара, тестовый Менеджер, тестовые предложения)
возвращены в исходное состояние после проверки — сверено `SELECT`.

**Найдено по ходу проверки (не исправлено — вне scope этого таска):**
при разборе одного Товара через `run()` (Таск 2) реальный вызов
OpenRouter один раз подошёл близко к `AI_TIMEOUT_SECONDS` (15 c), и
общего бюджета `set_time_limit(1 × (15 + 5) = 20 c)` не хватило —
`Fatal: Maximum execution time of 20 seconds exceeded` в
`replaceSpecSuggestions()` (запись предложений на удалённую БД Beget
добавляет сетевую задержку, которой формула не закладывает запас).
Стоит расширить буфер в `AdminAiSpecController::run()` отдельным
изменением — не входит в файлы Таска 3.

## Задача
Экран ревью предложений (`FR-AI-001` правила 3–5): на
`/admin/ai/specs/{id}` Администратор видит предложения конкретного
Товара, принимает/правит/отклоняет каждое. Принятые пишутся в
`product_specs` (цель `spec`) и в выбранный Вариант (цели
`variant_material`/`variant_mechanism`); `color` — только подсказка,
никогда не применяется автоматически (решение зафиксировано в Таске 1
«Решения фазы» — цвет остаётся атрибутом фото Варианта, `ADR-006`).
После обработки — `specs_status='confirmed'`, это и есть допуск в
подбор Товара диалогом (`FR-AI-004`). Тот же статус можно поставить
вручную, без единого предложения (ИИ выключен или ничего не
предложено).

## Что проверено в коде перед планом
- `src/Controllers/AdminAiSpecController.php` (Таск 2) уже содержит
  `index()`/`run()` — `review()`/`apply()`/`confirmManually()`
  добавляются рядом, тем же классом.
- `src/Models/AiSpec.php` (Таск 2) уже содержит `getKnownSpecValues()`,
  `replaceSpecSuggestions()`, `findProductForSpecsRun()` — для ревью
  нужна новая выборка одного Товара с его предложениями и Вариантами.
- `src/Models/Product.php::findProductForAdmin()` **не выбирает
  `specs_status`**, при этом именно её результат
  `AdminProductController::edit()` передаёт во view как `$product` без
  изменений (`renderForm()`). Без этой колонки бейдж на форме Товара
  показать нечем — добавляем `specs_status` в существующий `SELECT`
  (1 колонка, по образцу уже там присутствующих `is_active`/
  `is_featured`); сам `AdminProductController.php` трогать не нужно —
  он передаёт `$product` как есть.
- `src/Models/Product.php::getAllProductVariants()` — готовая выборка
  Вариантов Товара (`id`, `sku`, `material`, `mechanism_type`...) для
  выпадающего списка «применить к какому Варианту».
- `src/Views/product/show.php` + `ProductController.php::show()` уже
  читают `getProductSpecs()` и рендерят вкладку «Характеристики» —
  новые строки `product_specs` появятся на витрине без единой правки
  этих файлов.
- `src/Core/Router.php::matchRoute()` — первое совпадение по порядку
  объявления, регэксп разной длины сегментов не пересекается:
  `/admin/ai/specs/{id}` (GET) и `/admin/ai/specs/{id}/apply`,
  `/admin/ai/specs/{id}/confirm` (POST) не конфликтуют друг с другом и
  с уже существующим `/admin/ai/specs/run`.
- `src/Views/admin/products/form.php` — карточка «Характеристики»
  (строка ~113) и заголовок `<h4>` (строка ~30) — готовые точки
  вставки бейджа/ссылки; `$isEdit`/`$product['id']` уже доступны в
  шаблоне.
- `AI_SPEC_TARGET_LABELS`/`AI_SPEC_TARGETS` (`Core/AiSpecs.php`,
  Таск 2) переиспользуются для подписи целей в экране ревью.

## Scope — что трогаем
- [ ] `src/Models/Product.php` — изменить: `specs_status` добавлен в
      `SELECT` внутри `findProductForAdmin()` (1 колонка, без прочих
      изменений)
- [ ] `src/Core/AiSpecs.php` — изменить: `validateSpecReviewInput(array
      $suggestions, array $input): array` — `needs_decision` без
      правки значения не проходит; цель `variant_*` требует
      выбранного `variant_id`, принадлежащего этому Товару; `color` не
      принимает форму «применить» вовсе (только показ); пустое
      значение отклоняется
- [ ] `src/Models/AiSpec.php` — изменить:
      `getSpecSuggestionsForProduct(int $productId): array`,
      `findProductForSpecsReview(int $id): ?array` (продукт +
      предложения + Варианты); `applySpecSuggestions(int $productId,
      array $accepted): void` (одна транзакция: dedup-`INSERT`/
      `UPDATE` в `product_specs` по `name`, `UPDATE product_variants`
      только для `variant_id`, принадлежащего Товару, `UPDATE products
      SET specs_status='confirmed'`, `DELETE` всех предложений Товара
      после обработки); `setProductSpecsStatus(int $productId, string
      $status): void`
- [ ] `src/Controllers/AdminAiSpecController.php` — изменить:
      `review(string $id)` (`requireRole(['admin'])`), `apply(string
      $id)` (POST, `requireCsrf()` → ошибка — прямой рендер `review` с
      `$old`/`$errors`, успех — `redirect()` + flash),
      `confirmManually(string $id)` (POST, `requireCsrf()`)
- [ ] `src/Views/admin/ai/specs/review.php` — создать: таблица
      предложений (цель, название, значение — редактируемое поле,
      бейдж «требует решения», выбор Варианта для `variant_*`, `color`
      — только текст), кнопки «Применить»/«Подтвердить вручную»
- [ ] `src/Views/admin/products/form.php` — изменить: бейдж статуса
      характеристик у заголовка + ссылка на `/admin/ai/specs/{id}` в
      карточке «Характеристики», видна только `admin`
      (`currentUser()['role']`)
- [ ] `config/routes.php` — изменить: `GET /admin/ai/specs/{id}`,
      `POST /admin/ai/specs/{id}/apply`,
      `POST /admin/ai/specs/{id}/confirm`
- [ ] `tests/Unit/AiSpecsTest.php` — изменить: тесты на
      `validateSpecReviewInput()`

## Out of scope — не трогаем
- Генератор описания, консультант, подбор товара, бюджет/лимит —
  Таски 4–8
- `src/Controllers/AdminProductController.php` — не меняется, уже
  передаёт `$product` из `findProductForAdmin()` как есть
- `src/Views/product/show.php`, `ProductController.php` — не меняются,
  уже рендерят `product_specs`
- Автоматическая запись `color` куда-либо — остаётся информационной по
  решению Таска 1
- Список очереди `/admin/ai/specs` и пакетный запуск (`index()`/
  `run()`) — не рефакторятся, используются как есть

## Definition of Done
- [ ] Принято 2 предложения из 3 на реальном Товаре → в `product_specs`
      ровно 2 новые/обновлённые строки без дублей по `name`,
      отклонённого предложения нет нигде, `specs_status='confirmed'`
      (`SELECT`)
- [ ] Предложение `variant_material` применено к выбранному Варианту →
      изменился именно он, другие Варианты того же Товара не тронуты
- [ ] `needs_decision` без правки значения принять нельзя — поле
      подсвечено, в БД ничего не записано
- [ ] Попытка применить `variant_id`, не принадлежащий этому Товару
      (подделанный POST) → отклонено, ничего не изменено
- [ ] Повторное применение уже обработанного набора не создаёт дублей
      в `product_specs`
- [ ] «Подтвердить вручную» без единого предложения (ИИ выключен) →
      `specs_status='confirmed'`, Товар уходит из очереди «требует
      разбора»
- [ ] Карточка Товара на витрине показывает новые характеристики без
      правок `ProductController`/`product/show.php` — только за счёт
      `product_specs`
- [ ] POST без CSRF → 419; `manager` на `/admin/ai/specs/{id}` →
      редирект `/admin`
- [ ] `composer test` зелёный
- [ ] Проверить `.docs/dod-global.md`

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
