<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<string,string> $old */
/** @var array<string,bool> $errors */

include ROOT_PATH . '/src/Views/layout/header.php';
?>

<main>
    <?php include ROOT_PATH . '/src/Views/components/flash.php'; ?>
    <div class="section section-padding page-content-offset">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <!-- Register Start -->
                    <div class="login-register-wrapper">
                        <h4 class="title">Создать аккаунт</h4>
                        <p>Уже есть аккаунт? <a href="/login">Войти</a>.</p>
                        <form method="post" action="/register" novalidate>
                            <?= csrfField() ?>
                            <div class="single-form">
                                <input
                                    type="text"
                                    name="name"
                                    placeholder="Имя *"
                                    class="<?= !empty($errors['name']) ? 'is-invalid' : '' ?>"
                                    value="<?= e($old['name'] ?? '') ?>"
                                    required
                                >
                                <?php if (!empty($errors['name'])): ?>
                                    <div class="invalid-feedback">Введите имя.</div>
                                <?php endif; ?>
                            </div>
                            <div class="single-form">
                                <input
                                    type="email"
                                    name="email"
                                    placeholder="Email *"
                                    class="<?= !empty($errors['email']) ? 'is-invalid' : '' ?>"
                                    value="<?= e($old['email'] ?? '') ?>"
                                    required
                                >
                                <?php if (!empty($errors['email'])): ?>
                                    <div class="invalid-feedback">Введите корректный email.</div>
                                <?php endif; ?>
                            </div>
                            <div class="single-form">
                                <input
                                    type="tel"
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
                            <div class="single-form">
                                <input
                                    type="password"
                                    name="password"
                                    placeholder="Пароль *"
                                    class="<?= !empty($errors['password']) ? 'is-invalid' : '' ?>"
                                    required
                                >
                                <?php if (!empty($errors['password'])): ?>
                                    <div class="invalid-feedback">Пароль должен быть не короче 8 символов.</div>
                                <?php endif; ?>
                            </div>
                            <div class="single-form">
                                <button type="submit" class="btn btn-primary btn-hover-dark">Зарегистрироваться</button>
                            </div>
                        </form>
                    </div>
                    <!-- Register End -->
                </div>
            </div>
        </div>
    </div>
</main>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
