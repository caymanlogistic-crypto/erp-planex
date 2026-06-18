<?php if (!empty($missingEntityContext)): ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">КОМПАНИЯ<?= !empty($company['name']) ? ' / ' . e($company['name']) : '' ?></span>
        <span class="page-title">Загрузка документа</span>
    </div>
    <div class="page-head-actions">
        <a href="/company/dashboard" class="btn btn-ghost">← На главную</a>
        <a href="/company/contractors" class="btn btn-secondary">К справочникам</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p class="empty-title">Загрузка документа</p>
            <p class="empty-desc">Сначала откройте карточку объекта, к которому относится документ, и нажмите «Документы» или «Загрузить документ».</p>
            <p class="empty-desc">Например: подрядчик, водитель, транспортная единица, транспортный комплект, водитель + ТС, экипаж.</p>
            <a href="/company/contractors" class="btn btn-primary">К справочникам</a>
        </div>
    </div>
</div>

<?php elseif ($entityTypeError): ?>

<div class="page-head">
    <div>
        <h1>Загрузить документ</h1>
    </div>
</div>
<div class="notice warn">
    Не удалось открыть форму загрузки для выбранного объекта. Вернитесь в карточку объекта и откройте загрузку документа оттуда.
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

        <div class="kv mt-4">
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

        <div class="form-actions mt-4">
            <a href="/company/documents?entity_type=<?= e($entityType) ?>&entity_id=<?= $entityId ?>" class="btn btn-primary">&larr; К списку документов</a>
            <a href="/company/documents/upload?entity_type=<?= e($entityType) ?>&entity_id=<?= $entityId ?>" class="btn btn-ghost">Загрузить ещё</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <a href="/company/documents?entity_type=<?= e($entityType) ?>&entity_id=<?= $entityId ?>" class="btn btn-ghost back-action">&larr; Назад к документам</a>
        <h1><?= $replaceDocId > 0 ? 'Заменить документ' : 'Загрузить документ' ?></h1>
        <p class="text-muted"><?= e($entityLabel) ?> &laquo;<?= e($entityName) ?>&raquo; &bull; Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<?php if ($replacedDoc): ?>
    <div class="notice notice-compact">
        Замена файла <strong><?= e($replacedDoc['original_name']) ?></strong> (<?= e($replacedDoc['mime_type']) ?>, <?= e(formatFileSize($replacedDoc['file_size'])) ?>).
        Текущий файл будет заменён новым.
    </div>
<?php endif; ?>

<?php if ($formError): ?>
    <div class="notice warn notice-compact"><?= e($formError) ?></div>
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
                <label class="field-label">Тип документа</label>
                <?php if (!empty($docTypes)): ?>
                <select class="field-input" id="document_type_select" onchange="document.getElementById('document_type_text').value = this.value;">
                    <option value="">— Выберите тип или введите свой —</option>
                    <?php foreach ($docTypes as $dt): ?>
                    <option value="<?= e($dt['name']) ?>" <?= ($old['document_type'] ?? $replacedDoc['document_type'] ?? '') === $dt['name'] ? 'selected' : '' ?>>
                        <?= e($dt['name']) ?>
                        <?php if (($dt['category'] ?? '') === 'predefined'): ?> (системный)<?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="document_type" id="document_type_text" class="field-input" style="margin-top:0.5rem;"
                       value="<?= e($old['document_type'] ?? $replacedDoc['document_type'] ?? '') ?>"
                       placeholder="Или введите название типа вручную">
                <div class="field-msg">Выберите тип из списка или введите свой. <a href="/company/document-types/create" target="_blank">Создать новый тип</a></div>
                <?php else: ?>
                <input type="text" name="document_type" class="field-input"
                       value="<?= e($old['document_type'] ?? $replacedDoc['document_type'] ?? '') ?>"
                       placeholder="Например: Договор, Паспорт, СТС, Свидетельство">
                <div class="field-msg">Укажите тип документа (договор, паспорт, доверенность и т.д.)</div>
                <?php endif; ?>
                <?php if (!empty($errors['document_type'])): ?>
                    <div class="field-msg is-error"><?= e($errors['document_type']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Файл <span class="req">*</span></label>
                <input type="file" name="document_file" class="field-input" required
                       accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx">
                <div class="field-msg">
                    Допустимые форматы: PDF, JPG, PNG, WEBP, DOC, DOCX, XLS, XLSX. Максимальный размер: 10 МБ.
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
