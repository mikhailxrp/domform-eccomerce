<?php

declare(strict_types=1);

/** @var string $title */

include ROOT_PATH . '/src/Views/layout/header.php';
?>

<main>
    <?php include ROOT_PATH . '/src/Views/components/flash.php'; ?>
    <!-- Slider Section Start -->
    <div class="section slider-section">
        <div class="slider-shape"></div>

        <div class="container">
            <div class="slider-active">
                <div class="swiper-container">
                    <div class="swiper-wrapper">

                        <div class="single-slider swiper-slide animation-style-01">
                            <div class="slider-content">
                                <h2 class="title">Мебель на заказ <br> для вашего дома</h2>
                                <p>Уникальный стиль под ваш интерьер</p>
                                <a href="#" class="btn btn-primary btn-hover-dark">Смотреть каталог</a>
                            </div>
                            <div class="slider-images">
                                <img src="/assets/images/slider/slider-item-1.png" alt="Мебель на заказ">
                            </div>
                        </div>

                        <div class="single-slider swiper-slide animation-style-01">
                            <div class="slider-content">
                                <h2 class="title">Стиль и комфорт <br> в каждой детали</h2>
                                <p>Материал, цвет и размер — на ваш выбор</p>
                                <a href="#" class="btn btn-primary btn-hover-dark">Смотреть каталог</a>
                            </div>
                            <div class="slider-images">
                                <img src="/assets/images/slider/slider-item-2.png" alt="Мебель на заказ">
                            </div>
                        </div>

                        <div class="single-slider swiper-slide animation-style-01">
                            <div class="slider-content">
                                <h2 class="title">Мебель, сделанная <br> для вас</h2>
                                <p>От эскиза до сборки — под ключ</p>
                                <a href="#" class="btn btn-primary btn-hover-dark">Смотреть каталог</a>
                            </div>
                            <div class="slider-images">
                                <img src="/assets/images/slider/slider-item-3.png" alt="Мебель на заказ">
                            </div>
                        </div>

                    </div>

                    <div class="swiper-pagination"></div>
                </div>
            </div>
        </div>
    </div>
    <!-- Slider Section End -->

    <!-- Benefit Section Start -->
    <div class="section section-padding">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-6">
                    <div class="single-benefit">
                        <img src="/assets/images/icon/icon-1.png" alt="Изготовление под заказ">
                        <h3 class="title">Изготовление под заказ</h3>
                        <p>Материал, цвет и механизм — по вашему выбору.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="single-benefit">
                        <img src="/assets/images/icon/icon-2.png" alt="Оплата">
                        <h3 class="title">Удобная оплата</h3>
                        <p>Наличными, переводом или картой онлайн.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="single-benefit">
                        <img src="/assets/images/icon/icon-3.png" alt="Доставка">
                        <h3 class="title">Доставка по краю</h3>
                        <p>Самовывоз или доставка — уточнит менеджер.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Benefit Section End -->
</main>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
