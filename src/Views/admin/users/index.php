<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<int, array<string, mixed>> $staff */
/** @var array $old */
/** @var array $errors */

include ROOT_PATH . '/src/Views/layout/admin-header.php';

$roleLabels = ['manager' => 'Менеджер', 'admin' => 'Администратор'];
$myId       = (int) (currentUser()['id'] ?? 0);
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0">Сотрудники</h4>
        <p class="mb-0 text-muted">Создание и блокировка учётных записей Менеджеров (`FR-ADM-007`)</p>
    </div>
</div>

<div class="card custom-card">
    <div class="card-body">
        <?php if ($staff === []): ?>
            <p class="text-muted text-center py-5 mb-0">Сотрудников пока нет.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered text-nowrap w-100">
                    <thead>
                        <tr>
                            <th>Имя</th>
                            <th>Email</th>
                            <th>Телефон</th>
                            <th>Роль</th>
                            <th>Дата</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff as $user): ?>
                            <?php $isSelf = (int) $user['id'] === $myId; ?>
                            <tr>
                                <td><?= e($user['name']) ?><?= $isSelf ? ' <span class="text-muted">(вы)</span>' : '' ?></td>
                                <td><?= e($user['email']) ?></td>
                                <td><?= $user['phone'] !== null ? e((string) $user['phone']) : '—' ?></td>
                                <td><?= e($roleLabels[$user['role']] ?? $user['role']) ?></td>
                                <td><?= e(date('d.m.Y', strtotime((string) $user['created_at']))) ?></td>
                                <td>
                                    <?php if ((int) $user['is_blocked'] === 1): ?>
                                        <span class="badge bg-danger-transparent">Заблокирован</span>
                                    <?php else: ?>
                                        <span class="badge bg-success-transparent">Активен</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isSelf): ?>
                                        —
                                    <?php elseif ((int) $user['is_blocked'] === 1): ?>
                                        <form method="post" action="/admin/users/<?= e((string) $user['id']) ?>/unblock">
                                            <?= csrfField() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-success">Разблокировать</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="/admin/users/<?= e((string) $user['id']) ?>/block">
                                            <?= csrfField() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Заблокировать</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card custom-card">
    <div class="card-header">
        <h5 class="card-title mb-0">Новый сотрудник</h5>
        <p class="mb-0 text-muted">Пароль сотрудник может сменить сам через «Забыли пароль?» на странице входа</p>
    </div>
    <div class="card-body">
        <form method="post" action="/admin/users" class="row g-3">
            <?= csrfField() ?>
            <div class="col-md-4">
                <label for="staff-name" class="form-label">Имя</label>
                <input type="text" id="staff-name" name="name" class="form-control<?= !empty($errors['name']) ? ' is-invalid' : '' ?>" value="<?= e($old['name'] ?? '') ?>">
                <?php if (!empty($errors['name'])): ?>
                    <div class="invalid-feedback">Введите имя.</div>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <label for="staff-email" class="form-label">Email</label>
                <input type="email" id="staff-email" name="email" class="form-control<?= !empty($errors['email']) ? ' is-invalid' : '' ?>" value="<?= e($old['email'] ?? '') ?>">
                <?php if (!empty($errors['email'])): ?>
                    <div class="invalid-feedback">Такой email уже занят или указан некорректно.</div>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <label for="staff-phone" class="form-label">Телефон (необязательно)</label>
                <input type="tel" id="staff-phone" name="phone" class="form-control<?= !empty($errors['phone']) ? ' is-invalid' : '' ?>" value="<?= e($old['phone'] ?? '') ?>">
                <?php if (!empty($errors['phone'])): ?>
                    <div class="invalid-feedback">Введите корректный номер телефона.</div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <label for="staff-password" class="form-label">Пароль</label>
                <input type="password" id="staff-password" name="password" class="form-control<?= !empty($errors['password']) ? ' is-invalid' : '' ?>" autocomplete="new-password">
                <?php if (!empty($errors['password'])): ?>
                    <div class="invalid-feedback">Пароль не короче 8 символов.</div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <label for="staff-role" class="form-label">Роль</label>
                <select id="staff-role" name="role" class="form-select<?= !empty($errors['role']) ? ' is-invalid' : '' ?>">
                    <option value="">—</option>
                    <?php foreach (STAFF_ROLES as $roleValue): ?>
                        <option value="<?= e($roleValue) ?>"<?= ($old['role'] ?? '') === $roleValue ? ' selected' : '' ?>><?= e($roleLabels[$roleValue]) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['role'])): ?>
                    <div class="invalid-feedback">Выберите роль.</div>
                <?php endif; ?>
            </div>
            <div class="col-md-12">
                <button type="submit" class="btn btn-primary">Создать сотрудника</button>
            </div>
        </form>
    </div>
</div>

<?php include ROOT_PATH . '/src/Views/layout/admin-footer.php'; ?>
