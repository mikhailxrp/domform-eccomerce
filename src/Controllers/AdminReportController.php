<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Report.php';
require_once ROOT_PATH . '/src/Core/Report.php';

class AdminReportController
{
    private const PRESETS = ['today', 'week', 'month', 'custom'];

    public function index(): void
    {
        requireRole(['manager', 'admin']);

        $preset = (string) input('preset', 'today');
        if (!in_array($preset, self::PRESETS, true)) {
            $preset = 'today';
        }

        $from = trim((string) input('from', ''));
        $to   = trim((string) input('to', ''));

        $period = resolveReportPeriod(
            $preset,
            $from !== '' ? $from : null,
            $to !== '' ? $to : null,
            new \DateTimeImmutable()
        );

        if ($period['error'] !== null) {
            render('admin/reports/index', [
                'title'   => 'Отчёты',
                'preset'  => $preset,
                'from'    => $from,
                'to'      => $to,
                'error'   => $period['error'],
                'summary' => null,
                'byDay'   => [],
            ]);
            return;
        }

        render('admin/reports/index', [
            'title'   => 'Отчёты',
            'preset'  => $preset,
            'from'    => $period['from'],
            'to'      => $period['to'],
            'error'   => null,
            'summary' => getSalesSummary($period['from'], $period['to']),
            'byDay'   => getSalesByDay($period['from'], $period['to']),
        ]);
    }
}
