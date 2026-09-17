<?php

declare(strict_types=1);

/**
 * Чистые функции валидации ввода Auth-форм.
 * Без обращения к БД — только строки на входе/выходе.
 */

function validateEmail(string $email): bool
{
    return $email !== ''
        && strlen($email) <= 150
        && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Приводит телефон к формату +7XXXXXXXXXX.
 * Принимает написание с 8, +7, скобками/дефисами/пробелами.
 * Не удаётся нормализовать — возвращает как есть без телефонного кода.
 */
function normalizePhone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';

    if (strlen($digits) === 11 && ($digits[0] === '7' || $digits[0] === '8')) {
        return '+7' . substr($digits, 1);
    }

    if (strlen($digits) === 10 && $digits[0] === '9') {
        return '+7' . $digits;
    }

    return $digits;
}

function validatePhone(string $phone): bool
{
    return preg_match('/^\+7\d{10}$/', normalizePhone($phone)) === 1;
}

function validatePassword(string $password): bool
{
    return mb_strlen($password, 'UTF-8') >= 8;
}
