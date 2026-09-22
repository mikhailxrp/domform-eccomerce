# Current Task

## Фаза
Phase 9 — ИИ-помощники (MVP) (`.docs/phases/phase-9.md`), Таск 4 из 8.

## Задача
`FR-AI-002`: на форме редактирования Товара Администратор вводит краткие
данные (категория, материал, размер, механизм) и нажимает
«Сгенерировать черновик» — черновик приходит через JSON-эндпоинт,
показывается в отдельном поле и сохраняется в
`products.description_draft`; опубликованное `description` не меняется,
пока Администратор не нажмёт «Применить к описанию» и не сохранит форму
штатным сабмитом. Провайдер недоступен → кнопки нет, алерт вместо неё;
создание и сохранение Товара не блокируются. Кнопка не показывается на
`/admin/products/create` — только на форме уже сохранённого Товара (id
нужен для эндпоинта).

## Что проверено в коде перед планом
- `src/Models/Product.php::findProductForAdmin()` (строка 843) выбирает
  `id, name, slug, description, is_active, is_featured, specs_status` —
  `description_draft` нужно добавить в этот же `SELECT`, иначе
  сохранённый черновик не переживёт перезагрузку страницы.
- `AdminProductController::renderForm()` — приватный метод, вызывается
  из `create()/store()/edit()/update()`; в `create()`/`store()`
  `$product === null`, поэтому кнопка генерации там не рендерится сама
  собой без явной проверки `$isEdit` во view.
- `src/Controllers/SearchController.php::suggest()` — образец JSON-
  эндпоинта: `Content-Type: application/json`, `tooManyAttempts()` +
  `hitRateLimit()`, `http_response_code(429)`, `JSON_UNESCAPED_UNICODE`.
- `src/Controllers/AdminAiSpecController.php::run()` — образец вызова
  модели: `aiComplete('description', $messages)` возвращает
  `['text','tokens_in','tokens_out']` или `null`; `null` уже покрывает
  недоступность класса, сетевую ошибку и таймаут — `aiComplete()`
  сам пишет `ai_requests`/`logError()`, контроллеру повторно логировать
  не нужно.
- Отдельного meta-тега с CSRF-токеном в `layout/header.php` нет — форма
  `#product-form` уже содержит скрытый `_csrf` через `csrfField()`; JS
  читает токен оттуда же (`document.querySelector('#product-form
  input[name="_csrf"]')`), `layout/header.php` трогать не нужно.
- `src/Views/admin/ai/specs/index.php` (Таск 2) — образец UI-приёма при
  недоступном классе: кнопка не рендерится вовсе (алерт вместо неё), не
  просто `disabled` — тот же приём переносится сюда.
- `database/install.php` (строки 711–726) — готовый идемпотентный
  паттерн добавления колонки через `information_schema` (на примере
  `products.specs_status`); `description_draft` добавляется тем же
  способом.
- `public/assets/js/admin.js` — уже содержит несколько независимых IIFE
  на `admin`-специфичные блоки формы Товара (variant-row, spec-row) —
  новый блок генерации описания добавляется рядом, отдельным IIFE, без
  своих глобальных переменных.

## Scope — что трогаем
- [ ] `database/install.php` — изменить: `products.description_draft
      TEXT NULL`, идемпотентно через `information_schema` (по образцу
      `specs_status`)
- [ ] `.docs/database.md` — изменить: колонка `description_draft` в
      разделе таблицы `products`
- [ ] `src/Core/AiDescription.php` — создать, чистые функции:
      `validateDescriptionBrief(array $input): array` (категория/
      материал/размер/механизм — `trim`, лимит длины, пустые поля
      отбрасываются, не подставляются), `buildDescriptionPrompt(array
      $brief): array`, `normalizeDraft(string $text): string` (обрезка
      длины, снятие markdown-обрамления, `trim`)
