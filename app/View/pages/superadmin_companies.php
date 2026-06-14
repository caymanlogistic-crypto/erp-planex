<?php

function statusBadge(string $status): string
{
    $map = [
        'active'       => ['class' => 'badge-ok',    'label' => 'Активен'],
        'inactive'     => ['class' => '',             'label' => 'Неактивен'],
        'blocked'      => ['class' => 'badge-danger', 'label' => 'Заблокирован'],
        'archived'     => ['class' => '',             'label' => 'Архивирован'],
        'provisioning' => ['class' => 'badge-warn',   'label' => 'Настройка'],
        'error'        => ['class' => 'badge-danger', 'label' => 'Ошибка'],
        'suspended'    => ['class' => 'badge-warn',   'label' => 'Приостановлен'],
    ];
    $item = $map[$status] ?? ['class' => '', 'label' => $status];
    return '<span class="badge ' . $item['class'] . '"><span class="dot"></span>' . e($item['label']) . '</span>';
}

function provisioningBadge(string $status, ?string $dbIdentifier): string
{
    if (!empty($dbIdentifier)) {
        $label = e($dbIdentifier);
    } else {
        $label = '—';
    }
    $map = [
        'active'       => ['class' => 'badge-ok',    'label' => 'active'],
        'inactive'     => ['class' => '',             'label' => 'inactive'],
        'blocked'      => ['class' => 'badge-danger', 'label' => 'blocked'],
        'archived'     => ['class' => '',             'label' => 'archived'],
        'error'        => ['class' => 'badge-danger', 'label' => 'error'],
        'provisioning' => ['class' => 'badge-warn',   'label' => 'provisioning'],
    ];
    $item = $map[$status] ?? ['class' => '', 'label' => $status];
    return e($label) . ' <span class="badge ' . $item['class'] . '" style="margin-left:4px"><span class="dot"></span>' . e($item['label']) . '</span>';
}

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

<div class="filters-bar">
    <input type="text" class="field-input" placeholder="Поиск по названию или ИНН" name="search" style="max-width:240px">
    <select class="field-select" name="status" style="max-width:150px">
        <option value="">Все статусы</option>
        <option value="active">Активен</option>
        <option value="inactive">Неактивен</option>
        <option value="blocked">Заблокирован</option>
        <option value="archived">Архивирован</option>
        <option value="provisioning">Настройка</option>
        <option value="error">Ошибка</option>
    </select>
    <select class="field-select" name="provisioning" style="max-width:160px">
        <option value="">Все provisioning</option>
        <option value="active">active</option>
        <option value="inactive">inactive</option>
        <option value="blocked">blocked</option>
        <option value="archived">archived</option>
        <option value="error">error</option>
        <option value="provisioning">provisioning</option>
    </select>
    <button class="btn btn-toolbar">Сбросить</button>
</div>

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
                        <th>Provisioning</th>
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
                        <td><?= statusBadge($c['status']) ?></td>
                        <td><?= provisioningBadge($c['status'], $c['db_identifier'] ?? null) ?></td>
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
                                <a href="/superadmin/companies/<?= $c['id'] ?>" class="ra" title="Карточка">V</a>
                                <a href="/superadmin/companies/<?= $c['id'] ?>/edit" class="ra" title="Редактировать">E</a>
                                <a href="/superadmin/companies/<?= $c['id'] ?>/owner" class="ra" title="Руководитель">O</a>
                                <a href="/superadmin/companies/<?= $c['id'] ?>/users" class="ra" title="Пользователи">U</a>
                                <?php if ($c['status'] !== 'active'): ?>
                                <form method="post" action="/superadmin/companies/<?= $c['id'] ?>/activate" style="display:inline" onsubmit="return confirm('Активировать компанию?')">
                                    <button class="ra" title="Активировать">✓</button>
                                </form>
                                <?php endif; ?>
                                <?php if ($c['status'] === 'active'): ?>
                                <form method="post" action="/superadmin/companies/<?= $c['id'] ?>/block" style="display:inline" onsubmit="return confirm('Заблокировать компанию?')">
                                    <button class="ra" title="Заблокировать">⊗</button>
                                </form>
                                <?php endif; ?>
                                <form method="post" action="/superadmin/companies/<?= $c['id'] ?>/archive" style="display:inline" onsubmit="return confirm('Архивировать компанию? Все данные сохранятся.')">
                                    <button class="ra del" title="Архивировать">A</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
