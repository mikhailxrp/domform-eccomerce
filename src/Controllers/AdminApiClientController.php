<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Core/ApiAuth.php';
require_once ROOT_PATH . '/src/Models/ApiClient.php';

/**
 * Ключи внешних сайтов для `POST /api/v1/consultant` — только `admin`:
 * ключ открывает платный ИИ-вызов, как и остальные ИИ-разделы Панели.
 */
class AdminApiClientController
{
    public function index(): void
    {
        requireRole(['admin']);

        $this->renderList(['name' => '', 'allowed_origins' => ''], []);
    }

    public function store(): void
    {
        requireRole(['admin']);
        requireCsrf();

        $input = [
            'name'            => trim((string) input('name', '')),
            'allowed_origins' => trim((string) input('allowed_origins', '')),
        ];

        $errors = validateApiClientInput($input);
        if (in_array(true, $errors, true)) {
            $this->renderList($input, $errors);
            return;
        }

        createApiClient($input['name'], generateApiKey(), parseAllowedOrigins($input['allowed_origins']));

        setFlash('success', 'Ключ создан.');
        redirect('/admin/api-clients');
    }

    public function toggle(string $id): void
    {
        requireRole(['admin']);
        requireCsrf();

        setApiClientActive((int) $id, input('is_active', '') === '1');

        setFlash('success', 'Доступ обновлён.');
        redirect('/admin/api-clients');
    }

    private function renderList(array $values, array $errors): void
    {
        render('admin/api-clients/index', [
            'title'   => 'Внешние сайты',
            'clients' => getApiClients(),
            'values'  => $values,
            'errors'  => $errors,
            'appUrl'  => rtrim(APP_URL, '/'),
        ]);
    }
}
