# Current Task

## Фаза
Phase 7 — Кабинет покупателя и уведомления
(`.docs/phases/phase-7.md`), Таск 6 из 9.

**Статус:** ✅ Завершён 21.09.2026 — проверено на реальной БД живым HTTP
(`php -S` + `curl`): временный тестовый Покупатель
(`task6-test-customer-*@example.com`) зарегистрирован, вошёл. Проверено:
клик по сердцу на мини-карточке каталога → строка в `favorites`,
иконка становится активной (`action--active`) сразу на обеих
раскладках карточки (grid/list — карточка рендерит обе, видна одна по
CSS) и в AJAX-partial (`X-Requested-With: fetch`, `components/catalog-grid`
рендерится без `header.php`, `$favoriteIds` передан явно контроллером
— подтверждает решение из плана); счётчик в шапке (обе копии) = 1;
повторный клик → строка удалена, не ошибка дубля `UNIQUE(user_id,
product_id)`; на Главной — 33 формы `/favorites/toggle` в блоках
товаров, без ошибок; на странице Товара — кнопка «В избранном» с
`action--active`, когда Товар в избранном. Несуществующий `product_id`
→ 404; без CSRF → 419; гость на POST → редирект `/login`; redirect
после клика вернул на `/catalog?sort=price_asc` (referer с
query-параметрами сохранён). Тестовый Покупатель, избранное и корзина
удалены после проверки. `composer test` — 325/325 (без изменений —
регрессия, чистая логика без БД в этом таске не добавлялась).

## Задача
`FR-CAT-009` (иконка на мини-карточке) и первая половина `FR-ACC-003`:
иконка-сердце на мини-карточке (каталог, поиск, похожие, блоки
Главной), кнопка «В избранное» на карточке товара и иконка в шапке со
счётчиком. Клик добавляет/убирает Товар, иконка отражает состояние.
Гостю иконка ведёт на `/login`.

## Scope — что трогаем

- [ ] `src/Models/Favorite.php` — создать: `toggleFavorite(int $userId,
      int $productId): bool` (вернул `true` = добавлен; `INSERT` с
      перехватом дубля по `UNIQUE(user_id, product_id)` → `DELETE`),
      `getFavoriteProductIds(int $userId): array`,
      `countFavorites(int $userId): int`
- [ ] `src/Models/Product.php` — изменить: добавить
      `findActiveProductById(int $id): ?array` (минимальная строка,
      только для проверки существования/активности в
      `FavoriteController::toggle()` — `findProductById()` в проекте
      нет, ближайшие функции — `findProductForToggle()`/
      `findProductForAdmin()` — обе для админки, не переиспользуются)
- [ ] `src/Controllers/FavoriteController.php` — создать: `toggle()` —
      `requireAuth()`, `requireCsrf()`, `findActiveProductById()` или
      404, `toggleFavorite()`, `redirectBack()` (приватный метод —
      только путь из `HTTP_REFERER`, без хоста, по образцу
      `CartController::redirectBack()`; фолбэк `/`, не `/catalog`)
- [ ] `src/Controllers/HomeController.php` — изменить: `index()` —
      `$favoriteIds = $userId !== null ? getFavoriteProductIds($userId)
      : []`, передать в `render()`
- [ ] `src/Controllers/CatalogController.php` — изменить:
      `renderCatalog()` — `$favoriteIds` в `$viewData` (общий массив
      для полной страницы и AJAX-partial `components/catalog-grid`,
      который рендерится без `header.php`)
- [ ] `src/Controllers/SearchController.php` — изменить: `index()` —
      `$favoriteIds` в `render()`
- [ ] `src/Controllers/ProductController.php` — изменить: `show()` —
      `$favoriteIds` в `render()` (используется и для «Похожие товары»,
      и для кнопки на самом Товаре через `in_array()`)
- [ ] `src/Views/layout/header.php` — изменить: `$favoriteCount =
      $isLoggedIn ? countFavorites(currentUser()['id']) : 0` (по
      образцу `$cartCount = currentCartCount(...)` — прямое вычисление
      в `header.php`, не через `render()`); иконка `pe-7s-like` со
      счётчиком `.number` (обе копии шапки — десктоп и мобильная)
- [ ] `src/Views/components/product-card.php` — изменить: третий `<li>`
      в существующем `<ul class="product-meta">` (после иконки корзины,
      порядок как в теме: лупа → корзина → сердце) — POST-форма
      `/favorites/toggle` с классом `action--active` при
      `in_array($product['id'], $favoriteIds, true)` для
      авторизованного, `<a href="/login">` для гостя
- [ ] `src/Views/product/show.php` — изменить: кнопка «В избранное» /
      «В избранном» сразу после `include variant-selector.php` (не
      внутри самого компонента — избранное на уровне Товара, а не
      Варианта)
- [ ] `public/assets/css/app.css` — изменить: `.product-meta
      .action--active { color: #f2a100; }` рядом с существующим
      правилом `.header-meta .action--active`
- [ ] `config/routes.php` — изменить: `POST /favorites/toggle`

## Out of scope — не трогаем

- Страница `/account/favorites` и перенос из корзины — Таск 7 этой же
  фазы
- СМС — Таски 8–9
- `src/Views/components/variant-selector.php` — избранное не входит в
  его зону ответственности (только Вариант/корзина)
- `CartController::redirectBack()` — не выносится в общий хелпер
  `functions.php`; `FavoriteController` получает свою копию того же
  приёма (не рефакторим существующий код попутно)
- `account-sidebar.php` — пункт «Избранное» уже добавлен в Таске 1

## Definition of Done

- [x] Клик по сердцу → строка в `favorites`; повторный клик → строка
      удалена (не ошибка дубля `UNIQUE(user_id, product_id)`); иконка
      активна ровно у избранных Товаров на всех типах мини-карточки
      (grid/list/swiper — каталог/поиск/похожие/Главная) и на странице
      Товара
- [x] Счётчик в шапке = число строк `favorites` текущего пользователя;
      у гостя иконка ведёт на `/login`, счётчика нет
- [x] На каждой странице с карточками — ровно один запрос
      `getFavoriteProductIds()` (проверено и на обычном рендере, и на
      AJAX-фильтре каталога — оба пути получают `$favoriteIds` явно из
      `CatalogController::renderCatalog()`, не полагаются на `header.php`)
- [x] Несуществующий/неактивный `product_id` → 404 без 500; 419 без
      CSRF; гость на POST → `/login`
- [x] Redirect после клика возвращает на ту же страницу (в т.ч. с
      GET-параметрами каталога/поиска — проверено на `?sort=price_asc`);
      внешний или отсутствующий referer → `/`
- [x] `composer test` зелёный (325/325, регрессия)
- [x] Проверить `.docs/dod-global.md`

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
