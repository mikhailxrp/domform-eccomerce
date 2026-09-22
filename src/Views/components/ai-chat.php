<?php

declare(strict_types=1);

/**
 * Виджет чата консультанта (`FR-AI-003`, Таск 6 Фазы 9) — подключается
 * только `layout/footer.php` (витрина), в Панель управления не
 * попадает. `setting()`/`phoneToTel()` уже загружены к этому моменту
 * — `layout/header.php` подключает `Models/Setting.php` раньше на той
 * же странице.
 */
?>
<div class="ai-chat" id="ai-chat">
    <button type="button" class="ai-chat__toggle" id="ai-chat-toggle" aria-haspopup="dialog" aria-expanded="false" aria-controls="ai-chat-panel" data-tooltip="Консультант">
        <i class="pe-7s-chat" aria-hidden="true"></i>
        <span class="visually-hidden">Открыть чат с консультантом</span>
    </button>

    <div class="ai-chat__panel" id="ai-chat-panel" role="dialog" aria-labelledby="ai-chat-title" hidden>
        <div class="ai-chat__header">
            <p class="ai-chat__title" id="ai-chat-title">Консультант</p>
            <button type="button" class="ai-chat__close" id="ai-chat-close" aria-label="Закрыть чат">&times;</button>
        </div>

        <p class="ai-chat__warning">Пожалуйста, не указывайте в сообщениях личные данные — номер заказа, телефон, адрес. Переписка не связана с вашей учётной записью.</p>

        <div class="ai-chat__messages" id="ai-chat-messages" role="log" aria-live="polite"></div>

        <form class="ai-chat__form" id="ai-chat-form">
            <label for="ai-chat-input" class="visually-hidden">Ваш вопрос</label>
            <textarea id="ai-chat-input" class="ai-chat__input" name="question" rows="3" placeholder="Спросите про доставку, оплату, возврат или гарантию…" required></textarea>
            <button type="submit" class="ai-chat__send" id="ai-chat-send" aria-label="Отправить">
                <i class="pe-7s-paper-plane" aria-hidden="true"></i>
            </button>
        </form>

        <div class="ai-chat__contacts">
            <a class="ai-chat__contact" href="tel:<?= e(phoneToTel(setting('shop_phone'))) ?>"><i class="pe-7s-phone" aria-hidden="true"></i> Позвонить</a>
            <a class="ai-chat__contact" href="<?= e(setting('shop_whatsapp_url')) ?>" target="_blank" rel="noopener">WhatsApp</a>
        </div>
    </div>
</div>
