# Current Task

## Фаза
Phase 8 — Админ-панель (контент и доступ) и статические страницы
(`.docs/phases/phase-8.md`), Таск 1 из 8.

**Статус:** ✅ Завершён 21.09.2026 — проверено на реальной БД живым
HTTP (`php -S` + `curl`): `install.php` дважды → 7 строк, ручная
правка `title` сохранена; 7 slug → 200 с текстом из БД,
`/pages/nope`, `/pages/About`, `/pages/..%2Fetc` → 404; `<script>` и
теги в теле экранированы (временная подмена строки, откачена);
`image_path` рендерится `img-fluid` (временное значение, откачено).
`composer test` — 350/350 (+13). Реализовано по плану ниже; одно
уточнение: в `header.php` заменены обе копии ссылок (десктоп и
мобильное меню). Вёрстка 320px — структурно та же, что у корзины/
кабинета, новых CSS нет.

## Задача
`FR-CNT-004`, `FR-CNT-005`, `FR-CNT-006`: страницы «Доставка и оплата»,
«Возврат и гарантия», «Публичная оферта», «Политика конфиденциальности»
открываются по `/pages/{slug}` с заголовком и текстом из
`content_pages` (7 строк сидятся в `install.php`); текст хранится
плоским с мини-разметкой и рендерится чистой `renderContentBody()`
(решение фазы: не HTML — `phase-8.md`, «Решения фазы»). `about`,
`contacts`, `showroom` пока отдаются тем же общим шаблоном — свои View
у них появятся в Тасках 3–4. Все `href="#"` на страницы CNT в шапке и
футере становятся живыми ссылками.

## Что проверено в коде перед планом
- `abort404()` есть (`ProductController::show()`) — для неизвестного
  slug.
- `components/breadcrumbs.php` с `$breadcrumbs = [['name' => '...']]`
  рендерит одну крошку без ссылки (так делает `cart/index.php`) —
  переиспользуется без правок.
- Сид `INSERT IGNORE` по `UNIQUE(slug)` — тот же приём, что
  `sales_channels` в `install.php`; повторный запуск не перетрёт
  отредактированный текст.
- `tests/bootstrap.php` подключает Core-файлы явным `require_once` —
  нужна одна строка.

## Scope — что трогаем

- [x] `src/Core/Content.php` — создать: `CONTENT_PAGE_SLUGS` (список из
      7 slug: `contacts`, `about`, `showroom`, `delivery-payment`,
      `return-warranty`, `offer`, `privacy-policy` — только whitelist,
      заголовки живут в БД, чтобы не было двух источников),
      `isContentPageSlug(string): bool`, `renderContentBody(string):
      string` — чистые функции без БД. Правила разметки: `\r\n` → `\n`;
      текст режется на блоки по пустым строкам; блок из строк `- ` →
      `<ul><li>…</li></ul>`; строка `## ` → `<h2>`; остальное → `<p>`,
      переносы строк внутри блока → `<br>`; каждый текстовый фрагмент
      через `e()`; пустой/пробельный ввод → `''`
- [x] `tests/Unit/ContentTest.php` — создать: абзацы, `<br>` внутри
      абзаца, `h2`, список, смешанный документ, `<script>` экранируется,
      пустая строка, `\r\n`, slug-whitelist (в т.ч. `../etc`, `About`,
      пустая строка)
- [x] `tests/bootstrap.php` — изменить: `require_once` `Core/Content.php`
- [x] `src/Models/ContentPage.php` — создать:
      `findContentPageBySlug(string): ?array`, `getAllContentPages():
      array` (второй нужен Таску 6, заводится сразу как часть Model)
- [x] `src/Controllers/PageController.php` — создать: `show(string
      $slug)` — slug вне whitelist или строки нет → `abort404()`;
      `render('pages/show', ['title' => $page['title'], 'page' =>
      $page, 'bodyHtml' => renderContentBody($page['body'])])`
- [x] `src/Views/pages/show.php` — создать: `page-banner-section` с
      `<h1>` и крошкой (`components/breadcrumbs.php`),
      `<main>`/`<article>`, фото (`image_path`, `alt` = заголовок) если
      есть, тело — единственный `<?= $bodyHtml ?>` без `e()` (уже
      экранировано внутри `renderContentBody()` — комментарий об этом
      прямо во View)
- [x] `database/install.php` — изменить: `INSERT IGNORE` 7 строк
      `content_pages` со стартовыми текстами в мини-разметке по ТЗ:
      `delivery-payment` — самовывоз по записи, собственная доставка,
      оплата картой на сайте / наличными / переводом, предоплата
      30–50 %, остаток при получении (`FR-CNT-004`); `return-warranty`
      — возврат только по звонку, целиком, без автоматического
      возврата денег, гарантия производителя 18 месяцев (`FR-CNT-005`);
      `offer` и `privacy-policy` — типовые шаблоны (152-ФЗ) с
      реквизитами-плейсхолдерами (`FR-CNT-006`); `about`, `contacts`,
      `showroom` — короткие стартовые тексты
