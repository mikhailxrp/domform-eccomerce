<?php

declare(strict_types=1);

/** @var string $title */
/** @var array $product */
/** @var array<int,array> $breadcrumbs */
/** @var array<int,array> $variants */
/** @var array<int,array> $specs */
/** @var array<int,array> $related */
/** @var array<int,array> $reviews */
/** @var ?string $averageRating */
/** @var array $reviewOld */
/** @var array $reviewErrors */
/** @var array<int,int> $favoriteIds id избранных Товаров текущего пользователя (Таск 6 Фазы 7); пусто для гостя */
/** @var array $productSchema готовый массив для `schema.org/Product` (`ProductController::buildProductSchema()`) */

include ROOT_PATH . '/src/Views/layout/header.php';

$firstVariant = $variants[0];
$firstImage   = $firstVariant['images'][0] ?? null;
$productSlug  = $product['slug'];
$isFavorite   = in_array((int) $product['id'], $favoriteIds, true);

// Фото без цвета (общие ракурсы товара, необязательное поле формы
// загрузки — `admin/products/form.php`, Таск 9 Фазы 4) раньше молча
// выбрасывались из галереи: ключом дедупликации был сам `color`, а
// `null` пропускался условием. Теперь каждое такое фото получает
// собственный ключ по пути — не дедуплицируется с другими и не
// пропадает, в отличие от цветных фото (одно фото на цвет, как раньше).
$thumbnails = [];
foreach ($firstVariant['images'] as $image) {
    $key = $image['color'] ?? ('__nocolor__' . $image['path']);
    if (isset($thumbnails[$key])) {
        continue;
    }
    $thumbnails[$key] = $image;
}
?>

<script type="application/ld+json"><?= json_encode($productSchema, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

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
                                    data-path="<?= e($image['path']) ?>"
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
                            <span class="old-price" id="product-old-price"<?= hasDiscount($firstVariant['discount_percent']) ? '' : ' hidden' ?>><?= e($firstVariant['old_price_formatted']) ?></span>
                        </div>

                        <?php include ROOT_PATH . '/src/Views/components/variant-selector.php'; ?>

                        <?php if (isAuthenticated()): ?>
                            <form method="post" action="/favorites/toggle" class="product-favorite-form">
                                <?= csrfField() ?>
                                <input type="hidden" name="product_id" value="<?= e((string) $product['id']) ?>">
                                <button class="btn btn-outline-dark<?= $isFavorite ? ' action--active' : '' ?>" type="submit">
                                    <i class="pe-7s-like"></i> <?= $isFavorite ? 'В избранном' : 'В избранное' ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <a class="btn btn-outline-dark" href="/login"><i class="pe-7s-like"></i> В избранное</a>
                        <?php endif; ?>

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
    // Вкладка «Отзывы» есть всегда (Таск 4 Фазы 6) — форма работает и
    // без единого одобренного отзыва, поэтому вкладок теперь минимум
    // одна (сам блок вкладок больше не может быть пустым — `$hasTabs`
    // не нужен). Порядок и подпись — характеристики → описание →
    // отзывы; активная по умолчанию — первая доступная, а при ошибке
    // отправки формы (`$reviewErrors`) — сразу «Отзывы», чтобы
    // Покупатель увидел, что не так, без лишнего клика.
    $tabs = [];
    if ($hasSpecs) {
        $tabs['product-tab-specs'] = 'Характеристики';
    }
    if ($hasDescription) {
        $tabs['product-tab-description'] = 'Описание';
    }
    $tabs['product-tab-reviews'] = 'Отзывы (' . count($reviews) . ')';

    $reviewHasErrors = in_array(true, $reviewErrors, true);
    $activeTabId      = $reviewHasErrors ? 'product-tab-reviews' : array_key_first($tabs);
    ?>

    <div class="section section-padding">
        <div class="container">
            <!-- Вкладки по макету SCR-03 (product-details-affiliate.html) -->
            <div class="product-details-tabs">
                <?php if (count($tabs) > 1): ?>
                    <ul class="nav justify-content-center">
                        <?php foreach ($tabs as $tabId => $tabLabel): ?>
                            <li><button type="button" class="<?= $tabId === $activeTabId ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#<?= e($tabId) ?>"><?= e($tabLabel) ?></button></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <div class="tab-content">
                    <?php if ($hasSpecs): ?>
                        <div class="tab-pane fade<?= 'product-tab-specs' === $activeTabId ? ' show active' : '' ?>" id="product-tab-specs">
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
                        <div class="tab-pane fade<?= 'product-tab-description' === $activeTabId ? ' show active' : '' ?>" id="product-tab-description">
                            <div class="description-content">
                                <p><?= e($product['description']) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="tab-pane fade<?= 'product-tab-reviews' === $activeTabId ? ' show active' : '' ?>" id="product-tab-reviews">
                        <div class="reviews-content">
                            <?php include ROOT_PATH . '/src/Views/components/review-list.php'; ?>
                            <?php include ROOT_PATH . '/src/Views/components/review-form.php'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($related !== []): ?>
                <h4 class="title product-section-gap">Похожие товары</h4>
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
</main>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
