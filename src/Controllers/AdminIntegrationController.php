<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Integration.php';

class AdminIntegrationController
{
    public function index(): void
    {
        requireRole(['manager', 'admin']);

        $integrations = getIntegrations();

        $grouped = [];
        foreach ($integrations as $integration) {
            $grouped[$integration['category']][] = $integration;
        }

        render('admin/integrations/index', [
            'title'            => 'Интеграции',
            'groupedIntegrations' => $grouped,
        ]);
    }

    public function update(): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        $enabledCodes = input('integrations', []);
        updateIntegrations(is_array($enabledCodes) ? $enabledCodes : []);

        setFlash('success', 'Изменения сохранены. Это демо-версия — реальные интеграции не подключаются.');
        redirect('/admin/integrations');
    }
}
