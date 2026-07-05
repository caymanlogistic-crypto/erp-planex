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
        <span class="page-title">Водители компании</span>
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
                <p class="empty-title">Нет водителей</p>
                <p class="empty-desc">Пустой список водителей блокирует сборку экипажей. Для новой компании это ожидаемо, для действующей — повод проверить наполнение справочников.</p>
                <a href="/superadmin/companies/<?= $id ?>/directories" class="btn btn-secondary">← К аудиту справочников</a>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="panel">
        <div class="panel-head">
            <h3 class="panel-head-title">Водители</h3>
            <span class="badge"><?= $totalCount ?> записей</span>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Водитель</th>
                        <th>Контакты / категория</th>
                        <th>Статус</th>
                        <th>Создан</th>
                        <th>Документы</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="cell-double">
                            <span class="cell-main"><?= e($item['full_name'] ?? '') ?></span>
                            <span class="cell-sub">ID <?= $item['id'] ?></span>
                        </td>
                        <td class="cell-double">
                            <span class="cell-main col-mono"><?= e($item['phone'] ?? '—') ?></span>
                            <span class="cell-sub"><?= e($item['category'] ?? '—') ?></span>
                        </td>
                        <td><?= renderStatusBadge($item['status'] ?? '') ?></td>
                        <td class="col-muted"><?= e($item['created_at'] ?? '') ?></td>
                        <td class="col-actions"><div class="row-actions"><a href="/superadmin/companies/<?= $id ?>/documents?entity_type=driver&entity_id=<?= $item['id'] ?>" class="btn btn-ghost btn-sm">Документы</a></div></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

</div><!-- /.page-content -->

<?php endif; ?>
