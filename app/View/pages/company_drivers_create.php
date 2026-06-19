<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">ПОДРЯДЧИКИ / <?= e(mb_strtoupper($company['name'])) ?></span>
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
        <span class="page-eyebrow">ПОДРЯДЧИКИ / <?= e(mb_strtoupper($company['name'])) ?></span>
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
                    <?php if (!empty($createdDriver['email'])): ?>
                    <dt>Email</dt>
                    <dd><?= e($createdDriver['email']) ?></dd>
                    <?php endif; ?>
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
        <span class="page-eyebrow">ПОДРЯДЧИКИ / <?= e(mb_strtoupper($company['name'])) ?></span>
        <h1 class="page-title">Создать водителя</h1>
    </div>
    <div class="page-head-actions">
        <a href="/company/drivers" class="btn btn-secondary">← К списку</a>
    </div>
</div>

<div class="page-content">

<?php
$predefDocs = [
    ['name' => 'Паспорт',                  'code' => 'passport',       'ext' => 'PDF'],
    ['name' => 'Водительское удостоверение', 'code' => 'driver_license', 'ext' => 'PDF'],
    ['name' => 'СНИЛС',                     'code' => 'snils',          'ext' => 'PDF'],
];
?>
<form method="post" action="/company/drivers/create" class="panel" enctype="multipart/form-data">
<div class="driver-layout">

    <!-- ════ Левая колонка: данные водителя ════ -->
    <div class="driver-layout-main">

        <div class="section-title">Данные водителя</div>

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

        <div class="driver-fields">

            <!-- ФИО + Телефон + кнопка (одна строка, 3 колонки) -->
            <div class="driver-contact-top-row">
                <div class="field field-w-name<?= !empty($errors['full_name']) ? ' is-error' : '' ?>">
                    <label class="field-label">ФИО <span class="req">*</span></label>
                    <input type="text" name="full_name" class="field-input"
                           placeholder="Фамилия Имя Отчество"
                           value="<?= e($old['full_name'] ?? '') ?>">
                    <div class="field-msg"><?= !empty($errors['full_name']) ? e($errors['full_name']) : '' ?></div>
                </div>

                <div class="driver-contact-stack">
                    <div class="driver-contact-main-row">
                        <div class="field field-w-phone<?= !empty($errors['phone']) ? ' is-error' : '' ?>">
                            <label class="field-label">Телефон <span class="req">*</span></label>
                            <input type="text" name="phone" class="field-input"
                                   placeholder="+7 900 000-00-00"
                                   value="<?= e($old['phone'] ?? '') ?>">
                            <div class="field-msg"><?= !empty($errors['phone']) ? e($errors['phone']) : '' ?></div>
                        </div>

                        <button type="button" class="btn btn-ghost phone-add-btn" id="add-extra-phone-btn">+ Доп. телефон</button>
                    </div>

                    <div class="driver-extra-phones" id="extra-phones-container">
                        <?php if (!empty($old['extra_phones']) && is_array($old['extra_phones'])): ?>
                            <?php foreach ($old['extra_phones'] as $idx => $ep): ?>
                            <div class="driver-extra-phone-row">
                                <div class="field driver-extra-phone-field">
                                    <input type="text" name="extra_phones[]" class="field-input"
                                           placeholder="+7 900 000-00-00"
                                           value="<?= e($ep) ?>">
                                    <div class="field-msg"></div>
                                </div>
                                <div class="driver-extra-phone-comment-wrap">
                                    <input type="text" name="extra_phone_comments[]" class="field-input"
                                           placeholder="Комментарий к телефону"
                                           value="<?= e($old['extra_phone_comments'][$idx] ?? '') ?>">
                                    <button type="button" class="driver-extra-phone-remove" title="Удалить">×</button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Паспорт: серия + код + кем выдан + дата -->
            <div class="field-row field-row-group">
                <div class="field field-w-passport">
                    <label class="field-label">Серия и номер</label>
                    <input type="text" name="passport_number" class="field-input"
                           placeholder="0000 000000"
                           value="<?= e($old['passport_number'] ?? '') ?>">
                    <div class="field-msg"></div>
                </div>

                <div class="field field-w-code">
                    <label class="field-label">Код подразделения</label>
                    <input type="text" name="passport_department_code" class="field-input"
                           placeholder="000-000"
                           value="<?= e($old['passport_department_code'] ?? '') ?>">
                    <div class="field-msg"></div>
                </div>

                <div class="field field-w-issued-by">
                    <label class="field-label">Кем выдан</label>
                    <input type="text" name="passport_issued_by" class="field-input"
                           placeholder="Орган, выдавший паспорт"
                           value="<?= e($old['passport_issued_by'] ?? '') ?>">
                    <div class="field-msg"></div>
                </div>

                <div class="field field-w-date">
                    <label class="field-label">Дата выдачи</label>
                    <input type="text" name="passport_issue_date" class="field-input js-erp-date-picker"
                           placeholder="дд.мм.гггг"
                           inputmode="numeric"
                           autocomplete="off"
                           value="<?= e($old['passport_issue_date'] ?? '') ?>">
                    <div class="field-msg"></div>
                </div>
            </div>

            <!-- ВУ + СНИЛС + Email -->
            <div class="field-row field-row-group">
                <div class="field field-w-license">
                    <label class="field-label">Номер ВУ</label>
                    <input type="text" name="license_number" class="field-input"
                           placeholder="0000 000000"
                           value="<?= e($old['license_number'] ?? '') ?>">
                    <div class="field-msg"></div>
                </div>

                <div class="field field-w-date">
                    <label class="field-label">Дата выдачи ВУ</label>
                    <input type="text" name="license_issue_date" class="field-input js-erp-date-picker"
                           placeholder="дд.мм.гггг"
                           inputmode="numeric"
                           autocomplete="off"
                           value="<?= e($old['license_issue_date'] ?? '') ?>">
                    <div class="field-msg"></div>
                </div>

                <div class="field field-w-snils">
                    <label class="field-label">СНИЛС</label>
                    <input type="text" name="snils" class="field-input"
                           placeholder="000-000-000 00"
                           value="<?= e($old['snils'] ?? '') ?>">
                    <div class="field-msg"></div>
                </div>

                <div class="field field-w-email field-grow<?= !empty($errors['email']) ? ' is-error' : '' ?>">
                    <label class="field-label">Email</label>
                    <input type="text" name="email" class="field-input"
                           placeholder="driver@example.ru"
                           value="<?= e($old['email'] ?? '') ?>">
                    <div class="field-msg"><?= !empty($errors['email']) ? e($errors['email']) : '' ?></div>
                </div>
            </div>

            <!-- Комментарий -->
            <div class="field field-w-comment field-row-group">
                <label class="field-label">Комментарий</label>
                <textarea name="comments" class="field-textarea driver-textarea"
                          placeholder="Примечания по водителю..."><?= e($old['comments'] ?? '') ?></textarea>
                <div class="field-msg"></div>
            </div>

        </div><!-- /.driver-fields -->

        <!-- Action footer -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Создать водителя</button>
        </div>

    </div><!-- /.driver-layout-main -->

    <!-- ════ Правая колонка: документы ════ -->
    <div class="driver-layout-docs">

        <div>
            <div class="section-title">Документы</div>
            <div class="field-msg">Документы можно загрузить сейчас или позже в карточке водителя</div>
        </div>

        <div class="file-list">
            <?php foreach ($predefDocs as $pdoc): ?>
            <div class="file-item file-item-predef is-empty" id="frow-<?= $pdoc['code'] ?>">
                <div class="file-type-badge file-type-badge-empty" id="fbadge-<?= $pdoc['code'] ?>">—</div>
                <div class="file-info">
                    <div class="file-name"><?= e($pdoc['name']) ?></div>
                    <div class="file-meta" id="fname-<?= $pdoc['code'] ?>">Файл не выбран</div>
                </div>
                <button type="button"
                        class="btn btn-secondary file-action-btn js-file-pick-btn"
                        data-file-input="predef-file-<?= $pdoc['code'] ?>">
                    <span id="fbtn-<?= $pdoc['code'] ?>">Выбрать</span>
                </button>
                <button type="button"
                        class="predef-file-clear"
                        id="fclear-<?= $pdoc['code'] ?>"
                        title="Убрать файлы"
                        style="display:none">×</button>
                <input type="file"
                       id="predef-file-<?= $pdoc['code'] ?>"
                       class="file-input-hidden js-predef-file-input"
                       name="predef_doc[<?= $pdoc['code'] ?>][]"
                       multiple
                       data-label="fname-<?= $pdoc['code'] ?>"
                       data-badge="fbadge-<?= $pdoc['code'] ?>"
                       data-button-label="fbtn-<?= $pdoc['code'] ?>"
                       data-clear="fclear-<?= $pdoc['code'] ?>">
                <input type="hidden" name="predef_doc_type[<?= $pdoc['code'] ?>]" value="<?= e($pdoc['name']) ?>">
            </div>
            <?php endforeach; ?>
        </div>

        <div class="form-section">
            <div class="section-title">Произвольные документы</div>
            <div class="field-msg" style="margin-bottom:6px;">
                Укажите тип документа и выберите файл
            </div>
            <div id="custom-docs-container" class="file-list"></div>
            <button type="button" class="btn btn-ghost" id="add-custom-doc-btn">+ Добавить документ</button>
        </div>

    </div><!-- /.driver-layout-docs -->

