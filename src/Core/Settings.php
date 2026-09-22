<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Validation.php';

/**
 * Реквизиты магазина (`settings`, `FR-ADM-007` п. 1 и 4 в минимальном
 * составе, Таск 2 Фазы 8, `ADR-045`) — whitelist ключей, чтение с
 * кэшем на один запрос и чистая валидация формы «Настройки».
 */

const SETTING_KEYS = [
    'shop_phone'        => 'Телефон',
    'shop_whatsapp_url' => 'WhatsApp',
    'shop_email'        => 'Email',
    'workshop_address'  => 'Адрес цеха',
    'work_hours'        => 'Режим работы',
    'map_embed_url'     => 'Ссылка на карту (iframe)',
];

const SETTINGS_ADDRESS_MAX_LENGTH    = 255;
const SETTINGS_WORK_HOURS_MAX_LENGTH = 100;

/**
 * Одна строка на весь запрос — `getAllSettings()` (Model) вызывается
 * не больше одного раза, дальше отдаёт из статической переменной
 * (`dod-global.md`: ровно один `SELECT` на страницу).
 */
function setting(string $key): string
{
    static $settings = null;

    if ($settings === null) {
        $settings = getAllSettings();
    }

    return $settings[$key] ?? '';
}

/**
 * Формат для `href="tel:"` — тот же, что уже даёт `normalizePhone()`
 * (`Core/Validation.php`) для валидного российского номера; отдельное
 * имя называет цель вызова на месте использования, реализация не
 * дублируется (`ADR-045`).
 */
function phoneToTel(string $phone): string
{
    return normalizePhone($phone);
}

/**
 * Обычная ссылка на Яндекс.Карты (`yandex.ru/maps/-/...`,
 * «поделиться местом») отдаёт `X-Frame-Options`/CSP, запрещающие
 * встраивание — в `<iframe>` она не откроется, только виджет
 * Конструктора (`yandex.ru/map-widget/v1/...`) специально предназначен
 * для встраивания. Проверка по подстроке, а не домену целиком — ссылка
 * тем же способом получается и на других поддоменах Карт.
 */
const SETTINGS_MAP_EMBED_MARKER = 'map-widget';

/**
 * Обрезает пробелы сама, по образцу `validateAddressInput()`
 * (`Core/Address.php`). `map_embed_url` — единственное необязательное
 * поле: пустая строка допускается (карта на `/showroom` тогда не
 * выводится, Таск 3), но заполненная должна быть `https://` и вести на
 * виджет Конструктора, а не на обычную страницу Яндекс.Карт.
 */
function validateSettingsInput(array $input): array
{
    $shopPhone       = trim((string) ($input['shop_phone'] ?? ''));
    $shopWhatsappUrl = trim((string) ($input['shop_whatsapp_url'] ?? ''));
    $shopEmail       = trim((string) ($input['shop_email'] ?? ''));
    $workshopAddress = trim((string) ($input['workshop_address'] ?? ''));
    $workHours       = trim((string) ($input['work_hours'] ?? ''));
    $mapEmbedUrl     = trim((string) ($input['map_embed_url'] ?? ''));

    return [
        'shop_phone'        => !validatePhone($shopPhone),
        'shop_whatsapp_url' => !str_starts_with($shopWhatsappUrl, 'https://'),
        'shop_email'        => !validateEmail($shopEmail),
        'workshop_address'  => $workshopAddress === '' || mb_strlen($workshopAddress, 'UTF-8') > SETTINGS_ADDRESS_MAX_LENGTH,
        'work_hours'        => $workHours === '' || mb_strlen($workHours, 'UTF-8') > SETTINGS_WORK_HOURS_MAX_LENGTH,
        'map_embed_url'     => $mapEmbedUrl !== '' && (
            !str_starts_with($mapEmbedUrl, 'https://')
            || !str_contains($mapEmbedUrl, SETTINGS_MAP_EMBED_MARKER)
        ),
    ];
}
