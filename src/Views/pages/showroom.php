<?php

declare(strict_types=1);

/** @var string $title */
/** @var array $page строка content_pages (slug=showroom) */
/** @var string $bodyHtml уже экранированный HTML из renderContentBody() */

include ROOT_PATH . '/src/Views/layout/header.php';

$breadcrumbs = [['name' => $page['title']]];
$mapEmbedUrl = setting('map_embed_url');
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

    <div class="section section-padding">
        <div class="container">
            <div class="row g-4 align-items-start">
                <div class="col-lg-7">
                    <article class="information-content">
                        <?php if (!empty($page['image_path'])): ?>
                            <img src="<?= e('/' . ltrim((string) $page['image_path'], '/')) ?>" alt="<?= e($page['title']) ?>" class="img-fluid rounded mb-4">
                        <?php endif; ?>
                        <?php
                        // Единственный вывод без e(): каждый фрагмент уже
                        // экранирован внутри renderContentBody()
                        // (`Core/Content.php`, `ADR-044`).
                        echo $bodyHtml;
                        ?>
                    </article>
                </div>

                <div class="col-lg-5">
                    <div class="showroom-info-card">
                        <div class="showroom-info-card__row">
                            <span class="showroom-info-card__icon"><i class="fa fa-map-marker"></i></span>
                            <div>
                                <h6 class="showroom-info-card__label">Адрес</h6>
                                <p class="showroom-info-card__value"><?= e(setting('workshop_address')) ?></p>
                            </div>
                        </div>
                        <div class="showroom-info-card__row">
                            <span class="showroom-info-card__icon"><i class="fa fa-clock-o"></i></span>
                            <div>
                                <h6 class="showroom-info-card__label">Режим работы</h6>
                                <p class="showroom-info-card__value"><?= e(setting('work_hours')) ?></p>
                            </div>
                        </div>

                        <div class="showroom-info-card__notice">
                            <i class="fa fa-exclamation-circle"></i>
                            <span>Посещение — только по предварительной записи, свободного
                            входа нет. Позвоните или напишите в WhatsApp, и мы согласуем
                            удобное время.</span>
                        </div>

                        <div class="showroom-info-card__actions">
                            <a href="tel:<?= e(phoneToTel(setting('shop_phone'))) ?>" class="btn btn-dark w-100"><i class="fa fa-phone"></i> <?= e(setting('shop_phone')) ?></a>
                            <a href="<?= e(setting('shop_whatsapp_url')) ?>" class="btn btn-outline-dark w-100" target="_blank" rel="noopener"><i class="fa fa-whatsapp"></i> Написать в WhatsApp</a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($mapEmbedUrl !== ''): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="ratio ratio-16x9 showroom-map">
                            <iframe src="<?= e($mapEmbedUrl) ?>" class="border-0" loading="lazy" title="Карта проезда к шоуруму" allowfullscreen></iframe>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
