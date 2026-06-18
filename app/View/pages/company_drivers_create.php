<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Создать водителя</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/drivers" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Создание водителей недоступно.
</div>

<?php elseif ($success): ?>

<div class="page-head">
    <div>
        <h1>Водитель создан</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/drivers" class="btn btn-primary">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Водитель успешно создан.
            <?php if (!empty($uploadedDocs)): ?>
                <br>Загружено документов: <?= count($uploadedDocs) ?>.
            <?php endif; ?>
        </div>

        <?php if (!empty($docErrors)): ?>
            <div class="notice warn mt-4">
                <strong>Некоторые документы не были загружены:</strong>
                <ul style="margin:0.5rem 0 0 1.2rem;">
                    <?php foreach ($docErrors as $de): ?>
                        <li><?= e($de) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="kv mt-4">
            <div class="kv-row">
                <span class="kv-key">ФИО</span>
                <span class="kv-value"><?= e($createdDriver['full_name']) ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Телефон</span>
                <span class="kv-value"><code><?= e($createdDriver['phone']) ?></code></span>
            </div>
            <?php if (!empty($createdDriver['passport_number'])): ?>
            <div class="kv-row">
                <span class="kv-key">Паспорт</span>
                <span class="kv-value"><?= e($createdDriver['passport_number']) ?> / <?= e($createdDriver['passport_issued_by'] ?? '—') ?> / <?= e($createdDriver['passport_issue_date'] ?? '—') ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($createdDriver['snils'])): ?>
            <div class="kv-row">
                <span class="kv-key">СНИЛС</span>
                <span class="kv-value"><?= e($createdDriver['snils']) ?></span>
            </div>
            <?php endif; ?>
            <div class="kv-row">
                <span class="kv-key">Номер ВУ</span>
                <span class="kv-value"><?= e($createdDriver['license_number'] ?? '—') ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Категория ВУ</span>
                <span class="kv-value"><?= e($createdDriver['license_category'] ?? '—') ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Статус</span>
                <span class="kv-value">Активен</span>
            </div>
        </div>

        <div class="form-actions mt-4">
            <a href="/company/drivers" class="btn btn-primary">← К списку водителей</a>
            <a href="/company/drivers/create" class="btn btn-ghost">Создать ещё</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Создать водителя</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/drivers" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<?php if ($formError): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/company/drivers/create" class="panel" enctype="multipart/form-data">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Основные данные</h3>

            <div class="field">
                <label class="field-label">ФИО <span class="req">*</span></label>
                <input type="text" name="full_name" class="field-input" required
                       value="<?= e($old['full_name'] ?? '') ?>">
                <?php if (!empty($errors['full_name'])): ?>
                    <div class="field-msg is-error"><?= e($errors['full_name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Телефон <span class="req">*</span></label>
                <input type="text" name="phone" class="field-input"
                       value="<?= e($old['phone'] ?? '') ?>">
                <?php if (!empty($errors['phone'])): ?>
                    <div class="field-msg is-error"><?= e($errors['phone']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Паспортные данные</h3>

            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label">Серия и номер</label>
                    <input type="text" name="passport_number" class="field-input"
                           value="<?= e($old['passport_number'] ?? '') ?>">
                </div>

                <div class="field">
                    <label class="field-label">Код подразделения</label>
                    <input type="text" name="passport_department_code" class="field-input"
                           value="<?= e($old['passport_department_code'] ?? '') ?>">
                </div>
            </div>

            <div class="field">
                <label class="field-label">Кем выдан</label>
                <input type="text" name="passport_issued_by" class="field-input"
                       value="<?= e($old['passport_issued_by'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Дата выдачи</label>
                <input type="date" name="passport_issue_date" class="field-input"
                       value="<?= e($old['passport_issue_date'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">СНИЛС</label>
                <input type="text" name="snils" class="field-input"
                       value="<?= e($old['snils'] ?? '') ?>">
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Водительское удостоверение</h3>

            <div class="field">
                <label class="field-label">Номер ВУ</label>
                <input type="text" name="license_number" class="field-input"
                       value="<?= e($old['license_number'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Категория</label>
                <input type="text" name="license_category" class="field-input"
                       value="<?= e($old['license_category'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Дата выдачи</label>
                <input type="date" name="license_issue_date" class="field-input"
                       value="<?= e($old['license_issue_date'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Дата окончания</label>
                <input type="date" name="license_expire_date" class="field-input"
                       value="<?= e($old['license_expire_date'] ?? '') ?>">
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Дополнительно</h3>

            <div class="field">
                <label class="field-label">Комментарий</label>
                <textarea name="comments" class="field-textarea" rows="3"><?= e($old['comments'] ?? '') ?></textarea>
            </div>
        </div>

        <?php
        // Predefined documents for driver
        $predefDocs = [
            ['name' => 'Паспорт', 'code' => 'passport'],
            ['name' => 'Водительское удостоверение', 'code' => 'driver_license'],
            ['name' => 'СНИЛС', 'code' => 'snils'],
        ];
        if (!empty($predefDocs)):
        ?>
        <div class="form-section">
            <h3 class="panel-head-title">Предопределённые документы</h3>
            <p class="field-hint">Загрузите ожидаемые документы. Можно загрузить сейчас или позже в карточке водителя.</p>

            <?php foreach ($predefDocs as $pdoc): ?>
            <div class="doc-upload-row" style="display:flex; gap:0.75rem; align-items:center; margin-bottom:0.75rem;">
                <span style="min-width:200px; font-size:13px;"><?= e($pdoc['name']) ?></span>
                <input type="file" name="predef_doc[<?= $pdoc['code'] ?>]" accept=".pdf,.jpg,.jpeg,.png" class="field-input" style="flex:1;">
                <input type="hidden" name="predef_doc_type[<?= $pdoc['code'] ?>]" value="<?= e($pdoc['name']) ?>">
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Custom documents block -->
        <div class="form-section">
            <h3 class="panel-head-title">Произвольные документы</h3>
            <p class="field-hint">Добавьте дополнительные документы с указанием типа. <a href="/company/document-types/create" target="_blank">Создать новый тип</a></p>

            <div id="custom-docs-container"></div>

            <button type="button" class="btn btn-ghost btn-sm" id="add-custom-doc-btn">+ Добавить документ</button>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Создать водителя</button>
        </div>

    </div>
</form>

<!-- DOC_JS: custom document rows -->
<script>
(function() {
    var container = document.getElementById('custom-docs-container');
    var addBtn = document.getElementById('add-custom-doc-btn');
    if (!container || !addBtn) return;

    var docTypes = <?= json_encode($docTypes ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;

    function buildSelect(name) {
        var s = '<select name="' + name + '" class="field-input" style="flex:1;"><option value="">— Выберите тип —</option>';
        for (var i = 0; i < docTypes.length; i++) {
            s += '<option value="' + docTypes[i].name.replace(/"/g, '&quot;') + '" data-id="' + docTypes[i].id + '">' + docTypes[i].name.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</option>';
        }
        s += '</select>';
        return s;
    }

    function buildRow() {
        var idx = container.children.length;
        var div = document.createElement('div');
        div.className = 'custom-doc-row';
        div.style.cssText = 'display:flex; gap:0.75rem; align-items:flex-start; margin-bottom:0.75rem;';
        div.innerHTML =
            buildSelect('custom_doc_type[]') +
            '<input type="text" name="custom_doc_type_new[]" class="field-input" style="flex:1;" placeholder="Или новый тип...">' +
            '<input type="file" name="custom_doc_file[]" class="field-input" style="flex:2;" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">' +
            '<button type="button" class="btn btn-ghost btn-sm remove-custom-doc" title="Удалить строку">&times;</button>';
        container.appendChild(div);

        div.querySelector('.remove-custom-doc').addEventListener('click', function() {
            div.parentNode.removeChild(div);
        });
    }

    addBtn.addEventListener('click', function(e) {
        e.preventDefault();
        buildRow();
    });
})();
</script>

<?php endif; ?>
