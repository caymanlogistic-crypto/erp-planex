<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Блоки Водитель + ТС</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Работа с блоками недоступна.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Блоки Водитель + ТС</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif (empty($blocks)): ?>
<?php $isLogist = ($_SESSION['role_code'] ?? '') === 'logist'; ?>

<div class="page-head">
    <div>
        <h1>Блоки Водитель + ТС</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <?php if (!$isLogist): ?>
    <div class="page-head-actions">
        <a href="/company/driver-vehicle-blocks/create" class="btn btn-primary">Создать блок</a>
    </div>
    <?php endif; ?>
</div>

<div class="panel">
    <div class="panel-body">
        <?php if ($isLogist): ?>
        <div class="empty-state">
            <div class="empty-icon">🔒</div>
            <p class="empty-title">Нет доступа</p>
            <p class="empty-desc">У вас нет доступа к блокам Водитель + ТС. Обратитесь к руководителю для получения доступа.</p>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <p class="empty-title">Блоки Водитель + ТС ещё не созданы.</p>
            <p class="empty-desc">Создайте блок, чтобы связать водителя с транспортным комплектом для экипажа.</p>
            <a href="/company/driver-vehicle-blocks/create" class="btn btn-primary">Создать первый блок</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Блоки Водитель + ТС</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/driver-vehicle-blocks/create" class="btn btn-primary">Создать блок</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Блок</th>
                        <th>Водитель</th>
                        <th>Транспортный комплект</th>
                        <th>Статус</th>
                        <?php if (($_SESSION['role_code'] ?? '') === 'company_owner'): ?>
                        <th>Создал</th>
                        <?php endif; ?>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($blocks as $b): ?>
                    <tr>
                        <td class="cell-double">
                            <span class="cell-main">Водитель + ТС</span>
                        </td>
                        <td class="cell-double">
                            <span class="cell-main"><?= e($b['driver_name'] ?? '—') ?></span>
                            <span class="cell-sub col-mono"><?= e($b['driver_phone'] ?? '—') ?></span>
                        </td>
                        <td class="cell-double">
                            <span class="cell-main col-mono"><?= e($b['plates'] ?? '—') ?></span>
                            <span class="cell-sub"><?= e(ui_set_type($b['set_type'] ?? null)) ?></span>
                        </td>
                        <td>
                            <span class="badge<?= $b['status'] === 'active' ? ' badge-ok' : ($b['status'] === 'archived' ? ' badge-warn' : '') ?>">
                                <span class="dot"></span>
                                <?= $b['status'] === 'active' ? 'Активен' : ($b['status'] === 'archived' ? 'Архив' : 'Неактивен') ?>
                            </span>
                        </td>
                        <?php if (($_SESSION['role_code'] ?? '') === 'company_owner'): ?>
                        <td class="col-muted"><?= e(ui_actor($b['created_by_role'] ?? null, $b['created_by_user_id'] ?? null, $b['created_by_name'] ?? null)) ?></td>
                        <?php endif; ?>
                        <td class="col-actions">
                            <div class="row-actions">
                                <a href="/company/driver-vehicle-blocks/<?= $b['id'] ?>" class="btn btn-toolbar">Просмотр</a>
                                <a href="/company/driver-vehicle-blocks/<?= $b['id'] ?>/edit" class="btn btn-toolbar">Редактировать</a>
                                <a href="/company/documents?entity_type=driver_vehicle_block&entity_id=<?= $b['id'] ?>" class="btn btn-toolbar">Документы</a>
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
