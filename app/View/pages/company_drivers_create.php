<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">ПОДРЯДЧИКИ / <?= e($company['name']) ?></span>
        <h1 class="page-title">Создать водителя</h1>
    </div>
    <div class="page-head-actions">
        <a href="/company/drivers" class="btn btn-secondary">← К списку</a>
    </div>
</div>

<div class="page-content">
    <div class="form-alert alert-warning">
        <div class="alert-mark">
            <svg width="11" height="11" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="7" stroke="currentColor" stroke-width="1.6"/><path d="M9 6V10M9 12V12.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        </div>
        <div class="alert-body">
            <div class="alert-body-title">Компания неактивна</div>
            <div class="alert-body-sub">Статус компании: «<?= e($company['status']) ?>». Создание водителей недоступно.</div>
        </div>
    </div>
</div>

<?php elseif ($success): ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">ПОДРЯДЧИКИ / <?= e($company['name']) ?></span>
        <h1 class="page-title">Водитель создан</h1>
    </div>
    <div class="page-head-actions">
        <a href="/company/drivers/create" class="btn btn-secondary">Создать ещё</a>
        <a href="/company/drivers" class="btn btn-primary">← К списку</a>
    </div>
</div>

<div class="page-content">
    <div class="panel">
        <div class="panel-body">

            <div class="form-alert alert-success">
                <div class="alert-mark">
                    <svg width="11" height="11" viewBox="0 0 18 18" fill="none"><path d="M4 9.5L7 12.5L14 5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <div class="alert-body">
                    <div class="alert-body-title">Водитель успешно создан</div>
                    <?php if (!empty($uploadedDocs)): ?>
                        <div class="alert-body-sub">Загружено документов: <?= count($uploadedDocs) ?>.</div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($docErrors)): ?>
                <div class="form-alert alert-warning">
                    <div class="alert-mark">
                        <svg width="11" height="11" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="7" stroke="currentColor" stroke-width="1.6"/><path d="M9 6V10M9 12V12.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    </div>
                    <div class="alert-body">
                        <div class="alert-body-title">Некоторые документы не загружены</div>
                        <div class="alert-body-sub"><?= implode(', ', array_map('e', $docErrors)) ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-section">
                <div class="section-title">Данные водителя</div>
                <dl class="dl">
                    <dt>ФИО</dt>
                    <dd><?= e($createdDriver['full_name']) ?></dd>
                    <dt>Телефон</dt>
                    <dd class="mono"><?= e($createdDriver['phone']) ?></dd>
                    <?php if (!empty($createdDriver['extra_phones'] ?? [])): ?>
                    <dt>Доп. телефоны</dt>
                    <dd>
                        <?php foreach ($createdDriver['extra_phones'] as $ep): ?>
                            <div class="mono"><?= e($ep['phone']) ?><?= !empty($ep['comment']) ? ' (' . e($ep['comment']) . ')' : '' ?></div>
                        <?php endforeach; ?>
                    </dd>
                    <?php endif; ?>
                    <?php if (!empty($createdDriver['passport_number'])): ?>
                    <dt>Паспорт</dt>
                    <dd><?= e($createdDriver['passport_number']) ?><?= !empty($createdDriver['passport_issued_by']) ? ' · ' . e($createdDriver['passport_issued_by']) : '' ?><?= !empty($createdDriver['passport_issue_date']) ? ' · ' . e($createdDriver['passport_issue_date']) : '' ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($createdDriver['snils'])): ?>
                    <dt>СНИЛС</dt>
                    <dd class="mono"><?= e($createdDriver['snils']) ?></dd>
                    <?php endif; ?>
                    <dt>Номер ВУ</dt>
                    <dd class="mono"><?= e($createdDriver['license_number'] ?? '—') ?></dd>
                    <dt>Статус</dt>
                    <dd><span class="badge badge-ok">Активен</span></dd>
                </dl>
            </div>

        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">ПОДРЯДЧИКИ / <?= e($company['name']) ?></span>
        <h1 class="page-title">Создать водителя</h1>
    </div>
    <div class="page-head-actions">
        <a href="/company/drivers" class="btn btn-secondary">← К списку</a>
    </div>
</div>

<div class="page-content">

