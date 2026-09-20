# Current Task

## Фаза
Phase 6 — Скидки, отзывы и главная страница
(`.docs/phases/phase-6.md`), Таск 4 из 6.

**Статус:** ✅ Завершён 20.09.2026. Проверено на реальной БД живым HTTP
(`php -S` + `curl`, cookie-сессия) на реальном Товаре `divan-milan`.
`composer test` — 295/295 (было 274/274, +21). Один баг найден и
исправлен на HTTP-проверке — `ProductController.php` не требовал
`Core/Review.php` напрямую (только транзитивно через `Models/`),
`averageRating()` резолвилась в неймспейс `App\Controllers` и падала
как неопределённая функция на обычном `GET /product/{slug}`. Подробности
— `.docs/dev-log.md` 20.09.2026.

## Задача
`FR-CARD-006` — на карточке товара появляется вкладка отзывов: список
одобренных (имя, рейтинг, дата, текст), средний рейтинг и число
отзывов, форма (имя, email, рейтинг 1–5, текст). Отправленный отзыв
всегда `pending` и не виден никому до модерации (`FR-ADM-004` правило
1); Покупатель видит flash «отзыв появится после проверки».

Два места, где план скорректирован против черновика `phase-6.md` по
факту сверки с кодом/темой (см. переписку перед записью файла):

1. **Рейтинг — 5 нативных radio (`name="rating"`), стилизованных под
   звёзды чистым CSS**, а не декоративный `<ul id="rating">` темы:
   обработчик в `main.js` только переключает CSS-классы по клику,
   ничего никуда не пишет — буквальная разметка темы не даст отправить
   значение вообще, тем более без JS.
2. **Ошибки формы — прямой рендер карточки, не redirect+flash**, по
   образцу `CheckoutController::store()`/`AdminOrderController::store()`
   (`setFlash()` хранит только строку, не массив ошибок по полям).
   `ReviewController::store()` при ошибке вызывает
   `(new ProductController())->show($slug, $old, $errors)` напрямую.
   Средний рейтинг — чистая `averageRating()` над уже полученными
   строками `getApprovedProductReviews()`, без отдельного SQL-агрегата.

## Scope — что трогаем

- [x] `src/Core/Review.php` — создать: `REVIEW_STATUS_PENDING/
      _APPROVED/_REJECTED`, `reviewStatusLabel()`,
      `normalizeReviewInput(array $input): array` (trim имя/email/
      текст, каст рейтинга к `?int`), `validateReviewInput(array
      $input): array` (имя 2–150, email — `validateEmail()`, рейтинг ∈
      {1..5}, текст непустой ≤2000 символов — по образцу
      `validateCheckoutInput()`, массив `['field' => bool hasError]`),
      `averageRating(array $reviews): ?string` (среднее округлено до 1
      знака, `null` на пустом массиве) — чистые функции без БД
- [x] `tests/Unit/ReviewTest.php` — создать: `normalizeReviewInput()`,
      `validateReviewInput()` (валидные данные, короткое/длинное имя,
      невалидный email, рейтинг вне 1–5, пустой/слишком длинный текст),
      `averageRating()` (пусто → `null`, округление); `tests/
      bootstrap.php` — изменить: подключить `Core/Review.php`
- [x] `src/Models/Review.php` — создать: `createReview(array $data):
      int` (`status='pending'`), `getApprovedProductReviews(int
      $productId): array` (по `INDEX(product_id, status)`, `ORDER BY
      created_at DESC`)
- [x] `src/Controllers/ReviewController.php` — создать: `store(string
      $slug)` — `requireCsrf()`, `findProductBySlug()` (нет/неактивен
      → 404), `tooManyAttempts('review', 3, 600)`/`hitRateLimit
      ('review')` (лимит → `setFlash('error', ...)` + `redirect`),
      `normalizeReviewInput()` → `validateReviewInput()` → при ошибке
      `(new ProductController())->show($slug, $old, $errors)` (без
      redirect), при успехе — `createReview()` + `setFlash('success',
      'Отзыв появится после проверки.')` + `redirect('/product/{slug}
      #reviews')`
- [x] `src/Controllers/ProductController.php` — изменить: `show(string
      $slug, array $reviewOld = [], array $reviewErrors = [])` —
      подключает `getApprovedProductReviews()`, `averageRating()`,
      подстановку имени/email авторизованного Покупателя
      (`currentUser()` + `findUserById()`, по образцу
      `CheckoutController`); требует `Models/Review.php`,
      `Models/User.php`. **Дополнительно (баг, найден на HTTP-проверке):**
      также требует сам `Core/Review.php` напрямую — без него
      `averageRating()` не была загружена на обычном `GET`, только на
      `POST` через `ReviewController`
