<?php

declare(strict_types=1);

/** @var string $title */
/** @var array $product */
/** @var array<int,array> $breadcrumbs */
/** @var array<int,array> $variants */
/** @var array<int,array> $specs */
/** @var array<int,array> $related */

include ROOT_PATH . '/src/Views/layout/header.php';

$firstVariant = $variants[0];
$firstImage   = $firstVariant['images'][0] ?? null;

$thumbnails = [];
foreach ($firstVariant['images'] as $image) {
    if ($image['color'] === null || isset($thumbnails[$image['color']])) {
        continue;
    }
    $thumbnails[$image['color']] = $image;
}
?>

<main>
    <?php include ROOT_PATH . '/src/Views/components/flash.php'; ?>

    <div class="section page-banner-section page-banner-section--product">
        <div class="container">
            <div class="page-banner-content">
                <h2 class="title"><?= e($product['name']) ?></h2>
                <?php include ROOT_PATH . '/src/Views/components/breadcrumbs.php'; ?>
            </div>
        </div>
    </div>

    <div class="section section-padding-02">
        <div class="container">
            <div class="row">
                <div class="col-lg-6">
                    <div class="product-details-images">
                        <div class="details-gallery-images">
                            <?php if ($firstImage !== null): ?>
                                <div class="single-img">
                                    <img id="product-main-image" src="<?= e($firstImage['path']) ?>" alt="<?= e($product['name']) ?>">
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php
                        // Контейнер рендерится, даже если у ПЕРВОГО Варианта только один
                        // цвет (миниатюры тогда скрыты) — при переключении на другой
                        // Вариант с несколькими цветами JS сам наполнит и покажет этот
                        // же элемент; если бы он не существовал в DOM с самого начала,
                        // подставлять миниатюры при смене Варианта было бы некуда.
                        ?>
                        <div class="details-gallery-thumbs" id="product-thumbnails"<?= count($thumbnails) < 2 ? ' hidden' : '' ?>>
                            <?php foreach (array_values($thumbnails) as $index => $image): ?>
                                <button
                                    type="button"
                                    class="details-gallery-thumbs__item<?= $index === 0 ? ' active' : '' ?>"
                                    data-color="<?= e((string) $image['color']) ?>"
                                >
                                    <img src="<?= e($image['path']) ?>" alt="<?= e((string) $image['color']) ?>">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="product-details-description">
                        <h1 class="product-name"><?= e($product['name']) ?></h1>
                        <div class="price">
                            <span class="sale-price" id="product-price"><?= e($firstVariant['price_formatted']) ?></span>
                        </div>

                        <?php include ROOT_PATH . '/src/Views/components/variant-selector.php'; ?>

                        <div class="product-info">
                            <div class="single-info">
                                <span class="lable">Доставка:</span>
                                <span class="value">Стоимость доставки уточняется при подтверждении заказа.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    $hasSpecs       = $specs !== [];
    $hasDescription = ($product['description'] ?? '') !== '';
    $hasTabs        = $hasSpecs || $hasDescription;
    ?>

    <?php if ($hasTabs || $related !== []): ?>
        <?php
        // Вкладки и похожие товары — одна секция с гарантированным нижним
        // отступом перед футером: если бы это были отдельные секции
        // (каждая только с padding-top), а «Похожих товаров» не нашлось
        // (единственный активный товар в своей категории), низ вкладок
        // лип бы прямо к футеру.
        ?>
        <div class="section section-padding">
            <div class="container">
                <?php if ($hasTabs): ?>
                    <!-- Вкладки по макету SCR-03 (product-details-affiliate.html) —
                         без «Reviews»: отзывы не показываются раньше Фазы 6. -->
                    <div class="product-details-tabs">
                        <?php if ($hasSpecs && $hasDescription): ?>
                            <ul class="nav justify-content-center">
                                <li><button type="button" class="active" data-bs-toggle="tab" data-bs-target="#product-tab-specs">Характеристики</button></li>
                                <li><button type="button" data-bs-toggle="tab" data-bs-target="#product-tab-description">Описание</button></li>
                            </ul>
                        <?php endif; ?>

                        <div class="tab-content">
                            <?php if ($hasSpecs): ?>
                                <div class="tab-pane fade show active" id="product-tab-specs">
                                    <div class="information-content">
                                        <table class="table product-specs-table">
                                            <tbody>
                                                <?php foreach ($specs as $spec): ?>
                                                    <tr>
                                                        <th scope="row"><?= e($spec['name']) ?></th>
                                                        <td><?= e($spec['value']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($hasDescription): ?>
                                <div class="tab-pane fade<?= $hasSpecs ? '' : ' show active' ?>" id="product-tab-description">
                                    <div class="description-content">
                                        <p><?= e($product['description']) ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($related !== []): ?>
                    <h4 class="title<?= $hasTabs ? ' product-section-gap' : '' ?>">Похожие товары</h4>
                    <div class="shop-product-wrapper">
                        <div class="row">
                            <?php $viewMode = 'grid'; ?>
                            <?php foreach ($related as $product): ?>
                                <?php include ROOT_PATH . '/src/Views/components/product-card.php'; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
