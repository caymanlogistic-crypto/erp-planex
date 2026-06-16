<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Транспорт</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Работа с транспортом недоступна.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Транспорт</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif (empty($vehicles)): ?>

<div class="page-head">
    <div>
        <h1>Транспорт</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/vehicles/create" class="btn btn-primary">Добавить транспорт</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p>Транспорт ещё не добавлен.</p>
            <a href="/company/vehicles/create" class="btn btn-primary">Добавить первый транспорт</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Транспорт</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/vehicles/create" class="btn btn-primary">Добавить транспорт</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Госномер</th>
                        <th>Марка</th>
                        <th>Модель</th>
                        <th>Тип единицы</th>
                        <th>Грузоподъёмность (т)</th>
                        <th>Статус</th>
                        <th>Создан</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vehicles as $v): ?>
                    <tr>
                        <td class="col-mono"><?= $v['id'] ?></td>
                        <td class="col-mono"><?= e($v['plate_number']) ?></td>
                        <td><?= e($v['brand'] ?? '—') ?></td>
                        <td><?= e($v['model'] ?? '—') ?></td>
                        <td><?= e($v['unit_type'] ?? '—') ?></td>
                        <td class="col-mono"><?= $v['capacity_tons'] !== null ? e($v['capacity_tons']) : '—' ?></td>
                        <td>
                            <span class="badge<?= $v['status'] === 'active' ? ' badge-ok' : '' ?>">
                                <span class="dot"></span>
                                <?= $v['status'] === 'active' ? 'Активен' : 'Неактивен' ?>
                            </span>
                        </td>
                        <td class="col-muted"><?= e($v['created_at']) ?></td>
                        <td class="col-actions">
                            <a href="/company/vehicles/<?= $v['id'] ?>" class="btn btn-toolbar">Просмотр</a>
                            <a href="/company/vehicles/<?= $v['id'] ?>/edit" class="btn btn-toolbar">Редактировать</a>
                            <a href="/company/documents?entity_type=vehicle_unit&entity_id=<?= $v['id'] ?>" class="btn btn-toolbar">Документы</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>
