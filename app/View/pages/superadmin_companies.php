<?php

function statusBadge(string $status): string
{
    $map = [
        'active'       => ['class' => 'badge-ok',    'label' => 'Активен'],
        'provisioning' => ['class' => 'badge-warn',  'label' => 'Настройка'],
        'error'        => ['class' => 'badge-danger','label' => 'Ошибка'],
        'inactive'     => ['class' => '',             'label' => 'Неактивен'],
        'suspended'    => ['class' => 'badge-warn',  'label' => 'Приостановлен'],
    ];

    $item = $map[$status] ?? ['class' => '', 'label' => $status];

    return '<span class="badge ' . $item['class'] . '"><span class="dot"></span>' . e($item['label']) . '</span>';
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
    <input type="text" class="field-input" placeholder="Поиск по названию" name="search_name" style="max-width:200px">
    <input type="text" class="field-input" placeholder="ИНН" name="search_inn" style="max-width:140px">
    <select class="field-select" name="status" style="max-width:150px">
        <option value="">Все статусы</option>
        <option value="active">Активен</option>
        <option value="provisioning">Настройка</option>
        <option value="error">Ошибка</option>
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
                        <th>Создан</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($companies as $c): ?>
                    <tr>
                        <td class="col-mono"><?= $c['id'] ?></td>
                        <td><?= e($c['name']) ?></td>
                        <td class="col-mono"><?= e($c['inn'] ?? '') ?></td>
                        <td><?= statusBadge($c['status']) ?></td>
                        <td class="col-muted"><?= e($c['created_at'] ?? '') ?></td>
                        <td class="col-actions"></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