- [x] `src/Views/product/show.php` — изменить: блок вкладок
      перестраивается — вкладка «Отзывы» присутствует всегда (счётчик,
      форма — даже без отзывов); навигация (`<ul class="nav">`)
      показывается, когда вкладок больше одной (было: только когда
      есть и характеристики, и описание одновременно), активна по
      умолчанию первая доступная в порядке характеристики → описание →
      отзывы
- [x] `src/Views/components/review-form.php` — создать: имя/email/
      radio-рейтинг (5 шт., `name="rating"`)/текст, `csrfField()`,
      подсветка ошибок по полю, сохранённые значения при ошибке
- [x] `src/Views/components/review-list.php` — создать: список
      одобренных отзывов + средний рейтинг/счётчик; пусто — «Отзывов
      пока нет», без ошибки
- [x] `public/assets/css/app.css` — изменить: CSS-звёзды для
      radio-рейтинга (BEM, mobile-first, полностью на CSS, без JS)
- [x] `config/routes.php` — изменить: `POST /product/{slug}/reviews` →
      `['ReviewController', 'store']`

## Out of scope — не трогаем

- Модерация отзывов в Панели управления, отзыв о магазине (Таск 5)
- Блоки Главной, включая «Отзывы о магазине» (Таск 6)
- `getProductRatingSummary()` как отдельный SQL-агрегат — заменена
  чистой `averageRating()` над строками, уже полученными
  `getApprovedProductReviews()`
- Пагинация списка отзывов Товара — реальный масштаб проекта (единицы
  отзывов на товар, тот же аргумент, что уже применялся в
  `database.md`/`dev-log.md` для каталога на ≤200 товаров); решение
  зафиксировано явно, не молчаливый пропуск пункта `dod-global.md`
- Декоративный JS-виджет звёзд из `main.js` (`#rating li` hover/click)
  — не синхронизируется с формой ни в каком виде, используется
  CSS-only рейтинг вместо него; сам `main.js` не трогаем
- Изменение схемы БД / `database/install.php` — таблица `reviews`
  заведена с Фазы 0 (`ADR-015`)
- `src/Views/components/product-card.php`, `cart-row.php`,
  `checkout-summary.php` — цена/скидки, не отзывы, уже сделаны в
  Тасках 1–3
- Любой рефакторинг за пределами перечисленных файлов

## Definition of Done

- [x] Отправленный отзыв: строка `reviews` со `status='pending'`,
      `product_id` заполнен; на карточке **не виден** — проверено
      живым HTTP на реальной БД (`divan-milan`, реальное кириллическое
      имя «Иван Петров»)
- [x] Пустая форма → все 4 поля подсвечены с ошибкой, вкладка «Отзывы»
      открыта сразу, введённые значения (кроме рейтинга — radio)
      сохранены при повторном показе формы — проверено
- [x] Без JS: 5 radio с `name="rating"` реально кликабельны и
      отправляют значение — подтверждено кодом (никакой JS для
      рейтинга не подключается) и живой отправкой формы через `curl`
      (без браузера/JS вообще)
- [x] 419 без CSRF; 4-я отправка за 10 минут → flash-ограничение
      «Слишком много отзывов подряд», запись не создана — проверено
      живым HTTP (3 успешные + 1 заблокированная)
- [x] Вручную `approved` в БД (2 отзыва, рейтинги 4 и 2) → оба видны,
      средний рейтинг «3.0 из 5 (2)» посчитан верно; `pending` — не
      видна
- [x] Отзыв на несуществующий Товар (подмена `slug` в POST) → 404,
      запись не создана — проверено
- [x] Email автора нигде не выводится в HTML — `grep` по обоим тестовым
      адресам в отданном HTML дал пустой результат; весь вывод через
      `e()`
- [x] Авторизованный Покупатель — имя/email в форме подставляются
      (`currentUser()` + `findUserById()`); код-ревью, отдельно живым
      HTTP под авторизацией не прогонялось в этом таске
- [x] `composer test` зелёный (295/295), включая `ReviewTest`
- [x] Проверить `.docs/dod-global.md`

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
- На каждый шаг — чем проверяется (пункт DoD / unit-тест / ручная
  проверка на реальной БД/HTTP), не только что сделать