<form method="post" action="/company/drivers/create" class="panel" enctype="multipart/form-data">
    <div class="panel-body">

        <?php if ($formError): ?>
        <div class="form-alert alert-error">
            <div class="alert-mark">
                <svg width="11" height="11" viewBox="0 0 18 18" fill="none"><path d="M9 2L16.5 15H1.5L9 2Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 7V11M9 13V13.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            </div>
            <div class="alert-body">
                <div class="alert-body-title">Ошибка при сохранении</div>
                <div class="alert-body-sub"><?= e($formError) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ─── Основные данные ─── -->
        <div class="form-section">
            <div class="section-title">Основные данные</div>

            <div class="field<?= !empty($errors['full_name']) ? ' is-error' : '' ?>">
                <label class="field-label">ФИО <span class="req">*</span></label>
                <input type="text" name="full_name" class="field-input"
                       placeholder="Фамилия Имя Отчество"
                       value="<?= e($old['full_name'] ?? '') ?>">
                <div class="field-msg"><?= !empty($errors['full_name']) ? e($errors['full_name']) : '' ?></div>
            </div>

            <div class="field<?= !empty($errors['phone']) ? ' is-error' : '' ?>">
                <label class="field-label">Телефон <span class="req">*</span></label>
                <input type="text" name="phone" class="field-input"
                       placeholder="+7 900 000-00-00"
                       value="<?= e($old['phone'] ?? '') ?>">
                <div class="field-msg"><?= !empty($errors['phone']) ? e($errors['phone']) : '' ?></div>
            </div>
        </div>

        <!-- ─── Дополнительные телефоны ─── -->
        <div class="form-section">
            <div class="section-title">Дополнительные телефоны</div>
            <div class="field-msg" style="margin-bottom:6px;">Добавьте дополнительные контактные номера водителя</div>

            <div id="extra-phones-container"></div>

            <button type="button" class="btn btn-ghost" id="add-extra-phone-btn">+ Добавить телефон</button>
        </div>

        <!-- ─── Паспортные данные ─── -->
        <div class="form-section">
            <div class="section-title">Паспортные данные</div>

            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label">Серия и номер</label>
                    <input type="text" name="passport_number" class="field-input"
                           placeholder="00 00 000000"
                           value="<?= e($old['passport_number'] ?? '') ?>">
                    <div class="field-msg"></div>
                </div>

                <div class="field">
                    <label class="field-label">Код подразделения</label>
                    <input type="text" name="passport_department_code" class="field-input"
                           placeholder="000-000"
                           value="<?= e($old['passport_department_code'] ?? '') ?>">
                    <div class="field-msg"></div>
                </div>
            </div>

            <div class="field">
                <label class="field-label">Кем выдан</label>
                <input type="text" name="passport_issued_by" class="field-input"
                       placeholder="Орган, выдавший паспорт"
                       value="<?= e($old['passport_issued_by'] ?? '') ?>">
                <div class="field-msg"></div>
            </div>

            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label">Дата выдачи</label>
                    <input type="date" name="passport_issue_date" class="field-input"
                           value="<?= e($old['passport_issue_date'] ?? '') ?>">
                    <div class="field-msg"></div>
                </div>

                <div class="field">
                    <label class="field-label">СНИЛС</label>
                    <input type="text" name="snils" class="field-input"
                           placeholder="000-000-000 00"
                           value="<?= e($old['snils'] ?? '') ?>">
                    <div class="field-msg"></div>
                </div>
            </div>
        </div>

        <!-- ─── Водительское удостоверение ─── -->
        <div class="form-section">
            <div class="section-title">Водительское удостоверение</div>

            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label">Номер ВУ</label>
                    <input type="text" name="license_number" class="field-input"
                           placeholder="00 00 000000"
                           value="<?= e($old['license_number'] ?? '') ?>">
                    <div class="field-msg"></div>
                </div>

                <div class="field">
                    <label class="field-label">Дата выдачи</label>
                    <input type="date" name="license_issue_date" class="field-input"
                           value="<?= e($old['license_issue_date'] ?? '') ?>">
                    <div class="field-msg"></div>
                </div>
            </div>
        </div>

        <!-- ─── Дополнительно ─── -->
        <div class="form-section">
            <div class="section-title">Дополнительно</div>

            <div class="field">
                <label class="field-label">Комментарий</label>
                <textarea name="comments" class="field-textarea" rows="3"
                          placeholder="Примечания по водителю..."><?= e($old['comments'] ?? '') ?></textarea>
                <div class="field-msg"></div>
            </div>
        </div>

        <!-- ─── Предопределённые документы ─── -->
        <?php
        $predefDocs = [
            ['name' => 'Паспорт',                 'code' => 'passport',       'ext' => 'PDF'],
            ['name' => 'Водительское удостоверение','code' => 'driver_license','ext' => 'PDF'],
            ['name' => 'СНИЛС',                    'code' => 'snils',          'ext' => 'PDF'],
        ];
        ?>
        <div class="form-section">
            <div class="section-title">Документы</div>
            <div class="field-msg" style="margin-bottom:6px;">Загрузите сейчас или позже в карточке водителя · PDF, JPG, PNG, WEBP</div>

            <div class="file-list">
                <?php foreach ($predefDocs as $pdoc): ?>
                <div class="file-item" id="frow-<?= $pdoc['code'] ?>">
                    <div class="file-type-badge"><?= $pdoc['ext'] ?></div>
                    <div class="file-info">
                        <div class="file-name"><?= e($pdoc['name']) ?></div>
                        <div class="file-meta" id="fname-<?= $pdoc['code'] ?>">Файл не выбран</div>
                    </div>
                    <label class="btn btn-secondary" style="cursor:default;">
                        Выбрать файлы
                        <input type="file"
                               class="file-input-hidden"
                               name="predef_doc[<?= $pdoc['code'] ?>][]"
                               multiple
                               accept=".pdf,.jpg,.jpeg,.png,.webp"
                               data-label="fname-<?= $pdoc['code'] ?>"
                               onchange="erpFileMultiSelect(this)">
                    </label>
                    <input type="hidden" name="predef_doc_type[<?= $pdoc['code'] ?>]" value="<?= e($pdoc['name']) ?>">
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ─── Произвольные документы ─── -->
        <div class="form-section">
            <div class="section-title">Произвольные документы</div>
            <div class="field-msg" style="margin-bottom:6px;">
                Дополнительные документы с указанием типа.
                <a href="/company/document-types/create" target="_blank" style="color:var(--accent);text-decoration:none;font-weight:600;">Создать новый тип</a>
            </div>

            <div class="file-list" id="custom-docs-container"></div>

            <button type="button" class="btn btn-ghost" id="add-custom-doc-btn" style="align-self:flex-start;">+ Добавить документ</button>
        </div>

        <!-- ─── Действия ─── -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Создать водителя</button>
        </div>

    </div>
