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

    jQuery(function () {
        syncHeaderSticky();
        applyContentOffset();
        jQuery(window).on('load resize', applyContentOffset);
        jQuery(window).on('load', syncHeaderSticky);
    });
})();
