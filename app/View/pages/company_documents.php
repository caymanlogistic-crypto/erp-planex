<?php if ($entityTypeError): ?>

<div class="notice warn">
    Неизвестный тип сущности «<?= e($entityType) ?>». Допустимые типы: client, contractor, driver, vehicle_unit, vehicle_set, driver_vehicle_block, crew.
</div>

<?php elseif ($company === null): ?>

<div class="notice warn">
    Компания не найдена.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Документы</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Работа с документами недоступна.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Документы: <?= e($entityName) ?></h1>
        <p class="text-muted"><?= e($entityLabel) ?> &bull; Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif ($entityNotFound): ?>

<div class="page-head">
    <div>
        <h1>Документы</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<div class="notice warn">
    <?= e($entityLabel) ?> не найден. Проверьте, что сущность существует, и повторите попытку.
</div>

<?php elseif (empty($documents)): ?>

<div class="page-head">
    <div>
        <a href="<?= e($backRoute) ?>" class="btn btn-ghost" style="margin-bottom:4px">&larr; Назад к <?= e($entityLabelDative) ?></a>
        <h1>Документы: <?= e($entityName) ?></h1>
        <p class="text-muted"><?= e($entityLabel) ?> &bull; Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/documents/upload?entity_type=<?= e($entityType) ?>&entity_id=<?= $entityId ?>" class="btn btn-primary">Загрузить документ</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p>Документы не загружены.</p>
            <a href="/company/documents/upload?entity_type=<?= e($entityType) ?>&entity_id=<?= $entityId ?>" class="btn btn-primary">Загрузить первый документ</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <a href="<?= e($backRoute) ?>" class="btn btn-ghost" style="margin-bottom:4px">&larr; Назад к <?= e($entityLabelDative) ?></a>
        <h1>Документы: <?= e($entityName) ?></h1>
        <p class="text-muted"><?= e($entityLabel) ?> &bull; Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/documents/upload?entity_type=<?= e($entityType) ?>&entity_id=<?= $entityId ?>" class="btn btn-primary">Загрузить документ</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Тип документа</th>
                        <th>Имя файла</th>
                        <th>Размер</th>
                        <th>MIME</th>
                        <th>Статус</th>
                        <th>Дата загрузки</th>
                        <th>Комментарий</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $doc): ?>
                    <?php $isDeleted = !empty($doc['deleted_at']); ?>
                    <tr<?= $isDeleted ? ' style="opacity:0.6"' : '' ?>>
                        <td><?= e($doc['document_type']) ?><?php if ($isDeleted): ?> <span class="badge badge-warn">Удалён</span><?php endif; ?></td>
                        <td><?= e($doc['original_name']) ?></td>
                        <td class="col-num"><?= e($doc['file_size_formatted']) ?></td>
                        <td class="col-mono"><?= e($doc['mime_type']) ?></td>
                        <td>
                            <?php if ($isDeleted): ?>
                                <span class="badge badge-warn"><span class="dot"></span>Удалён</span>
                            <?php elseif ($doc['status'] === 'uploaded'): ?>
                                <span class="badge badge-ok"><span class="dot"></span>Загружен</span>
                            <?php else: ?>
                                <span class="badge"><span class="dot"></span><?= e($doc['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="col-muted"><?= e($doc['created_at']) ?></td>
                        <td class="col-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis"><?= e($doc['comments'] ?? '') ?></td>
                        <td class="col-actions">
                            <?php if (!$isDeleted): ?>
                            <a href="/company/documents/download?id=<?= $doc['id'] ?>" class="btn btn-toolbar">Скачать</a>
                            <a href="/company/documents/upload?entity_type=<?= e($entityType) ?>&entity_id=<?= $entityId ?>&replace=<?= $doc['id'] ?>" class="btn btn-toolbar">Заменить</a>
                            <form method="post" action="/company/documents/delete?id=<?= $doc['id'] ?>&redirect=<?= urlencode('/company/documents?entity_type=' . $entityType . '&entity_id=' . $entityId) ?>" style="display:inline" onsubmit="return confirm('Архивировать документ «<?= e(addslashes($doc['original_name'])) ?>»?')">
                                <button type="submit" class="btn btn-toolbar" style="color:var(--danger)">Архивировать</button>
                            </form>
                            <?php else: ?>
                            <span class="text-muted">Документ удалён</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>
