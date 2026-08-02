<?php if (!empty($missingEntityContext)): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Документы</h1>
        <div class="page-summary"><span>Управление загруженными файлами</span></div>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/company/dashboard') ?>" class="btn btn-ghost">← На главную</a>
        <a href="<?= app_url('/company/contractors') ?>" class="btn btn-secondary">К справочникам</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p class="empty-title">Документы</p>
            <p class="empty-desc">Для просмотра документов откройте карточку подрядчика, водителя, транспортной единицы, транспортного комплекта, блока «Водитель + ТС» или экипажа.</p>
        </div>
        <div class="form-actions">
            <a href="<?= app_url('/company/contractors') ?>" class="btn btn-primary">К справочникам</a>
        </div>
    </div>
</div>

<?php elseif ($entityTypeError): ?>

<div class="notice warn">
    Не удалось открыть документы для выбранного объекта. Вернитесь в карточку объекта и откройте раздел «Документы» оттуда.
</div>

<?php elseif ($company === null): ?>

<div class="notice warn">
    Компания не найдена.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Документы: <?= e($entityName) ?></h1>
        <div class="page-summary"><span><?= e($entityLabel) ?> · Управление загруженными файлами</span></div>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Работа с документами недоступна.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Документы: <?= e($entityName) ?></h1>
        <div class="page-summary"><span><?= e($entityLabel) ?> · Управление загруженными файлами</span></div>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif ($entityNotFound): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Документы</h1>
        <div class="page-summary"><span>Управление загруженными файлами</span></div>
    </div>
</div>

<div class="notice warn">
    <?= e($entityLabel) ?> не найден. Проверьте, что сущность существует, и повторите попытку.
</div>

<?php elseif (empty($documents)): ?>

<div class="page-head">
    <div class="page-head-left">
        <a href="<?= e($backRoute) ?>" class="btn btn-ghost back-action">&larr; Назад к <?= e($entityLabelDative) ?></a>
        <h1 class="page-title">Документы: <?= e($entityName) ?></h1>
        <div class="page-summary"><span><?= e($entityLabel) ?> · Управление загруженными файлами</span></div>
    </div>
    <div class="page-head-actions">
        <a href="/company/documents/upload?entity_type=<?= e($entityType) ?>&entity_id=<?= $entityId ?>" class="btn btn-primary">Загрузить документ</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p class="empty-title">Документы не загружены.</p>
            <p class="empty-desc">Загрузите файлы для данной сущности через кнопку «Загрузить документ».</p>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <a href="<?= e($backRoute) ?>" class="btn btn-ghost back-action">&larr; Назад к <?= e($entityLabelDative) ?></a>
        <h1 class="page-title">Документы: <?= e($entityName) ?></h1>
        <div class="page-summary"><span><?= e($entityLabel) ?> · Управление загруженными файлами</span></div>
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
                        <th>Документ</th>
                        <th>Файл</th>
                        <th>Параметры</th>
                        <th>Статус</th>
                        <th>Дата загрузки</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $doc): ?>
                    <?php $isDeleted = !empty($doc['deleted_at']); ?>
                    <tr class="<?= $isDeleted ? 'is-muted-row' : '' ?>">
                        <td class="cell-double">
                            <span class="cell-main"><?= e($doc['document_type']) ?><?php if ($isDeleted): ?> <span class="badge badge-warn">Удалён</span><?php endif; ?></span>
                            <span class="cell-sub"><?= e($doc['comments'] ?? '') ?: 'Комментарий не указан' ?></span>
                        </td>
                        <td class="cell-double">
                            <span class="cell-main"><?= e($doc['original_name']) ?></span>
                            <span class="cell-sub col-mono"><?= e($doc['mime_type']) ?></span>
                        </td>
                        <td class="col-num"><?= e($doc['file_size_formatted']) ?></td>
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
                        <td class="col-actions">
                            <?php if (!$isDeleted): ?>
                            <a href="/company/documents/download?id=<?= $doc['id'] ?>" class="btn btn-toolbar">Скачать</a>
                            <a href="/company/documents/upload?entity_type=<?= e($entityType) ?>&entity_id=<?= $entityId ?>&replace=<?= $doc['id'] ?>" class="btn btn-toolbar">Заменить</a>
                            <form method="post" action="<?= app_url('/company/documents/delete') ?>" class="inline-form" onsubmit="return confirm('Удалить документ «<?= e(addslashes($doc['original_name'])) ?>»?')">
                                <input type="hidden" name="id" value="<?= $doc['id'] ?>">
                                <input type="hidden" name="entity_type" value="<?= e($entityType) ?>">
                                <input type="hidden" name="entity_id" value="<?= $entityId ?>">
                                <button type="submit" class="btn btn-toolbar text-danger">Удалить</button>
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
