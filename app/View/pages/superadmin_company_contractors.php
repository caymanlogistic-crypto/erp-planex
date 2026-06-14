<?php

require_once __DIR__ . '/../components/status_badge.php';

?>
<?php if ($company === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            <?= e($dbError ?? 'Компания не найдена.') ?> <a href="/superadmin/companies">← К реестру</a>
        </div>
    </div>
</div>

<?php elseif (isset($dbError)): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice danger">
            <?= e($dbError) ?>
        </div>
        <div class="form-actions" style="margin-top:16px">
            <a href="/superadmin/companies/<?= $id ?>" class="btn btn-ghost">← К карточке</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Подрядчики компании: <?= e($company['name']) ?></h1>
        <p class="text-muted">ID: <?= $id ?> · Режим SUPERADMIN: просмотр</p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $id ?>" class="btn btn-ghost">← К карточке</a>
    </div>
</div>

<?php if (empty($items)): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="empty-state">
                <p class="text-muted">Нет подрядчиков</p>
                <p class="text-muted">В компании ещё не созданы подрядчики.</p>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="panel">
        <div class="panel-head">
            <h3 class="panel-head-title">Подрядчики</h3>
            <span class="badge"><?= $totalCount ?> записей</span>
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
                        <th>Документы</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="col-mono"><?= $item['id'] ?></td>
                        <td><?= e($item['name'] ?? '') ?></td>
                        <td class="col-mono"><?= e($item['inn'] ?? '—') ?></td>
                        <td><?= renderStatusBadge($item['status'] ?? '') ?></td>
                        <td class="col-muted"><?= e($item['created_at'] ?? '') ?></td>
                        <td class="col-actions"><div class="row-actions"><a href="/superadmin/companies/<?= $id ?>/documents?entity_type=contractor&entity_id=<?= $item['id'] ?>" class="btn btn-ghost">Документы</a></div></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php endif; ?>