- [ ] `src/Models/Product.php` — изменить: `description_draft`
      добавлен в `SELECT` внутри `findProductForAdmin()`;
      `saveDescriptionDraft(int $productId, string $draft): void` —
      новая функция
- [ ] `src/Controllers/AdminProductController.php` — изменить:
      подключить `Core/Ai.php`, `Core/AiDescription.php`,
      `Services/Ai/ai.php`; `generateDescription(string $id)` —
      `requireRole(['admin'])`, `requireCsrf()`,
      `tooManyAttempts('ai_description', 10, 60)`/`hitRateLimit()`,
      JSON-ответ `{draft}` / `{unavailable: true}` / `{error}`;
      `renderForm()` — передаёт `aiDescriptionAvailable`
      (`aiClassAvailable(aiClassForAssistant('description'))`) во view
- [ ] `src/Views/admin/products/form.php` — изменить: блок «Краткие
      данные для описания» (категория/материал/размер/механизм),
      кнопка «Сгенерировать черновик», поле черновика, кнопка
      «Применить к описанию» — видны только когда `$isEdit &&
      $aiDescriptionAvailable`; при `$isEdit` и недоступном классе —
      алерт вместо кнопки; на create ничего из этого не рендерится
- [ ] `public/assets/js/admin.js` — изменить: новый IIFE — `fetch` на
      `/admin/products/{id}/ai-description` с `_csrf` из формы,
      состояние «генерируется», рендер черновика, «Применить» копирует
      текст в `#product-description`, обработка `unavailable`/429/сети
- [ ] `config/routes.php` — изменить: `POST
      /admin/products/{id}/ai-description`
- [ ] `tests/Unit/AiDescriptionTest.php` — создать: тесты на
      `validateDescriptionBrief()` (пустые поля отбрасываются, лишнее
      не подставляется) и `normalizeDraft()` (обрезка длины, снятие
      markdown, пустая строка)
- [ ] `tests/bootstrap.php` — изменить: подключить
      `src/Core/AiDescription.php`

## Out of scope — не трогаем
- Консультант в чате, подбор товара диалогом, бюджет/лимит — Таски 5–8
- Кнопка/эндпоинт генерации на `/admin/products/create` — не делаем,
  показывается только на уже сохранённом Товаре
- `product_specs`, `specs_status`, экран `/admin/ai/specs/*` — из
  Тасков 2–3, не трогаются
- Основной сабмит формы Товара (`store()`/`update()`,
  `collectRawInput()`, `updateProductWithVariants()`) — черновик не
  идёт через обычное сохранение формы, применяется отдельной
  JS-кнопкой, которая лишь копирует текст в поле «Описание»
- Автогенерация черновика при смене полей без явного нажатия кнопки
- `src/Views/layout/header.php` — CSRF уже доступен через `#product-form`

## Definition of Done
- [ ] Краткие данные → черновик появляется в отдельном поле;
      `products.description` в БД не изменился (`SELECT` до и после)
- [ ] `products.description_draft` в БД после генерации соответствует
      показанному тексту (`SELECT`)
- [ ] «Применить к описанию» + сохранение формы → `products.description`
      обновился, изменение видно на витрине (`/product/{slug}`)
- [ ] Три генерации подряд на разных кратких данных: в черновике нет
      характеристик, которых не было во введённых данных (ручная
      проверка, правило 3 `FR-AI-002`)
- [ ] Провайдер выключен (ключ снят) → кнопки и блока «Краткие данные»
      нет, алерт вместо них; создание и сохранение Товара проходят как
      обычно, описание вводится вручную
- [ ] `manager` не видит блок «Краткие данные»/кнопку на форме Товара;
      прямой POST от `manager` на
      `/admin/products/{id}/ai-description` → отказ, `description_draft`
      не создан
- [ ] POST без CSRF → 419; частые повторные нажатия → 429 после лимита
- [ ] `composer test` зелёный
- [ ] Проверить `.docs/dod-global.md`

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
