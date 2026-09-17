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
                    <!-- Login Start -->
                    <div class="login-register-wrapper">
                        <h4 class="title">Вход в аккаунт</h4>
                        <form method="post" action="/login" novalidate>
                            <?= csrfField() ?>
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
                                    <div class="invalid-feedback">Проверьте email.</div>
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
                                    <div class="invalid-feedback">Проверьте пароль.</div>
                                <?php endif; ?>
                            </div>
                            <div class="single-form">
                                <input type="checkbox" id="remember" name="remember" value="1">
                                <label for="remember"><span></span> Запомнить меня</label>
                            </div>
                            <div class="single-form">
                                <button type="submit" class="btn btn-primary btn-hover-dark">Войти</button>
                            </div>
                        </form>
                        <p>Нет аккаунта? <a href="/register">Зарегистрироваться</a>.</p>
                    </div>
                    <!-- Login End -->
                </div>
            </div>
        </div>
    </div>
</main>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
