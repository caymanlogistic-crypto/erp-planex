<?php

require_once __DIR__ . '/../components/status_badge.php';

?>
<div class="page-head">
    <div>
        <h1>Реестр компаний</h1>
        <p class="text-muted">Центральный реестр локальных ERP-систем</p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/create" class="btn btn-primary">Создать экспедитора</a>
    </div>
</div>

<?php if (isset($dbError)): ?>
    <div class="notice warn">
        <?= e($dbError) ?>
    </div>
<?php endif; ?>

<?php if (($_GET['status_changed'] ?? '') === '1'): ?>
    <div class="notice success">Статус изменён.</div>
<?php endif; ?>

<?php if (($_GET['deleted'] ?? '') !== ''): ?>
<div class="notice success">Компания ID <?= (int)$_GET['deleted'] ?> полностью удалена.</div>
<?php endif; ?>

<form method="get" action="/superadmin/companies" class="filters-bar">
    <input type="text" class="field-input" placeholder="Поиск по названию или ИНН" name="search" value="<?= e($search ?? '') ?>" style="max-width:240px">
    <select class="field-select" name="status" style="max-width:150px">
        <option value="">Все статусы</option>
        <option value="active" <?= ($filterStatus ?? '') === 'active' ? 'selected' : '' ?>>Активен</option>
        <option value="inactive" <?= ($filterStatus ?? '') === 'inactive' ? 'selected' : '' ?>>Неактивен</option>
        <option value="blocked" <?= ($filterStatus ?? '') === 'blocked' ? 'selected' : '' ?>>Заблокирован</option>
        <option value="archived" <?= ($filterStatus ?? '') === 'archived' ? 'selected' : '' ?>>Архивирован</option>
        <option value="provisioning" <?= ($filterStatus ?? '') === 'provisioning' ? 'selected' : '' ?>>Настройка</option>
        <option value="error" <?= ($filterStatus ?? '') === 'error' ? 'selected' : '' ?>>Ошибка</option>
    </select>

    <button type="submit" class="btn btn-toolbar">Применить</button>
    <a href="/superadmin/companies" class="btn btn-ghost">Сбросить</a>
</form>

<?php if (empty($companies)): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="empty-state">
                <p class="text-muted">Нет созданных компаний</p>
                <p class="text-muted">Создайте первого экспедитора для начала работы системы.</p>
                <a href="/superadmin/companies/create" class="btn btn-primary">Создать экспедитора</a>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="panel">
        <div class="panel-head">
            <h3 class="panel-head-title">Компании</h3>
            <span class="badge"><?= count($companies) ?> записей</span>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Название</th>
                        <th>ИНН</th>
                        <th>Статус</th>
                        <th>Локальная БД</th>
                        <th>Руководитель</th>
                        <th>Пользователей</th>
                        <th>Создан</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($companies as $c): ?>
                    <tr>
                        <td class="col-mono"><?= $c['id'] ?></td>
                        <td><?= e($c['name']) ?></td>
                        <td class="col-mono"><?= e($c['inn']) ?></td>
                        <td><?= renderStatusBadge($c['status']) ?></td>
                        <td class="col-mono col-muted"><?= e($c['db_identifier'] ?? '—') ?></td>
                        <td>
                            <?php if (in_array($c['status'], ['error', 'provisioning'], true)): ?>
                                <span class="col-muted">—</span>
                            <?php elseif (!empty($c['owner_name'])): ?>
                                <span class="dot" style="background:var(--success)"></span>
                                <?= e($c['owner_name']) ?>
                            <?php else: ?>
                                <a href="/superadmin/companies/<?= $c['id'] ?>/create-owner" class="btn btn-primary" style="font-size:11px;padding:2px 10px">
                                    Создать
                                </a>
                            <?php endif; ?>
                        </td>
                        <td class="col-mono"><?= (int)($c['user_count'] ?? 0) ?></td>
                        <td class="col-muted"><?= e($c['created_at'] ?? '') ?></td>
                        <td class="col-actions">
                            <div class="row-actions">
                                <a href="/superadmin/companies/<?= $c['id'] ?>" class="btn btn-ghost">Карточка</a>
                                <a href="/superadmin/companies/<?= $c['id'] ?>/edit" class="btn btn-ghost">Редактировать</a>
                                <a href="/superadmin/companies/<?= $c['id'] ?>/owner" class="btn btn-ghost">Руководитель</a>
                                <a href="/superadmin/companies/<?= $c['id'] ?>/users" class="btn btn-ghost">Пользователи</a>
                                <?php if ($c['status'] !== 'active'): ?>
                                <form method="post" action="/superadmin/companies/<?= $c['id'] ?>/activate" style="display:inline" onsubmit="return confirm('Активировать компанию?')">
                                        <button type="submit" class="btn btn-ghost">Активировать</button>
                                </form>
                                <?php endif; ?>
                                <?php if (in_array($c['status'], ['active', 'inactive'], true)): ?>
                                <form method="post" action="/superadmin/companies/<?= $c['id'] ?>/block" style="display:inline" onsubmit="return confirm('Заблокировать компанию?')">
                                        <button type="submit" class="btn btn-ghost">Заблокировать</button>
                                </form>
                                <?php endif; ?>
                                <form method="post" action="/superadmin/companies/<?= $c['id'] ?>/archive" style="display:inline" onsubmit="return confirm('Архивировать компанию? Все данные сохранятся.')">
                                        <button type="submit" class="btn btn-danger">Архивировать</button>
                                </form>
                                <?php if ($c['status'] === 'active'): ?>
                                <form method="post" action="/superadmin/companies/<?= $c['id'] ?>/deactivate" style="display:inline" onsubmit="return confirm('Отключить компанию?')">
                                        <button type="submit" class="btn btn-ghost">Отключить</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
