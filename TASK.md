# Current Task

## Фаза
Phase 4 — Панель менеджера и каталог в админке (`.docs/phases/phase-4.md`, Таск 1)

**Статус:** ✅ Завершён — код реализован и проверен. `composer test`
127/127 (2 новых теста `orderStatusBadgeClass()`). Проверено `php -S`
+ `curl` против реальной БД (`mikhail700.beget.tech`): Гость по
`/admin` → 302 `/login`; логин `admin@domform.local` → редирект на
`/admin`, 200, счётчики совпали с ручным `SELECT status, COUNT(*)
FROM orders GROUP BY status` (`new => 1`, остальные 0); имя
«Волков Сергей» и весь сайдбар (7 пунктов) присутствуют; в HTML нет ни
одного `http(s)://`-адреса. `customer` (сессия подставлена вручную с
`role=customer`) по `/admin` → 302 `/`. «Выйти» — POST с CSRF → 302 `/`,
повторный `/admin` тем же cookie → 302 `/login`. `storage/logs/app.log`
не пополнился новыми записями. Не проверено вручную (нет браузера в
сессии): реальная раскладка сайдбара на 320px и консоль JS — тот же
пробел, что в `dev-log.md` 17.09.2026 для Таска 2 Фазы 0.

## Задача
`/admin` открывается в собственном layout Панели управления (сайдбар:
Заказы, Новый заказ, Товары, Категории, Клиенты, Отчёты; имя текущего
пользователя и «Выйти»), показывает счётчики Заказов по 7 статусам со
ссылками на будущий фильтр списка (`/admin/orders?status=…`);
Покупатель → редирект на `/`, Гость → `/login` (уже делает
`requireRole()`). Пункты меню для ещё не построенных экранов ведут на
маршруты, которые появятся в следующих тасках — до этого 404 допустим.

## Scope — что трогаем

- [x] `public/assets/admin/` — создан: куратированный минимум из
      `00-input/admin/assets/` — `css/styles.min.css`, `css/icons.css`,
      `icon-fonts/` (только те, на которые ссылается `icons.css`),
      `libs/bootstrap/`, `libs/@popperjs/`, `libs/simplebar/`,
      `js/defaultmenu.min.js`, `js/sticky.js`, `js/simplebar.js`,
      `js/main.js`; `custom-switcher`, `pickr`, `choices`, `flatpickr`,
      `node-waves`, `jsvectormap`, `apexcharts` — не скопированы.
      Из копии `styles.min.css` вырезан единственный CDN-`@import`
      (Google Fonts) — единственная найденная зависимость от внешнего
      хоста в ассетах Valex (`ADR-036`)
- [x] `src/Views/layout/admin-header.php` — создан: `<html>`, ассеты
      админки, сайдбар с навигацией (активный пункт по текущему URI,
      с учётом самого длинного совпадающего префикса), верхняя панель
      с именем пользователя и формой `POST /logout` (`csrfField()`),
      `flash.php`
- [x] `src/Views/layout/admin-footer.php` — создан: подключение JS
      админки + `admin.js`
- [x] `src/Views/admin/index.php` — переписан: дашборд — 7 карточек-
      счётчиков по статусам с градиентом по цвету бейджа и ссылкой на
      `/admin/orders?status=…`
- [x] `src/Views/components/admin/order-status-badge.php` — создан:
      `$status` → `<span class="badge bg-…">Подпись</span>` через
      `orderStatusLabel()` + `orderStatusBadgeClass()`
- [x] `src/Core/OrderStatus.php` — изменён: `orderStatusBadgeClass(string
      $status): string` — 7 статусов → 7 классов `bg-*`
      (`new`→`bg-secondary`, `confirmed`→`bg-info`,
      `in_production`→`bg-primary`, `ready_for_shipment`→`bg-warning
      text-dark`, `shipping`→`bg-warning`, `delivered`→`bg-success`,
      `cancelled`→`bg-danger`), неизвестный → `bg-light text-dark`
- [x] `tests/Unit/OrderStatusTest.php` — изменён: тест для всех 7
      статусов и для неизвестного
- [x] `src/Models/Order.php` — изменён: `countOrdersByStatus(): array`
      — `['new' => 3, …]`, все 7 ключей, 0 для отсутствующих
- [x] `src/Controllers/AdminController.php` — изменён: `index()`
      передаёт счётчики
- [x] `public/assets/js/admin.js` — создан: пустой IIFE
      (`'use strict'`), точка входа для следующих тасков
