<?php

declare(strict_types=1);

/** @var string $title */
/** @var string $activeSection */
/** @var array<int,array> $addresses */
/** @var array|null $editingAddress */
/** @var array<string,string> $old */
/** @var array<string,bool> $errors */
/** @var bool $maxReached */

include ROOT_PATH . '/src/Views/layout/header.php';

$breadcrumbs = [['name' => 'Мои адреса']];

$formTitle = $editingAddress !== null ? 'Редактировать адрес' : 'Новый адрес';
$formAction = $editingAddress !== null ? '/account/addresses/' . $editingAddress['id'] : '/account/addresses';

// Значения формы: сначала $old (после ошибки валидации), потом
// редактируемый адрес, иначе значение по умолчанию — тот же приём,
// что `account/details.php`.
$formValue = static function (string $field, string $default = '') use ($old, $editingAddress): string {
    if (array_key_exists($field, $old)) {
        return $old[$field];
    }
    if ($editingAddress !== null) {
        return (string) ($editingAddress[$field] ?? '');
    }
    return $default;
};
?>

<main>
    <?php include ROOT_PATH . '/src/Views/components/flash.php'; ?>

    <div class="section page-banner-section page-banner-section--cart">
        <div class="container">
            <div class="page-banner-content">
                <h1 class="title">Мои адреса</h1>
                <?php include ROOT_PATH . '/src/Views/components/breadcrumbs.php'; ?>
            </div>
        </div>
    </div>

    <div class="section section-padding mt-n6">
        <div class="container">
            <div class="row">
                <div class="col-xl-3 col-md-4">
                    <?php include ROOT_PATH . '/src/Views/components/account-sidebar.php'; ?>
                </div>
                <div class="col-xl-9 col-md-8">
                    <div class="my-account-tab mt-6">
                        <div class="my-account-address account-wrapper">
                            <h4 class="account-title">Адреса</h4>

                            <?php if ($addresses === []): ?>
                                <p class="mt-25">У вас пока нет сохранённых адресов.</p>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($addresses as $address): ?>
                                        <div class="col-md-6">
                                            <div class="account-address mt-30">
                                                <h6 class="name">
                                                    <?= e($address['title'] !== null ? $address['title'] : 'Адрес') ?>
                                                    <?php if ((int) $address['is_default'] === 1): ?>
                                                        <span class="badge bg-success">Основной</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <p><?= e(formatAddress($address)) ?></p>
                                                <a class="btn btn-primary btn-hover-dark" href="/account/addresses?edit=<?= e((string) $address['id']) ?>">
                                                    <i class="fa fa-edit"></i> Изменить
                                                </a>
                                                <?php if ((int) $address['is_default'] !== 1): ?>
                                                    <form method="post" action="/account/addresses/<?= e((string) $address['id']) ?>/default" class="d-inline">
                                                        <?= csrfField() ?>
                                                        <button type="submit" class="btn btn-outline-dark">Сделать основным</button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="post" action="/account/addresses/<?= e((string) $address['id']) ?>/delete" class="d-inline">
                                                    <?= csrfField() ?>
                                                    <button type="submit" class="btn btn-outline-dark">Удалить</button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($maxReached && $editingAddress === null): ?>
                                <p class="mt-30">
                                    Достигнут лимит сохранённых адресов (<?= e((string) ACCOUNT_ADDRESSES_MAX) ?>).
                                    Удалите один из адресов, чтобы добавить новый.
                                </p>
                            <?php else: ?>
                                <h5 class="title mt-30"><?= e($formTitle) ?></h5>
                                <form method="post" action="<?= e($formAction) ?>" class="account-details" novalidate>
                                    <?= csrfField() ?>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="single-form">
                                                <input
                                                    type="text"
                                                    name="title"
                                                    placeholder="Название (например, «Дом»)"
                                                    class="<?= !empty($errors['title']) ? 'is-invalid' : '' ?>"
                                                    value="<?= e($formValue('title')) ?>"
                                                >
                                                <?php if (!empty($errors['title'])): ?>
                                                    <div class="invalid-feedback">Слишком длинное название.</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="single-form">
                                                <input
                                                    type="text"
                                                    name="city"
                                                    placeholder="Город *"
                                                    class="<?= !empty($errors['city']) ? 'is-invalid' : '' ?>"
                                                    value="<?= e($formValue('city', 'Краснодар')) ?>"
                                                    required
                                                >
                                                <?php if (!empty($errors['city'])): ?>
                                                    <div class="invalid-feedback">Укажите город.</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="single-form">
                                                <input
                                                    type="text"
                                                    name="street"
                                                    placeholder="Улица *"
                                                    class="<?= !empty($errors['street']) ? 'is-invalid' : '' ?>"
                                                    value="<?= e($formValue('street')) ?>"
                                                    required
                                                >
                                                <?php if (!empty($errors['street'])): ?>
                                                    <div class="invalid-feedback">Укажите улицу.</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="single-form">
                                                <input
                                                    type="text"
                                                    name="house"
                                                    placeholder="Дом *"
                                                    class="<?= !empty($errors['house']) ? 'is-invalid' : '' ?>"
                                                    value="<?= e($formValue('house')) ?>"
                                                    required
                                                >
                                                <?php if (!empty($errors['house'])): ?>
                                                    <div class="invalid-feedback">Укажите дом.</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="single-form">
                                                <input
                                                    type="text"
                                                    name="apartment"
                                                    placeholder="Квартира/офис"
                                                    class="<?= !empty($errors['apartment']) ? 'is-invalid' : '' ?>"
                                                    value="<?= e($formValue('apartment')) ?>"
                                                >
                                                <?php if (!empty($errors['apartment'])): ?>
                                                    <div class="invalid-feedback">Слишком длинное значение.</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="single-form">
                                                <textarea
                                                    name="comment"
                                                    placeholder="Подъезд, этаж, домофон"
                                                    class="<?= !empty($errors['comment']) ? 'is-invalid' : '' ?>"
                                                ><?= e($formValue('comment')) ?></textarea>
                                                <?php if (!empty($errors['comment'])): ?>
                                                    <div class="invalid-feedback">Слишком длинный комментарий.</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="single-form">
                                                <button type="submit" class="btn btn-primary btn-hover-dark">
                                                    <?= $editingAddress !== null ? 'Сохранить' : 'Добавить адрес' ?>
                                                </button>
                                            </div>
                                        </div>
                                        <?php if ($editingAddress !== null): ?>
                                            <div class="col-md-6">
                                                <div class="single-form">
                                                    <a href="/account/addresses" class="btn btn-outline-dark">Отмена</a>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
