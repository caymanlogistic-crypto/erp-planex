<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Экипажи</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Работа с экипажами недоступна.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Экипажи</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif (empty($crews)): ?>

<div class="page-head">
    <div>
        <h1>Экипажи</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/crews/create" class="btn btn-primary">Создать экипаж</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p>Экипажи ещё не созданы.</p>
            <a href="/company/crews/create" class="btn btn-primary">Создать первый экипаж</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Экипажи</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/crews/create" class="btn btn-primary">Создать экипаж</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Подрядчик</th>
                        <th>Транспорт</th>
                        <th>Водитель</th>
                        <th>Статус</th>
                        <th>Создан</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($crews as $c): ?>
                    <tr>
                        <td class="col-mono"><?= $c['id'] ?></td>
                        <td><?= e($c['contractor_name'] ?? '—') ?></td>
                        <td class="col-mono"><?= e($c['plate_number'] ?? '—') ?></td>
                        <td><?= e($c['driver_name'] ?? '—') ?></td>
                        <td>
                            <span class="badge<?= $c['status'] === 'active' ? ' badge-ok' : '' ?>">
                                <span class="dot"></span>
                                <?= $c['status'] === 'active' ? 'Активен' : 'Неактивен' ?>
                            </span>
                        </td>
                        <td class="col-muted"><?= e($c['created_at']) ?></td>
                        <td class="col-actions">
                            <a href="/company/crews/<?= $c['id'] ?>" class="btn btn-toolbar">Просмотр</a>
                            <a href="/company/crews/<?= $c['id'] ?>/edit" class="btn btn-toolbar">Редактировать</a>
                            <a href="/company/documents?entity_type=crew&entity_id=<?= $c['id'] ?>" class="btn btn-toolbar">Документы</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>
