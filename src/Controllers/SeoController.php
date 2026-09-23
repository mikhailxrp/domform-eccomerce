<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Category.php';
require_once ROOT_PATH . '/src/Models/Product.php';
require_once ROOT_PATH . '/src/Models/ContentPage.php';
require_once ROOT_PATH . '/src/Core/Content.php';

/**
 * `robots.txt`/`sitemap.xml` (`tz.md` §13.3, MUST/MVP) — читаются через
 * маршрут, а не лежат статикой в `public/`: `Sitemap:`/URL внутри должны
 * указывать на текущий `APP_URL` (`.env`), а не быть захардкожены под
 * один хостинг/домен на каждый деплой.
 */
class SeoController
{
    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');

        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /account',
            'Disallow: /cart',
            'Disallow: /checkout',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /forgot-password',
            'Disallow: /reset-password',
            '',
            'Sitemap: ' . rtrim(APP_URL, '/') . '/sitemap.xml',
        ];

        echo implode("\n", $lines) . "\n";
    }

    public function sitemap(): void
    {
        header('Content-Type: application/xml; charset=utf-8');

        $baseUrl = rtrim(APP_URL, '/');
        $urls    = [
            ['loc' => $baseUrl . '/', 'lastmod' => null],
            ['loc' => $baseUrl . '/catalog', 'lastmod' => null],
        ];

        foreach (getCategorySlugsForSitemap() as $slug) {
            $urls[] = ['loc' => $baseUrl . '/catalog/' . $slug, 'lastmod' => null];
        }

        foreach (getActiveProductSlugsForSitemap() as $product) {
            $urls[] = [
                'loc'     => $baseUrl . '/product/' . $product['slug'],
                'lastmod' => substr((string) $product['updated_at'], 0, 10),
            ];
        }

        foreach (getAllContentPages() as $page) {
            $urls[] = [
                'loc'     => $baseUrl . publicUrlForContentSlug($page['slug']),
                'lastmod' => substr((string) $page['updated_at'], 0, 10),
            ];
        }

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $url) {
            echo '<url><loc>' . e($url['loc']) . '</loc>';
            if ($url['lastmod'] !== null) {
                echo '<lastmod>' . e($url['lastmod']) . '</lastmod>';
            }
            echo '</url>' . "\n";
        }
        echo '</urlset>' . "\n";
    }
}
