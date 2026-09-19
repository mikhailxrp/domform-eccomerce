# Current Task

## Фаза
Phase 4 — Панель менеджера и каталог в админке (`.docs/phases/phase-4.md`, Таск 9)

**Статус:** ✅ Завершён — реализован и проверен на реальной БД
(`mikhail700.beget.tech`) живой HTTP-сессией через `php -S` + `curl`
(нет браузера в сессии — сверка вёрстки на 320px не проводилась, тот
же пробел, что в предыдущих тасках). `composer test` 215/215
(+11 `UploadTest`). Подробности — `.docs/dev-log.md`, 19.09.2026.

При планировании (до начала кода) найдено расхождение между DoD и
изначальным списком файлов: мини-карточка каталога
(`product-card.php`) игнорировала `$product['image_path']` в пользу
заглушки темы, а DoD требует показывать загруженное фото на
мини-карточке. Решение подтверждено пользователем и добавлено в Scope
до начала кода: показывать реальное фото, только если оно загружено
через эту форму (`uploads/products/` в пути) — старые сиды темы
остаются на заглушке без изменений.

Сигнатуры Model-функций в реализации отличаются от изначально
записанных в Scope — везде добавлен `$productId` первым параметром
(`addVariantImage(int $productId, int $variantId, ...)` и т.д.):
принадлежность Варианта Товару из URL проверяется прямо в запросе
мутации (`WHERE`/`JOIN`), а не отдельным `find*` до неё — тот же приём,
что уже использует `updateOrderItemQuantity()` (Таск 4) для
`order_id`/`itemId`.

## Задача
На форме редактирования Товара у каждого Варианта появляется блок
фото (`variant_images`): загрузка (jpg/png/webp, тип проверяется по
содержимому файла, не по расширению), цвет, отметка «образец ткани»,
«главное» фото (ровно одно на Вариант), порядок, удаление (файл +
запись). Загрузка доступна только у уже сохранённого Варианта (нужен
`variant_id`).

## Scope — что трогаем

- [x] `src/Core/Upload.php` — создать: константы `UPLOAD_MAX_BYTES`,
      `UPLOAD_ALLOWED_MIME` (whitelist MIME → расширение) —
      определяются здесь, а не в `config/config.php`, потому что
      `config.php` не подключается в `tests/bootstrap.php` (та же
      причина, по которой `CART_MAX_QUANTITY` живёт в `Core/Cart.php`,
      см. `config.php:31-35`), а `validateUploadedImage()` обязана
      быть чистой и покрытой юнит-тестом; `validateUploadedImage(array
      $file, string $detectedMime): ?string` (код ошибки PHP, размер,
      MIME по whitelist — `finfo` передаётся снаружи, сама функция БД/
      файлов не касается); `uploadExtensionForMime(string $mime):
      ?string`
- [x] `tests/Unit/UploadTest.php` — создать
- [x] `tests/bootstrap.php` — изменить: добавить `require_once
      .../Core/Upload.php`
- [x] `config/config.php` — изменить: добавить `UPLOAD_PRODUCTS_DIR`
      (путь `public/uploads/products`) — только путь, нужен
      `FileUpload.php`, который не покрывается юнит-тестами
- [x] `src/Services/FileUpload.php` — создать: `detectUploadedMime(string
      $tmpName): string` (`finfo` по временному файлу, отдельно от
      `storeProductImage()` — используется и для валидации до
      сохранения, не входило дословно в Scope как отдельное имя, но
      нужно, т.к. MIME должен быть известен ещё до вызова
      `validateUploadedImage()`), `storeProductImage(array
      $file): ?string` (`finfo_file` по временному файлу,
      `random_bytes` → случайное имя, `move_uploaded_file`, возвращает
      относительный путь), `deleteStoredFile(string $path): void`
      (проверка, что путь остаётся внутри `UPLOAD_PRODUCTS_DIR` —
      защита от `..`)
- [x] `src/Models/Product.php` — изменить: `getVariantImagesForAdmin(int
      $variantId): array` (с `id` каждой строки — не входило дословно
      в Scope, но нужно: `getVariantImages()` для витрины `id` не
      отдаёт, а форме редактирования он нужен для ссылок действий),
      `addVariantImage(int $productId, int $variantId, array $data):
      ?int`, `updateVariantImage(int $productId, int $variantId, int
      $imageId, array $data): bool` (цвет/образец/порядок),
      `deleteVariantImage(int $productId, int $variantId, int $imageId):
      ?string` (путь файла для удаления сервисом, `null` — фото не
      найдено или принадлежит другому Варианту/Товару; если удалённое
      было главным — следующее по `sort_order` становится главным в
      той же транзакции), `setMainVariantImage(int $productId, int
      $variantId, int $imageId): bool` (в транзакции снимает `is_main`
      с остальных, затем ставит одному) — везде `$productId` первым
      параметром (не было в изначальной сигнатуре Scope):
      принадлежность Варианта Товару из URL проверяется прямо в
      запросе (`WHERE`/`JOIN`), а не отдельным `find*` до него