- [x] `src/Core/functions.php` — изменён: `requireRole()` — авторизованный
      пользователь с неподходящей ролью → `redirect(homeUrlForRole($user['role']))`
      вместо 403 (только для этого случая; гость по-прежнему → `/login`);
      требовалось буквальным DoD этого таска («Покупатель по `/admin` →
      редирект на `/`»), решение подтверждено пользователем
- [x] `.docs/planning-log.md` — изменён: `ADR-036` — второй layout на
      ассетах Valex, `admin.js` как второй файл своего JS, вырезанный
      CDN-импорт шрифта
- [x] `public/assets/css/admin.css` — создан (по мотивам ручной
      проверки пользователем в браузере): точечное переопределение
      `.app-sidebar .side-menu__icon` — `styles.min.css` задаёт
      `line-height:34px` при `height:1.375rem`, из-за чего глиф иконки
      вылезал за пределы бокса и визуально не совпадал по центру с
      подписью пункта меню; `display:inline-flex` + `align-items`/
      `justify-content:center` центрируют содержимое независимо от
      метрик конкретного шрифта иконок. Также на ширине ≤991.98px
      возвращены `.main-sidebar-header{display:flex!important}` и
      `.main-sidebar{margin-block-start:4rem!important}` — тема на
      этой ширине безусловно гасит блок-логотип сайдбара и обнуляет
      отступ под него, из-за чего открытое мобильное меню прижималось
      к верхнему краю экрана. Подключён последним в `admin-header.php`,
      тему (`styles.min.css`) не трогаем — тот же приём, что
      `public/assets/css/app.css` у витрины

## Out of scope — не трогаем

- Список/карточка Заказа, действия над Заказом, редактирование
  состава, ручное создание Заказа (Таски 2–5 этой же фазы)
- Клиенты, Категории/Товары, форма Товара, фото Вариантов, отчёт по
  продажам (Таски 6–10 этой же фазы)
- Пункты меню, ведущие на ещё не построенные экраны — 404 допустим до
  соответствующего таска
- Резерв, Выставочный образец, STOCK-эффекты отмены (`FR-STOCK-001…005`
  → Фаза 5)
- Отображение скидки на витрине, фильтр «Со скидкой» (`FR-DISC-002`,
  `FR-CAT-010` → Фаза 6)
- Модерация отзывов (`FR-ADM-004` → Фаза 6)
- СМС на переходах статуса (`FR-NOTIF-001` → Фаза 7)
- Сотрудники, технические настройки, контент/баннеры (`FR-ADM-003`,
  `FR-ADM-007` → Фаза 8)
- Баг `hitRateLimit()` из `dev-log.md` 17.09.2026 — отдельный таск, не
  этот
- Витринные `header.php`/`footer.php` — не трогаем и не переиспользуем
  в админке
- DataTables, ApexCharts, любые плагины из `00-input/admin/assets/`
  сверх перечисленного в Scope — не копируются в этом таске

## Definition of Done

- [x] `manager` и `admin` видят layout с сайдбаром и счётчиками;
      `customer` по `/admin` → редирект на `/`; Гость → `/login`
- [x] Счётчики совпадают с `SELECT status, COUNT(*) FROM orders GROUP
      BY status` (ручная сверка), статусы без Заказов показывают 0
- [x] Сайдбар сворачивается/раскрывается на 320px, контент читаем —
      ручной проверкой пользователя в браузере найден и исправлен баг:
      текст шапки «ДомФорм — Панель управления» на ширине 320–576px
      переносился на 3 строки, `.app-header` (без явной высоты)
      растягивался, а `.app-content` с фиксированным `margin-block-
      start:4rem` перекрывался заголовком дашборда под шапкой. Текст
      шапки сокращён до «ДомФорм» (дублировал `<h4>Панель управления
      </h4>` на самой странице) + `text-nowrap`, чтобы шапка больше не
      могла вырасти по высоте от переноса текста; заодно поправлен
      `<title>` (был «Панель управления — Панель управления»)
- [x] В отданном HTML нет ни одного `http(s)://`-адреса (проверено
      `grep` по ответу `/admin`); вкладку Network браузера не открывал
      — тот же пробел, что пункт выше
- [x] «Выйти» — POST с CSRF, после выхода `/admin` недоступен
- [x] `composer test` зелёный (127/127), включая проверку
      `orderStatusBadgeClass()`
- [x] Проверить `.docs/dod-global.md`

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
