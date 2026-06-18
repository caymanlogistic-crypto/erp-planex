<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Создать перевозчика</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Создание перевозчиков недоступно.
</div>

<?php elseif ($success): ?>

<div class="page-head">
    <div>
        <h1>Перевозчик создан</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors" class="btn btn-primary">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Перевозчик успешно создан.
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
                <span class="kv-key">Наименование</span>
                <span class="kv-value"><?= e($createdContractor['name']) ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">ИНН</span>
                <span class="kv-value"><code><?= e($createdContractor['inn']) ?></code></span>
            </div>
            <?php if (!empty($createdContractor['contractor_type'])): ?>
            <div class="kv-row">
                <span class="kv-key">Тип</span>
                <span class="kv-value"><?= e(ui_contractor_type($createdContractor['contractor_type'])) ?></span>
            </div>
            <?php endif; ?>
            <div class="kv-row">
                <span class="kv-key">Статус</span>
                <span class="kv-value">Активен</span>
            </div>
        </div>

        <div class="form-actions mt-4">
            <a href="/company/contractors" class="btn btn-primary">← К списку перевозчиков</a>
            <a href="/company/contractors/create" class="btn btn-ghost">Создать ещё</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Создать перевозчика</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<?php if ($formError): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/company/contractors/create" class="panel" enctype="multipart/form-data">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Основные данные</h3>

            <div class="field">
                <label class="field-label">Наименование <span class="req">*</span></label>
                <input type="text" name="name" class="field-input" required
                       value="<?= e($old['name'] ?? '') ?>">
                <?php if (!empty($errors['name'])): ?>
                    <div class="field-msg is-error"><?= e($errors['name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label">ИНН <span class="req">*</span></label>
                    <input type="text" name="inn" class="field-input"
                           value="<?= e($old['inn'] ?? '') ?>">
                    <?php if (!empty($errors['inn'])): ?>
                        <div class="field-msg is-error"><?= e($errors['inn']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label class="field-label">КПП</label>
                    <input type="text" name="kpp" class="field-input"
                           value="<?= e($old['kpp'] ?? '') ?>">
                </div>
            </div>

            <div class="field">
                <label class="field-label">ОГРН</label>
                <input type="text" name="ogrn" class="field-input"
                       value="<?= e($old['ogrn'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Тип перевозчика</label>
                <select name="contractor_type" class="field-input">
                    <option value="">— Не указан —</option>
                    <option value="legal_entity" <?= ($old['contractor_type'] ?? '') === 'legal_entity' ? 'selected' : '' ?>>Юридическое лицо</option>
                    <option value="individual" <?= ($old['contractor_type'] ?? '') === 'individual' ? 'selected' : '' ?>>Индивидуальный предприниматель</option>
                    <option value="self_employed" <?= ($old['contractor_type'] ?? '') === 'self_employed' ? 'selected' : '' ?>>Самозанятый</option>
                    <option value="private_person" <?= ($old['contractor_type'] ?? '') === 'private_person' ? 'selected' : '' ?>>Физическое лицо</option>
                </select>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Адреса</h3>

            <div class="field">
                <label class="field-label">Юридический адрес</label>
                <textarea name="legal_address" class="field-textarea" rows="2"><?= e($old['legal_address'] ?? '') ?></textarea>
            </div>

            <div class="field">
                <label class="field-label">Фактический адрес</label>
                <textarea name="physical_address" class="field-textarea" rows="2"><?= e($old['physical_address'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Контакты</h3>

            <div class="field">
                <label class="field-label">Контактное лицо</label>
                <input type="text" name="contact_person" class="field-input"
                       value="<?= e($old['contact_person'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Телефон</label>
                <input type="text" name="contact_phone" class="field-input"
                       value="<?= e($old['contact_phone'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Email</label>
                <input type="email" name="contact_email" class="field-input"
                       value="<?= e($old['contact_email'] ?? '') ?>">
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Банковские реквизиты</h3>

            <div class="field">
                <label class="field-label">Расчётный счёт</label>
                <input type="text" name="bank_account" class="field-input"
                       value="<?= e($old['bank_account'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Банк</label>
                <input type="text" name="bank_name" class="field-input"
                       value="<?= e($old['bank_name'] ?? '') ?>">
            </div>

            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label">БИК</label>
                    <input type="text" name="bank_bik" class="field-input"
                           value="<?= e($old['bank_bik'] ?? '') ?>">
                </div>

                <div class="field">
                    <label class="field-label">Корр. счёт</label>
                    <input type="text" name="bank_corr_account" class="field-input"
                           value="<?= e($old['bank_corr_account'] ?? '') ?>">
                </div>
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
        // Predefined documents for contractor
        $predefDocs = [
            ['name' => 'Карточка предприятия', 'code' => 'company_card'],
            ['name' => 'Свидетельство ИНН', 'code' => 'inn_cert'],
            ['name' => 'Свидетельство ОГРН', 'code' => 'ogrn_cert'],
            ['name' => 'Договор', 'code' => 'contract'],
        ];
        if (!empty($predefDocs)):
        ?>
        <div class="form-section">
            <h3 class="panel-head-title">Предопределённые документы</h3>
            <p class="field-hint">Загрузите ожидаемые документы. Можно загрузить сейчас или позже в карточке перевозчика.</p>

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
            <button type="submit" class="btn btn-primary">Создать перевозчика</button>
            <a href="/company/contractors" class="btn btn-ghost">Отмена</a>
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