- [x] `src/Controllers/AdminProductController.php` — изменить:
      `uploadImage(string $productId, string $variantId)`,
      `updateImage(string $productId, string $variantId, string
      $imageId)`, `deleteImage(...)`, `setMainImage(...)` — везде
      `requireCsrf()`, `requireRole(['manager','admin'])`, редирект на
      `edit()` с flash-сообщением
- [x] `src/Views/admin/products/form.php` — изменить: секция «Фото по
      Вариантам» **после** `</form>` Товара (только для `$isEdit`, по
      каждому Варианту с `id > 0`)
- [x] `src/Views/components/admin/variant-photos.php` — создать: блок
      фото Варианта (миниатюры, поле `color`, чекбокс «образец ткани»,
      радио «главное», кнопка «удалить», форма загрузки
      `multipart/form-data`) — отдельными `<form>`, **не** внутри
      `variant-row.php`: тот уже вложен в общую форму Товара, а
      вложенные `<form>` в HTML недопустимы
- [x] `src/Views/components/product-card.php` — изменить: мини-карточка
      каталога сейчас всегда показывает детерминированную заглушку темы
      (`$placeholderNumber`), игнорируя `$product['image_path']`
      (комментарий в коде: «до реальной фотосъёмки — Фаза 4»). Теперь
      показывать `image_path` вместо заглушки, но только если это фото
      загружено через эту форму (путь начинается с
      `uploads/products/`), не для старых сидов из темы
      (`assets/images/...`) — решение подтверждено пользователем
      (пропорции сидов не под сетку мини-карточки)
- [x] `config/routes.php` — изменить: `POST
      /admin/products/{productId}/variants/{variantId}/images`
      (загрузка), `POST
      /admin/products/{productId}/variants/{variantId}/images/{imageId}`
      (обновить цвет/порядок/образец), `POST .../images/{imageId}/remove`,
      `POST .../images/{imageId}/main`

## Out of scope — не трогаем

- Схема `variant_images` — таблица уже существует с предыдущей фазы,
  миграции не нужны
- Отчёт по продажам — Таск 10
- Отображение скидки на витрине / фильтр «Со скидкой» — Фаза 6
- `getVariantImages()` (используется витриной для чтения) —
  переиспользуется как есть, не меняется
- Загрузка нескольких файлов за один запрос — только один файл за раз,
  как в остальных формах проекта
- `public/uploads/baners/` и всё, что с ним связано — вне scope

## Definition of Done

- [x] `.php`, переименованный в `.jpg`, отклонён по фактическому MIME
      (`finfo`, не по расширению/имени поля); файл больше
      `UPLOAD_MAX_BYTES` → дружелюбная ошибка формы, не 500;
      `UPLOAD_ERR_*` ≠ `OK` → ошибка + запись в `storage/logs/app.log`
      — проверено на реальной БД: `.php`, переименованный в `.jpg`
      (`finfo` вернул `text/x-php`), отклонён, ни строка, ни файл не
      появились; отсутствующий файл в форме → ошибка «Выберите файл.»,
      без 500
- [x] Загруженное фото с `color` видно на витрине: мини-карточка
      (главное фото) и галерея карточки товара при выборе этого цвета
      — проверено: после загрузки и «сделать главным» фото появилось
      и на `/catalog` (мини-карточка), и на `/product/divan-milan`
      (главное изображение и подпись цвета); остальные Товары каталога
      остались на заглушке темы — регрессии нет
- [x] После добавления/переключения «главного» фото ровно одно
      `is_main=1` на Вариант (проверка в БД) — проверено прямым
      запросом к БД до и после переключения
- [x] Удаление фото стирает и файл с диска, и строку `variant_images`;
      подделанный путь с `..` не выходит за `UPLOAD_PRODUCTS_DIR` —
      проверено: удаление главного фото при наличии другого
      промотировало следующее по `sort_order` в главное (в той же
      транзакции), файл пропал с диска; `deleteStoredFile()` также
      проверяет префикс `uploads/products/` и `realpath()` до `unlink()`
- [x] Чужой `imageId` (другого Варианта) или Вариант чужого Товара в
      URL → без изменений, редирект без 500 — проверено тремя
      подменами: чужой Вариант того же Товара, чужой Товар в пути,
      несуществующий `variantId` — везде без изменений в БД, без 500
      (последний случай — 404, файл не осел на диске благодаря очистке
      через `deleteStoredFile()` при отказе `addVariantImage()`)
- [x] `UploadTest` зелёный отдельно, `composer test` зелёный целиком —
      215/215 (+11 `UploadTest`)
- [x] Проверить `.docs/dod-global.md` (включая пункт про загрузку
      файлов: MIME по содержимому, размер, `.htaccess` не ослаблен) —
      `public/uploads/.htaccess` не менялся; `storage/logs/app.log` не
      получил новых записей за всю сессию проверки; неаутентифицированный
      `POST` на маршрут загрузки — редирект, не утечка

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
