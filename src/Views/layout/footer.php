<?php

declare(strict_types=1);

?>
    <!-- Footer Section Start -->
    <div class="section footer-section">
        <!-- Footer Top Start -->
        <div class="footer-top">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-3 col-md-4">
                        <div class="footer-logo">
                            <a href="/"><img src="/assets/images/logo.png" width="159" height="46" alt="ДомФорм"></a>
                        </div>
                    </div>
                    <div class="col-lg-9 col-md-8">
                        <div class="footer-contact-payment">
                            <div class="footer-contact ms-auto">
                                <div class="contact-icon">
                                    <img src="/assets/images/icon/icon-4.png" width="39" height="46" alt="">
                                </div>
                                <div class="contact-content">
                                    <h6 class="title">Позвоните нам:</h6>
                                    <p><a href="tel:<?= e(phoneToTel(setting('shop_phone'))) ?>"><?= e(setting('shop_phone')) ?></a></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Footer Top End -->

        <div class="footer-widget-section">
            <div class="container">
                <div class="row">
                    <div class="col-lg-3 col-md-4 col-sm-6">
                        <div class="footer-widget">
                            <h4 class="footer-widget-title">Покупателям</h4>
                            <ul class="footer-link">
                                <li><a href="/pages/delivery-payment">Доставка и оплата</a></li>
                                <li><a href="/pages/return-warranty">Возврат и гарантия</a></li>
                                <li><a href="/pages/offer">Публичная оферта</a></li>
                                <li><a href="/pages/privacy-policy">Политика конфиденциальности</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-4 col-sm-6">
                        <div class="footer-widget">
                            <h4 class="footer-widget-title">Информация</h4>
                            <ul class="footer-link">
                                <li><a href="/pages/about">О компании</a></li>
                                <li><a href="/pages/contacts">Контакты</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-4 col-sm-6">
                        <div class="footer-widget">
                            <h4 class="footer-widget-title">Личный кабинет</h4>
                            <ul class="footer-link">
                                <li><a href="/login">Вход</a></li>
                                <li><a href="/register">Регистрация</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-12">
                        <div class="footer-widget">
                            <h4 class="footer-widget-title">ДомФорм</h4>
                            <div class="widget-about">
                                <p>Мебель на заказ для дома в Краснодаре и крае.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="copyright">
            <div class="container">
                <div class="copyright-text">
                    <p>&copy; <?= e((string) date('Y')) ?> ДомФорм</p>
                </div>
            </div>
        </div>
    </div>
    <!-- Footer Section End -->

    <a href="#" class="back-to-top"><i class="pe-7s-angle-up"></i></a>

    <?php include ROOT_PATH . '/src/Views/components/cookie-notice.php'; ?>
    <?php include ROOT_PATH . '/src/Views/components/page-loader.php'; ?>

    <!-- Modernizer & jQuery JS -->
    <script src="/assets/js/vendor/modernizr-3.11.2.min.js"></script>
    <script src="/assets/js/vendor/jquery-3.5.1.min.js"></script>

    <!-- Bootstrap JS -->
    <script src="/assets/js/plugins/popper.min.js"></script>
    <script src="/assets/js/plugins/bootstrap.min.js"></script>

    <!-- Plugins JS -->
    <script src="/assets/js/plugins/swiper-bundle.min.js"></script>
    <script src="/assets/js/plugins/ajax-contact.js"></script>
    <script src="/assets/js/plugins/appear.js"></script>
    <script src="/assets/js/plugins/odometer.min.js"></script>
    <script src="/assets/js/plugins/jquery.nice-select.min.js"></script>
    <script src="/assets/js/plugins/select2.min.js"></script>
    <script src="/assets/js/plugins/ion.rangeSlider.min.js"></script>
    <script src="/assets/js/plugins/jquery.zoom.min.js"></script>

    <!-- Main JS -->
    <script src="/assets/js/main.js"></script>

    <!-- Свой код -->
    <script src="/assets/js/app.js"></script>

</body>

</html>
