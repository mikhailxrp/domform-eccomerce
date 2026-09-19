<?php

declare(strict_types=1);

/**
 * Транслитерация в URL-slug (`FR-ADM-001`, Таск 7 Фазы 4) — чистая
 * функция без обращения к БД. Таблица — практическая транслитерация
 * (не ГОСТ/ISO 9): `х`→`h`, `ц`→`c`, `щ`→`sch`, мягкий/твёрдый знак
 * вырезаются — читаемее в URL, чем варианты с апострофами/`kh`/`shh`.
 */

const SLUG_TRANSLIT_MAP = [
    'а' => 'a',  'б' => 'b',  'в' => 'v',  'г' => 'g',  'д' => 'd',
    'е' => 'e',  'ё' => 'e',  'ж' => 'zh', 'з' => 'z',  'и' => 'i',
    'й' => 'y',  'к' => 'k',  'л' => 'l',  'м' => 'm',  'н' => 'n',
    'о' => 'o',  'п' => 'p',  'р' => 'r',  'с' => 's',  'т' => 't',
    'у' => 'u',  'ф' => 'f',  'х' => 'h',  'ц' => 'c',  'ч' => 'ch',
    'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',  'ы' => 'y',  'ь' => '',
    'э' => 'e',  'ю' => 'yu', 'я' => 'ya',
];

function slugify(string $text): string
{
    $transliterated = strtr(mb_strtolower($text, 'UTF-8'), SLUG_TRANSLIT_MAP);

    $slug = preg_replace('/[^a-z0-9]+/', '-', $transliterated) ?? '';
    $slug = trim($slug, '-');

    return $slug;
}
