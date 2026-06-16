<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Блоки "Водитель + ТС"</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Работа с блоками недоступна.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Блоки "Водитель + ТС"</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif (empty($blocks)): ?>

<div class="page-head">
    <div>
        <h1>Блоки "Водитель + ТС"</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/driver-vehicle-blocks/create" class="btn btn-primary">Создать блок</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p>Блоки "Водитель + ТС" ещё не созданы.</p>
            <a href="/company/driver-vehicle-blocks/create" class="btn btn-primary">Создать первый блок</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Блоки "Водитель + ТС"</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
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
                        <th>ID</th>
                        <th>Водитель</th>
                        <th>Телефон</th>
                        <th>Тип комплекта</th>
                        <th>Транспорт</th>
                        <th>Статус</th>
                        <?php if (($_SESSION['role_code'] ?? '') === 'company_owner'): ?>
                        <th>Владелец</th>
                        <?php endif; ?>
                        <th>Создан</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($blocks as $b): ?>
                    <tr>
                        <td class="col-mono"><?= $b['id'] ?></td>
                        <td><?= e($b['driver_name'] ?? '—') ?></td>
                        <td class="col-mono"><?= e($b['driver_phone'] ?? '—') ?></td>
                        <td><?= e($b['set_type'] ?? '—') ?></td>
                        <td class="col-mono"><?= e($b['plates'] ?? '—') ?></td>
                        <td>
                            <span class="badge<?= $b['status'] === 'active' ? ' badge-ok' : ($b['status'] === 'archived' ? ' badge-warn' : '') ?>">
                                <span class="dot"></span>
                                <?= $b['status'] === 'active' ? 'Активен' : ($b['status'] === 'archived' ? 'Архив' : 'Неактивен') ?>
                            </span>
                        </td>
                        <?php if (($_SESSION['role_code'] ?? '') === 'company_owner'): ?>
                        <td class="col-muted"><?= e($b['created_by_role'] ?? '—') ?> #<?= e($b['created_by_user_id'] ?? '—') ?></td>
                        <?php endif; ?>
                        <td class="col-muted"><?= e($b['created_at']) ?></td>
                        <td class="col-actions">
                            <a href="/company/driver-vehicle-blocks/<?= $b['id'] ?>" class="btn btn-toolbar">Просмотр</a>
                            <a href="/company/driver-vehicle-blocks/<?= $b['id'] ?>/edit" class="btn btn-toolbar">Редактировать</a>
                            <a href="/company/documents?entity_type=driver_vehicle_block&entity_id=<?= $b['id'] ?>" class="btn btn-toolbar">Документы</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>
