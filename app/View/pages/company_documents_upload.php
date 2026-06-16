<?php if ($entityTypeError): ?>

<div class="page-head">
    <div>
        <h1>Загрузить документ</h1>
    </div>
</div>
<div class="notice warn">
    Неизвестный тип сущности «<?= e($entityType) ?>». Допустимые типы: client, contractor, driver, vehicle, crew.
</div>

<?php elseif ($company === null): ?>

<div class="page-head">
    <div>
        <h1>Загрузить документ</h1>
    </div>
</div>
<div class="notice warn">
    Компания не найдена.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Загрузить документ</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
</div>
<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Загрузка документов недоступна.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Загрузить документ</h1>
    </div>
</div>
<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif ($entityNotFound): ?>

<div class="page-head">
    <div>
        <h1>Загрузить документ</h1>
    </div>
</div>
<div class="notice warn">
    <?= e($entityLabel) ?> не найден. Проверьте, что сущность существует, и повторите попытку.
</div>

<?php elseif ($success): ?>

<div class="page-head">
    <div>
        <h1>Документ загружен</h1>
        <p class="text-muted"><?= e($entityLabel) ?> &laquo;<?= e($entityName) ?>&raquo; &bull; Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/documents?entity_type=<?= e($entityType) ?>&entity_id=<?= $entityId ?>" class="btn btn-primary">&larr; К списку документов</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Документ успешно загружен.
        </div>

        <div class="kv" style="margin-top:16px">
            <div class="kv-row">
                <span class="kv-key">Тип документа</span>
                <span class="kv-value"><?= e($createdDoc['document_type']) ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Имя файла</span>
                <span class="kv-value"><?= e($createdDoc['original_name']) ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Размер</span>
                <span class="kv-value"><?= e($createdDoc['file_size_formatted']) ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">MIME</span>
                <span class="kv-value"><?= e($createdDoc['mime_type']) ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Статус</span>
                <span class="kv-value">
                    <span class="badge badge-ok"><span class="dot"></span>Загружен</span>
                </span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Сущность</span>
                <span class="kv-value"><?= e($entityLabel) ?> &laquo;<?= e($entityName) ?>&raquo;</span>
            </div>
            <?php if (!empty($createdDoc['comments'])): ?>
            <div class="kv-row">
                <span class="kv-key">Комментарий</span>
                <span class="kv-value"><?= e($createdDoc['comments']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <div class="form-actions" style="margin-top:16px">
            <a href="/company/documents?entity_type=<?= e($entityType) ?>&entity_id=<?= $entityId ?>" class="btn btn-primary">&larr; К списку документов</a>
            <a href="/company/documents/upload?entity_type=<?= e($entityType) ?>&entity_id=<?= $entityId ?>" class="btn btn-ghost">Загрузить ещё</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <a href="/company/documents?entity_type=<?= e($entityType) ?>&entity_id=<?= $entityId ?>" class="btn btn-ghost" style="margin-bottom:4px">&larr; Назад к документам</a>
        <h1><?= $replaceDocId > 0 ? 'Заменить документ' : 'Загрузить документ' ?></h1>
        <p class="text-muted"><?= e($entityLabel) ?> &laquo;<?= e($entityName) ?>&raquo; &bull; Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<?php if ($replacedDoc): ?>
    <div class="notice" style="margin-bottom:8px;background:var(--surface-strong);border:1px solid var(--border)">
        Замена файла <strong><?= e($replacedDoc['original_name']) ?></strong> (<?= e($replacedDoc['mime_type']) ?>, <?= e(formatFileSize($replacedDoc['file_size'])) ?>).
        Текущий файл будет заменён новым.
    </div>
<?php endif; ?>

<?php if ($formError): ?>
    <div class="notice warn" style="margin-bottom:8px"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post"
      action="/company/documents/<?= $replaceDocId > 0 ? 'replace' : 'upload' ?>?entity_type=<?= e($entityType) ?>&entity_id=<?= $entityId ?>"
      enctype="multipart/form-data"
      class="panel">
    <div class="panel-body">

        <?php if ($replaceDocId > 0): ?>
            <input type="hidden" name="replace_doc_id" value="<?= $replaceDocId ?>">
            <input type="hidden" name="redirect" value="/company/documents?entity_type=<?= e($entityType) ?>&entity_id=<?= $entityId ?>">
        <?php endif; ?>

        <div class="form-section">
            <h3 class="panel-head-title">Документ</h3>

            <div class="field">
                <label class="field-label">Тип документа <span class="req">*</span></label>
                <input type="text" name="document_type" class="field-input"
                       value="<?= e($old['document_type'] ?? $replacedDoc['document_type'] ?? '') ?>"
                       placeholder="Например: Договор, Паспорт, СТС, Свидетельство">
                <div class="field-msg">Укажите тип документа (договор, паспорт, доверенность и т.д.)</div>
                <?php if (!empty($errors['document_type'])): ?>
                    <div class="field-msg is-error"><?= e($errors['document_type']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Файл <span class="req">*</span></label>
                <input type="file" name="document_file" class="field-input" required
                       accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                <div class="field-msg">
                    Допустимые форматы: PDF, JPG, PNG, DOC, DOCX, XLS, XLSX. Максимальный размер: 10 МБ.
                </div>
                <?php if (!empty($errors['document_file'])): ?>
                    <div class="field-msg is-error"><?= e($errors['document_file']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Дополнительно</h3>

            <div class="field">
                <label class="field-label">Комментарий</label>
                <textarea name="comments" class="field-textarea" rows="3"
                          placeholder="Примечание к документу (необязательно)"><?= e($old['comments'] ?? '') ?></textarea>
                <div class="field-msg">Необязательное поле. Например: &laquo;Скан договора за 2025 год&raquo;.</div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $replaceDocId > 0 ? 'Заменить' : 'Загрузить' ?></button>
            <a href="/company/documents?entity_type=<?= e($entityType) ?>&entity_id=<?= $entityId ?>" class="btn btn-ghost">&larr; Назад к документам</a>
        </div>

    </div>
</form>

<?php endif; ?>
