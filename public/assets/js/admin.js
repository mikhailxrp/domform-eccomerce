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
