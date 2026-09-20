# Current Task

## Фаза
Phase 6 — Скидки, отзывы и главная страница
(`.docs/phases/phase-6.md`), Таск 5 из 6.

**Статус:** ✅ Завершён 20.09.2026. Проверено на реальной БД живым HTTP
(`php -S` + `curl`, cookie-сессия) на реальной строке `reviews` (id=7)
и через временный customer-аккаунт для проверки роли. `composer test`
— 296/296 (было 295/295, +1). Все тестовые строки/аккаунты удалены
после проверки, `id=7` возвращён в исходный `pending`.

## Задача
`FR-ADM-004` — `/admin/reviews`: очередь модерации с фильтром по
статусу (по умолчанию `pending`), тип «о Товаре» (ссылка на Товар) /
«о магазине», действия «Одобрить» / «Отклонить», бейдж с числом
`pending` в сайдбаре. Плюс форма ручного добавления отзыва о магазине
(`FR-HOME-007` правило 5: Владелец переносит отзывы из
Instagram/WhatsApp) — `product_id = NULL`, сразу `approved`. Автор
отклонённого отзыва не уведомляется (`FR-ADM-004` правило 3).

Три уточнения против черновика `phase-6.md` по факту сверки с кодом
(первые два обсуждены и подтверждены пользователем перед реализацией,
третье — решение, принятое в процессе кодирования по образцу уже
одобренного паттерна Таска 4, не требовало отдельного подтверждения):

1. **`config/config.php` добавлен в Scope** — черновик фазы не включал
   его, но `AdminReviewController::index()` не может вызвать
   `buildPagination()` без своей константы (по образцу
   `ADMIN_ORDERS_PER_PAGE`, `ADMIN_RETURNS_PER_PAGE` и т.д., уже
   заведённых построчно в этом файле).
2. **`setReviewStatus()` не может полагаться только на
   `rowCount()`** — соединение PDO не выставляет
   `MYSQL_ATTR_FOUND_ROWS` (`Core/Database.php`), поэтому `UPDATE ...
   WHERE id = :id` на уже `approved` строке тоже вернёт
   `rowCount() === 0`, как и на несуществующем `id`. DoD различает эти
   два случая (повторное «Одобрить» — успех, несуществующий id —
   «не найден»), значит при `rowCount() === 0` нужна отдельная
   проверка существования строки, а не единственный сигнал
   `rowCount()`.
3. **`storeShopReview()` при ошибке валидации — прямой рендер
   `index()` с `$old`/`$errors`, не `setFlash()` + `redirect`** (как
   исходно записано в Scope ниже): `setFlash()` хранит только строку,
   массив ошибок по полям не переживёт редирект — тот же приём, что
   `ReviewController::store()` (Таск 4) и `AdminCategoryController`
   уже используют в проекте; без этого `dod-global.md` («Некорректные
   данные — поля подсвечиваются с ошибкой») не выполняется для формы
   отзыва о магазине.

## Scope — что трогаем

- [x] `config/config.php` — изменить: константа `ADMIN_REVIEWS_PER_PAGE`
      (по аналогии с `ADMIN_RETURNS_PER_PAGE`)
- [x] `src/Models/Review.php` — изменить:
      `getAdminReviews(array $filters, int $page, int $perPage): array`
      (`LEFT JOIN products` для ссылки на Товар и его `slug`/`name`, по
      образцу `getAdminReturns()`), `countAdminReviews(array $filters):
      int`, `setReviewStatus(int $id, string $status): bool` (см.
      уточнение 2 выше — при `rowCount() === 0` дополнительно проверяет
      существование строки, чтобы вернуть `true` на идемпотентном
      повторе и `false` только когда `id` реально не существует),
      `countPendingReviews(): int`, `createStoreReview(array $data):
      int` (`product_id = NULL`, `status = 'approved'` сразу в
      `INSERT`, без отдельного `setReviewStatus()`; сигнатура — `int`,
      не `?int` из черновика: у отзыва о магазине нет уникальных
      ограничений, которые могли бы дать `INSERT` провалиться)
- [x] `src/Controllers/AdminReviewController.php` — создать:
      `index(array $storeReviewOld = [], array $storeReviewErrors = [])`
      — `requireRole(['manager','admin'])`, статус из
      `input('status', 'pending')` через whitelist
      `REVIEW_STATUS_PENDING/_APPROVED/_REJECTED` (иначе — `pending`, по
      образцу `AdminOrderController::index()`), `countAdminReviews()` +
      `buildPagination()` + `getAdminReviews()`, `render('admin/reviews/
      index', ...)`; `approve(string $id)` / `reject(string $id)` —
      `requireRole(...)`, `requireCsrf()`, `setReviewStatus()` →
      `false` → `setFlash('error', 'Отзыв не найден.')`, `true` →
      `setFlash('success', ...)`, оба варианта `redirect('/admin/
      reviews')`; `storeShopReview()` — `requireRole(...)`,
      `requireCsrf()`, `normalizeReviewInput()` → `validateReviewInput()`
      (тот же валидатор формы витрины, `product_id` не участвует в
      проверке) → при ошибке `$this->index($input, $errors)` без
      редиректа (см. уточнение 3), при успехе `createStoreReview()` +
      `setFlash('success', ...)` + `redirect('/admin/reviews')`
