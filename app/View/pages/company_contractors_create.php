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

<div class="page-content">
    <div class="form-alert alert-warning">
        <div class="alert-body">
            <div class="alert-body-title">Компания неактивна</div>
            <div class="alert-body-sub">Статус компании: «<?= e($company['status']) ?>». Создание перевозчиков недоступно.</div>
        </div>
    </div>
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

<div class="page-content">
    <div class="panel">
        <div class="panel-body">
            <div class="form-alert alert-success">
                <div class="alert-body">
                    <div class="alert-body-title">Перевозчик успешно создан</div>
                    <?php if (!empty($uploadedDocs)): ?>
                        <div class="alert-body-sub">Загружено документов: <?= count($uploadedDocs) ?>.</div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($docErrors)): ?>
                <div class="form-alert alert-warning">
                    <div class="alert-body">
                        <div class="alert-body-title">Некоторые документы не были загружены</div>
                        <div class="alert-body-sub"><?= implode(', ', array_map('e', $docErrors)) ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-section">
                <div class="section-title">ДАННЫЕ ПЕРЕВОЗЧИКА</div>
                <dl class="dl">
                    <dt>Наименование</dt>
                    <dd><?= e($createdContractor['name']) ?></dd>
                    <dt>ИНН</dt>
                    <dd class="mono"><?= e($createdContractor['inn']) ?></dd>
                    <?php if (!empty($createdContractor['contractor_type'])): ?>
                    <dt>Тип</dt>
                    <dd><?= e(ui_contractor_type($createdContractor['contractor_type'])) ?></dd>
                    <?php endif; ?>
                    <dt>Статус</dt>
                    <dd><span class="badge badge-ok">Активен</span></dd>
                </dl>
            </div>

            <div class="form-actions">
                <a href="/company/contractors" class="btn btn-primary">← К списку перевозчиков</a>
                <a href="/company/contractors/create" class="btn btn-secondary">Создать ещё</a>
            </div>
        </div>
    </div>
</div>

<?php else: ?>

<?php
$predefDocs = [
    ['name' => 'Карточка предприятия', 'code' => 'company_card'],
    ['name' => 'Свидетельство ИНН', 'code' => 'inn_cert'],
    ['name' => 'Свидетельство ОГРН', 'code' => 'ogrn_cert'],
    ['name' => 'Договор', 'code' => 'contract'],
];
?>

