<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Транспорт</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Работа с транспортом недоступна.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Транспорт</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif (empty($vehicleSets)): ?>
<?php $isLogist = ($_SESSION['role_code'] ?? '') === 'logist'; ?>

<div class="page-head">
    <div>
        <h1>Транспорт</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <?php if (!$isLogist): ?>
    <div class="page-head-actions">
        <a href="/company/vehicle-sets/create" class="btn btn-primary">Создать транспорт</a>
    </div>
    <?php endif; ?>
</div>

<div class="panel">
    <div class="panel-body">
        <?php if ($isLogist): ?>
        <div class="empty-state">
            <div class="empty-icon">🔒</div>
            <p class="empty-title">Нет доступа</p>
            <p class="empty-desc">У вас нет доступа к транспорту. Обратитесь к руководителю для получения доступа.</p>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <p class="empty-title">Транспорт ещё не создан.</p>
            <p class="empty-desc">Создайте транспорт, чтобы объединить тягач и полуприцеп для экипажа.</p>
            <a href="/company/vehicle-sets/create" class="btn btn-primary">Создать первый транспорт</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Транспорт</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/vehicle-sets/create" class="btn btn-primary">Создать транспорт</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Транспорт</th>
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
                            <span class="cell-main"><?= e($vs['set_type'] ?? 'Транспорт') ?></span>
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
                        <td class="col-muted"><?= e(ui_actor($vs['created_by_role'] ?? null, $vs['created_by_user_id'] ?? null, $vs['created_by_name'] ?? null)) ?></td>
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
