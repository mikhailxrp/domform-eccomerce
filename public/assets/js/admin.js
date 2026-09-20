'use strict';

(() => {
    document.addEventListener('click', (event) => {
        if (event.target.closest('a, button, input, textarea, select, label')) {
            return;
        }

        const row = event.target.closest('[data-href]');
        if (row) {
            window.location.assign(row.dataset.href);
        }
    });
})();

// Подсказки variant-picker (Таск 4/5 Фазы 4) — делегирование на
// document, а не querySelector на конкретный узел: на странице может
// быть несколько блоков `[data-variant-picker]` одновременно (создание
// Заказа, Таск 5), включая добавленные позже кнопкой «Добавить
// позицию» — делегирование обслуживает их все без повторного связывания.
(() => {
    let debounceTimer = null;

    const renderColors = (picker, colors) => {
        const colorSelect = picker.querySelector('[data-variant-picker-color]');
        colorSelect.innerHTML = '<option value="">—</option>';
        colors.forEach((color) => {
            const option = document.createElement('option');
            option.value = color;
            option.textContent = color;
            colorSelect.append(option);
        });
    };

    const renderSuggestions = (picker, items) => {
        const box = picker.querySelector('[data-variant-picker-suggestions]');
        box.innerHTML = '';
        items.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action';
            button.textContent = `${item.name} — ${item.sku}`;
            button.addEventListener('click', () => {
                picker.querySelector('[data-variant-picker-id]').value = String(item.id);
                picker.querySelector('[data-variant-picker-query]').value = item.sku;
                renderColors(picker, item.colors ?? []);
                box.innerHTML = '';
            });
            box.append(button);
        });
    };

    document.addEventListener('input', (event) => {
        const queryInput = event.target.closest('[data-variant-picker-query]');
        if (!queryInput) {
            return;
        }

        const picker = queryInput.closest('[data-variant-picker]');
        picker.querySelector('[data-variant-picker-id]').value = '';
        clearTimeout(debounceTimer);

        const q = queryInput.value.trim();
        if (q.length < 2) {
            picker.querySelector('[data-variant-picker-suggestions]').innerHTML = '';
            return;
        }

        debounceTimer = setTimeout(async () => {
            try {
                const response = await fetch(`/admin/variants/search?q=${encodeURIComponent(q)}`);
                const data = await response.json();
                renderSuggestions(picker, data.items ?? []);
            } catch {
                picker.querySelector('[data-variant-picker-suggestions]').innerHTML = '';
            }
        }, 250);
    });

    document.addEventListener('click', (event) => {
        document.querySelectorAll('[data-variant-picker-suggestions]').forEach((box) => {
            const picker = box.closest('[data-variant-picker]');
            if (picker && !picker.contains(event.target)) {
                box.innerHTML = '';
            }
        });
    });
})();

// Добавление/удаление строки позиции на форме создания Заказа (Таск 5
// Фазы 4) — клонирование `<template>` с плейсхолдером `__INDEX__` в
// именах полей, замена на следующий порядковый номер.
(() => {
    const container = document.querySelector('[data-variant-picker-list]');
    const addButton = document.querySelector('[data-variant-picker-add]');
    const template  = document.querySelector('[data-variant-picker-template]');

    if (!container || !addButton || !template) {
        return;
    }

    let nextIndex = container.querySelectorAll('[data-variant-picker-row]').length;

    addButton.addEventListener('click', () => {
        const html    = template.innerHTML.replaceAll('__INDEX__', String(nextIndex));
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();

        if (wrapper.firstElementChild) {
            container.append(wrapper.firstElementChild);
            nextIndex += 1;
        }
    });

    container.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-variant-picker-remove]');
        if (!removeButton) {
            return;
        }
        removeButton.closest('[data-variant-picker-row]')?.remove();
    });
})();

// Поиск клиента по телефону на форме создания Заказа (Таск 5 Фазы 4) —
// перехватывает GET-отправку мини-формы «Найти» (без JS та же форма
// перезагружает страницу с `?phone=...`, `AdminOrderController::create()`
// делает тот же поиск на сервере).
(() => {
    const form = document.querySelector('[data-customer-lookup-form]');
    if (!form) {
        return;
    }

    const phoneInput    = form.querySelector('[data-customer-lookup-input]');
    const resultBox     = document.querySelector('[data-customer-lookup-result]');
    const userIdInput   = document.querySelector('[data-customer-mode-user-id]');
    const userModeInput = document.querySelector('[data-customer-mode-user]');
    const guestModeInput = document.querySelector('[data-customer-mode-guest]');
    const guestPhoneInput = document.querySelector('[data-guest-phone-input]');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const phone = phoneInput.value.trim();
        if (phone === '') {
            return;
        }

        try {
            const response = await fetch(`/admin/customers/lookup?phone=${encodeURIComponent(phone)}`);
            const data     = await response.json();

            if (data.customer) {
                resultBox.innerHTML = `<div class="alert alert-info mb-0">Найден покупатель: ${data.customer.name} (${data.customer.phone})</div>`;
                userIdInput.value    = String(data.customer.id);
                userModeInput.checked = true;
            } else {
                resultBox.innerHTML = '<div class="alert alert-secondary mb-0">Покупатель не найден — заполните контакты гостя ниже.</div>';
                userIdInput.value     = '';
                guestModeInput.checked = true;
                if (guestPhoneInput.value.trim() === '') {
                    guestPhoneInput.value = phone;
                }
            }
        } catch {
            resultBox.innerHTML = '';
        }
    });
})();