</div><!-- /.driver-layout -->
</form>

</div><!-- /.page-content -->

<script>
(function () {
    var createForm = document.querySelector('form[action="/company/drivers/create"][method="post"]');
    var allowedDocumentExtensions = ['pdf', 'doc', 'docx', 'rtf', 'odt', 'xls', 'xlsx', 'csv', 'ods', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tif', 'tiff', 'heic', 'heif', 'txt'];
    var maxDocumentSize = 20 * 1024 * 1024;

    function neutralSummary() {
        return {
            text: 'Файл не выбран',
            badge: { text: '—', cls: 'file-type-badge-empty' },
            filled: false
        };
    }
    function getFileExtension(fileName) {
        var parts = String(fileName || '').split('.');
        return parts.length > 1 ? parts.pop().toLowerCase() : '';
    }

    function validateSelectedFile(file) {
        var ext = getFileExtension(file && file.name);
        if (!ext || allowedDocumentExtensions.indexOf(ext) === -1) {
            return 'Недопустимый формат файла';
        }
        if (typeof file.size === 'number' && file.size > maxDocumentSize) {
            return 'Файл больше 20 МБ';
        }
        return '';
    }

    function clearPredefRowError(input) {
        var row = input.closest('.file-item');
        if (row) row.classList.remove('is-error');
    }

    function setPredefRowError(input, message) {
        var row = input.closest('.file-item');
        var meta = input.getAttribute('data-label') ? document.getElementById(input.getAttribute('data-label')) : null;
        if (row) row.classList.add('is-error');
        if (meta) meta.textContent = message;
    }

    function clearCustomFileError(input) {
        var row = input.closest('.custom-doc-row');
        var meta = input.getAttribute('data-label') ? document.getElementById(input.getAttribute('data-label')) : null;
        if (row) row.classList.remove('is-error');
        if (meta) meta.classList.remove('is-error');
    }

    function setCustomFileError(input, message) {
        var row = input.closest('.custom-doc-row');
        var meta = input.getAttribute('data-label') ? document.getElementById(input.getAttribute('data-label')) : null;
        if (row) row.classList.add('is-error');
        if (meta) {
            meta.textContent = message;
            meta.classList.add('is-error');
        }
    }

    function setPredefLoading(input, message) {
        var row = input.closest('.file-item');
        var badge = input.getAttribute('data-badge') ? document.getElementById(input.getAttribute('data-badge')) : null;
        var meta = input.getAttribute('data-label') ? document.getElementById(input.getAttribute('data-label')) : null;
        if (badge) {
            badge.className = 'file-type-badge is-loading';
            badge.textContent = '';
        }
        if (meta) meta.textContent = message || 'Обработка файла...';
        if (row) row.classList.remove('is-empty');
    }

    /* Один файл */
    window.erpFileSelect = function (input) {
        var el = input.getAttribute('data-label') ? document.getElementById(input.getAttribute('data-label')) : null;
        clearCustomFileError(input);
        if (el) el.textContent = input.files.length ? input.files[0].name : 'Файл не выбран';
    };

    function getFileKind(fileName) {
        var ext = String(fileName || '').split('.').pop().toLowerCase();
        if (ext === 'pdf') return 'pdf';
        if (['doc', 'docx', 'rtf', 'odt'].indexOf(ext) !== -1) return 'word';
        if (['xls', 'xlsx', 'csv', 'ods'].indexOf(ext) !== -1) return 'excel';
        if (['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tif', 'tiff', 'heic', 'heif'].indexOf(ext) !== -1) return 'photo';
        return 'other';
    }

    function getBadgeMeta(kind) {
        if (kind === 'pdf') return { text: 'PDF', cls: 'is-pdf' };
        if (kind === 'word') return { text: 'DOC', cls: 'is-doc' };
        if (kind === 'excel') return { text: 'XLS', cls: 'is-xls' };
        if (kind === 'photo') return { text: 'IMG', cls: 'is-img' };
        return { text: '—', cls: 'is-other' };
    }

    function summarizeFiles(files) {
        console.time('  └ summarizeFiles');
        if (!files || files.length === 0) {
            console.timeEnd('  └ summarizeFiles');
            return neutralSummary();
        }

        /* 1 файл — показываем тип и имя */
        if (files.length === 1) {
            var kind = getFileKind(files[0].name);
            var badge = getBadgeMeta(kind);
            var result = {
                text: files[0].name,
                badge: badge,
                filled: true
            };
            console.timeEnd('  └ summarizeFiles');
            return result;
        }

        /* 2+ файла — не перебираем, показываем только количество */
        console.timeEnd('  └ summarizeFiles');
        return {
            text: 'Выбрано файлов: ' + files.length,
            badge: getBadgeMeta('other'),
            filled: true
        };
    }

    function paintPredefFileState(input, summary) {
        console.time('  └ paintPredefFileState');
        var row = input.closest('.file-item');
        var badge = input.getAttribute('data-badge') ? document.getElementById(input.getAttribute('data-badge')) : null;
        var meta = input.getAttribute('data-label') ? document.getElementById(input.getAttribute('data-label')) : null;
        var buttonLabel = input.getAttribute('data-button-label') ? document.getElementById(input.getAttribute('data-button-label')) : null;
        var clearBtn = input.getAttribute('data-clear') ? document.getElementById(input.getAttribute('data-clear')) : null;
        clearPredefRowError(input);
        if (meta) {
            meta.textContent = summary.text;
            meta.style.display = summary.text ? '' : 'none';
        }
        if (buttonLabel) buttonLabel.textContent = summary.filled ? 'Заменить' : 'Выбрать';
        if (badge) {
            badge.className = 'file-type-badge ' + (summary.filled ? summary.badge.cls : 'file-type-badge-empty');
            badge.textContent = summary.badge.text;
        }
        if (clearBtn) clearBtn.style.display = summary.filled ? '' : 'none';
        if (row) row.classList.toggle('is-empty', !summary.filled);
        console.timeEnd('  └ paintPredefFileState');
    }

    /* Несколько файлов */
    window.erpFileMultiSelect = function (input) {
        console.time('[file] erpFileMultiSelect (total)');
        if (!input.files || input.files.length === 0) {
            paintPredefFileState(input, neutralSummary());
            console.timeEnd('[file] erpFileMultiSelect (total)');
            return;
        }
        var summary = summarizeFiles(input.files);
        paintPredefFileState(input, summary);
        console.timeEnd('[file] erpFileMultiSelect (total)');
    };

    /* ── Дополнительные телефоны ── */
    var phonesContainer = document.getElementById('extra-phones-container');
    var addPhoneBtn     = document.getElementById('add-extra-phone-btn');
    if (phonesContainer && addPhoneBtn) {
        function buildPhoneRow() {
            var row = document.createElement('div');
            row.className = 'driver-extra-phone-row';
            row.innerHTML =
                '<div class="field driver-extra-phone-field">' +
                    '<input type="text" name="extra_phones[]" class="field-input" placeholder="+7 900 000-00-00">' +
                    '<div class="field-msg"></div>' +
                '</div>' +
                '<div class="driver-extra-phone-comment-wrap">' +
                    '<input type="text" name="extra_phone_comments[]" class="field-input" placeholder="Комментарий к телефону">' +
                    '<button type="button" class="driver-extra-phone-remove" title="Удалить">×</button>' +
                '</div>';
            row.querySelector('.driver-extra-phone-remove').addEventListener('click', function () {
                phonesContainer.removeChild(row);
            });
            phonesContainer.appendChild(row);
        }
        addPhoneBtn.addEventListener('click', function (e) {
            e.preventDefault();
            buildPhoneRow();
        });
        // Поддержка удаления для server-side восстановленных строк
        Array.prototype.forEach.call(
            phonesContainer.querySelectorAll('.driver-extra-phone-remove'),
            function (btn) {
                btn.addEventListener('click', function () {
                    var row = btn.closest('.driver-extra-phone-row');
                    if (row) phonesContainer.removeChild(row);
                });
            }
        );
    }

    /* ── Произвольные документы ── */
    var customContainer = document.getElementById('custom-docs-container');
    var addDocBtn       = document.getElementById('add-custom-doc-btn');
    if (!customContainer || !addDocBtn) return;

    var docTypes = <?= json_encode($docTypes ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;

    function filterDocTypes(query) {
        if (!query) return [];
        var q = query.toLowerCase();
        var result = [];
        for (var i = 0; i < docTypes.length; i++) {
            if (docTypes[i].name.toLowerCase().indexOf(q) !== -1) result.push(docTypes[i]);
            if (result.length >= 5) break;
        }
        return result;
    }

    function buildDocRow() {
        var row = document.createElement('div');
        row.className = 'file-item custom-doc-row is-empty';
        var idx = customContainer.children.length;

        var badgeId   = 'cbadge-' + idx;
        var metaId    = 'cmeta-' + idx;
        var btnLabelId = 'cbtnlabel-' + idx;
        var inputId   = 'cinput-' + idx;

        row.innerHTML =
            '<div class="file-type-badge file-type-badge-empty" id="' + badgeId + '">—</div>' +
            '<div class="file-info">' +
                '<input type="text" name="custom_doc_type[]" class="field-input custom-doc-type-input" placeholder="Тип документа" autocomplete="off">' +
                '<div class="custom-doc-suggestions"></div>' +
                '<div class="file-meta" id="' + metaId + '" style="display:none"></div>' +
            '</div>' +
            '<button type="button" class="btn btn-secondary file-action-btn js-custom-file-pick-btn" data-file-input="' + inputId + '">' +
                '<span id="' + btnLabelId + '">Выбрать</span>' +
            '</button>' +
            '<input type="file" id="' + inputId + '" class="file-input-hidden js-custom-file-input" name="custom_doc_file[]" data-label="' + metaId + '" data-badge="' + badgeId + '" data-button-label="' + btnLabelId + '">' +
            '<button type="button" class="file-remove" title="Удалить строку">×</button>';

        row.querySelector('.file-remove').addEventListener('click', function () {
            customContainer.removeChild(row);
        });

        /* ── Подсказки типов документов ── */
        var typeInput = row.querySelector('.custom-doc-type-input');
        var suggestionsDiv = row.querySelector('.custom-doc-suggestions');

        typeInput.addEventListener('input', function () {
            var filtered = filterDocTypes(typeInput.value.trim());
            if (filtered.length === 0) {
                suggestionsDiv.innerHTML = '';
                suggestionsDiv.style.display = 'none';
                return;
            }
            var html = '';
            for (var i = 0; i < filtered.length; i++) {
                html += '<div class="custom-doc-suggestion">' + filtered[i].name.replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') + '</div>';
            }
            suggestionsDiv.innerHTML = html;
            suggestionsDiv.style.display = 'block';
        });

        suggestionsDiv.addEventListener('mousedown', function (e) {
            if (e.target.classList.contains('custom-doc-suggestion')) {
                typeInput.value = e.target.textContent;
                suggestionsDiv.innerHTML = '';
                suggestionsDiv.style.display = 'none';
            }
        });

        typeInput.addEventListener('blur', function () {
            window.setTimeout(function () {
                suggestionsDiv.style.display = 'none';
            }, 120);
        });

        typeInput.addEventListener('focus', function () {
            if (typeInput.value.trim() !== '') typeInput.dispatchEvent(new Event('input'));
        });

        /* Init button → input.click() */
        var btn = row.querySelector('.js-custom-file-pick-btn');
        var fileInput = row.querySelector('.js-custom-file-input');
        if (btn && fileInput) {
            btn.addEventListener('click', function () {
                var badge = fileInput.getAttribute('data-badge') ? document.getElementById(fileInput.getAttribute('data-badge')) : null;
                var rowEl = fileInput.closest('.file-item');
                preClickState[fileInput.id] = {
                    badgeClassName: badge ? badge.className : '',
                    badgeText: badge ? badge.textContent : '',
                    metaText: document.getElementById(fileInput.getAttribute('data-label') || '') ? document.getElementById(fileInput.getAttribute('data-label')).textContent : '',
                    buttonText: document.getElementById(fileInput.getAttribute('data-button-label') || '') ? document.getElementById(fileInput.getAttribute('data-button-label')).textContent : '',
                    isEmpty: rowEl ? rowEl.classList.contains('is-empty') : true
                };
                if (badge) { badge.className = 'file-type-badge is-loading'; badge.textContent = ''; }
                fileInput.click();
            });
        }

        /* Init change → update UI */
        if (fileInput) {
            fileInput.addEventListener('change', function () {
                var saved = preClickState[fileInput.id];
                if (fileInput.files && fileInput.files.length > 0) {
                    var summary = summarizeFiles(fileInput.files);
                    paintPredefFileState(fileInput, summary);
                    delete preClickState[fileInput.id];
                } else if (saved) {
                    var rowEl = fileInput.closest('.file-item');
                    var badge = fileInput.getAttribute('data-badge') ? document.getElementById(fileInput.getAttribute('data-badge')) : null;
                    var meta = fileInput.getAttribute('data-label') ? document.getElementById(fileInput.getAttribute('data-label')) : null;
                    var buttonLabel = fileInput.getAttribute('data-button-label') ? document.getElementById(fileInput.getAttribute('data-button-label')) : null;
                    if (badge) { badge.className = saved.badgeClassName; badge.textContent = saved.badgeText; }
                    if (meta) {
                        meta.textContent = saved.metaText;
                        meta.style.display = saved.metaText ? '' : 'none';
                    }
                    if (buttonLabel) buttonLabel.textContent = saved.buttonText;
                    if (rowEl) { if (saved.isEmpty) rowEl.classList.add('is-empty'); else rowEl.classList.remove('is-empty'); }
                    delete preClickState[fileInput.id];
                }
            });
        }

        customContainer.appendChild(row);
    }

    addDocBtn.addEventListener('click', function (e) {
        e.preventDefault();
        buildDocRow();
    });

    window.erpDriverCreateBeforeSubmit = function (formEl) {
        if (!createForm || formEl !== createForm) return;
        createForm.dataset.submitting = '1';
        Array.prototype.forEach.call(
            createForm.querySelectorAll('input[type="file"][name^="predef_doc["]'),
            function (input) {
                if (input.files && input.files.length > 0) {
                    setPredefLoading(input, 'Загрузка...');
                }
            }
        );
        Array.prototype.forEach.call(
            createForm.querySelectorAll('input[type="file"].js-custom-file-input'),
            function (input) {
                if (input.files && input.files.length > 0) {
                    setPredefLoading(input, 'Загрузка...');
                }
            }
        );

        var submitBtn = createForm.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Создание...';
        }
    };

    window.erpDriverCreateValidateFiles = function (formEl) {
        if (!createForm || formEl !== createForm) return true;
        var ok = true;

        Array.prototype.forEach.call(
            createForm.querySelectorAll('input[type="file"][name^="predef_doc["]'),
            function (input) {
                clearPredefRowError(input);
                if (!input.files || input.files.length === 0) return;
                for (var i = 0; i < input.files.length; i++) {
                    var message = validateSelectedFile(input.files[i]);
                    if (message) {
                        setPredefRowError(input, message);
                        ok = false;
                        break;
                    }
                }
            }
        );

        Array.prototype.forEach.call(
            createForm.querySelectorAll('input[type="file"][name="custom_doc_file[]"]'),
            function (input) {
                clearCustomFileError(input);
                if (!input.files || input.files.length === 0) return;
                for (var i = 0; i < input.files.length; i++) {
                    var message = validateSelectedFile(input.files[i]);
                    if (message) {
                        setCustomFileError(input, message);
                        ok = false;
                        break;
                    }
                }
            }
        );

        return ok;
    };

    /* ── Кнопки выбора файла: click → spinner → input.click() ── */
    var preClickState = {};

    Array.prototype.forEach.call(
        document.querySelectorAll('.js-file-pick-btn'),
        function (btn) {
            if (btn.dataset.filePickReady === '1') return;
            btn.dataset.filePickReady = '1';
            btn.addEventListener('click', function () {
                console.time('[file] TOTAL from button click to UI done');
                var inputId = btn.getAttribute('data-file-input');
                var input = inputId ? document.getElementById(inputId) : null;
                if (!input) return;

                /* Сохранить состояние строки до выбора */
                var row = input.closest('.file-item');
                var badge = input.getAttribute('data-badge') ? document.getElementById(input.getAttribute('data-badge')) : null;
                var meta = input.getAttribute('data-label') ? document.getElementById(input.getAttribute('data-label')) : null;
                var buttonLabel = input.getAttribute('data-button-label') ? document.getElementById(input.getAttribute('data-button-label')) : null;

                preClickState[inputId] = {
                    badgeClassName: badge ? badge.className : '',
                    badgeText: badge ? badge.textContent : '',
                    metaText: meta ? meta.textContent : '',
                    buttonText: buttonLabel ? buttonLabel.textContent : '',
                    isEmpty: row ? row.classList.contains('is-empty') : true
                };

                /* Spinner только на бейдж, мета-текст не трогаем */
                if (badge) {
                    badge.className = 'file-type-badge is-loading';
                    badge.textContent = '';
                }

                console.log('[file] → spinner set, calling input.click()...');
                input.click();
            });
        }
    );

    /* ── Скрытые file input: change → обновить или восстановить ── */
    Array.prototype.forEach.call(
        document.querySelectorAll('.js-predef-file-input'),
        function (input) {
            if (input.dataset.filePickReady === '1') return;
            input.dataset.filePickReady = '1';
            input.addEventListener('change', function () {
                console.log('[file] ← change event fired (dialog closed)');
                var inputId = input.id;
                var saved = preClickState[inputId];

                if (input.files && input.files.length > 0) {
                    /* Файл(ы) выбраны — стандартное обновление */
                    window.erpFileMultiSelect(input);
                    delete preClickState[inputId];
                } else if (saved) {
                    /* Отмена выбора — восстановить прежнее состояние */
                    var row = input.closest('.file-item');
                    var badge = input.getAttribute('data-badge') ? document.getElementById(input.getAttribute('data-badge')) : null;
                    var meta = input.getAttribute('data-label') ? document.getElementById(input.getAttribute('data-label')) : null;
                    var buttonLabel = input.getAttribute('data-button-label') ? document.getElementById(input.getAttribute('data-button-label')) : null;

                    if (badge) {
                        badge.className = saved.badgeClassName;
                        badge.textContent = saved.badgeText;
                    }
                    if (meta) meta.textContent = saved.metaText;
                    if (buttonLabel) buttonLabel.textContent = saved.buttonText;
                    if (row) {
                        if (saved.isEmpty) row.classList.add('is-empty');
                        else row.classList.remove('is-empty');
                    }
                    delete preClickState[inputId];
                    console.log('[file] ← cancelled, state restored');
                }

                console.timeEnd('[file] TOTAL from button click to UI done');
            });
        }
    );

    /* ── Кнопки очистки файла для основных документов ── */
    Array.prototype.forEach.call(
        document.querySelectorAll('.predef-file-clear'),
        function (btn) {
            if (btn.dataset.clearReady === '1') return;
            btn.dataset.clearReady = '1';
            btn.addEventListener('click', function () {
                var row = btn.closest('.file-item');
                var fileInput = row ? row.querySelector('input[type="file"][name^="predef_doc["]') : null;
                if (!fileInput) return;
                // Clear the file input
                fileInput.value = '';
                // Reset UI
                paintPredefFileState(fileInput, neutralSummary());
                // Reset row error if any
                if (row) row.classList.remove('is-error');
            });
        }
    );

})();
</script>

<?php endif; ?>