- [x] `config/routes.php` — изменить: `GET /pages/{slug}` →
      `['PageController', 'show']`
- [x] `src/Views/layout/header.php` — изменить: «О компании» →
      `/pages/about`, «Доставка и оплата» → `/pages/delivery-payment`,
      «Контакты» → `/pages/contacts` (первая и третья — временно,
      Таски 3–4 переключат на `/about`, `/contacts`)
- [x] `src/Views/layout/footer.php` — изменить: колонка «Покупателям» —
      «Доставка и оплата» (вместо двух пунктов «Оплата»/«Доставка» на
      одну и ту же страницу), «Возврат и гарантия», «Публичная оферта»,
      «Политика конфиденциальности» (вместо «Условия использования»);
      колонка «Информация» — «О компании» → `/pages/about`, «Контакты»
      → `/pages/contacts` (временные URL, см. выше)
- [x] `.docs/planning-log.md` — изменить: `ADR-044` — мини-разметка
      вместо HTML в `content_pages.body` (почему: самописный
      whitelist-санитайзер — риск XSS; Менеджер по `php.md` такой же
      внешний ввод; правило 3 `FR-ADM-003` ограничивает редактирование
      текстом и фото)
- [x] `.docs/database.md` — изменить: у `content_pages.body` уточнение
      формата (плоский текст с мини-разметкой, рендер только через
      `renderContentBody()`, ссылка на `ADR-044`)
- [x] `.docs/dev-log.md` — запись по итогам таска

## Out of scope — не трогаем
- Свои View и маршруты `/about`, `/contacts`, `/showroom`, блок команды,
  карта шоурума, форма обратного звонка — Таски 3–4
- Таблица `settings`, замена констант `SHOP_*`, раздел «Настройки» —
  Таск 2
- Редактирование текстов/фото в Панели управления,
  `storeContentImage()`, `UPLOAD_CONTENT_DIR` — Таск 6; баннеры —
  Таск 7; сотрудники — Таск 8
- Правки `components/breadcrumbs.php` — используется как есть
- Общая `meta description` в `header.php` (сейчас одна на весь сайт) —
  per-page SEO-мета в этом таске не заводится; упомянуть в dev-log как
  возможное улучшение
- Ссылки-согласия на оферту/политику в формах чекаута и регистрации —
  не в ТЗ Фазы 8, только упомянуть в dev-log
- Любой рефакторинг `header.php`/`footer.php` сверх замены `href`;
  `_status.md` — статус Фазы 8 → 🔄 при старте этого таска

## Definition of Done
- [x] `GET /pages/delivery-payment`, `/pages/return-warranty`,
      `/pages/offer`, `/pages/privacy-policy` — 200, `<h1>` и текст из
      БД; `/pages/about`, `/pages/contacts`, `/pages/showroom` — 200
      общим шаблоном
- [x] `/pages/nope`, `/pages/About`, `/pages/..%2Fetc` — 404 через
      `abort404()`, не 500; в `storage/logs/app.log` нет ошибок и
      warnings
- [x] Строка `content_pages` с телом `<script>alert(1)</script>`
      показывает текст буквально; `## Заголовок`, список `- ...`, абзацы
      через пустую строку, перенос внутри абзаца рендерятся в
      `h2`/`ul`/`p`/`br` (проверить на реальной БД временной правкой
      строки, откатить после проверки)
- [x] `php database/install.php` дважды подряд → ровно 7 строк
      `content_pages`; отредактированный вручную `title` после второго
      запуска не перезаписан
- [x] В `header.php`/`footer.php` не осталось `href="#"` на страницы CNT
      (`grep -n 'href="#"' src/Views/layout/` — остаются только
      дропдауны с `role="button"` и `.back-to-top`); все новые ссылки
      открываются, `href` ведут на существующие маршруты
- [x] Страница корректна на 320px (тот же `page-banner-section`/
      `section-padding`, что у корзины); фото страницы не выходит за
      контейнер; в HTML нет внешних адресов
- [x] Нет SQL в `PageController`/View; вывод через `e()`, кроме
      единственного `$bodyHtml`
- [x] `composer test` зелёный: было 337/337, плюс тесты `ContentTest`
- [x] Проверить `.docs/dod-global.md`

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
- Каждый шаг проверяется тем, что указано в DoD: чистая логика —
  `composer test`, маршруты/404/сид — живым HTTP и SQL на реальной БД,
  вёрстка — вручную в браузере на 320px
