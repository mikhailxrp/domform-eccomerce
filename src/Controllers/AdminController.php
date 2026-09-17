<?php

declare(strict_types=1);

namespace App\Controllers;

class AdminController
{
    public function index(): void
    {
        requireRole(['manager', 'admin']);
        render('admin/index', ['title' => 'Панель управления']);
    }
}