<div class="page-head">
    <div>
        <h1>Создать перевозчика</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="page-content">
    <form method="post" action="/company/contractors/create" class="panel" enctype="multipart/form-data" data-contractor-create-form>
        <div class="entity-form-layout driver-layout">
            <div class="entity-form-main driver-layout-main">

                <div class="section-title">ДАННЫЕ ПЕРЕВОЗЧИКА</div>

                <?php if ($formError): ?>
                <div class="form-alert alert-error">
                    <div class="alert-body">
                        <div class="alert-body-title">Ошибка при сохранении</div>
                        <div class="alert-body-sub"><?= e($formError) ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-section">
                    <div class="form-grid-2">
                        <div class="field<?= !empty($errors['name']) ? ' is-error' : '' ?>" data-field="name">
                            <label class="field-label">Наименование <span class="req">*</span></label>
                            <input type="text"
                                   name="name"
                                   class="field-input"
                                   placeholder="ООО &quot;РОМАШКА&quot;"
                                   value="<?= e($old['name'] ?? '') ?>">
                            <div class="field-msg"><?= !empty($errors['name']) ? e($errors['name']) : '' ?></div>
                        </div>

                        <div class="field<?= !empty($errors['inn']) ? ' is-error' : '' ?>" data-field="inn">
                            <label class="field-label">ИНН <span class="req">*</span></label>
                            <input type="text"
                                   name="inn"
                                   class="field-input"
                                   inputmode="numeric"
                                   placeholder="7701234567"
                                   value="<?= e($old['inn'] ?? '') ?>">
                            <div class="field-msg"><?= !empty($errors['inn']) ? e($errors['inn']) : '' ?></div>
                        </div>
                    </div>

                    <div class="form-grid-3">
                        <div class="field" data-field="contractor_type">
                            <label class="field-label">Тип перевозчика</label>
                            <select name="contractor_type" class="field-select">
                                <option value="">— Не указан —</option>
                                <option value="legal_entity" <?= ($old['contractor_type'] ?? '') === 'legal_entity' ? 'selected' : '' ?>>Юридическое лицо</option>
                                <option value="individual" <?= ($old['contractor_type'] ?? '') === 'individual' ? 'selected' : '' ?>>Индивидуальный предприниматель</option>
                                <option value="self_employed" <?= ($old['contractor_type'] ?? '') === 'self_employed' ? 'selected' : '' ?>>Самозанятый</option>
                                <option value="private_person" <?= ($old['contractor_type'] ?? '') === 'private_person' ? 'selected' : '' ?>>Физическое лицо</option>
                            </select>
                            <div class="field-msg"></div>
                        </div>

                        <div class="field<?= !empty($errors['kpp']) ? ' is-error' : '' ?>" data-field="kpp">
                            <label class="field-label">КПП</label>
                            <input type="text"
                                   name="kpp"
                                   class="field-input"
                                   inputmode="numeric"
                                   placeholder="770101001"
                                   value="<?= e($old['kpp'] ?? '') ?>">
                            <div class="field-msg"><?= !empty($errors['kpp']) ? e($errors['kpp']) : '' ?></div>
                        </div>

                        <div class="field<?= !empty($errors['ogrn']) ? ' is-error' : '' ?>" data-field="ogrn">
                            <label class="field-label">ОГРН</label>
                            <input type="text"
                                   name="ogrn"
                                   class="field-input"
                                   inputmode="numeric"
                                   placeholder="1027700132195"
                                   value="<?= e($old['ogrn'] ?? '') ?>">
                            <div class="field-msg"><?= !empty($errors['ogrn']) ? e($errors['ogrn']) : '' ?></div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">РЕКВИЗИТЫ</div>

                    <div class="field" data-field="legal_address">
                        <label class="field-label">Юридический адрес</label>
                        <textarea name="legal_address"
                                  class="field-textarea"
                                  rows="2"
                                  placeholder="Юридический адрес перевозчика"><?= e($old['legal_address'] ?? '') ?></textarea>
                        <div class="field-msg"></div>
                    </div>

                    <div class="field" data-field="physical_address">
                        <label class="field-label">Фактический адрес</label>
                        <textarea name="physical_address"
                                  class="field-textarea"
                                  rows="2"
                                  placeholder="Фактический адрес перевозчика"><?= e($old['physical_address'] ?? '') ?></textarea>
                        <div class="field-msg"></div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">КОНТАКТЫ</div>

                    <div class="form-grid-3">
                        <div class="field" data-field="contact_person">
                            <label class="field-label">Контактное лицо</label>
                            <input type="text"
                                   name="contact_person"
                                   class="field-input"
                                   placeholder="Иванов Иван Иванович"
                                   value="<?= e($old['contact_person'] ?? '') ?>">
                            <div class="field-msg"></div>
                        </div>

                        <div class="field<?= !empty($errors['contact_phone']) ? ' is-error' : '' ?>" data-field="contact_phone">
                            <label class="field-label">Телефон</label>
                            <input type="text"
                                   name="contact_phone"
                                   class="field-input"
                                   inputmode="tel"
                                   placeholder="+7 900 000-00-00"
                                   value="<?= e($old['contact_phone'] ?? '') ?>">
                            <div class="field-msg"><?= !empty($errors['contact_phone']) ? e($errors['contact_phone']) : '' ?></div>
                        </div>

                        <div class="field<?= !empty($errors['contact_email']) ? ' is-error' : '' ?>" data-field="contact_email">
                            <label class="field-label">Email</label>
                            <input type="text"
                                   name="contact_email"
                                   class="field-input"
                                   placeholder="contractor@example.ru"
                                   value="<?= e($old['contact_email'] ?? '') ?>">
                            <div class="field-msg"><?= !empty($errors['contact_email']) ? e($errors['contact_email']) : '' ?></div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">БАНКОВСКИЕ РЕКВИЗИТЫ</div>

                    <div class="form-grid-2">
                        <div class="field<?= !empty($errors['bank_account']) ? ' is-error' : '' ?>" data-field="bank_account">
                            <label class="field-label">Расчётный счёт</label>
                            <input type="text"
                                   name="bank_account"
                                   class="field-input"
                                   inputmode="numeric"
                                   placeholder="40702810000000000000"
                                   value="<?= e($old['bank_account'] ?? '') ?>">
                            <div class="field-msg"><?= !empty($errors['bank_account']) ? e($errors['bank_account']) : '' ?></div>
                        </div>

                        <div class="field<?= !empty($errors['bank_bik']) ? ' is-error' : '' ?>" data-field="bank_bik">
                            <label class="field-label">БИК</label>
                            <input type="text"
                                   name="bank_bik"
                                   class="field-input"
                                   inputmode="numeric"
                                   placeholder="044525225"
                                   value="<?= e($old['bank_bik'] ?? '') ?>">
                            <div class="field-msg"><?= !empty($errors['bank_bik']) ? e($errors['bank_bik']) : '' ?></div>
                        </div>
                    </div>

                    <div class="field" data-field="bank_name">
                        <label class="field-label">Банк</label>
                        <input type="text"
                               name="bank_name"
                               class="field-input"
                               placeholder="АО &quot;БАНК&quot;"
                               value="<?= e($old['bank_name'] ?? '') ?>">
                        <div class="field-msg"></div>
                    </div>

                    <div class="field<?= !empty($errors['bank_corr_account']) ? ' is-error' : '' ?>" data-field="bank_corr_account">
                        <label class="field-label">Корр. счёт</label>
                        <input type="text"
                               name="bank_corr_account"
                               class="field-input"
                               inputmode="numeric"
                               placeholder="30101810400000000225"
                               value="<?= e($old['bank_corr_account'] ?? '') ?>">
                        <div class="field-msg"><?= !empty($errors['bank_corr_account']) ? e($errors['bank_corr_account']) : '' ?></div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">КОММЕНТАРИЙ</div>
                    <div class="field" data-field="comments">
                        <label class="field-label">Комментарий</label>
                        <textarea name="comments"
                                  class="field-textarea driver-textarea"
                                  rows="3"
                                  placeholder="Примечания по перевозчику"><?= e($old['comments'] ?? '') ?></textarea>
                        <div class="field-msg"></div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Создать перевозчика</button>
                </div>
            </div>

            <div class="entity-form-docs driver-layout-docs">
                <div>
                    <div class="section-title">ДОКУМЕНТЫ</div>
                    <div class="field-msg">Документы можно загрузить сейчас или позже в карточке перевозчика</div>
                </div>

                <div class="file-list">
                    <?php foreach ($predefDocs as $pdoc): ?>
                    <div class="file-item file-item-predef document-file-row is-empty">
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
                                class="predef-file-clear is-hidden"
                                id="fclear-<?= $pdoc['code'] ?>"
                                title="Очистить файл">×</button>
                        <input type="file"
                               id="predef-file-<?= $pdoc['code'] ?>"
                               class="file-input-hidden js-predef-file-input"
                               name="predef_doc[<?= $pdoc['code'] ?>]"
                               accept=".pdf,.jpg,.jpeg,.png"
                               data-label="fname-<?= $pdoc['code'] ?>"
                               data-badge="fbadge-<?= $pdoc['code'] ?>"
                               data-button-label="fbtn-<?= $pdoc['code'] ?>"
                               data-clear="fclear-<?= $pdoc['code'] ?>">
                        <input type="hidden" name="predef_doc_type[<?= $pdoc['code'] ?>]" value="<?= e($pdoc['name']) ?>">
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-section">
                    <div class="section-title">ПРОИЗВОЛЬНЫЕ ДОКУМЕНТЫ</div>
                    <div class="field-msg">Введите название документа и выберите файл</div>
                    <div id="custom-docs-container" class="file-list"></div>
                    <button type="button" class="btn btn-ghost" id="add-custom-doc-btn">+ Добавить документ</button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    var createForm = document.querySelector('form[data-contractor-create-form]');
    var customContainer = document.getElementById('custom-docs-container');
    var addDocBtn = document.getElementById('add-custom-doc-btn');
    var docTypes = <?= json_encode($docTypes ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    var allowedPredefExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
    var allowedCustomExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'];

    if (!createForm) {
        return;
    }

    createForm.noValidate = true;

    function byName(name) {
        return createForm.querySelector('[name="' + name + '"]');
    }

    function normalizeSpaces(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    }

    function stripSpacesAndHyphens(value) {
        return String(value || '').replace(/[\s-]+/g, '');
    }

    function setFieldState(name, message) {
        var field = createForm.querySelector('[data-field="' + name + '"]');
        if (!field) {
            return;
        }
        field.classList.toggle('is-error', !!message);
        var msg = field.querySelector('.field-msg');
        if (msg) {
            msg.textContent = message || '';
        }
    }

    function clearClientErrors() {
        Array.prototype.forEach.call(createForm.querySelectorAll('[data-field]'), function (field) {
            field.classList.remove('is-error');
        });
        Array.prototype.forEach.call(createForm.querySelectorAll('[data-field] .field-msg'), function (msg) {
            if (msg.textContent === '' || msg.dataset.clientManaged === '1') {
                msg.textContent = '';
            }
            msg.dataset.clientManaged = '1';
        });
    }

    function normalizePhone(value) {
        var raw = String(value || '').trim();
        if (raw === '') {
            return { value: '', error: '' };
        }
        if (/[^0-9+\s()\-]/.test(raw)) {
            return { value: raw, error: 'Телефон: 10–11 цифр' };
        }
        var digits = raw.replace(/\D/g, '');
        if (digits.length === 11 && digits.charAt(0) === '8') {
            digits = '7' + digits.slice(1);
        }
        if (digits.length === 10) {
            digits = '7' + digits;
        }
        if (digits.length !== 11 || digits.charAt(0) !== '7') {
            return { value: raw, error: 'Телефон: 10–11 цифр' };
        }
        return {
            value: '+7 ' + digits.slice(1, 4) + ' ' + digits.slice(4, 7) + '-' + digits.slice(7, 9) + '-' + digits.slice(9, 11),
            error: ''
        };
    }

    function getFileExtension(fileName) {
        var parts = String(fileName || '').split('.');
        return parts.length > 1 ? parts.pop().toLowerCase() : '';
    }

    function getFileKind(fileName) {
        var ext = getFileExtension(fileName);
        if (ext === 'pdf') return 'pdf';
        if (['doc', 'docx'].indexOf(ext) !== -1) return 'word';
        if (['xls', 'xlsx'].indexOf(ext) !== -1) return 'excel';
        if (['jpg', 'jpeg', 'png'].indexOf(ext) !== -1) return 'photo';
        return 'other';
    }

    function getBadgeMeta(kind) {
        if (kind === 'pdf') return { text: 'PDF', cls: 'is-pdf' };
        if (kind === 'word') return { text: 'DOC', cls: 'is-doc' };
        if (kind === 'excel') return { text: 'XLS', cls: 'is-xls' };
        if (kind === 'photo') return { text: 'IMG', cls: 'is-img' };
        return { text: '—', cls: 'file-type-badge-empty' };
    }

    function neutralSummary() {
        return {
            text: 'Файл не выбран',
            badge: { text: '—', cls: 'file-type-badge-empty' },
            filled: false
        };
    }

    function summarizeFile(input) {
        if (!input.files || input.files.length === 0) {
            return neutralSummary();
        }
        var kind = getFileKind(input.files[0].name);
        return {
            text: input.files[0].name,
            badge: getBadgeMeta(kind),
            filled: true
        };
    }

    function paintDocumentFileState(input, summary) {
        var row = input.closest('.file-item');
        var badge = input.getAttribute('data-badge') ? document.getElementById(input.getAttribute('data-badge')) : null;
        var meta = input.getAttribute('data-label') ? document.getElementById(input.getAttribute('data-label')) : null;
        var buttonLabel = input.getAttribute('data-button-label') ? document.getElementById(input.getAttribute('data-button-label')) : null;
        var clearBtn = input.getAttribute('data-clear') ? document.getElementById(input.getAttribute('data-clear')) : null;

        if (meta) {
            meta.textContent = summary.text;
            meta.classList.toggle('is-hidden', !summary.text);
            meta.classList.remove('is-error');
        }
        if (badge) {
            badge.className = 'file-type-badge ' + (summary.filled ? summary.badge.cls : 'file-type-badge-empty');
            badge.textContent = summary.badge.text;
        }
        if (buttonLabel) {
            buttonLabel.textContent = summary.filled ? 'Заменить' : 'Выбрать';
        }
        if (clearBtn) {
            clearBtn.classList.toggle('is-hidden', !summary.filled);
        }
        if (row) {
            row.classList.toggle('is-empty', !summary.filled);
            row.classList.toggle('has-file', summary.filled);
            row.classList.remove('is-error', 'has-error');
        }
    }

    function setDocumentError(input, message) {
        var row = input.closest('.file-item');
        var meta = input.getAttribute('data-label') ? document.getElementById(input.getAttribute('data-label')) : null;
        if (row) {
            row.classList.add('is-error', 'has-error');
        }
        if (meta) {
            meta.textContent = message;
            meta.classList.remove('is-hidden');
            meta.classList.add('is-error');
        }
    }

    function clearDocumentError(input) {
        var row = input.closest('.file-item');
        var meta = input.getAttribute('data-label') ? document.getElementById(input.getAttribute('data-label')) : null;
        if (row) {
            row.classList.remove('is-error', 'has-error');
        }
        if (meta) {
            meta.classList.remove('is-error');
        }
    }

    function validateSelectedFile(file, allowedExtensions) {
        var ext = getFileExtension(file && file.name);
        if (!ext || allowedExtensions.indexOf(ext) === -1) {
            return 'Недопустимый формат файла';
        }
        return '';
    }

    function validateForm() {
        var ok = true;

        clearClientErrors();

        var nameField = byName('name');
        var innField = byName('inn');
        var kppField = byName('kpp');
        var ogrnField = byName('ogrn');
        var legalAddressField = byName('legal_address');
        var physicalAddressField = byName('physical_address');
        var contactPersonField = byName('contact_person');
        var contactPhoneField = byName('contact_phone');
        var contactEmailField = byName('contact_email');
        var bankAccountField = byName('bank_account');
        var bankNameField = byName('bank_name');
        var bankBikField = byName('bank_bik');
        var bankCorrAccountField = byName('bank_corr_account');
        var commentsField = byName('comments');

        nameField.value = normalizeSpaces(nameField.value);
        innField.value = stripSpacesAndHyphens(innField.value);
        kppField.value = stripSpacesAndHyphens(kppField.value);
        ogrnField.value = stripSpacesAndHyphens(ogrnField.value);
        legalAddressField.value = String(legalAddressField.value || '').trim();
        physicalAddressField.value = String(physicalAddressField.value || '').trim();
        contactPersonField.value = normalizeSpaces(contactPersonField.value);
        bankAccountField.value = stripSpacesAndHyphens(bankAccountField.value);
        bankBikField.value = stripSpacesAndHyphens(bankBikField.value);
        bankCorrAccountField.value = stripSpacesAndHyphens(bankCorrAccountField.value);
        bankNameField.value = String(bankNameField.value || '').trim();
        commentsField.value = String(commentsField.value || '').trim();
        contactEmailField.value = String(contactEmailField.value || '').trim();

        var normalizedPhone = normalizePhone(contactPhoneField.value);
        contactPhoneField.value = normalizedPhone.value;

        if (nameField.value === '') {
            setFieldState('name', 'Укажите наименование');
            ok = false;
        }

        if (innField.value === '') {
            setFieldState('inn', 'Укажите ИНН');
            ok = false;
        } else if (!/^\d+$/.test(innField.value) || (innField.value.length !== 10 && innField.value.length !== 12)) {
            setFieldState('inn', 'ИНН: 10 или 12 цифр');
            ok = false;
        }

        if (kppField.value !== '' && (!/^\d{9}$/.test(kppField.value))) {
            setFieldState('kpp', 'КПП: 9 цифр');
            ok = false;
        }

        if (ogrnField.value !== '' && (!/^\d+$/.test(ogrnField.value) || (ogrnField.value.length !== 13 && ogrnField.value.length !== 15))) {
            setFieldState('ogrn', 'ОГРН: 13 или 15 цифр');
            ok = false;
        }

        if (normalizedPhone.error) {
            setFieldState('contact_phone', normalizedPhone.error);
            ok = false;
        }

        if (contactEmailField.value !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(contactEmailField.value)) {
            setFieldState('contact_email', 'Некорректный email');
            ok = false;
        }

        if (bankAccountField.value !== '' && !/^\d{20}$/.test(bankAccountField.value)) {
            setFieldState('bank_account', 'Счёт: 20 цифр');
            ok = false;
        }

        if (bankBikField.value !== '' && !/^\d{9}$/.test(bankBikField.value)) {
            setFieldState('bank_bik', 'БИК: 9 цифр');
            ok = false;
        }

        if (bankCorrAccountField.value !== '' && !/^\d{20}$/.test(bankCorrAccountField.value)) {
            setFieldState('bank_corr_account', 'Корр. счёт: 20 цифр');
            ok = false;
        }

        Array.prototype.forEach.call(createForm.querySelectorAll('.js-predef-file-input'), function (input) {
            clearDocumentError(input);
            if (!input.files || input.files.length === 0) {
                return;
            }
            var message = validateSelectedFile(input.files[0], allowedPredefExtensions);
            if (message) {
                setDocumentError(input, message);
                ok = false;
            }
        });

        Array.prototype.forEach.call(createForm.querySelectorAll('.js-custom-file-input'), function (input) {
            clearDocumentError(input);
            var row = input.closest('.custom-doc-row');
            var hiddenType = row ? row.querySelector('input[name="custom_doc_type[]"]') : null;
            var customType = row ? row.querySelector('input[name="custom_doc_type_new[]"]') : null;
            var titleField = row ? row.querySelector('.custom-doc-title-field') : null;
            var titleMsg = titleField ? titleField.querySelector('.field-msg') : null;

            if (titleField) {
                titleField.classList.remove('is-error');
            }
            if (titleMsg) {
                titleMsg.textContent = '';
            }

            if ((!input.files || input.files.length === 0) && (!customType || customType.value.trim() === '')) {
                return;
            }

            if (customType) {
                customType.value = normalizeSpaces(customType.value);
            }

            if (!customType || customType.value === '') {
                if (titleField) {
                    titleField.classList.add('is-error');
                }
                if (titleMsg) {
                    titleMsg.textContent = 'Введите название документа';
                }
                ok = false;
            }

            if (hiddenType) {
                hiddenType.value = '';
            }

            if (!input.files || input.files.length === 0) {
                setDocumentError(input, 'Выберите файл');
                ok = false;
                return;
            }

            var message = validateSelectedFile(input.files[0], allowedCustomExtensions);
            if (message) {
                setDocumentError(input, message);
                ok = false;
            }
        });

        return ok;
    }

    function bindBlurNormalization() {
        var nameField = byName('name');
        var contactPersonField = byName('contact_person');
        var legalAddressField = byName('legal_address');
        var physicalAddressField = byName('physical_address');
        var contactPhoneField = byName('contact_phone');
        var bankNameField = byName('bank_name');
        var commentsField = byName('comments');

        nameField.addEventListener('blur', function () {
            nameField.value = normalizeSpaces(nameField.value);
        });
        contactPersonField.addEventListener('blur', function () {
            contactPersonField.value = normalizeSpaces(contactPersonField.value);
        });
        legalAddressField.addEventListener('blur', function () {
            legalAddressField.value = String(legalAddressField.value || '').trim();
        });
        physicalAddressField.addEventListener('blur', function () {
            physicalAddressField.value = String(physicalAddressField.value || '').trim();
        });
        bankNameField.addEventListener('blur', function () {
            bankNameField.value = String(bankNameField.value || '').trim();
        });
        commentsField.addEventListener('blur', function () {
            commentsField.value = String(commentsField.value || '').trim();
        });
        contactPhoneField.addEventListener('blur', function () {
            var normalized = normalizePhone(contactPhoneField.value);
            contactPhoneField.value = normalized.value;
            setFieldState('contact_phone', normalized.error);
        });

        Array.prototype.forEach.call([
            byName('inn'),
            byName('kpp'),
            byName('ogrn'),
            byName('bank_account'),
            byName('bank_bik'),
            byName('bank_corr_account')
        ], function (input) {
            input.addEventListener('blur', function () {
                input.value = stripSpacesAndHyphens(input.value);
            });
        });

        byName('contact_email').addEventListener('blur', function () {
            byName('contact_email').value = String(byName('contact_email').value || '').trim();
        });
    }

    function buildCustomDocRow() {
        if (!customContainer) {
            return;
        }

        var row = document.createElement('div');
        var idx = customContainer.children.length;
        var badgeId = 'cbadge-' + idx;
        var metaId = 'cmeta-' + idx;
        var btnLabelId = 'cbtnlabel-' + idx;
        var inputId = 'cinput-' + idx;

        row.className = 'file-item custom-doc-row document-file-row is-empty';
        row.innerHTML =
            '<div class="file-type-badge file-type-badge-empty" id="' + badgeId + '">—</div>' +
            '<div class="file-info">' +
                '<div class="field custom-doc-title-field">' +
                    '<input type="hidden" name="custom_doc_type[]" value="">' +
                    '<input type="text" name="custom_doc_type_new[]" class="field-input" placeholder="Введите название">' +
                    '<div class="field-msg"></div>' +
                '</div>' +
                '<div class="file-meta is-hidden" id="' + metaId + '"></div>' +
            '</div>' +
            '<button type="button" class="btn btn-secondary js-file-pick-btn" data-file-input="' + inputId + '">' +
                '<span id="' + btnLabelId + '">Выбрать</span>' +
            '</button>' +
            '<button type="button" class="file-remove" title="Удалить документ">×</button>' +
            '<input type="file" id="' + inputId + '" class="file-input-hidden js-custom-file-input" name="custom_doc_file[]" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" data-label="' + metaId + '" data-badge="' + badgeId + '" data-button-label="' + btnLabelId + '">';

        customContainer.appendChild(row);
        bindFileRow(row);

        row.querySelector('.file-remove').addEventListener('click', function () {
            customContainer.removeChild(row);
        });
    }

    function bindFileRow(scope) {
        Array.prototype.forEach.call(scope.querySelectorAll('.js-file-pick-btn'), function (btn) {
            if (btn.dataset.bound === '1') {
                return;
            }
            btn.dataset.bound = '1';
            btn.addEventListener('click', function () {
                var inputId = btn.getAttribute('data-file-input');
                var input = inputId ? document.getElementById(inputId) : null;
                if (input) {
                    input.click();
                }
            });
        });

        Array.prototype.forEach.call(scope.querySelectorAll('.js-predef-file-input, .js-custom-file-input'), function (input) {
            if (input.dataset.bound === '1') {
                return;
            }
            input.dataset.bound = '1';
            input.addEventListener('change', function () {
                clearDocumentError(input);
                paintDocumentFileState(input, summarizeFile(input));
            });
        });
    }

    Array.prototype.forEach.call(createForm.querySelectorAll('.predef-file-clear'), function (btn) {
        if (btn.dataset.bound === '1') {
            return;
        }
        btn.dataset.bound = '1';
        btn.addEventListener('click', function () {
            var input = btn.id ? document.getElementById(btn.id.replace('fclear', 'predef-file')) : null;
            if (!input) {
                return;
            }
            input.value = '';
            paintDocumentFileState(input, neutralSummary());
        });
    });

    bindFileRow(createForm);
    bindBlurNormalization();

    if (addDocBtn) {
        addDocBtn.addEventListener('click', function (event) {
            event.preventDefault();
            buildCustomDocRow();
        });
    }

    createForm.addEventListener('submit', function (event) {
        if (!validateForm()) {
            event.preventDefault();
            var firstError = createForm.querySelector('.field.is-error .field-input, .field.is-error .field-select, .field.is-error .field-textarea, .file-item.is-error');
            if (firstError && typeof firstError.focus === 'function') {
                firstError.focus();
            }
            return;
        }

        var submitBtn = createForm.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Создание...';
        }
    });
})();
</script>

<?php endif; ?>
