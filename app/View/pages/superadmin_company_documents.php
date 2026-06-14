<?php

function docStatusBadge(string $status): string
{
    $map = [
        'active'   => ['class' => 'badge-ok',   'label' => 'Активен'],
        'archived' => ['class' => '',            'label' => 'Архивирован'],
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
        <div class="form-actions" style="margin-top:16px">
            <a href="/superadmin/companies" class="btn btn-ghost">← К реестру</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Документы компании: <?= e($company['name']) ?></h1>
        <p class="text-muted">ID: <?= $id ?> · Режим SUPERADMIN: просмотр</p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $id ?>" class="btn btn-ghost">← К карточке</a>
    </div>
</div>

<?php if (!empty($dbError)): ?>
    <div class="notice warn">
        Локальная БД компании недоступна. Документы не могут быть загружены.
    </div>
<?php elseif (empty($documents)): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="empty-state">
                <p class="text-muted">Нет документов</p>
                <p class="text-muted">В компании ещё не загружены документы.</p>
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
                        <th>ID</th>
                        <th>Тип сущности</th>
                        <th>ID сущности</th>
                        <th>Имя файла</th>
                        <th class="col-num">Размер</th>
                        <th>Тип</th>
                        <th>Статус</th>
                        <th>Загружен</th>
                        <th>Кем</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $d): ?>
                    <tr>
                        <td class="col-mono"><?= $d['id'] ?></td>
                        <td><?= e($d['entity_type']) ?></td>
                        <td class="col-mono"><?= (int)$d['entity_id'] ?></td>
                        <td><?= e($d['original_name']) ?></td>
                        <td class="col-num col-mono"><?= formatFileSize((int)$d['file_size']) ?></td>
                        <td><?= e($d['mime_type']) ?></td>
                        <td><?= docStatusBadge($d['status']) ?></td>
                        <td class="col-muted"><?= e($d['created_at']) ?></td>
                        <td><?= e($d['uploaded_by_role'] ?? '—') ?> #<?= (int)($d['uploaded_by_user_id'] ?? 0) ?></td>
                        <td class="col-actions">
                            <div class="row-actions">
                                <a href="/company/documents/download?id=<?= $d['id'] ?>&company_id=<?= $id ?>" class="ra" title="Скачать">↓</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php endif; ?>
