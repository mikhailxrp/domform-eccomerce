# Current Task

## Фаза
Phase 0 — Фундамент (`.docs/phases/phase-0.md`, Таск 4)

## Задача
Реализовать `requireRole()` для двух уровней доступа (Покупатель не
попадает в заглушку Панели управления) и «Remember me» на логине —
плюс, раз появляется первая cookie за пределами сессии, уведомление о
сборе cookie на сайте.

## Scope — что трогаем

- [ ] `src/Core/functions.php` — изменить:
      `currentUser(): ?array` (читает `id`/`name`/`role` из
      `$_SESSION` — эти поля уже кладутся туда при логине, похода в БД
      не требует), `roleAllowed(array $allowedRoles, ?string $role): bool`
      (чистая функция сравнения — вынесена отдельно ради unit-теста без
      сессии/redirect), `requireRole(array $roles): void` (редирект на
      `/login`, если не залогинен; `http_response_code(403)`, если роль
      не подходит), `attemptRememberLogin(): void` (проверяет
      remember-cookie, поднимает сессию через `regenerateSession()`,
      если пользователь ещё не в системе)
- [ ] `src/Models/RememberToken.php` — создать:
      `createRememberToken(int $userId): array{selector: string, validator: string}`,
      `findRememberToken(string $selector): ?array`,
      `deleteRememberTokens(int $userId): void` (удаляет все токены
      пользователя — переиспользуется и Таском 5 при смене пароля)
- [ ] `src/Controllers/AuthController.php` — изменить: `login()` — при
      отмеченном чекбоксе создаёт remember-cookie
      (`selector:validator`, `httponly`, `samesite=Lax`, `secure` в
      production, срок жизни как в `remember_tokens.expires_at`);
      `logout()` — удаляет все remember-токены пользователя и саму
      cookie (выход сбрасывает «запомнить меня» на всех устройствах,
      не только текущем — самый безопасный дефолт)
- [ ] `src/Views/auth/login.php` — изменить: чекбокс «Запомнить меня»
      (ссылку «Забыли пароль» не добавляю — Таск 5)
- [ ] `public/index.php` — изменить: вызов `attemptRememberLogin()`
      после `ensureSessionStarted()`, до `dispatch()` — сама логика в
      `functions.php`, `index.php` остаётся front controller-ом без
      бизнес-логики
- [ ] `src/Controllers/AdminController.php` — создать: `index()` под
      `requireRole(['manager', 'admin'])`
- [ ] `src/Views/admin/index.php` — создать: заглушка «Панель
      управления» (реальная функциональность — Фаза 4 проекта, другая
      фаза, не этот таск)
- [ ] `config/routes.php` — изменить: добавить `GET /admin`
- [ ] `tests/Unit/AuthHelpersTest.php` — создать: тесты на
      `roleAllowed()` и `currentUser()` через `$_SESSION` в CLI (без
      БД, без реального redirect/403 — это отдельная ручная проверка)

### Уведомление о cookie

- [ ] `src/Views/components/cookie-notice.php` — создать: баннер с
      текстом о том, что сайт использует cookie (сессия + «запомнить
      меня»), кнопка «Понятно». Информационное уведомление, не
      блокирующий запрос согласия — remember-cookie ставится по факту
      отметки чекбокса на форме входа, это и есть согласие пользователя
      на конкретное действие; отдельной страницы политики cookie ещё
      нет (`content_pages` не заполнены), поэтому баннер без ссылки
- [ ] `src/Views/layout/footer.php` — изменить: подключить
      `cookie-notice.php` перед `</body>` (общий для всех страниц
      элемент, не только Auth)
- [ ] `public/assets/js/app.js` — изменить: показ баннера, если флag в
      `localStorage` не установлен; по клику «Понятно» — скрыть и
      выставить флаг, чтобы не показывался повторно
- [ ] `public/assets/css/app.css` — изменить: минимальные стили баннера
      (фикс-позиция внизу экрана) — наш файл точечных переопределений,
      тему не трогаем

## Out of scope — не трогаем

- Реальная функциональность Панели управления (товары/заказы/
  пользователи) — Фаза 4 проекта
- Восстановление пароля, `src/Services/Mailer.php`, ссылка «Забыли
  пароль» — Таск 5
- Блокирующий cookie-consent (запрет ставить cookie до согласия),
  отдельная страница политики cookie — не входит в это уведомление,
  только информационный баннер
- Ссылка на Панель управления в `header.php` — не в списке файлов
  таска
- `database/install.php` / `.docs/database.md` — таблица
  `remember_tokens` уже развёрнута Таском 1
- Каталог/карточка товара/корзина/чекаут — другие фазы

## Definition of Done

- [ ] `customer` по URL `/admin` получает 403; `manager` и `admin` —
      видят страницу-заглушку; незалогиненный — редирект на `/login`
      (ручная проверка тремя разными аккаунтами)
- [ ] Вход с чекбоксом «Запомнить меня» → удалить cookie сессии
      (DevTools, эмулирует закрытие браузера) → открыть сайт заново →
      пользователь всё ещё залогинен; без чекбокса — разлогинен
- [ ] Remember-cookie: `httponly`, `samesite=Lax`, `secure` в
      production; в БД только хэш validator (`token_hash`), сам
      validator — только в cookie (проверка в БД + DevTools)
- [ ] «Выход» удаляет строки из `remember_tokens` и саму cookie
      (проверка в БД + DevTools)
- [ ] Просроченный/несуществующий/подделанный selector или validator
      игнорируется без ошибки, cookie очищается (протухшая запись в БД
      и кривой cookie — вручную)
- [ ] `roleAllowed()`/`currentUser()` покрыты
      `tests/Unit/AuthHelpersTest.php`, `composer test` зелёный
- [ ] При первом визите показывается баннер о cookie с кнопкой
      «Понятно»; после клика — скрывается и не появляется повторно при
      обновлении страницы и в следующих визитах (флаг в
      `localStorage`, проверить в приватном окне браузера)
- [ ] Проверить `.docs/dod-global.md` (применимо: «Безопасность»,
      «Код»)

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
