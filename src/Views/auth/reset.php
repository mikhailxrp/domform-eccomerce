<?php

declare(strict_types=1);

/** @var string $title */
/** @var string $token */
/** @var array<string,bool> $errors */

include ROOT_PATH . '/src/Views/layout/header.php';
?>

<main>
    <?php include ROOT_PATH . '/src/Views/components/flash.php'; ?>
    <div class="section section-padding page-content-offset">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <!-- Reset Password Start -->
                    <div class="login-register-wrapper">
                        <h4 class="title">Новый пароль</h4>
                        <form method="post" action="/reset-password/<?= e($token) ?>" novalidate>
                            <?= csrfField() ?>
                            <?php
                            $fieldName          = 'password';
                            $fieldPlaceholder   = 'Новый пароль *';
                            $fieldHasError      = !empty($errors['password']);
                            $fieldErrorMessage  = 'Пароль должен быть не короче 8 символов.';
                            include ROOT_PATH . '/src/Views/components/password-field.php';
                            ?>
                            <div class="single-form">
                                <button type="submit" class="btn btn-primary btn-hover-dark">Сохранить пароль</button>
                            </div>
                        </form>
                    </div>
                    <!-- Reset Password End -->
                </div>
            </div>
        </div>
    </div>
</main>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
