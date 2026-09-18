(function () {
    'use strict';

    function currentHeaderHeight() {
        var $header = jQuery('.header-area.header-sticky:visible');
        if (!$header.length) {
            $header = jQuery('.header-mobile:visible');
        }
        return $header.length ? $header.outerHeight() : 0;
    }

    /** Небольшой запас сверх точной высоты хедера — иначе контент начинается
        ровно в притык к нему, без визуального «воздуха». */
    var CONTENT_OFFSET_GAP = 24;

    function applyContentOffset() {
        var height = currentHeaderHeight();
        if (height > 0) {
            jQuery('.page-content-offset').css('padding-top', (height + CONTENT_OFFSET_GAP) + 'px');
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

    /**
     * `<select id="catalog-sort">` приходит внутри fetch-фрагмента, а
     * main.js оборачивает `.nice_select` только один раз при загрузке —
     * после подмены `#catalog-results` оформляем новый select сами.
     * Плагин триггерит `change` на исходном select при выборе пункта —
     * делегированный обработчик в `initCatalogFilters()` его увидит.
     */
    function initCatalogSortSelect() {
        var $sort = jQuery('#catalog-sort');
        if (!$sort.length || typeof $sort.niceSelect !== 'function') {
            return;
        }
        if (!$sort.next('.nice-select').length) {
            $sort.niceSelect();
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

    /**
     * В полёте — ровно один запрос: новый фильтр отменяет предыдущий,
     * иначе ответы приходят вразнобой и старый перезаписывает новый.
     */
    var catalogFetchController = null;

    function loadCatalogResults(url, pushState) {
        var $results = jQuery('#catalog-results');
        if (!$results.length) {
            return;
        }
        $results.attr('aria-busy', 'true');

        if (catalogFetchController) {
            catalogFetchController.abort();
        }
        var controller = window.AbortController ? new AbortController() : null;
        catalogFetchController = controller;

        fetch(url, {
            headers: { 'X-Requested-With': 'fetch' },
            credentials: 'same-origin',
            signal: controller ? controller.signal : undefined
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('catalog fetch failed: ' + response.status);
                }
                return response.text();
            })
            .then(function (html) {
                catalogFetchController = null;
                $results.html(html).removeAttr('aria-busy');
                initCatalogSortSelect();
                applyStoredCatalogView();
                if (pushState) {
                    window.history.pushState({ catalogUrl: url }, '', url);
                }
            })
            .catch(function (error) {
                // Отменён более новым запросом — тот сам снимет aria-busy
                if (error && error.name === 'AbortError') {
                    return;
                }
                // Сеть подвела/сервер недоступен — обычная навигация как
                // запасной вариант, тот же URL работает и без JS
                window.location.href = url;
            });
    }

    /** Чекбоксы/цвет в сайдбаре сбрасываются вручную — вне `#catalog-results`, повторный fetch их не перерисует. */
    function resetCatalogFilterForm() {
        jQuery('#catalog-filter-form input[type=checkbox]').prop('checked', false);
        // Hidden-поля — до `update()` слайдера: он триггерит `change` на
        // своём input, и обработчик ниже не должен увидеть старые значения
        jQuery('#catalog-price-min, #catalog-price-max').val('');

        var $slider = jQuery('#catalog-price-slider');
        var instance = $slider.data('ionRangeSlider');
        if (instance) {
            instance.update({ from: instance.options.min, to: instance.options.max });
        }
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

        // `#catalog-price-slider` исключён: ion.rangeSlider сам триггерит
        // `change` на своём input при каждом сдвиге ползунка (и при
        // `update()`) — это дало бы fetch на каждый mousemove; для цены
        // фильтр применяет `onFinish` в `initCatalogPriceSlider()`
        jQuery(document).on(
            'change',
            '#catalog-filter-form input:not(#catalog-price-slider), #catalog-filter-form select, #catalog-sort',
            applyCatalogFilters
        );

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

    /**
     * Карточка товара (Таск 4) — переключение Варианта/цвета на клиенте
     * без похода на сервер: данные всех Вариантов уже в разметке
     * (`data-variants`, JSON). Миниатюры под главным фото — визуальный
     * выбор цвета (`#product-thumbnails`, живёт в колонке с фото, а не
     * внутри `.product-variant-selector` в колонке описания — оба
     * читают одни и те же `data-variants`).
     */
    function initProductVariantSelector() {
        var $root = jQuery('.product-variant-selector');
        if (!$root.length) {
            return;
        }

        var variants = [];
        try {
            variants = JSON.parse($root.attr('data-variants') || '[]');
        } catch (e) {
            variants = [];
        }

        var $thumbnails   = jQuery('#product-thumbnails');
        var $colorName    = jQuery('#product-color-name');
        var $mechanism    = $root.find('.product-variant-selector__mechanism');
        var $leadTime     = $root.find('.product-variant-selector__lead-time');
        var $variantInput = $root.find('.product-variant-selector__variant-input');
        var $colorInput   = $root.find('.product-variant-selector__color-input');
        var $hint         = $root.find('.product-variant-selector__hint');
        var $price        = jQuery('#product-price');
        var $mainImage    = jQuery('#product-main-image');

        var preselected        = parseInt($root.attr('data-preselected'), 10);
        var selectedVariantId  = isNaN(preselected) ? null : preselected;

        function findVariant(variantId) {
            for (var i = 0; i < variants.length; i++) {
                if (variants[i].id === variantId) {
                    return variants[i];
                }
            }
            return null;
        }

        /** Точное совпадение по цвету, иначе первое фото Варианта — своя фотка есть не у каждого цвета. */
        function findImage(variant, color) {
            if (!variant || !variant.images.length) {
                return null;
            }
            for (var i = 0; i < variant.images.length; i++) {
                if (variant.images[i].color === color) {
                    return variant.images[i];
                }
            }
            return variant.images[0];
        }

        /** Миниатюры принадлежат текущему Варианту — при смене материала перестраиваем весь ряд. */
        function renderThumbnails(variant) {
            if (!$thumbnails.length) {
                return;
            }
            $thumbnails.empty();

            var seen = {};
            variant.images.forEach(function (image) {
                if (!image.color || seen[image.color]) {
                    return;
                }
                seen[image.color] = true;
                jQuery('<button type="button" class="details-gallery-thumbs__item"><img></button>')
                    .attr('data-color', image.color)
                    .find('img').attr('src', image.path).attr('alt', image.color)
                    .end()
                    .appendTo($thumbnails);
            });

            $thumbnails.attr('hidden', Object.keys(seen).length < 2);
        }

        function renderVariant(variantId, preferredColor) {
            var variant = findVariant(variantId);
            if (!variant) {
                return;
            }
            selectedVariantId = variantId;

            $root.find('.product-variant-selector__option[data-variant-id]').removeClass('active');
            $root.find('.product-variant-selector__option[data-variant-id="' + variantId + '"]').addClass('active');

            renderThumbnails(variant);

            var image = findImage(variant, preferredColor);
            var selectedColor = image ? image.color : null;

            $thumbnails.children('.details-gallery-thumbs__item').removeClass('active');
            if (selectedColor) {
                $thumbnails.find('.details-gallery-thumbs__item[data-color="' + selectedColor + '"]').addClass('active');
            }

            if (image && $mainImage.length) {
                $mainImage.attr('src', image.path).attr('alt', variant.material);
            }

            if ($price.length) {
                $price.text(variant.price_formatted);
            }

            if ($colorName.length) {
                $colorName.text(selectedColor || '');
            }

            $mechanism.text(variant.mechanism_type ? 'Механизм: ' + variant.mechanism_type : '').attr('hidden', !variant.mechanism_type);

            if (variant.is_showroom_sample) {
                $leadTime.html('<strong>Выставочный образец</strong> — готов к выдаче.');
            } else {
                $leadTime.html(
                    'Срок изготовления: <strong>' + variant.production_time + '</strong>. Товар изготавливается под заказ.'
                );
            }

            $variantInput.val(variantId);
            $colorInput.val(selectedColor || '');
            $hint.attr('hidden', true);
        }

        $root.on('click', '.product-variant-selector__option[data-variant-id]', function () {
            renderVariant(parseInt(jQuery(this).attr('data-variant-id'), 10), null);
        });

        jQuery(document).on('click', '#product-thumbnails .details-gallery-thumbs__item', function () {
            var variantId = selectedVariantId !== null ? selectedVariantId : (variants[0] ? variants[0].id : null);
            renderVariant(variantId, jQuery(this).attr('data-color'));
        });

        $root.on('submit', '.product-variant-selector__cart-form', function (event) {
            if (!selectedVariantId) {
                event.preventDefault();
                $hint.removeAttr('hidden');
            }
        });

        if (selectedVariantId !== null) {
            renderVariant(selectedVariantId, null);
        }
    }

    /**
     * Подсказки поиска в шапке (Таск 5) — `#search-suggest`-контейнер живёт
     * рядом с каждым из двух полей (десктоп/мобильное, оба в разметке
     * одновременно, переключаются медиа-запросами темы), поэтому ищем его
     * через `.closest('.header-search')`, а не по фиксированному id.
     */
    var SEARCH_SUGGEST_DEBOUNCE_MS = 250;
    var searchSuggestTimer         = null;
    var searchSuggestActiveIndex   = -1;

    function closeSearchSuggest($suggest) {
        $suggest.attr('hidden', true).empty();
        searchSuggestActiveIndex = -1;
    }

    function renderSearchSuggest($suggest, items) {
        $suggest.empty();

        if (!items.length) {
            closeSearchSuggest($suggest);
            return;
        }

        items.forEach(function (item) {
            jQuery('<a class="search-suggest__item" role="option"></a>')
                .attr('href', item.url)
                .append(jQuery('<img alt="">').attr('src', item.image))
                .append(jQuery('<span></span>').text(item.name))
                .appendTo($suggest);
        });

        $suggest.removeAttr('hidden');
        searchSuggestActiveIndex = -1;
    }

    function fetchSearchSuggest($input) {
        var q        = $input.val().trim();
        var $suggest = $input.closest('.header-search').find('.search-suggest');
        if (!$suggest.length) {
            return;
        }

        if (q.length < 2) {
            closeSearchSuggest($suggest);
            return;
        }

        fetch('/search/suggest?q=' + encodeURIComponent(q), {
            headers: { 'X-Requested-With': 'fetch' },
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.ok ? response.json() : { items: [] };
            })
            .then(function (data) {
                renderSearchSuggest($suggest, data.items || []);
            })
            .catch(function () {
                closeSearchSuggest($suggest);
            });
    }

    function initSearchSuggest() {
        if (!jQuery('.header-search__input').length) {
            return;
        }

        jQuery(document).on('input', '.header-search__input', function () {
            var $input = jQuery(this);
            clearTimeout(searchSuggestTimer);
            searchSuggestTimer = setTimeout(function () {
                fetchSearchSuggest($input);
            }, SEARCH_SUGGEST_DEBOUNCE_MS);
        });

        jQuery(document).on('keydown', '.header-search__input', function (event) {
            var $suggest = jQuery(this).closest('.header-search').find('.search-suggest');
            var $items   = $suggest.find('.search-suggest__item');
            if (!$items.length || $suggest.attr('hidden') !== undefined) {
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                searchSuggestActiveIndex = Math.min(searchSuggestActiveIndex + 1, $items.length - 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                searchSuggestActiveIndex = Math.max(searchSuggestActiveIndex - 1, 0);
            } else if (event.key === 'Enter') {
                if (searchSuggestActiveIndex >= 0) {
                    event.preventDefault();
                    window.location.href = $items.eq(searchSuggestActiveIndex).attr('href');
                }
                return;
            } else if (event.key === 'Escape') {
                closeSearchSuggest($suggest);
                return;
            } else {
                return;
            }

            $items.removeClass('active').attr('aria-selected', 'false');
            $items.eq(searchSuggestActiveIndex).addClass('active').attr('aria-selected', 'true');
        });

        jQuery(document).on('click', function (event) {
            if (jQuery(event.target).closest('.header-search').length) {
                return;
            }
            jQuery('.search-suggest').each(function () {
                closeSearchSuggest(jQuery(this));
            });
        });
    }

    /** Страница `/search` (Таск 5) — сортировка обычной формой, без fetch. */
    function initSearchSortSubmit() {
        jQuery(document).on('change', '#search-sort', function () {
            if (this.form) {
                this.form.submit();
            }
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
        initProductVariantSelector();
        initSearchSuggest();
        initSearchSortSubmit();
        jQuery(window).on('load resize', applyContentOffset);
        jQuery(window).on('load', syncHeaderSticky);
        jQuery(document).on('close.bs.alert', '.alert', function () {
            cleanupEmptyContentOffset(jQuery(this));
        });
    });
})();
