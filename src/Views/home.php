<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<int, array<string, mixed>> $banners */
/** @var array<int, array{label: string, products: array<int, array<string, mixed>>}> $productTabs */
/** @var array<int, array{label: string, products: array<int, array<string, mixed>>}> $bestsellerTabs */
/** @var array<int, array{slug: string, name: string, image: string, count: int}> $categoryBanners */
/** @var array<int, array<string, mixed>> $storeReviews */
/** @var array<int,int> $favoriteIds id избранных Товаров текущего пользователя (Таск 6 Фазы 7); пусто для гостя */

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
                <img src="<?= e('/' . ltrim($banner['image_path'], '/')) ?>"
                  alt="<?= e($banner['title'] ?? 'Мебель на заказ') ?>">
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

  <!-- Banner Section Start -->
  <div class="section section-padding-02 mt-n6">
    <div class="container">
      <div class="row">
        <div class="col-lg-4 col-md-6">
          <div class="single-banner">
            <img src="/assets/images/banner/banner-01.webp" alt="Диваны">
            <div class="banner-content">
              <h3 class="title"><a href="/catalog/divany">Диваны</a></h3>
              <span class="discount">Новинки</span>
              <a class="btn btn-primary btn-hover-dark" href="/catalog/divany">Смотреть</a>
            </div>
          </div>
        </div>
        <div class="col-lg-4 col-md-6">
          <div class="single-banner">
            <img src="/assets/images/banner/banner-02.webp" alt="Шкафы">
            <div class="banner-content">
              <h3 class="title"><a href="/catalog/shkafy">Шкафы</a></h3>
              <span class="discount">Хиты продаж</span>
              <a class="btn btn-primary btn-hover-dark" href="/catalog/shkafy">Смотреть</a>
            </div>
          </div>
        </div>
        <div class="col-lg-4 col-md-6">
          <div class="single-banner">
            <img src="/assets/images/banner/banner-03.webp" alt="Товары со скидкой">
            <div class="banner-content">
              <h3 class="title"><a href="/catalog?on_sale=1">Товары со скидкой</a></h3>
              <span class="discount">Скидки</span>
              <a class="btn btn-primary btn-hover-dark" href="/catalog?on_sale=1">Смотреть</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- Banner Section End -->

  <?php if ($productTabs !== []): ?>
  <!-- New Products Section Start -->
  <div class="section section-padding mt-n10">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-3 col-md-6">
          <div class="single-banner-02 mt-10">
            <img src="/assets/images/banner/banner-04.webp" alt="Каталог мебели">
            <div class="banner-content">
              <h3 class="title"><a href="/catalog">Вся мебель</a></h3>
              <span class="discount">Каталог</span>
              <a class="btn btn-primary btn-hover-dark" href="/catalog">Смотреть</a>
            </div>
          </div>
        </div>
        <div class="col-lg-9">
          <!-- Product Wrapper Start -->
          <div class="product-wrapper mt-9 home-products-tabs">
            <div class="product-top-wrapper">
              <div class="section-title">
                <h2 class="title">Товары</h2>
              </div>

              <?php if (count($productTabs) > 1): ?>
              <div class="product-menu">
                <ul class="nav">
                  <?php foreach ($productTabs as $tabIndex => $tab): ?>
                  <li>
                    <button class="<?= $tabIndex === 0 ? 'active' : '' ?>" data-bs-toggle="tab"
                      data-bs-target="#home-products-tab-<?= $tabIndex ?>">
                      <?= e($tab['label']) ?>
                    </button>
                  </li>
                  <?php endforeach; ?>
                </ul>
              </div>
              <?php endif; ?>

              <div class="swiper-arrows">
                <div class="swiper-button-prev"><i class="pe-7s-angle-left"></i></div>
                <div class="swiper-button-next"><i class="pe-7s-angle-right"></i></div>
              </div>
            </div>

            <div class="product-tabs-content">
              <div class="tab-content">
                <?php foreach ($productTabs as $tabIndex => $tab): ?>
                <div class="tab-pane fade <?= $tabIndex === 0 ? 'show active' : '' ?>"
                  id="home-products-tab-<?= $tabIndex ?>">
                  <div class="swiper-container">
                    <div class="swiper-wrapper">
                      <?php $viewMode = 'swiper'; ?>
                      <?php foreach ($tab['products'] as $product): ?>
                      <?php include ROOT_PATH . '/src/Views/components/product-card.php'; ?>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <!-- Product Wrapper End -->
        </div>
      </div>
    </div>
  </div>
  <!-- New Products Section End -->
  <?php endif; ?>

  <!-- Call To Action Section Start -->
  <div class="section call-to-action" style="background-image: url(/uploads/baners/bg-1.webp)">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-8">
          <div class="call-to-action-content text-center">
            <h1 class="title">ДомФорм — мебель на заказ</h1>
            <p>Изготавливаем мебель под ваш размер, ткань и бюджет — от диванов до шкафов-купе. Оформите заказ онлайн,
              остальное согласует менеджер по телефону.</p>
            <a href="/catalog" class="btn btn-primary btn-hover-dark">Смотреть каталог</a>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- Call To Action Section End -->

  <!-- Brand Logo Section Start -->
  <div class="section brand-logo">
    <div class="container">
      <div class="row row-cols-3 row-cols-sm-4 row-cols-lg-5 g-0 justify-content-center">
        <?php for ($brandIndex = 1; $brandIndex <= 5; $brandIndex++): ?>
        <div class="col">
          <div class="single-brand-02">
            <img src="/assets/images/brand/brand-<?= $brandIndex ?>.png" alt="Наш ориентир по качеству">
          </div>
        </div>
        <?php endfor; ?>
      </div>
    </div>
  </div>
  <!-- Brand Logo Section End -->

  <?php if ($bestsellerTabs !== []): ?>
  <!-- Best Sellers Section Start -->
  <div class="section section-padding-02 mt-n10">
    <div class="container">
      <div class="row flex-row-reverse justify-content-center">
        <div class="col-lg-3 col-md-6">
          <div class="single-banner-02 mt-10">
            <img src="/assets/images/banner/banner-04.webp" alt="Лидеры продаж">
            <div class="banner-content">
              <h3 class="title"><a href="/catalog">Лидеры продаж</a></h3>
              <span class="discount">Топ</span>
              <a class="btn btn-primary btn-hover-dark" href="/catalog">Смотреть</a>
            </div>
          </div>
        </div>
        <div class="col-lg-9">
          <!-- Product Wrapper Start -->
          <div class="product-wrapper mt-9 home-bestsellers-tabs">
            <div class="product-top-wrapper">
              <div class="section-title">
                <h2 class="title"># Лидеры продаж</h2>
              </div>

              <?php if (count($bestsellerTabs) > 1): ?>
              <div class="product-menu">
                <ul class="nav">
                  <?php foreach ($bestsellerTabs as $tabIndex => $tab): ?>
                  <li>
                    <button class="<?= $tabIndex === 0 ? 'active' : '' ?>" data-bs-toggle="tab"
                      data-bs-target="#home-bestsellers-tab-<?= $tabIndex ?>">
                      <?= e($tab['label']) ?>
                    </button>
                  </li>
                  <?php endforeach; ?>
                </ul>
              </div>
              <?php endif; ?>

              <div class="swiper-arrows">
                <div class="swiper-button-prev"><i class="pe-7s-angle-left"></i></div>
                <div class="swiper-button-next"><i class="pe-7s-angle-right"></i></div>
              </div>
            </div>

            <div class="product-tabs-content">
              <div class="tab-content">
                <?php foreach ($bestsellerTabs as $tabIndex => $tab): ?>
                <div class="tab-pane fade <?= $tabIndex === 0 ? 'show active' : '' ?>"
                  id="home-bestsellers-tab-<?= $tabIndex ?>">
                  <div class="swiper-container">
                    <div class="swiper-wrapper">
                      <?php $viewMode = 'swiper'; ?>
                      <?php foreach ($tab['products'] as $product): ?>
                      <?php include ROOT_PATH . '/src/Views/components/product-card.php'; ?>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <!-- Product Wrapper End -->
        </div>
      </div>
    </div>
  </div>
  <!-- Best Sellers Section End -->
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

  <?php if ($categoryBanners !== []): ?>
  <!-- Product Banner Section Start -->
  <div class="section">
    <div class="products-banner products-banner-active">
      <div class="swiper-container">
        <div class="swiper-wrapper">
          <?php foreach ($categoryBanners as $banner): ?>
          <div class="swiper-slide">
            <div class="single-products-banner">
              <img src="/assets/images/banner/<?= e($banner['image']) ?>" alt="<?= e($banner['name']) ?>">
              <div class="products-banner-content">
                <div class="banner-content-wrapper">
                  <h4 class="title"><a href="/catalog/<?= e($banner['slug']) ?>"><?= e($banner['name']) ?></a></h4>
                  <a href="/catalog/<?= e($banner['slug']) ?>" class="arrow"><i class="pe-7s-angle-right"></i></a>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <!-- Product Banner Section End -->
  <?php endif; ?>

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
</main>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>