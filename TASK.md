# Current Task

## Фаза
Phase 8 — Админ-панель (контент и доступ) и статические страницы
(`.docs/phases/phase-8.md`), Таск 6 из 8.

**Статус:** ✅ Завершён 22.09.2026 — проверено на реальной БД живым
HTTP (`php -S` + `curl`), тремя временными пользователями ролей
(удалены после проверки): `admin`/`manager` → 200, `customer` →
редирект, гость → `/login`; список — все 7 страниц с корректными
ссылками «Открыть на сайте»; неизвестный slug → 404; пустые
заголовок/тело — подсветка обоих полей, БД не изменена; POST без
CSRF → 419. Главный критерий приёмки ТЗ подтверждён напрямую: текст
«О компании» изменён через Панель с маркерной строкой — `/about`
сразу отдал новый текст без деплоя; после проверки восстановлен из
снапшота БД, `diff` до/после — идентичны. Загрузка фото — реальными
файлами: `.php` с настоящим PHP-кодом внутри отклонён по MIME
(`finfo`), диск остался пуст; валидный PNG сохранён, `image_path`
обновлён; повторная загрузка удаляет старый файл; «Удалить фото» —
файл стёрт, `image_path = NULL`. Все временные данные удалены,
`storage/logs/app.log` — без новых записей. `composer test` —
383/383 (+8 `ContentTest`).

## Задача
`FR-ADM-003` п. 1, 3 — `/admin/content`: список 7 страниц (заголовок,
slug, «обновлено», ссылка «Открыть на сайте»); `/admin/content/{slug}/edit`:
заголовок, тело (textarea с подсказкой мини-разметки), фото (загрузить
/ удалить). Критерий приёмки ТЗ: изменён текст «О компании» →
изменение видно на сайте без разработчика.

## Что проверено в коде перед планом
- Черновик `phase-8.md` был частично устаревшим: `UPLOAD_CONTENT_DIR`
  и `storeContentImage()`/`deleteStoredFile()` уже сделаны раньше
  (`ADR-046`, галерея «О компании») — `config/config.php` и
  `src/Services/FileUpload.php` в Scope не вошли и не менялись.
- `AdminAboutGalleryController::store()` — образец загрузки фото, но
  там оно обязательно; для контента фото необязательно при обновлении
  текста — использован паттерн `AdminReviewController::storeShopReview()`
  (`$hasPhoto` проверяется до вызова валидатора).
- `admin/products/form.php`, `admin/about-gallery/index.php` — стиль
  форм/карточек/превью фото Панели, переиспользован как есть.

## Scope — что трогали
- [x] `src/Core/Content.php` — добавлены `validateContentPageInput()`,
      `publicUrlForContentSlug()`
- [x] `tests/Unit/ContentTest.php` — дополнен 8 тестами
- [x] `src/Models/ContentPage.php` — добавлены `updateContentPage()`,
      `updateContentPageImage()`
- [x] `src/Controllers/AdminContentController.php` — создан:
      `index()`, `edit()`, `update()`, `deleteImage()`
- [x] `src/Views/admin/content/index.php` — создан
- [x] `src/Views/admin/content/edit.php` — создан
- [x] `src/Views/layout/admin-header.php` — пункт «Контент»
- [x] `config/routes.php` — `GET /admin/content`,
      `GET /admin/content/{slug}/edit`, `POST /admin/content/{slug}`,
      `POST /admin/content/{slug}/image/remove`
- [x] `.docs/dev-log.md` — запись по итогам таска

## Out of scope — не трогали
- `config/config.php`, `src/Services/FileUpload.php` — уже готовы
  (`ADR-046`)
- Управление баннерами слайдера — Таск 7
- Сотрудники/`admin`-only разделы — Таск 8
- Формат мини-разметки (`renderContentBody()`) — не менялся

## Definition of Done
- [x] Правка заголовка/текста «О компании» → `/about` показывает новое
      сразу (критерий приёмки `FR-ADM-003`); то же для `/pages/*`,
      `/contacts`, `/showroom`
- [x] Загрузка `.php`, файла с поддельным расширением или > лимита —
      отклонена, flash с ошибкой, файл на диске не появился; валидное
      фото → файл в `public/uploads/content/`, `image_path` обновлён,
      старый файл удалён; «Удалить фото» → файл стёрт,
      `image_path = NULL`
- [x] Неизвестный slug на `/admin/content/{slug}/edit` → 404; пустой
      заголовок/тело — подсветка, БД не изменена; отправка формы без
      файла — не ошибка (фото необязательно)
- [x] `customer` → редирект; Гость → `/login`; POST без CSRF → 419
- [x] `composer test` зелёный (383/383, новые тесты
      `validateContentPageInput()`, `publicUrlForContentSlug()`)
- [x] Проверить `.docs/dod-global.md`

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
- Каждый шаг проверялся тем, что указано в DoD: редактирование/
  загрузка фото/404/CSRF — живым HTTP на реальной БД, чистая логика —
  `composer test`
