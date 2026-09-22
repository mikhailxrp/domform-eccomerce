<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Setting.php';

class AdminSettingController
{
    /**
     * `admin`-only (`FR-ADM-007` п. 4, `Q-DEV-001`) — первый маршрут
     * проекта с `requireRole(['admin'])` без `manager`.
     */
    public function index(): void
    {
        requireRole(['admin']);

        $this->renderForm(getAllSettings(), []);
    }

    public function update(): void
    {
        requireRole(['admin']);
        requireCsrf();

        $input  = $this->settingsInputFromRequest();
        $errors = validateSettingsInput($input);

        if (in_array(true, $errors, true)) {
            $this->renderForm($input, $errors);
            return;
        }

        updateSettings($input);

        setFlash('success', 'Реквизиты магазина обновлены.');
        redirect('/admin/settings');
    }

    private function settingsInputFromRequest(): array
    {
        $values = [];
        foreach (array_keys(SETTING_KEYS) as $key) {
            $values[$key] = trim((string) input($key, ''));
        }

        return $values;
    }

    /**
     * `$values` — либо текущие реквизиты (GET), либо присланная форма
     * после ошибки валидации (POST) — тот же приём, что `$old` в
     * других формах Панели, только без отдельного параметра: у формы
     * настроек нет «исходной записи» отдельно от значений полей.
     */
    private function renderForm(array $values, array $errors): void
    {
        render('admin/settings/index', [
            'title'      => 'Настройки',
            'values'     => $values,
            'errors'     => $errors,
            'systemInfo' => $this->systemInfo(),
        ]);
    }

    private function systemInfo(): array
    {
        $logPath = LOG_DIR . '/' . LOG_FILE;

        return [
            'app_env'           => APP_ENV,
            'php_version'       => PHP_VERSION,
            'mysql_version'     => (string) getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION),
            'log_size'          => is_file($logPath) ? filesize($logPath) : 0,
            'uploads_writable' => is_writable(ROOT_PATH . '/public/uploads'),
        ];
    }
}
