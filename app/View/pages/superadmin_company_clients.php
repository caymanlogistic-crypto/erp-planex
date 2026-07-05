<?php

require_once __DIR__ . '/../components/status_badge.php';

?>
<?php if ($company === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            <?= e($dbError ?? 'Компания не найдена.') ?> <a href="<?= app_url('/superadmin/companies') ?>">← К реестру</a>
        </div>
    </div>
</div>

<?php elseif (isset($dbError)): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice danger">
            <?= e($dbError) ?>
        </div>
        <div class="form-actions mt-4">
            <a href="/superadmin/companies/<?= $id ?>" class="btn btn-ghost">← К карточке</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">SUPERADMIN / <?= e($company['name']) ?></span>
        <span class="page-title">Клиенты компании</span>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $id ?>" class="btn btn-ghost">← К карточке</a>
    </div>
</div>

<div class="page-content">

<?php if (empty($items)): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="empty-state empty-state-left">
                <p class="empty-title">Нет клиентов</p>
                <p class="empty-desc">Для новой компании это может быть нормальным состоянием. Для рабочей компании это сигнал проверить, заполнены ли справочники на стороне владельца компании.</p>
                <a href="/superadmin/companies/<?= $id ?>/directories" class="btn btn-secondary">← К аудиту справочников</a>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="panel">
        <div class="panel-head">
            <h3 class="panel-head-title">Клиенты</h3>
            <span class="badge"><?= $totalCount ?> записей</span>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Клиент</th>
                        <th>ИНН</th>
                        <th>Статус</th>
                        <th>Создан</th>
                        <th>Документы</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="cell-double">
                            <span class="cell-main"><?= e($item['name'] ?? '') ?></span>
                            <span class="cell-sub">ID <?= $item['id'] ?></span>
                        </td>
                        <td class="col-mono"><?= e($item['inn'] ?? '—') ?></td>
                        <td><?= renderStatusBadge($item['status'] ?? '') ?></td>
                        <td class="col-muted"><?= e($item['created_at'] ?? '') ?></td>
                        <td class="col-actions"><div class="row-actions"><a href="/superadmin/companies/<?= $id ?>/documents?entity_type=client&entity_id=<?= $item['id'] ?>" class="btn btn-ghost btn-sm">Документы</a></div></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

</div><!-- /.page-content -->

<?php endif; ?>
