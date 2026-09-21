# Current Task

## Фаза
Phase 7 — Кабинет покупателя и уведомления
(`.docs/phases/phase-7.md`), Таск 7 из 9.

**Статус:** ✅ Завершён 21.09.2026 — проверено на реальной БД живым HTTP
(`php -S` + `curl`): временный Покупатель
(`task7-test-customer-*@example.com`) зарегистрирован, вошёл. Пустое
избранное показывает инлайн-заглушку со ссылкой в каталог. Товар
добавлен в избранное напрямую (`/favorites/toggle`) и перенесён из
корзины (`/cart/move-to-favorites`) — оба оказались на
`/account/favorites` с корректной ценой (старая/новая), фото и формой
«В корзину», которая добавляет тот же Вариант/цвет, что кнопка
мини-карточки; строка `cart_items` при переносе удалена, счётчики в
шапке (избранное/корзина) обновились. Удаление из избранного убрало
строку из БД и со страницы, счётчик уменьшился. Идемпотентность:
перенос уже избранного Товара из второй строки корзины не создал дубль
в `favorites` и не упал (500 нет), сама строка корзины удалена.
Изоляция: Гость не видит кнопку «В избранное» в строке корзины,
GET `/account/favorites` и POST `/cart/move-to-favorites` для Гостя —
редирект на `/login`; чужой `item_id` в `moveToFavorites()` (запрос от
другого авторизованного пользователя) — без изменений ни в чужой
корзине, ни в своём избранном. Тестовые Покупатель, избранное, обе
корзины (авторизованная и гостевая) удалены после проверки. `composer
test` — 325/325 (без изменений — регрессия, чистая логика без БД в
этом таске не добавлялась).

## Задача
Вторая половина `FR-ACC-003` и `FR-CART-004`: `/account/favorites` —
список избранных Товаров с фото, ценой (старая/новая), «В корзину» и
удалением; перенос позиции из корзины в Избранное кнопкой в строке
корзины.

## Scope — что трогаем

- [ ] `src/Models/Favorite.php` — изменить: добавить
      `getFavoriteProducts(int $userId): array` (Товары пользователя из
      `favorites` с активным Вариантом — `INNER JOIN product_variants
      ... is_active = 1`, по образцу `getRelatedProducts()`, затем
      `attachCheapestVariant()` — неактивный Товар и Товар без
      активного Варианта отсекаются самим запросом),
      `removeFavorite(int $userId, int $productId): bool` (`DELETE`,
      `rowCount() > 0`), `addFavorite(int $userId, int $productId): void`
      (идемпотентно — перехват `SQLSTATE 23000`/MySQL 1062 как «уже в
      избранном», тот же приём, что `toggleFavorite()`)
- [ ] `src/Controllers/FavoriteController.php` — изменить: добавить
      `index()` — `requireAuth()`, `getFavoriteProducts()`,
      `countFavorites()`, рендер с сайдбаром кабинета
      (`$activeSection = 'favorites'`); `remove()` — `requireAuth()`,
      `requireCsrf()`, `removeFavorite()`, `redirect('/account/favorites')`
      (фиксированный путь, не `HTTP_REFERER` — действие только с этой
      страницы)
- [ ] `src/Views/account/favorites.php` — создать: таблица по
      `wishlist.html` (фото, название, цена старая/новая — как в Фазе
      6, «В корзину», удаление; без колонки количества — Избранное на
      уровне Товара, не корзины) с сайдбаром кабинета; инлайн пустое
      состояние по образцу `empty-cart` (`cart/index.php`), не
      `components/empty-state.php` (жёстко зашитый текст «Сбросить
      фильтры» не подходит — та же находка, что в Тасках 2/4)
- [ ] `src/Controllers/CartController.php` — изменить: добавить
      `moveToFavorites()` — `requireAuth()`, `requireCsrf()`, находит
      строку корзины владельца (`getCartItems()` уже содержит
      `product_id`), `addFavorite()` + `removeCartItem()` +
      `refreshCartCount()`, `redirect('/cart')`
- [ ] `src/Views/components/cart-row.php` — изменить: кнопка «В
      избранное» в существующем `<td class="product-action">` —
      только для авторизованного (`isAuthenticated()`, без отдельной
      переданной переменной, как в `product-card.php` Таска 6)
- [ ] `config/routes.php` — изменить: `GET /account/favorites`,
      `POST /favorites/remove`, `POST /cart/move-to-favorites`

## Out of scope — не трогаем

- `src/Views/components/account-sidebar.php` — пункт «Избранное» уже
  добавлен в Таске 1, ведёт на `/account/favorites`
- `src/Views/components/empty-state.php` — не переиспользуется (см.
  Scope выше)
- СМС — Таски 8–9 этой же фазы
- `FavoriteController::toggle()` и приватный `redirectBack()` — уже
  реализованы в Таске 6, не трогаются

## Definition of Done

- [x] Перенос из корзины (`/cart/move-to-favorites`) → строка
      `cart_items` удалена, строка `favorites` есть; счётчики корзины и
      избранного в шапке обновлены; уже избранный Товар — перенос не
      создаёт дубль и не падает (`FR-CART-004`)
- [x] Гостю в строке корзины кнопки «В избранное» нет в HTML; чужой
      `item_id` в форме → без изменений (изоляция по владельцу корзины)
- [x] «В корзину» со страницы Избранного добавляет тот же (самый
      дешёвый активный) Вариант, что кнопка мини-карточки — тот же
      `attachCheapestVariant()`, что и мини-карточка (регрессии Резерва
      на образце нет, код общий и не менялся)
- [x] Неактивный Товар или Товар без активных Вариантов в Избранном не
      показывается на странице (запрос `getFavoriteProducts()`
      отсекает их `INNER JOIN`)
- [x] Удаление (`/favorites/remove`) → строка исчезла со страницы и из
      БД, счётчик в шапке уменьшился; чужой `product_id` → без
      изменений
- [x] Пустое состояние с ссылкой в каталог при пустом избранном;
      разметка на общем шаблоне кабинета (Bootstrap grid, тот же, что
      уже проверенные на 320px `cart/index.php`/`account/addresses.php`)
- [x] `composer test` зелёный (325/325, регрессия)
- [x] Проверить `.docs/dod-global.md`

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
