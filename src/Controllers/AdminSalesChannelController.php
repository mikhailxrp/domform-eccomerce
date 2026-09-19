<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/SalesChannel.php';

class AdminSalesChannelController
{
    public function index(): void
    {
        requireRole(['manager', 'admin']);

        render('admin/sales-channels/index', [
            'title'    => 'Каналы продаж',
            'channels' => getSalesChannels(),
        ]);
    }

    public function update(): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        $enabledCodes = input('channels', []);
        updateSalesChannels(is_array($enabledCodes) ? $enabledCodes : []);

        setFlash('success', 'Изменения сохранены. Это демо-версия — реальные каналы не подключаются.');
        redirect('/admin/sales-channels');
    }
}
