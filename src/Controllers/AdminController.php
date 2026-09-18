<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Order.php';

class AdminController
{
    public function index(): void
    {
        requireRole(['manager', 'admin']);
        render('admin/index', [
            'title'        => 'Панель управления',
            'statusCounts' => countOrdersByStatus(),
        ]);
    }
}
