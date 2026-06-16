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
                        <th>ID</th>
                        <th>Тип комплекта</th>
                        <th>Основная единица</th>
                        <th>Доп. единица</th>
                        <th>Статус</th>
                        <?php if (($_SESSION['role_code'] ?? '') === 'company_owner'): ?>
                        <th>Владелец</th>
                        <?php endif; ?>
                        <th>Создан</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vehicleSets as $vs): ?>
                    <tr>
                        <td class="col-mono"><?= $vs['id'] ?></td>
                        <td><?= e($vs['set_type'] ?? '—') ?></td>
                        <td class="col-mono"><?= e($vs['primary_plate'] ?? '—') ?> <?= e($vs['primary_brand'] ?? '') ?></td>
                        <td class="col-mono"><?= e($vs['secondary_plate'] ?? '—') ?> <?= e($vs['secondary_brand'] ?? '') ?></td>
                        <td>
                            <span class="badge<?= $vs['status'] === 'active' ? ' badge-ok' : ($vs['status'] === 'archived' ? ' badge-warn' : '') ?>">
                                <span class="dot"></span>
                                <?= $vs['status'] === 'active' ? 'Активен' : ($vs['status'] === 'archived' ? 'Архив' : 'Неактивен') ?>
                            </span>
                        </td>
                        <?php if (($_SESSION['role_code'] ?? '') === 'company_owner'): ?>
                        <td class="col-muted"><?= e($vs['created_by_role'] ?? '—') ?> #<?= e($vs['created_by_user_id'] ?? '—') ?></td>
                        <?php endif; ?>
                        <td class="col-muted"><?= e($vs['created_at']) ?></td>
                        <td class="col-actions">
                            <a href="/company/vehicle-sets/<?= $vs['id'] ?>" class="btn btn-toolbar">Просмотр</a>
                            <a href="/company/vehicle-sets/<?= $vs['id'] ?>/edit" class="btn btn-toolbar">Редактировать</a>
                            <a href="/company/documents?entity_type=vehicle_set&entity_id=<?= $vs['id'] ?>" class="btn btn-toolbar">Документы</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>
