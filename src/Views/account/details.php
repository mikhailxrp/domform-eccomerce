<?php

declare(strict_types=1);

/** @var string $title */
/** @var string $activeSection */
/** @var string $name */
/** @var string $email */
/** @var string $phone */
/** @var array<string,string> $old */
/** @var array<string,bool> $errors */
/** @var array<string,bool> $passwordErrors */

include ROOT_PATH . '/src/Views/layout/header.php';

$breadcrumbs = [['name' => 'Личные данные']];
?>

<main>
    <?php include ROOT_PATH . '/src/Views/components/flash.php'; ?>

    <div class="section page-banner-section page-banner-section--cart">
        <div class="container">
            <div class="page-banner-content">
                <h1 class="title">Личные данные</h1>
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
                        <div class="my-account-details account-wrapper">
                            <h4 class="account-title">Личные данные</h4>

                            <form method="post" action="/account/details" class="account-details" novalidate>
                                <?= csrfField() ?>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="single-form">
                                            <input
                                                type="text"
                                                name="name"
                                                placeholder="Имя *"
                                                class="<?= !empty($errors['name']) ? 'is-invalid' : '' ?>"
                                                value="<?= e($old['name'] ?? $name) ?>"
                                                required
                                            >
                                            <?php if (!empty($errors['name'])): ?>
                                                <div class="invalid-feedback">Введите имя (от 2 до 100 символов).</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="single-form">
                                            <input
                                                type="email"
                                                name="email"
                                                placeholder="Email *"
                                                class="<?= !empty($errors['email']) ? 'is-invalid' : '' ?>"
                                                value="<?= e($old['email'] ?? $email) ?>"
                                                required
                                            >
                                            <?php if (!empty($errors['email'])): ?>
                                                <div class="invalid-feedback">
                                                    <?= !empty($errors['account_error']) ? 'Проверьте текущий пароль и email.' : 'Введите корректный email.' ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="single-form">
                                            <input type="tel" value="<?= e($phone ?? '') ?>" disabled>
                                            <small class="form-text text-muted">
                                                Телефон меняет только Менеджер — позвоните по <?= e(setting('shop_phone')) ?>.
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="single-form">
                                            <input
                                                type="password"
                                                name="current_password"
                                                placeholder="Текущий пароль (обязателен при смене email)"
                                                class="<?= !empty($errors['current_password']) ? 'is-invalid' : '' ?>"
                                            >
                                            <?php if (!empty($errors['current_password'])): ?>
                                                <div class="invalid-feedback">
                                                    <?= !empty($errors['account_error']) ? 'Проверьте текущий пароль и email.' : 'Укажите текущий пароль, чтобы сменить email.' ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="single-form">
                                            <button type="submit" class="btn btn-primary btn-hover-dark">Сохранить</button>
                                        </div>
                                    </div>
                                </div>
                            </form>

                            <form method="post" action="/account/password" class="account-details mt-30" novalidate>
                                <?= csrfField() ?>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="single-form">
                                            <h5 class="title">Смена пароля</h5>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <?php
                                        $fieldName         = 'current_password';
                                        $fieldPlaceholder  = 'Текущий пароль *';
                                        $fieldHasError     = !empty($passwordErrors['current_password']);
                                        $fieldErrorMessage = !empty($passwordErrors['wrong_password'])
                                            ? 'Неверный текущий пароль.'
                                            : 'Введите текущий пароль.';
                                        include ROOT_PATH . '/src/Views/components/password-field.php';
                                        ?>
                                    </div>
                                    <div class="col-md-6">
                                        <?php
                                        $fieldName         = 'new_password';
                                        $fieldPlaceholder  = 'Новый пароль *';
                                        $fieldHasError     = !empty($passwordErrors['new_password']);
                                        $fieldErrorMessage = 'Не короче 8 символов и отличается от текущего.';
                                        include ROOT_PATH . '/src/Views/components/password-field.php';
                                        ?>
                                    </div>
                                    <div class="col-md-6">
                                        <?php
                                        $fieldName         = 'new_password_confirm';
                                        $fieldPlaceholder  = 'Подтверждение пароля *';
                                        $fieldHasError     = !empty($passwordErrors['new_password_confirm']);
                                        $fieldErrorMessage = 'Пароли не совпадают.';
                                        include ROOT_PATH . '/src/Views/components/password-field.php';
                                        ?>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="single-form">
                                            <button type="submit" class="btn btn-primary btn-hover-dark">Сменить пароль</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
