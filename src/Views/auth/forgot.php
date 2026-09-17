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
                    <!-- Forgot Password Start -->
                    <div class="login-register-wrapper">
                        <h4 class="title">Восстановление пароля</h4>
                        <p>Укажите email, указанный при регистрации — мы отправим на него ссылку для восстановления пароля.</p>
                        <form method="post" action="/forgot-password" novalidate>
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
                                    <div class="invalid-feedback">Введите корректный email.</div>
                                <?php endif; ?>
                            </div>
                            <div class="single-form">
                                <button type="submit" class="btn btn-primary btn-hover-dark">Отправить ссылку</button>
                            </div>
                        </form>
                        <p><a href="/login">Вернуться ко входу</a>.</p>
                    </div>
                    <!-- Forgot Password End -->
                </div>
            </div>
        </div>
    </div>
</main>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
