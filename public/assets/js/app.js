(function () {
    'use strict';

    function currentHeaderHeight() {
        var $header = jQuery('.header-area.header-sticky:visible');
        if (!$header.length) {
            $header = jQuery('.header-mobile:visible');
        }
        return $header.length ? $header.outerHeight() : 0;
    }

    function applyContentOffset() {
        var height = currentHeaderHeight();
        if (height > 0) {
            jQuery('.page-content-offset').css('padding-top', height + 'px');
        }
    }

    /**
     * main.js добавляет/снимает .sticky только по событию scroll — если
     * страница открывается уже прокрученной (якорь, восстановленная
     * браузером позиция), белый закреплённый хедер остаётся навсегда:
     * событие ни разу не срабатывает при текущем состоянии скролла.
     * Синхронизируем состояние сразу при загрузке той же логикой.
     */
    function syncHeaderSticky() {
        jQuery('.header-sticky').toggleClass('sticky', jQuery(window).scrollTop() > 1);
    }

    var COOKIE_NOTICE_STORAGE_KEY = 'domform_cookie_notice_dismissed';

    function initCookieNotice() {
        var $notice = jQuery('#cookie-notice');
        if (!$notice.length) {
            return;
        }

        var dismissed = false;
        try {
            dismissed = window.localStorage.getItem(COOKIE_NOTICE_STORAGE_KEY) === '1';
        } catch (e) {
            dismissed = false;
        }

        if (!dismissed) {
            $notice.removeAttr('hidden');
        }

        jQuery('#cookie-notice-accept').on('click', function () {
            $notice.attr('hidden', true);
            try {
                window.localStorage.setItem(COOKIE_NOTICE_STORAGE_KEY, '1');
            } catch (e) {
                // localStorage недоступен (приватный режим и т.п.) —
                // баннер просто покажется снова в следующий раз
            }
        });
    }

    /**
     * flash.php оборачивает уведомление в .page-content-offset, чтобы не
     * прятаться под хедером (см. выше). Bootstrap при закрытии крестиком
     * удаляет только сам .alert — пустая обёртка остаётся вместе со своим
     * padding-top: белое пустое место сверху страницы, будто хедер
     * «залип». Убираем обёртку следом, если в ней не осталось алертов.
     */
    function cleanupEmptyContentOffset($alert) {
        var $wrapper = $alert.closest('.page-content-offset');
        if (!$wrapper.length) {
            return;
        }
        setTimeout(function () {
            if ($wrapper.find('.alert').length === 0) {
                $wrapper.remove();
            }
        }, 200);
    }

    /**
     * Кнопка-«глазик» у поля пароля (login/register/reset) — переключает
     * type у соседнего input между password/text и иконку fa-eye/
     * fa-eye-slash.
     */
    function initPasswordToggles() {
        jQuery(document).on('click', '.single-form__password-toggle', function () {
            var $button    = jQuery(this);
            var $input     = $button.siblings('input');
            var isPassword = $input.attr('type') === 'password';

            $input.attr('type', isPassword ? 'text' : 'password');
            $button.find('i').toggleClass('fa-eye fa-eye-slash');
            $button.attr('aria-label', isPassword ? 'Скрыть пароль' : 'Показать пароль');
        });
    }

    /**
     * Каталог (Таск 3) — фильтры/сортировка/пагинация без перезагрузки:
     * `#catalog-filter-form` (сайдбар) остаётся статичным между запросами,
     * `#catalog-results` (счётчик + плитка/список + пагинация) целиком
     * подменяется ответом `fetch` с `X-Requested-With: fetch`
     * (`phase-1.md`, «Решения фазы» — тот же Controller, без отдельного
     * JSON API). Без JS форма — обычный GET, тот же путь `action`.
     */
    var CATALOG_VIEW_STORAGE_KEY = 'domform_catalog_view';

    function storedCatalogView() {
        try {
            return window.sessionStorage.getItem(CATALOG_VIEW_STORAGE_KEY) === 'list' ? 'list' : 'grid';
        } catch (e) {
            return 'grid';
        }
    }

    /**
     * Серверный фрагмент всегда рендерит плитку активной по умолчанию —
     * после каждой подмены `#catalog-results` (и при первой загрузке
     * страницы) переключаем на сохранённый вид сами.
     */
    function applyStoredCatalogView() {
        var view     = storedCatalogView();
        var $button  = jQuery('#catalog-view-' + view);
        if (!$button.length || $button.hasClass('active')) {
            return;
        }
        if (window.bootstrap && window.bootstrap.Tab) {
            window.bootstrap.Tab.getOrCreateInstance($button.get(0)).show();
        } else {
            $button.trigger('click');
        }
    }

    function initCatalogViewToggle() {
        jQuery(document).on('shown.bs.tab', '[data-catalog-view]', function () {
            try {
                window.sessionStorage.setItem(CATALOG_VIEW_STORAGE_KEY, jQuery(this).data('catalog-view'));
            } catch (e) {
                // sessionStorage недоступен — переключение работает,
                // просто не переживёт следующую загрузку страницы
            }
        });
    }

    /**
     * Свой id слайдера (не `#price-range` из main.js) — тема уже вешает
     * на `#price-range` инициализацию с чужими жёстко зашитыми
     * min/max/from/to при загрузке любой страницы; свой id не даёт им
     * столкнуться.
     */
    function initCatalogPriceSlider() {
        var $slider = jQuery('#catalog-price-slider');
        if (!$slider.length || typeof $slider.ionRangeSlider !== 'function') {
            return;
        }

        $slider.ionRangeSlider({
            type: 'double',
            grid: false,
            min: parseInt($slider.data('min'), 10) || 0,
            max: parseInt($slider.data('max'), 10) || 0,
            from: parseInt($slider.data('from'), 10) || 0,
            to: parseInt($slider.data('to'), 10) || 0,
            postfix: ' ₽',
            onFinish: function (data) {
                jQuery('#catalog-price-min').val(data.from);
                jQuery('#catalog-price-max').val(data.to);
                applyCatalogFilters();
            }
        });
    }

    /**
     * `<select name="sort">` живёт внутри `#catalog-results` (значение
     * должно приходить со свежими данными после каждого фильтра), сам
     * `<select>` связан с сайдбар-формой через HTML-атрибут `form=` —
     * FormData видит только реальных потомков DOM-узла формы, поэтому
     * добавляем сортировку в URL отдельно.
     */
    function buildCatalogFilterUrl() {
        var $form = jQuery('#catalog-filter-form');
        if (!$form.length) {
            return null;
        }

        var params = new URLSearchParams(new FormData($form.get(0)));
        var $sort  = jQuery('#catalog-sort');
        if ($sort.length && $sort.val() && $sort.val() !== 'newest') {
            params.set('sort', $sort.val());
        }

        var query = params.toString();
        return $form.attr('action') + (query ? '?' + query : '');
    }

    function loadCatalogResults(url, pushState) {
        var $results = jQuery('#catalog-results');
        if (!$results.length) {
            return;
        }
        $results.attr('aria-busy', 'true');

        fetch(url, { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('catalog fetch failed: ' + response.status);
                }
                return response.text();
            })
            .then(function (html) {
                $results.html(html).removeAttr('aria-busy');
                applyStoredCatalogView();
                if (pushState) {
                    window.history.pushState({ catalogUrl: url }, '', url);
                }
            })
            .catch(function () {
                // Сеть подвела/сервер недоступен — обычная навигация как
                // запасной вариант, тот же URL работает и без JS
                window.location.href = url;
            });
    }

    /** Чекбоксы/цвет в сайдбаре сбрасываются вручную — вне `#catalog-results`, повторный fetch их не перерисует. */
    function resetCatalogFilterForm() {
        jQuery('#catalog-filter-form input[type=checkbox]').prop('checked', false);

        var $slider = jQuery('#catalog-price-slider');
        var instance = $slider.data('ionRangeSlider');
        if (instance) {
            instance.update({ from: instance.options.min, to: instance.options.max });
        }
        jQuery('#catalog-price-min, #catalog-price-max').val('');
    }

    function applyCatalogFilters() {
        var url = buildCatalogFilterUrl();
        if (url) {
            loadCatalogResults(url, true);
        }
    }

    function initCatalogFilters() {
        if (!jQuery('#catalog-filter-form').length) {
            return;
        }

        jQuery(document).on('change', '#catalog-filter-form input, #catalog-filter-form select, #catalog-sort', applyCatalogFilters);

        jQuery(document).on('submit', '#catalog-filter-form', function (event) {
            event.preventDefault();
            applyCatalogFilters();
        });

        jQuery(document).on('click', '#catalog-results .page-link', function (event) {
            var $link = jQuery(this);
            var href  = $link.attr('href');
            if (!href || href === '#' || $link.closest('.page-item').hasClass('disabled')) {
                return;
            }
            event.preventDefault();
            loadCatalogResults(href, true);
        });

        jQuery(document).on('click', '.catalog-reset-link', function (event) {
            var href = jQuery(this).attr('href');
            if (!href) {
                return;
            }
            event.preventDefault();
            resetCatalogFilterForm();
            loadCatalogResults(href, true);
        });

        window.addEventListener('popstate', function () {
            loadCatalogResults(window.location.pathname + window.location.search, false);
        });
    }

    jQuery(function () {
        syncHeaderSticky();
        applyContentOffset();
        initCookieNotice();
        initPasswordToggles();
        initCatalogViewToggle();
        initCatalogPriceSlider();
        initCatalogFilters();
        applyStoredCatalogView();
        jQuery(window).on('load resize', applyContentOffset);
        jQuery(window).on('load', syncHeaderSticky);
        jQuery(document).on('close.bs.alert', '.alert', function () {
            cleanupEmptyContentOffset(jQuery(this));
        });
    });
})();