</form>

</div><!-- /.page-content -->

<!-- JS: обновление имени файла и динамические строки документов -->
<script>
(function () {

    /* Показывает имя выбранного файла */
    window.erpFileSelect = function (input) {
        var labelId = input.getAttribute('data-label');
        var el = labelId ? document.getElementById(labelId) : null;
        if (el) {
            el.textContent = input.files.length ? input.files[0].name : 'Файл не выбран';
        }
    };

    /* Показывает количество выбранных файлов (для multiple) */
    window.erpFileMultiSelect = function (input) {
        var labelId = input.getAttribute('data-label');
        var el = labelId ? document.getElementById(labelId) : null;
        if (el) {
            var n = input.files.length;
            if (n === 0) {
                el.textContent = 'Файл не выбран';
            } else if (n === 1) {
                el.textContent = input.files[0].name;
            } else {
                el.textContent = 'Выбрано ' + n + ' файлов';
            }
        }
    };

    /* Кастомные строки документов */
    var container = document.getElementById('custom-docs-container');
    var addBtn    = document.getElementById('add-custom-doc-btn');
    if (!container || !addBtn) return;

    var docTypes = <?= json_encode($docTypes ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;

    function buildTypeOptions() {
        var html = '<option value="">— Выберите тип —</option>';
        for (var i = 0; i < docTypes.length; i++) {
            var n = docTypes[i].name.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            html += '<option value="' + n + '" data-id="' + docTypes[i].id + '">' + n + '</option>';
        }
        return html;
    }

    function buildRow() {
        var row = document.createElement('div');
        row.className = 'file-item';
        row.style.cssText = 'grid-template-columns: 1fr 1fr auto auto;';

        var idx = container.children.length;
        var fnId = 'cname-' + idx;

        row.innerHTML =
            '<select name="custom_doc_type[]" class="field-select" style="height:var(--control-h);border:none;box-shadow:none;background:transparent;padding:0 26px 0 0;">' + buildTypeOptions() + '</select>' +
            '<div class="file-info"><div class="file-name" id="' + fnId + '">Файл не выбран</div></div>' +
            '<label class="btn btn-secondary" style="cursor:default;">Выбрать файл' +
                '<input type="file" class="file-input-hidden" name="custom_doc_file[]" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx" data-label="' + fnId + '" onchange="erpFileSelect(this)">' +
            '</label>' +
            '<button type="button" class="btn btn-ghost" title="Удалить строку" style="width:30px;padding:0;flex-shrink:0;">✕</button>';

        row.querySelector('[title="Удалить строку"]').addEventListener('click', function () {
            container.removeChild(row);
        });

        container.appendChild(row);
    }

    addBtn.addEventListener('click', function (e) {
        e.preventDefault();
        buildRow();
    });

    /* Дополнительные телефоны */
    var phonesContainer = document.getElementById('extra-phones-container');
    var addPhoneBtn    = document.getElementById('add-extra-phone-btn');
    if (phonesContainer && addPhoneBtn) {
        function buildPhoneRow() {
            var row = document.createElement('div');
            row.className = 'form-grid-2';
            row.style.cssText = 'align-items:center;gap:var(--gap);margin-bottom:8px;';
            var idx = phonesContainer.children.length;
            row.innerHTML =
                '<div class="field" style="margin-bottom:0;"><input type="text" name="extra_phones[]" class="field-input" placeholder="+7 900 000-00-00"></div>' +
                '<div style="display:flex;align-items:center;gap:var(--gap);"><div class="field" style="margin-bottom:0;flex:1;"><input type="text" name="extra_phone_comments[]" class="field-input" placeholder="Комментарий"></div>' +
                '<button type="button" class="btn btn-ghost" title="Удалить" style="width:30px;padding:0;flex-shrink:0;">✕</button></div>';
            row.querySelector('[title="Удалить"]').addEventListener('click', function () {
                phonesContainer.removeChild(row);
            });
            phonesContainer.appendChild(row);
        }
        addPhoneBtn.addEventListener('click', function (e) {
            e.preventDefault();
            buildPhoneRow();
        });
    }

})();
</script>

<?php endif; ?>
