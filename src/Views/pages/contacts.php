<?php

declare(strict_types=1);

/** @var string $title */
/** @var array $page строка content_pages (slug=contacts) */
/** @var string $bodyHtml уже экранированный HTML из renderContentBody() */
/** @var array{name?:string,phone?:string,comment?:string} $old */
/** @var array<string,bool> $errors */

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
            <!-- Разметка и классы .contact-wrapper/.contact-info/
                 .contact-info-items/.contact-form — из темы
                 (`00-input/design/assets/scss/page/_contact.scss`),
                 уже собраны в `style.css`; своих CSS-классов не
                 добавлено. Демо-страницы с этим блоком в поставке темы
                 не было, только его SCSS/JS-заготовка — вёрстка ниже
                 собрана по образцу разметки `.social`/`.title` с
                 акцентной полосой, уже встречающейся в других файлах
                 темы (`index.html`). -->
            <div class="contact-wrapper">
                <div class="row g-0">
                    <div class="col-lg-5">
                        <div class="contact-info">
                            <h2 class="title"><?= e($page['title']) ?></h2>
                            <?php
                            // Единственный вывод без e(): каждый фрагмент уже
                            // экранирован внутри renderContentBody()
                            // (`Core/Content.php`, `ADR-044`).
                            echo $bodyHtml;
                            ?>

                            <div class="contact-info-items">
                                <div class="single-contact-info">
                                    <div class="info-icon"><i class="fa fa-phone"></i></div>
                                    <div class="info-content">
                                        <p><a href="tel:<?= e(phoneToTel(setting('shop_phone'))) ?>"><?= e(setting('shop_phone')) ?></a></p>
                                    </div>
                                </div>
                                <div class="single-contact-info">
                                    <div class="info-icon"><i class="fa fa-envelope"></i></div>
                                    <div class="info-content">
                                        <p><a href="mailto:<?= e(setting('shop_email')) ?>"><?= e(setting('shop_email')) ?></a></p>
                                    </div>
                                </div>
                                <div class="single-contact-info">
                                    <div class="info-icon"><i class="fa fa-map-marker"></i></div>
                                    <div class="info-content">
                                        <p><?= e(setting('workshop_address')) ?></p>
                                    </div>
                                </div>
                                <div class="single-contact-info">
                                    <div class="info-icon"><i class="fa fa-whatsapp"></i></div>
                                    <div class="info-content">
                                        <p><a href="<?= e(setting('shop_whatsapp_url')) ?>" target="_blank" rel="noopener">Написать в WhatsApp</a></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="contact-form">
                            <h4 class="title">Перезвоните мне</h4>

                            <form method="post" action="/contacts/callback" novalidate>
                                <?= csrfField() ?>
                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="single-form">
                                            <label for="callback-name" class="visually-hidden">Ваше имя</label>
                                            <input
                                                type="text"
                                                id="callback-name"
                                                name="name"
                                                placeholder="Ваше имя *"
                                                class="<?= !empty($errors['name']) ? 'is-invalid' : '' ?>"
                                                value="<?= e($old['name'] ?? '') ?>"
                                                required
                                            >
                                            <?php if (!empty($errors['name'])): ?>
                                                <div class="invalid-feedback">Имя от 2 до 150 символов.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="single-form">
                                            <label for="callback-phone" class="visually-hidden">Телефон</label>
                                            <input
                                                type="tel"
                                                id="callback-phone"
                                                name="phone"
                                                placeholder="Телефон *"
                                                class="<?= !empty($errors['phone']) ? 'is-invalid' : '' ?>"
                                                value="<?= e($old['phone'] ?? '') ?>"
                                                required
                                            >
                                            <?php if (!empty($errors['phone'])): ?>
                                                <div class="invalid-feedback">Введите корректный телефон, например +7 900 123-45-67.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="single-form">
                                            <label for="callback-comment" class="visually-hidden">Комментарий</label>
                                            <textarea
                                                id="callback-comment"
                                                name="comment"
                                                placeholder="Комментарий (необязательно)"
                                                class="<?= !empty($errors['comment']) ? 'is-invalid' : '' ?>"
                                            ><?= e($old['comment'] ?? '') ?></textarea>
                                            <?php if (!empty($errors['comment'])): ?>
                                                <div class="invalid-feedback">Слишком длинный комментарий.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="single-form">
                                            <button class="btn btn-dark btn-hover-primary" type="submit">Заказать звонок</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($mapEmbedUrl !== ''): ?>
                <div class="contact-map mt-4">
                    <iframe src="<?= e($mapEmbedUrl) ?>" class="border-0" loading="lazy" title="Карта проезда к цеху" allowfullscreen></iframe>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
