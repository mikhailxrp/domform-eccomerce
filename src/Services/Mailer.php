<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/functions.php';
require_once ROOT_PATH . '/src/Core/Logger.php';

/**
 * Рендерит шаблон письма (src/Views/emails/*.php) в строку — не HTML-
 * страница сайта, поэтому без header.php/footer.php.
 */
function renderEmailBody(string $template, array $data = []): string
{
    $viewPath = ROOT_PATH . '/src/Views/emails/' . $template . '.php';
    if (!is_file($viewPath)) {
        throw new RuntimeException("Шаблон письма не найден: {$template}");
    }

    extract($data, EXTR_SKIP);
    ob_start();
    require $viewPath;

    return (string) ob_get_clean();
}

/**
 * MAIL_DRIVER=log (локально) — тело письма пишется в storage/logs/app.log;
 * MAIL_DRIVER=mail (прод, shared-хостинг) — через встроенный PHP mail().
 * Самописный SMTP-клиент не пишется — решение phase-0.md.
 */
function sendMail(string $to, string $subject, string $body): void
{
    $driver = env('MAIL_DRIVER', 'log');

    match ($driver) {
        'log'   => logInfo('Письмо (MAIL_DRIVER=log)', ['to' => $to, 'subject' => $subject, 'body' => $body]),
        'mail'  => sendMailViaPhpMail($to, $subject, $body),
        default => throw new RuntimeException("Неизвестный MAIL_DRIVER: {$driver}"),
    };
}

function sendMailViaPhpMail(string $to, string $subject, string $body): void
{
    $sent = mail($to, $subject, $body, "Content-Type: text/html; charset=UTF-8\r\n");
    if (!$sent) {
        logError('Не удалось отправить письмо через mail()', ['to' => $to, 'subject' => $subject]);
    }
}
