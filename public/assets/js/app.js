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

    jQuery(function () {
        syncHeaderSticky();
        applyContentOffset();
        initCookieNotice();
        jQuery(window).on('load resize', applyContentOffset);
        jQuery(window).on('load', syncHeaderSticky);
        jQuery(document).on('close.bs.alert', '.alert', function () {
            cleanupEmptyContentOffset(jQuery(this));
        });
    });
})();