- [x] `src/Views/admin/reviews/index.php` — создать: фильтр статуса
      (select, как `admin/orders/index.php`), список (автор, рейтинг,
      тип со ссылкой на Товар при `product_id` / «о магазине», дата,
      текст, кнопки «Одобрить»/«Отклонить» — показаны всегда, действие
      идемпотентно на бэкенде), пустое состояние, серверная пагинация
      (`paginationLinks`, `prevUrl`/`nextUrl`, по образцу
      `admin/returns/index.php`), форма добавления отзыва о магазине
      (имя, email, рейтинг, текст, `csrfField()`, подсветка ошибок по
      полю через `$storeReviewOld`/`$storeReviewErrors`)
- [x] `src/Views/layout/admin-header.php` — изменить: пункт «Отзывы»
      (`/admin/reviews`) в `$adminNavItems` с бейджем — количество
      считается прямым вызовом `countPendingReviews()` внутри этого
      файла (по образцу `currentUser()`/`requestPath()`, вызываемых там
      же напрямую, без передачи через `render()`), требует
      `Models/Review.php`
- [x] `config/routes.php` — изменить: `GET /admin/reviews` →
      `['AdminReviewController', 'index']`,
      `POST /admin/reviews` → `['AdminReviewController',
      'storeShopReview']`,
      `POST /admin/reviews/{id}/approve` → `['AdminReviewController',
      'approve']`,
      `POST /admin/reviews/{id}/reject` → `['AdminReviewController',
      'reject']`
- [x] `tests/Unit/ReviewTest.php` — изменить: кейс на
      `validateReviewInput()` для формы отзыва о магазине (те же
      данные, но без `product_id` в входном массиве — подтверждает, что
      валидатор не завязан на его наличие)

## Out of scope — не трогаем

- Уведомление автора отзыва (email/SMS) при отклонении/одобрении —
  `FR-ADM-004` правило 3 прямо запрещает это для отклонения; для
  одобрения ТЗ уведомление не требует
- Блоки Главной, включая «Отзывы о магазине» и их вывод на `/` (Таск 6)
- `src/Views/product/show.php`, `review-form.php`, `review-list.php` —
  витринная часть отзывов уже сделана в Таске 4, не трогали
- Изменение схемы БД / `database/install.php` — `reviews` заведена с
  Фазы 0 (`ADR-015`)
- Массовые действия (одобрить/отклонить несколько отзывов сразу) — не
  в ТЗ, каждая строка — отдельное действие
- Редактирование текста отзыва Менеджером — ТЗ не описывает такую
  функцию, только одобрение/отклонение
- Любой рефакторинг за пределами перечисленных файлов

## Definition of Done

- [x] Одобрение → отзыв появляется на карточке Товара; отклонение → не
      появляется, никаких писем/СМС автору — проверено живым HTTP на
      реальной БД: `id=7` → `approved` → появился на
      `/product/shkaf-klassik` (средняя оценка «3.0 из 5»); → `rejected`
      → пропал
- [x] Повторное «Одобрить» на уже `approved` — успех (идемпотентно, не
      «не найден»); запрос с несуществующим `id=999999` → flash «Отзыв
      не найден.», строка в `reviews` не создана и не изменена —
      проверено
- [x] Отзыв о магазине из формы: в БД `product_id IS NULL`,
      `status = 'approved'` сразу после отправки — проверено (запись с
      кириллическим именем через percent-encoded файл, обойдя тот же
      Bash `--data-urlencode`-артефакт, что и в Таске 4); пустая форма
      → все 4 поля подсвечены `is-invalid`, значения сохранены при
      повторном показе
- [x] Фильтр `pending`/`approved`/`rejected`/без параметра (все) —
      проверено; мусорное значение `?status=garbage` → 200, откат к
      `pending`, не 500; бейдж в сайдбаре виден на любой странице
      Панели (`/admin/orders` тоже) и совпадает с фактическим числом
      `pending`
- [x] `customer` (временный тестовый аккаунт) по `/admin/reviews` (GET
      и POST) → редирект на `/`; незалогиненный → `/login`; POST без
      CSRF-токена → 419 — проверено, действие не выполняется ни в
      одном из случаев
- [x] `composer test` зелёный (296/296, было 295/295, +1)
- [x] Проверить `.docs/dod-global.md`

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
- На каждый шаг — чем проверяется (пункт DoD / unit-тест / ручная
  проверка на реальной БД/HTTP), не только что сделать
