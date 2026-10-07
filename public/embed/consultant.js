/**
 * Виджет консультанта для внешних сайтов. Подключение:
 *   <script src="https://<магазин>/embed/consultant.js" data-key="dfk_..." async></script>
 * Адрес API берётся из `src` самого скрипта. Весь интерфейс живёт в
 * Shadow DOM — стили сайта не ломают виджет, виджет не ломает сайт.
 * Текст ответа выводится только через `textContent`.
 */
(() => {
    const script = document.currentScript;
    if (!script || !script.src || !script.dataset.key) {
        return;
    }

    const apiKey = script.dataset.key;
    const apiUrl = new URL('/api/v1/consultant', script.src).href;
    const storageKey = `domform-consultant:${apiKey}`;

    const STYLES = `
        :host { all: initial; }
        .chat { position: fixed; right: 20px; bottom: 20px; z-index: 2147483000; font: 14px/1.4 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #222; }
        .chat__toggle { width: 56px; height: 56px; border: 0; border-radius: 50%; background: #1f6f5c; color: #fff; font-size: 24px; cursor: pointer; box-shadow: 0 4px 14px rgba(0, 0, 0, .25); }
        .chat__panel { position: absolute; right: 0; bottom: 70px; display: flex; flex-direction: column; width: 340px; max-width: calc(100vw - 40px); height: 480px; max-height: calc(100vh - 110px); background: #fff; border-radius: 12px; box-shadow: 0 8px 30px rgba(0, 0, 0, .25); overflow: hidden; }
        .chat__panel[hidden] { display: none; }
        .chat__header { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; background: #1f6f5c; color: #fff; font-weight: 600; }
        .chat__close { border: 0; background: none; color: inherit; font-size: 22px; line-height: 1; cursor: pointer; }
        .chat__messages { flex: 1; overflow-y: auto; padding: 12px; background: #f5f6f7; }
        .chat__message { max-width: 85%; margin: 0 0 8px; padding: 8px 12px; border-radius: 12px; white-space: pre-wrap; word-wrap: break-word; }
        .chat__message--user { margin-left: auto; background: #1f6f5c; color: #fff; }
        .chat__message--assistant { background: #fff; border: 1px solid #e2e4e6; }
        .chat__message--pending { color: #888; background: transparent; }
        .chat__card { display: block; margin: 0 0 8px; padding: 10px 12px; background: #fff; border: 1px solid #1f6f5c; border-radius: 12px; color: inherit; text-decoration: none; }
        .chat__card-name { display: block; font-weight: 600; }
        .chat__card-price { display: block; color: #1f6f5c; font-weight: 600; }
        .chat__card-excerpt { display: block; color: #666; font-size: 12px; }
        .chat__form { display: flex; gap: 8px; padding: 10px; border-top: 1px solid #e2e4e6; background: #fff; }
        .chat__input { flex: 1; resize: none; padding: 8px 10px; border: 1px solid #ccd0d3; border-radius: 8px; font: inherit; }
        .chat__send { padding: 0 14px; border: 0; border-radius: 8px; background: #1f6f5c; color: #fff; font: inherit; cursor: pointer; }
        .chat__send:disabled { background: #9db8b0; cursor: default; }
    `;

    const readConversationId = () => {
        try {
            return sessionStorage.getItem(storageKey) || '';
        } catch (error) {
            return '';
        }
    };

    const saveConversationId = (id) => {
        try {
            sessionStorage.setItem(storageKey, id);
        } catch (error) {
            // Приватный режим/запрет хранилища — диалог просто не переживёт перезагрузку.
        }
    };

    const create = (tag, className, text) => {
        const element = document.createElement(tag);
        if (className) {
            element.className = className;
        }
        if (text !== undefined) {
            element.textContent = text;
        }
        return element;
    };

    const isHttpUrl = (value) => {
        try {
            return ['http:', 'https:'].includes(new URL(value).protocol);
        } catch (error) {
            return false;
        }
    };

    const mount = () => {
        const host = document.createElement('div');
        const root = host.attachShadow({ mode: 'open' });
        root.append(create('style', '', STYLES));

        const chat = create('div', 'chat');
        const toggle = create('button', 'chat__toggle', '💬');
        toggle.type = 'button';
        toggle.setAttribute('aria-label', 'Открыть чат с консультантом');
        toggle.setAttribute('aria-expanded', 'false');

        const panel = create('div', 'chat__panel');
        panel.hidden = true;
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', 'Консультант');

        const header = create('div', 'chat__header');
        const close = create('button', 'chat__close', '×');
        close.type = 'button';
        close.setAttribute('aria-label', 'Закрыть чат');
        header.append(create('span', '', 'Консультант'), close);

        const messages = create('div', 'chat__messages');
        messages.setAttribute('role', 'log');
        messages.setAttribute('aria-live', 'polite');

        const form = create('form', 'chat__form');
        const input = create('textarea', 'chat__input');
        input.rows = 2;
        input.required = true;
        input.maxLength = 500;
        input.placeholder = 'Спросите про доставку, оплату, гарантию или подбор мебели…';
        input.setAttribute('aria-label', 'Ваш вопрос');
        const send = create('button', 'chat__send', 'Отправить');
        send.type = 'submit';
        form.append(input, send);

        panel.append(header, messages, form);
        chat.append(panel, toggle);
        root.append(chat);
        document.body.append(host);

        let conversationId = readConversationId();
        let limitReached = false;
        let greeted = false;

        const scrollDown = () => {
            messages.scrollTop = messages.scrollHeight;
        };

        const addMessage = (role, text) => {
            const bubble = create('p', `chat__message chat__message--${role}`, text);
            messages.append(bubble);
            scrollDown();
            return bubble;
        };

        const addProductCard = (product) => {
            const card = create('a', 'chat__card');
            if (isHttpUrl(product.url)) {
                card.href = product.url;
                card.target = '_blank';
                card.rel = 'noopener';
            }
            card.append(
                create('span', 'chat__card-name', product.name),
                create('span', 'chat__card-price', product.price),
                create('span', 'chat__card-excerpt', product.excerpt)
            );
            messages.append(card);
            scrollDown();
        };

        const setOpen = (open) => {
            panel.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                if (!greeted) {
                    greeted = true;
                    addMessage('assistant', 'Здравствуйте! Спросите про доставку, оплату, возврат, гарантию или подбор мебели.');
                }
                input.focus();
            }
        };

        const showAnswer = (data) => {
            if (data.conversation_id) {
                conversationId = data.conversation_id;
                saveConversationId(conversationId);
            }

            if (data.answer) {
                addMessage('assistant', data.answer);
                if (data.product) {
                    addProductCard(data.product);
                }
            } else if (data.unavailable) {
                addMessage('assistant', `Консультант временно недоступен. Позвоните: ${data.phone} или напишите в WhatsApp: ${data.whatsapp}.`);
            } else if (data.limit_reached) {
                limitReached = true;
                addMessage('assistant', data.message);
            } else {
                addMessage('assistant', data.error || 'Не удалось получить ответ — попробуйте ещё раз.');
            }
        };

        const ask = async (question) => {
            const body = new URLSearchParams({ key: apiKey, question });
            if (conversationId) {
                body.set('conversation_id', conversationId);
            }

            const pending = addMessage('pending', 'Печатает…');
            send.disabled = true;

            try {
                const response = await fetch(apiUrl, { method: 'POST', body });
                const data = await response.json();
                pending.remove();
                showAnswer(data);
            } catch (error) {
                pending.remove();
                addMessage('assistant', 'Не удалось связаться с сервером — проверьте соединение.');
            } finally {
                send.disabled = limitReached;
            }
        };

        toggle.addEventListener('click', () => setOpen(panel.hidden));
        close.addEventListener('click', () => setOpen(false));

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                form.requestSubmit();
            }
        });

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const question = input.value.trim();
            if (question === '' || limitReached) {
                return;
            }
            addMessage('user', question);
            input.value = '';
            ask(question);
        });
    };

    if (document.body) {
        mount();
    } else {
        document.addEventListener('DOMContentLoaded', mount);
    }
})();
