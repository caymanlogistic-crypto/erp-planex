<?php if ($entityTypeError): ?>

<div class="notice warn">
    Неизвестный тип сущности «<?= e($entityType) ?>». Допустимые типы: client, contractor, driver, vehicle, crew.
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
                    <tr>
                        <td><?= e($doc['document_type']) ?></td>
                        <td><?= e($doc['original_name']) ?></td>
                        <td class="col-num"><?= e($doc['file_size_formatted']) ?></td>
                        <td class="col-mono"><?= e($doc['mime_type']) ?></td>
                        <td>
                            <span class="badge<?= $doc['status'] === 'uploaded' ? ' badge-ok' : '' ?>">
                                <span class="dot"></span>
                                <?= $doc['status'] === 'uploaded' ? 'Загружен' : e($doc['status']) ?>
                            </span>
                        </td>
                        <td class="col-muted"><?= e($doc['created_at']) ?></td>
                        <td class="col-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis"><?= e($doc['comments'] ?? '') ?></td>
                        <td class="col-actions">
                            <a href="/company/documents/download?id=<?= $doc['id'] ?>" class="btn btn-toolbar">Скачать</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>
