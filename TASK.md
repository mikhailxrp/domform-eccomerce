# Current Task

## Фаза
Phase 7 — Кабинет покупателя и уведомления
(`.docs/phases/phase-7.md`), Таск 2 из 9.

**Статус:** ✅ Завершён 21.09.2026. Проверено на реальной БД живым HTTP
(`php -S` + `curl`): 11 тестовых Заказов для одного Покупателя (пагинация
2 страницы, 1 из них `delivered` с гарантией), 1 Заказ второго
Покупателя (изоляция/404), полный цикл гостевой Заказ → регистрация на
тот же email → Заказ появляется в кабинете (`linkGuestOrdersToUser()`).
Все тестовые Заказы/Покупатели удалены после проверки. `composer test`
— 296/296 (без изменений, регрессия). Реализация полностью соответствует
плану ниже, отклонений не потребовалось.

## Задача
`FR-ACC-001` — `/account/orders`: таблица Заказов текущего пользователя
(№, дата, статус, сумма, «Подробнее») с пагинацией; `/account/orders/{id}`
— состав заказа (снэпшоты позиций), способ получения/адрес, способ и
статус оплаты, стоимость доставки, гарантия для `delivered`. Чужой или
несуществующий Заказ → 404.

Уточнение против черновика `phase-7.md` (обсуждено и подтверждено перед
записью): `components/empty-state.php` требует `$resetUrl` и текст
«Сбросить фильтры» — для списка заказов без фильтров не подходит.
Пустое состояние — инлайн, по образцу `empty-cart` в `cart/index.php`,
не через этот компонент.

## Scope — что трогаем

- [ ] `config/config.php` — изменить: добавить `ACCOUNT_ORDERS_PER_PAGE = 10`
      (по аналогии с `ADMIN_ORDERS_PER_PAGE`, но кабинет одного
      покупателя — меньше записей)
- [ ] `src/Models/Order.php` — изменить: `getUserOrders(int $userId,
      int $page, int $perPage): array` (по `INDEX(user_id)`,
      `ORDER BY created_at DESC LIMIT/OFFSET`), `countUserOrders(int
      $userId): int`, `findOrderForUser(int $id, int $userId): ?array`
- [ ] `src/Controllers/AccountController.php` — изменить: добавить
      `orders()` и `orderShow(string $id)` (`requireAuth()`,
      `buildPagination()`, `abort404()` на чужом/несуществующем id;
      `require_once` `Core/Pagination.php` и `Core/Warranty.php`)
- [ ] `src/Views/account/orders.php` — создать: таблица по
      `my-account.html` («Orders»), инлайн-пустое состояние,
      `components/pagination.php`
- [ ] `src/Views/account/order-show.php` — создать: карточка заказа по
      образцу `checkout/success.php` (состав, способ получения/адрес,
      оплата, доставка, гарантия для `delivered`)
- [ ] `config/routes.php` — изменить: `GET /account/orders`,
      `GET /account/orders/{id}`

## Out of scope — не трогаем

- Личные данные, адреса, избранное, СМС — Таски 3–9 этой же фазы
- `AdminOrderController`, панель менеджера — не связаны с задачей
- `account-sidebar.php` — ссылка на «Заказы» уже проставлена в Таске 1
- `components/empty-state.php` — не переиспользуется (см. уточнение выше)
- Изменение схемы БД — `orders`/`order_items` не меняются

## Definition of Done

- [x] Список на `/account/orders` содержит только Заказы текущего
      пользователя; подмена `id` чужого Заказа в URL на
      `/account/orders/{id}` → 404 (изоляция по `user_id`,
      `dod-global.md`) — проверено: чужой `id` и несуществующий `id`
      оба дают 404
- [x] Гостевой Заказ, привязанный после регистрации на тот же email
      (`linkGuestOrdersToUser()`), виден в списке — проверено полным
      циклом (гостевой чекаут → регистрация → заказ в кабинете)
- [x] Статусы — подписи `orderStatusLabel()` (не демо
      `Pending/Approved/On Hold`); статус и способ оплаты — подписи
      `PAYMENT_*_LABELS`
- [x] Суммы через `formatPrice()`; позиции карточки заказа — из
      снэпшотов `order_items` (материал/цвет/цена на момент заказа),
      не из текущей цены Варианта
- [x] У `delivered` показан срок гарантии (`warrantyExpiresAt()`/
      `isUnderWarranty()`), у остальных статусов — не показан —
      проверено на тестовом заказе (`delivered_at` −2 мес. → «В
      пределах гарантии», дата верная)
- [x] Пустое состояние при отсутствии Заказов; пагинация появляется
      при `> ACCOUNT_ORDERS_PER_PAGE` — проверено на 11 заказах
      (страница 1 — 10 строк, страница 2 — 1 строка)
- [x] `composer test` зелёный (296/296, без изменений — регрессия)
- [x] Проверить `.docs/dod-global.md`

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