// Добавление/удаление блока Варианта на форме Товара (Таск 8 Фазы 4) —
// тот же приём клонирования `<template>` с `__INDEX__`, что у позиций
// Заказа (Таск 5), под свои data-атрибуты (`variant-row`, не
// `variant-picker` — форма Товара не ищет Вариант по артикулу, поля
// вводятся вручную).
(() => {
    const container = document.querySelector('[data-variant-row-list]');
    const addButton = document.querySelector('[data-variant-row-add]');
    const template  = document.querySelector('[data-variant-row-template]');

    if (!container || !addButton || !template) {
        return;
    }

    let nextIndex = container.querySelectorAll('[data-variant-row]').length;

    addButton.addEventListener('click', () => {
        const html    = template.innerHTML.replaceAll('__INDEX__', String(nextIndex));
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();

        if (wrapper.firstElementChild) {
            container.append(wrapper.firstElementChild);
            nextIndex += 1;
        }
    });

    container.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-variant-row-remove]');
        if (!removeButton) {
            return;
        }
        removeButton.closest('[data-variant-row]')?.remove();
    });
})();

// Добавление/удаление строки характеристики на форме Товара (Таск 8
// Фазы 4) — тот же приём, что блок Варианта выше.
(() => {
    const container = document.querySelector('[data-spec-row-list]');
    const addButton = document.querySelector('[data-spec-row-add]');
    const template  = document.querySelector('[data-spec-row-template]');

    if (!container || !addButton || !template) {
        return;
    }

    let nextIndex = container.querySelectorAll('[data-spec-row]').length;

    addButton.addEventListener('click', () => {
        const html    = template.innerHTML.replaceAll('__INDEX__', String(nextIndex));
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();

        if (wrapper.firstElementChild) {
            container.append(wrapper.firstElementChild);
            nextIndex += 1;
        }
    });

    container.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-spec-row-remove]');
        if (!removeButton) {
            return;
        }
        removeButton.closest('[data-spec-row]')?.remove();
    });
})();

// «Основная» категория доступна только среди отмеченных (Таск 8 Фазы
// 4) — снятие чекбокса категории снимает и её радио «основная», если
// оно было выбрано; без JS ту же связку проверяет
// `validateProductInput()` на сервере.
(() => {
    document.addEventListener('change', (event) => {
        const checkbox = event.target.closest('[data-category-checkbox]');
        if (!checkbox || checkbox.checked) {
            return;
        }

        const option = checkbox.closest('[data-category-option]');
        const radio  = option?.querySelector('[data-category-primary]');
        if (radio) {
            radio.checked = false;
        }
    });
})();

// Диаграмма отчёта по продажам (Таск 10 Фазы 4) — данные приходят из
// `data-*` (JSON, уже посчитан на сервере), не запрашиваются отдельно;
// `chart.min.js` подключается только на этой странице
// (`admin/reports/index.php`), поэтому `Chart` есть не всегда — блок
// просто ничего не делает на остальных страницах админки.
(() => {
    const canvas = document.getElementById('report-chart');
    if (!canvas || typeof Chart === 'undefined') {
        return;
    }

    const labels = JSON.parse(canvas.dataset.labels || '[]');
    const values = JSON.parse(canvas.dataset.values || '[]');

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Сумма заказов, ₽',
                data: values,
                backgroundColor: '#0d6efd',
            }],
        },
        options: {
            scales: {
                y: { beginAtZero: true },
            },
            plugins: {
                legend: { display: false },
            },
        },
    });
})();

// Переключатель «Выставочный образец» в списке Товаров (Таск 4 Фазы 5)
// — прогрессивное улучшение: без JS форму отправляет видимая кнопка
// «OK» рядом с переключателем; при наличии JS изменение переключателя
// сразу отправляет ту же форму, кнопка остаётся резервным путём.
(() => {
    document.addEventListener('change', (event) => {
        const toggle = event.target.closest('[data-variant-showroom-toggle]');
        if (!toggle || toggle.disabled) {
            return;
        }

        toggle.closest('form')?.requestSubmit();
    });
})();
