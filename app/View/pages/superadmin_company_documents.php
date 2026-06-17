<?php

function docStatusBadge(string $status): string
{
    $map = [
        'uploaded' => ['class' => '',            'label' => 'Загружен'],
        'pending'  => ['class' => 'badge-warn',  'label' => 'Ожидает проверки'],
        'approved' => ['class' => 'badge-ok',    'label' => 'Принят'],
        'rejected' => ['class' => '',            'label' => 'Отклонён'],
        'archived' => ['class' => '',            'label' => 'Архивирован'],
        'active'   => ['class' => 'badge-ok',   'label' => 'Активен'],
        'verified' => ['class' => 'badge-ok',   'label' => 'Проверен'],
        'review'   => ['class' => 'badge-warn',  'label' => 'На проверке'],
    ];
    $item = $map[$status] ?? ['class' => '', 'label' => $status];
    return '<span class="badge ' . $item['class'] . '"><span class="dot"></span>' . e($item['label']) . '</span>';
}

?>
<?php if ($company === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Компания не найдена. <a href="/superadmin/companies">← К реестру</a>
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
            <a href="/superadmin/companies" class="btn btn-ghost">← К реестру</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">SUPERADMIN / <?= e($company['name']) ?></span>
        <span class="page-title">Документы компании</span>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $id ?>" class="btn btn-ghost">← К карточке</a>
    </div>
</div>

<div class="page-content">

<?php if (!empty($dbError)): ?>
    <div class="notice warn">
        Локальная БД компании недоступна. Документы не могут быть загружены.
    </div>
<?php elseif (empty($documents)): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="empty-state empty-state-left">
                <p class="empty-title">Нет документов</p>
                <p class="empty-desc">Документы загружаются пользователями компании к клиентам, подрядчикам, водителям, транспорту или экипажам. Для новой компании это нормально; для рабочей — проверьте, есть ли заполненные справочники и доступные пользователи.</p>
                <a href="/superadmin/companies/<?= $id ?>" class="btn btn-secondary">← К готовности компании</a>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="panel">
        <div class="panel-head">
            <h3 class="panel-head-title">Документы</h3>
            <span class="badge"><?= $totalCount ?> документов</span>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Файл</th>
                        <th>Объект</th>
                        <th class="col-tight col-num">Размер</th>
                        <th class="col-tight">Статус</th>
                        <th class="col-tight">Загружен</th>
                        <th class="col-tight"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $d): ?>
                    <tr>
                        <td class="cell-double">
                            <span class="cell-main"><?= e($d['original_name']) ?></span>
                            <span class="cell-sub"><?= e($d['mime_type']) ?></span>
                        </td>
                        <td class="cell-double">
                            <span class="cell-main"><?= e(ui_entity_type($d['entity_type'])) ?></span>
                            <span class="cell-sub">ID <?= (int)$d['entity_id'] ?></span>
                        </td>
                        <td class="col-tight col-num col-mono"><?= formatFileSize((int)$d['file_size']) ?></td>
                        <td class="col-tight"><?= docStatusBadge($d['status']) ?></td>
                        <td class="col-tight cell-double">
                            <span class="cell-main col-muted"><?= e(substr($d['created_at'] ?? '', 0, 10)) ?></span>
                            <span class="cell-sub"><?= e(ui_role($d['uploaded_by_role'] ?? null)) ?></span>
                        </td>
                        <td class="col-tight col-actions">
                            <div class="row-actions">
                                <a href="/superadmin/companies/<?= $id ?>/documents/<?= $d['id'] ?>/download" class="btn btn-ghost btn-sm">Скачать</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

</div><!-- /.page-content -->

<?php endif; ?>
