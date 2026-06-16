<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Транспортные комплекты</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Работа с транспортными комплектами недоступна.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Транспортные комплекты</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif (empty($vehicleSets)): ?>

<div class="page-head">
    <div>
        <h1>Транспортные комплекты</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/vehicle-sets/create" class="btn btn-primary">Создать комплект</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p>Транспортные комплекты ещё не созданы.</p>
            <a href="/company/vehicle-sets/create" class="btn btn-primary">Создать первый комплект</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Транспортные комплекты</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/vehicle-sets/create" class="btn btn-primary">Создать комплект</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Комплект</th>
                        <th>Состав</th>
                        <th>Статус</th>
                        <?php if (($_SESSION['role_code'] ?? '') === 'company_owner'): ?>
                        <th>Создал</th>
                        <?php endif; ?>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vehicleSets as $vs): ?>
                    <tr>
                        <td class="cell-double">
                            <span class="cell-main"><?= e($vs['set_type'] ?? 'Комплект') ?></span>
                            <span class="cell-sub">ID <?= $vs['id'] ?></span>
                        </td>
                        <td class="cell-double">
                            <span class="cell-main col-mono"><?= e($vs['primary_plate'] ?? '—') ?><?= !empty($vs['secondary_plate']) ? ' + ' . e($vs['secondary_plate']) : '' ?></span>
                            <span class="cell-sub"><?= e($vs['primary_brand'] ?? '') ?><?= !empty($vs['secondary_brand']) ? ' / ' . e($vs['secondary_brand']) : '' ?></span>
                        </td>
                        <td>
                            <span class="badge<?= $vs['status'] === 'active' ? ' badge-ok' : ($vs['status'] === 'archived' ? ' badge-warn' : '') ?>">
                                <span class="dot"></span>
                                <?= $vs['status'] === 'active' ? 'Активен' : ($vs['status'] === 'archived' ? 'Архив' : 'Неактивен') ?>
                            </span>
                        </td>
                        <?php if (($_SESSION['role_code'] ?? '') === 'company_owner'): ?>
                        <td class="col-muted"><?= e($vs['created_by_role'] ?? '—') ?> #<?= e($vs['created_by_user_id'] ?? '—') ?></td>
                        <?php endif; ?>
                        <td class="col-actions">
                            <div class="row-actions">
                                <a href="/company/vehicle-sets/<?= $vs['id'] ?>" class="btn btn-toolbar">Просмотр</a>
                                <a href="/company/vehicle-sets/<?= $vs['id'] ?>/edit" class="btn btn-toolbar">Редактировать</a>
                                <a href="/company/documents?entity_type=vehicle_set&entity_id=<?= $vs['id'] ?>" class="btn btn-toolbar">Документы</a>
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
