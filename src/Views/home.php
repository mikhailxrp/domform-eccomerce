<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<int, array<string, mixed>> $banners */
/** @var array<int, array<string, mixed>> $featuredProducts */
/** @var array<int, array<string, mixed>> $storeReviews */
/** @var array<int, array<string, mixed>> $newestProducts */
/** @var array<int, array<string, mixed>> $discountedProducts */

include ROOT_PATH . '/src/Views/layout/header.php';
?>

<main>
    <?php include ROOT_PATH . '/src/Views/components/flash.php'; ?>

    <?php if ($banners !== []): ?>
    <!-- Slider Section Start -->
    <div class="section slider-section">
        <div class="slider-shape"></div>

        <div class="container">
            <div class="slider-active">
                <div class="swiper-container">
                    <div class="swiper-wrapper">
                        <?php foreach ($banners as $banner): ?>
                            <?php $bannerLink = $banner['link'] ?? '/catalog'; ?>
                            <div class="single-slider swiper-slide animation-style-01">
                                <div class="slider-content">
                                    <?php if ($banner['title'] !== null): ?>
                                        <h2 class="title"><?= e($banner['title']) ?></h2>
                                    <?php endif; ?>
                                    <a href="<?= e($bannerLink) ?>" class="btn btn-primary btn-hover-dark">Смотреть каталог</a>
                                </div>
                                <div class="slider-images">
                                    <img src="<?= e('/' . ltrim($banner['image_path'], '/')) ?>" alt="<?= e($banner['title'] ?? 'Мебель на заказ') ?>">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="swiper-pagination"></div>
                </div>
            </div>
        </div>
    </div>
    <!-- Slider Section End -->
    <?php endif; ?>

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

    <?php
    $heading = 'Хиты продаж';
    $products = $featuredProducts;
    include ROOT_PATH . '/src/Views/components/home-product-section.php';
    ?>

    <?php if ($storeReviews !== []): ?>
    <!-- Store Reviews Section Start -->
    <div class="section section-padding">
        <div class="container">
            <div class="section-title">
                <h2 class="title">Отзывы наших покупателей</h2>
            </div>
            <div class="row g-4">
                <?php foreach ($storeReviews as $review): ?>
                    <?php include ROOT_PATH . '/src/Views/components/store-review-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <!-- Store Reviews Section End -->
    <?php endif; ?>

    <?php
    $heading = 'Новинки';
    $products = $newestProducts;
    include ROOT_PATH . '/src/Views/components/home-product-section.php';
    ?>

    <?php
    $heading = 'Товары со скидкой';
    $products = $discountedProducts;
    include ROOT_PATH . '/src/Views/components/home-product-section.php';
    ?>
</main>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
