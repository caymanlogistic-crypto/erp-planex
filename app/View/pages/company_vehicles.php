<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Транспортные единицы</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Работа с транспортными единицами недоступна.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Транспортные единицы</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif (empty($vehicles)): ?>
<?php $isLogist = ($_SESSION['role_code'] ?? '') === 'logist'; ?>

<div class="page-head">
    <div>
        <h1>Транспортные единицы</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <?php if (!$isLogist): ?>
    <div class="page-head-actions">
        <a href="/company/vehicles/create" class="btn btn-primary">Добавить транспортную единицу</a>
    </div>
    <?php endif; ?>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p class="empty-title">Нет доступных транспортных единиц</p>
            <p class="empty-desc">У вас пока нет созданных транспортных единиц, либо руководитель ещё не выдал вам доступ к существующим.</p>
            <?php if (!$isLogist): ?>
            <a href="/company/vehicles/create" class="btn btn-primary">Добавить первую единицу</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Транспортные единицы</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/vehicles/create" class="btn btn-primary">Добавить транспортную единицу</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Единица</th>
                        <th>Модель</th>
                        <th>Параметры</th>
                        <th>Статус</th>
                        <th>Создал</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vehicles as $v): ?>
                    <tr>
                        <td class="cell-double">
                            <span class="cell-main col-mono"><?= e($v['plate_number']) ?></span>
                            <span class="cell-sub"><?= e(ui_unit_type($v['unit_type'] ?? null)) ?></span>
                        </td>
                        <td class="cell-double">
                            <span class="cell-main"><?= e($v['brand'] ?? '—') ?></span>
                            <span class="cell-sub"><?= e($v['model'] ?? '—') ?></span>
                        </td>
                        <td class="cell-double">
                            <span class="cell-main"><?= $v['capacity_tons'] !== null ? e($v['capacity_tons']) . ' т' : '—' ?></span>
                            <span class="cell-sub">Грузоподъёмность</span>
                        </td>
                        <td>
                            <span class="badge<?= $v['status'] === 'active' ? ' badge-ok' : '' ?>">
                                <span class="dot"></span>
                                <?= $v['status'] === 'active' ? 'Активен' : 'Неактивен' ?>
                            </span>
                        </td>
                        <td class="col-muted"><?= e(ui_actor($v['created_by_role'] ?? null, $v['created_by_user_id'] ?? null, $v['created_by_name'] ?? null)) ?></td>
                        <td class="col-actions">
                            <div class="row-actions">
                                <a href="/company/vehicles/<?= $v['id'] ?>" class="btn btn-toolbar">Просмотр</a>
                                <a href="/company/vehicles/<?= $v['id'] ?>/edit" class="btn btn-toolbar">Редактировать</a>
                                <a href="/company/documents?entity_type=vehicle_unit&entity_id=<?= $v['id'] ?>" class="btn btn-toolbar">Документы</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>
