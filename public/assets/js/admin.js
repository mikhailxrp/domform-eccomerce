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

(() => {
    const picker = document.querySelector('[data-variant-picker]');
    if (!picker) {
        return;
    }

    const queryInput   = picker.querySelector('[data-variant-picker-query]');
    const idInput      = picker.querySelector('[data-variant-picker-id]');
    const colorSelect  = picker.querySelector('[data-variant-picker-color]');
    const suggestions  = picker.querySelector('[data-variant-picker-suggestions]');

    let debounceTimer = null;

    const renderColors = (colors) => {
        colorSelect.innerHTML = '<option value="">—</option>';
        colors.forEach((color) => {
            const option = document.createElement('option');
            option.value = color;
            option.textContent = color;
            colorSelect.append(option);
        });
    };

    const renderSuggestions = (items) => {
        suggestions.innerHTML = '';
        items.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action';
            button.textContent = `${item.name} — ${item.sku}`;
            button.addEventListener('click', () => {
                idInput.value = String(item.id);
                queryInput.value = item.sku;
                renderColors(item.colors ?? []);
                suggestions.innerHTML = '';
            });
            suggestions.append(button);
        });
    };

    queryInput.addEventListener('input', () => {
        idInput.value = '';
        clearTimeout(debounceTimer);

        const q = queryInput.value.trim();
        if (q.length < 2) {
            suggestions.innerHTML = '';
            return;
        }

        debounceTimer = setTimeout(async () => {
            try {
                const response = await fetch(`/admin/variants/search?q=${encodeURIComponent(q)}`);
                const data = await response.json();
                renderSuggestions(data.items ?? []);
            } catch {
                suggestions.innerHTML = '';
            }
        }, 250);
    });

    document.addEventListener('click', (event) => {
        if (!picker.contains(event.target)) {
            suggestions.innerHTML = '';
        }
    });
})();
