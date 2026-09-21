<?php

declare(strict_types=1);

/** @var string $title */
/** @var array $page строка content_pages (slug=about) */
/** @var string $bodyHtml уже экранированный HTML из renderContentBody() */
/** @var array<int, array<string, mixed>> $galleryImages about_gallery_images, ADR-046 */
/** @var array<int, array{name:string,rating:int,text:string,photo_path:string}> $testimonials ADR-046 */

include ROOT_PATH . '/src/Views/layout/header.php';

$breadcrumbs = [['name' => $page['title']]];

// Та же раскладка колонок, что в теме (about.html) — большая/меньшая/
// две средних; если фото меньше 4, лишние позиции просто не рисуются.
$galleryColumnClasses = ['col-lg-8', 'col-lg-4', 'col-lg-6', 'col-lg-6'];
?>

<main>
  <?php include ROOT_PATH . '/src/Views/components/flash.php'; ?>

  <div class="section page-banner-section page-banner-section--cart">
    <div class="container">
      <div class="page-banner-content">
        <h1 class="title"><?= e($page['title']) ?></h1>
        <?php include ROOT_PATH . '/src/Views/components/breadcrumbs.php'; ?>
      </div>
    </div>
  </div>

  <div class="section section-padding-02">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-8">
          <div class="section-title-03 text-center">
            <h6 class="sub-title">О нас</h6>
            <h2 class="title">Мебель на заказ для дома в Краснодаре и крае</h2>
          </div>
          <article class="information-content information-content--intro">
            <?php if (!empty($page['image_path'])): ?>
            <img src="<?= e('/' . ltrim((string) $page['image_path'], '/')) ?>" alt="<?= e($page['title']) ?>"
              class="img-fluid mb-4 d-block mx-auto">
            <?php endif; ?>
            <?php
                        // Единственный вывод без e(): каждый фрагмент уже
                        // экранирован внутри renderContentBody()
                        // (`Core/Content.php`, `ADR-044`). Первый абзац —
                        // вводный текст, центрируется как в оригинале темы
                        // (`.information-content > p:first-child`,
                        // `app.css`); подзаголовок и список ниже — обычный
                        // читаемый текст по левому краю.
                        echo $bodyHtml;
                        ?>
          </article>
        </div>
      </div>

      <div class="history-icon text-center">
        <img src="/assets/images/icon/icon-5.jpg" alt="">
      </div>
    </div>
  </div>

  <?php if ($galleryImages !== []): ?>
  <!-- Gallery Section Start (`about_gallery_images`, `ADR-046`) -->
  <div class="section section-padding-02 pt-0">
    <div class="container">
      <div class="row">
        <?php foreach ($galleryImages as $index => $image): ?>
        <div class="<?= e($galleryColumnClasses[$index % 4]) ?>">
          <div class="image-gallery">
            <img src="<?= e($image['path']) ?>" alt="">
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <!-- Gallery Section End -->
  <?php endif; ?>

  <!-- Counter Section Start (демо-цифры для портфолио, кроме гарантии — `ADR-046`) -->
  <div class="section section-padding mt-n6">
    <div class="container">
      <div class="row">
        <div class="col-lg-3 col-6">
          <div class="single-counter">
            <span class="count"><span class="odometer" data-count-to="8"></span><sub>+</sub></span>
            <p>Лет на рынке</p>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="single-counter">
            <span class="count"><span class="odometer" data-count-to="1200"></span><sub>+</sub></span>
            <p>Выполненных заказов</p>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="single-counter">
            <span class="count"><span class="odometer" data-count-to="18"></span><sub>мес.</sub></span>
            <p>Гарантия на мебель</p>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="single-counter">
            <span class="count"><span class="odometer" data-count-to="98"></span><sub>%</sub></span>
            <p>Довольных клиентов</p>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- Counter Section End -->

  <?php if ($testimonials !== []): ?>
  <!-- Testimonial Section Start (слайдер настоящих отзывов о магазине с
       фото, `ADR-046`; `.testimonial-active`/`.swiper-container` — та
       же разметка и JS-инициализация (`main.js`), что в оригинале
       темы, просто не использовалась до этого таска) -->
  <div class="section section-padding bg-color-02">
    <div class="container">
      <div class="testimonial-wrapper testimonial-active">
        <div class="swiper-container">
          <div class="swiper-wrapper">
            <?php foreach ($testimonials as $testimonial): ?>
              <div class="single-testimonial swiper-slide">
                <img class="quote" src="/assets/images/icon/quote.png" alt="">
                <p><?= e($testimonial['text']) ?></p>
                <img class="author-thumb" src="<?= e($testimonial['photo_path']) ?>" alt="<?= e($testimonial['name']) ?>">
                <h6 class="name"><?= e($testimonial['name']) ?></h6>
                <span class="designation">
                  <span class="review-list__stars" aria-hidden="true">
                    <span class="review-list__stars-fill"
                      style="width: <?= e((string) ((int) $testimonial['rating'] * 20)) ?>%"></span>
                  </span>
                  <span class="visually-hidden">Оценка <?= e((string) $testimonial['rating']) ?> из 5</span>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="swiper-pagination"></div>
        </div>
      </div>
    </div>
  </div>
  <!-- Testimonial Section End -->
  <?php endif; ?>

  <!-- Team Section Start -->
  <div class="section section-padding-02 pt-0 mb-5 mt-5">
    <div class="container">
      <div class="section-title-03 text-center">
        <h6 class="sub-title">Наша команда</h6>
        <h2 class="title">Кто делает вашу мебель</h2>
      </div>

      <div class="team-wrapper">
        <div class="row justify-content-center">
          <div class="col-lg-4 col-md-6">
            <div class="single-team">
              <div class="team-images">
                <img src="/assets/images/team/team-2.webp" alt="Волков С. И.">
              </div>
              <div class="team-content">
                <h5 class="name">Волков С.И.</h5>
                <span class="designation">Владелец</span>
              </div>
            </div>
          </div>
          <div class="col-lg-4 col-md-6">
            <div class="single-team">
              <div class="team-images">
                <img src="/assets/images/team/team-1.webp" alt="Волкова А.">
              </div>
              <div class="team-content">
                <h5 class="name">Волкова А.В.</h5>
                <span class="designation">Менеджер</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- Team Section End -->
</main>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>