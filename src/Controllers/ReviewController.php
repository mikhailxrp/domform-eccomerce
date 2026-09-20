<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Product.php';
require_once ROOT_PATH . '/src/Models/Review.php';
require_once ROOT_PATH . '/src/Core/Review.php';

class ReviewController
{
    /**
     * `FR-CARD-006` — отзыв о Товаре с карточки. При ошибке валидации
     * рендерит ту же карточку напрямую (`ProductController::show()`,
     * без редиректа) с введёнными значениями и ошибками по полям —
     * `setFlash()` хранит только строку, массив ошибок не переживёт
     * редирект; тот же приём, что `CheckoutController::store()`.
     */
    public function store(string $slug): void
    {
        requireCsrf();

        $product = findProductBySlug($slug);
        if ($product === null) {
            abort404();
        }

        if (tooManyAttempts('review', 3, 600)) {
            setFlash('error', 'Слишком много отзывов подряд. Попробуйте позже.');
            redirect('/product/' . $slug . '#reviews');
        }
        hitRateLimit('review');

        $input  = normalizeReviewInput([
            'name'   => input('name'),
            'email'  => input('email'),
            'rating' => input('rating'),
            'text'   => input('text'),
        ]);
        $errors = validateReviewInput($input);

        if (in_array(true, $errors, true)) {
            (new ProductController())->show($slug, $input, $errors);
            return;
        }

        createReview([
            'product_id' => (int) $product['id'],
            'name'       => $input['name'],
            'email'      => $input['email'],
            'rating'     => $input['rating'],
            'text'       => $input['text'],
        ]);

        // Лимит не снимается успешной отправкой (в отличие от логина) —
        // это защита от спама количеством, а не от подбора пароля;
        // тот же принцип, что `CheckoutController::store()`, где
        // `hitRateLimit('checkout')` тоже ничем не сбрасывается.
        setFlash('success', 'Спасибо! Отзыв появится на странице после проверки Менеджером.');
        redirect('/product/' . $slug . '#reviews');
    }
}
