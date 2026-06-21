<?php
require_once __DIR__ . '/../components/client_contact_fields.php';
$contactValues = $old['contacts'] ?? [];
$contactErrors = $errors['contacts'] ?? [];
?>

<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Создать клиента</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/clients" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="page-content">
    <div class="form-alert alert-warning">
        <div class="alert-body">
            <div class="alert-body-title">Компания неактивна</div>
            <div class="alert-body-sub">Статус компании: «<?= e($company['status']) ?>». Создание клиентов недоступно.</div>
        </div>
    </div>
</div>

<?php elseif ($success): ?>

<div class="page-head">
    <div>
        <h1>Клиент создан</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/clients" class="btn btn-primary">← К списку</a>
    </div>
</div>

<div class="page-content">
    <div class="panel">
        <div class="panel-body">
            <div class="form-alert alert-success">
                <div class="alert-body">
                    <div class="alert-body-title">Клиент успешно создан</div>
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
                <div class="section-title">ДАННЫЕ КЛИЕНТА</div>
                <dl class="dl">
                    <dt>Наименование</dt>
                    <dd><?= e($createdClient['name']) ?></dd>
                    <dt>ИНН</dt>
                    <dd class="mono"><?= e($createdClient['inn']) ?></dd>
                    <?php if (!empty($createdClient['entity_type'])): ?>
                    <dt>Тип</dt>
                    <dd><?= e(ui_contractor_type($createdClient['entity_type'])) ?></dd>
                    <?php endif; ?>
                    <dt>Статус</dt>
                    <dd><span class="badge badge-ok">Активен</span></dd>
                </dl>
            </div>

            <div class="form-actions">
                <a href="/company/clients" class="btn btn-primary">← К списку клиентов</a>
                <a href="/company/clients/create" class="btn btn-secondary">Создать ещё</a>
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
        <h1>Создать клиента</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/clients" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="page-content">
    <form method="post" action="/company/clients/create" class="panel" enctype="multipart/form-data" data-client-create-form>
        <div class="entity-form-layout driver-layout">
            <div class="entity-form-main driver-layout-main">

                <div class="section-title">ДАННЫЕ КЛИЕНТА</div>

                <?php if ($formError): ?>
                <div class="form-alert alert-error">
                    <div class="alert-body">
                        <div class="alert-body-title">Ошибка при сохранении</div>
                        <div class="alert-body-sub"><?= e($formError) ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-alert alert-warning is-hidden" data-inn-autofill-message>
                    <div class="alert-body">
                        <div class="alert-body-title" data-inn-autofill-title></div>
                        <div class="alert-body-sub" data-inn-autofill-sub></div>
                    </div>
                </div>
                <div class="form-section">
                    <div class="form-grid-2 client-autofill-grid">
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

                        <div class="field client-autofill-action">
                            <label class="field-label">Автозаполнение</label>
                            <button type="button" class="btn btn-secondary" data-inn-autofill-btn>Заполнить автоматически</button>
                            <div class="field-msg"></div>
                        </div>
                    </div>

                    <div class="form-grid-3">
                        <div class="field" data-field="entity_type">
                            <label class="field-label">Тип клиента</label>
                            <select name="entity_type" class="field-select">
                                <option value="">— Не указан —</option>
                                <option value="legal_entity" <?= ($old['entity_type'] ?? '') === 'legal_entity' ? 'selected' : '' ?>>Юридическое лицо</option>
                                <option value="individual" <?= ($old['entity_type'] ?? '') === 'individual' ? 'selected' : '' ?>>Индивидуальный предприниматель</option>
                                <option value="self_employed" <?= ($old['entity_type'] ?? '') === 'self_employed' ? 'selected' : '' ?>>Самозанятый</option>
                                <option value="private_person" <?= ($old['entity_type'] ?? '') === 'private_person' ? 'selected' : '' ?>>Физическое лицо</option>
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

                    <div class="form-grid-2">
                        <div class="field" data-field="director_full_name">
                            <label class="field-label">Руководитель</label>
                            <input type="text"
                                   name="director_full_name"
                                   class="field-input"
                                   placeholder="ФИО руководителя"
                                   value="<?= e($old['director_full_name'] ?? '') ?>">
                            <div class="field-msg"></div>
                        </div>

                        <div class="field" data-field="director_position">
                            <label class="field-label">Должность руководителя</label>
                            <input type="text"
                                   name="director_position"
                                   class="field-input"
                                   placeholder="Должность"
                                   value="<?= e($old['director_position'] ?? '') ?>">
                            <div class="field-msg"></div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">РЕКВИЗИТЫ</div>

                    <div class="form-grid-2 client-address-grid">
                    <div class="field" data-field="legal_address">
                        <label class="field-label">Юридический адрес</label>
                        <textarea name="legal_address"
                                  class="field-textarea"
                                  rows="3"
                                  placeholder="Юридический адрес клиента"><?= e($old['legal_address'] ?? '') ?></textarea>
                        <div class="field-msg"></div>
                    </div>

                    <div class="field" data-field="physical_address">
                        <label class="field-label">Фактический адрес</label>
                        <textarea name="physical_address"
                                  class="field-textarea"
                                  rows="3"
                                  placeholder="Фактический адрес клиента"><?= e($old['physical_address'] ?? '') ?></textarea>
                        <div class="field-msg"></div>
                    </div>
                </div>
                </div>

                <?php renderClientContactFields($contactValues, $contactErrors); ?>

                <div class="form-section">
                    <div class="section-title">БАНКОВСКИЕ РЕКВИЗИТЫ</div>

                    <div class="form-grid-4 client-bank-grid">
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
                </div>

                <div class="field" data-field="comments">
                    <label class="field-label">Комментарий</label>
                    <textarea name="comments"
                              class="field-textarea driver-textarea"
                              rows="3"
                              placeholder="Примечания по клиенту"><?= e($old['comments'] ?? '') ?></textarea>
                    <div class="field-msg"></div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Создать клиента</button>
                </div>
            </div>

            <div class="entity-form-docs driver-layout-docs">
                <div>
                    <div class="section-title">ДОКУМЕНТЫ</div>
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
                               accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                               data-label="fname-<?= $pdoc['code'] ?>"
                               data-badge="fbadge-<?= $pdoc['code'] ?>"
                               data-button-label="fbtn-<?= $pdoc['code'] ?>"
                               data-clear="fclear-<?= $pdoc['code'] ?>">
                        <input type="hidden" name="predef_doc_type[<?= $pdoc['code'] ?>]" value="<?= e($pdoc['name']) ?>">
                    </div>
                    <?php endforeach; ?>
                </div>

                <div id="custom-docs-container" class="file-list"></div>
                <button type="button" class="btn btn-ghost" id="add-custom-doc-btn">+ Добавить документ</button>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    var createForm = document.querySelector('form[data-client-create-form]');
    var customContainer = document.getElementById('custom-docs-container');
    var addDocBtn = document.getElementById('add-custom-doc-btn');
    var docTypes = <?= json_encode($docTypes ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    var allowedPredefExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];
    var allowedCustomExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];
    var innAutofillBtn = createForm ? createForm.querySelector('[data-inn-autofill-btn]') : null;
    var innAutofillBox = createForm ? createForm.querySelector('[data-inn-autofill-message]') : null;
    var innAutofillTitle = createForm ? createForm.querySelector('[data-inn-autofill-title]') : null;
    var innAutofillSub = createForm ? createForm.querySelector('[data-inn-autofill-sub]') : null;
    var contactList = createForm ? createForm.querySelector('[data-client-contacts-list]') : null;
    var contactTemplate = createForm ? createForm.querySelector('[data-contact-template]') : null;
    var addContactBtn = createForm ? createForm.querySelector('[data-add-contact]') : null;

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

    function getContactRows() {
        return contactList ? contactList.querySelectorAll('[data-contact-row]') : [];
    }

    function clearFieldError(field) {
        if (!field) {
            return;
        }
        field.classList.remove('is-error');
        var msg = field.querySelector('.field-msg');
        if (msg) {
            msg.textContent = '';
        }
    }

    function clearContactFieldError(input) {
        if (!input) return;
        var wrap = input.closest('.contact-cell-wrap');
        if (wrap) wrap.classList.remove('is-error');
        input.classList.remove('is-error');
        var suffix = input.closest('.contact-input-suffix');
        if (suffix) suffix.classList.remove('is-error');
        var err = input.closest('.contact-cell-wrap') ? input.closest('.contact-cell-wrap').querySelector('[data-contact-error]') : null;
        if (err) err.textContent = '';
    }

    function setContactFieldError(input, message) {
        if (!input) {
            return;
        }
        var wrap = input.closest('.contact-cell-wrap');
        if (wrap) wrap.classList.add('is-error');
        var suffix = input.closest('.contact-input-suffix');
        if (suffix) {
            suffix.classList.add('is-error');
        } else {
            input.classList.add('is-error');
        }
        var err = input.closest('.contact-cell-wrap') ? input.closest('.contact-cell-wrap').querySelector('[data-contact-error]') : null;
        if (err) err.textContent = message || '';
    }

    function renameContactRows() {
        Array.prototype.forEach.call(getContactRows(), function (row, index) {
            var map = {
                '[data-contact-person]': 'contact_person',
                '[data-contact-phone]': 'phone',
                '[data-contact-email]': 'email',
                '[data-contact-comment]': 'comment',
                '[data-contact-primary]': 'is_primary',
                '[data-contact-document-email]': 'is_document_email'
            };

            Object.keys(map).forEach(function (selector) {
                var input = row.querySelector(selector);
                if (input) {
                    input.name = 'contacts[' + index + '][' + map[selector] + ']';
                }
            });
        });
    }

    function ensureSinglePrimary(current) {
        if (!current || !current.checked) {
            return;
        }
        Array.prototype.forEach.call(createForm.querySelectorAll('[data-contact-primary]'), function (checkbox) {
            if (checkbox !== current) {
                checkbox.checked = false;
            }
        });
    }

    function bindContactRow(row) {
        if (!row) {
            return;
        }

        var removeBtn = row.querySelector('[data-remove-contact]');
        var primaryCheckbox = row.querySelector('[data-contact-primary]');
        var emailField = row.querySelector('[data-contact-email]');
        var phoneField = row.querySelector('[data-contact-phone]');
        var personField = row.querySelector('[data-contact-person]');
        var commentField = row.querySelector('[data-contact-comment]');

        if (removeBtn && removeBtn.dataset.bound !== '1') {
            removeBtn.dataset.bound = '1';
            removeBtn.addEventListener('click', function () {
                var rows = getContactRows();
                if (rows.length <= 1) {
                    Array.prototype.forEach.call(row.querySelectorAll('input[type="text"], textarea'), function (input) {
                        input.value = '';
                    });
                    Array.prototype.forEach.call(row.querySelectorAll('input[type="checkbox"]'), function (input, index) {
                        input.checked = index === 0;
                    });
            Array.prototype.forEach.call(row.querySelectorAll('.contact-input-suffix'), function(w) { w.classList.remove('is-error'); });
            Array.prototype.forEach.call(row.querySelectorAll('.field-input.is-error'), function(i) { i.classList.remove('is-error'); });
            Array.prototype.forEach.call(row.querySelectorAll('.contact-cell-wrap'), function(w) { w.classList.remove('is-error'); });
            Array.prototype.forEach.call(row.querySelectorAll('[data-contact-error]'), function(e) { e.textContent = ''; });
                    return;
                }
                row.remove();
                renameContactRows();
            });
        }

        if (primaryCheckbox && primaryCheckbox.dataset.bound !== '1') {
            primaryCheckbox.dataset.bound = '1';
            primaryCheckbox.addEventListener('change', function () {
                ensureSinglePrimary(primaryCheckbox);
            });
        }

        if (personField && personField.dataset.bound !== '1') {
            personField.dataset.bound = '1';
            personField.addEventListener('blur', function () {
                personField.value = normalizeSpaces(personField.value);
            });
        }

        if (commentField && commentField.dataset.bound !== '1') {
            commentField.dataset.bound = '1';
            commentField.addEventListener('blur', function () {
                commentField.value = String(commentField.value || '').trim();
            });
        }

        if (emailField && emailField.dataset.bound !== '1') {
            emailField.dataset.bound = '1';
            emailField.addEventListener('blur', function () {
                emailField.value = String(emailField.value || '').trim();
            });
        }

        if (phoneField && phoneField.dataset.bound !== '1') {
            phoneField.dataset.bound = '1';
            phoneField.addEventListener('blur', function () {
                var normalized = normalizePhone(phoneField.value);
                phoneField.value = normalized.value;
                if (normalized.error) {
                    setContactFieldError(phoneField, normalized.error);
                } else {
                    clearContactFieldError(phoneField);
                }
            });
        }
    }

    function addContactRow() {
        if (!contactTemplate || !contactTemplate.content || !contactList) {
            return;
        }
        var fragment = contactTemplate.content.cloneNode(true);
        var row = fragment.querySelector('[data-contact-row]');
        contactList.appendChild(fragment);
        if (row) {
            bindContactRow(row);
        }
        renameContactRows();
    }

    function setAutofillMessage(type, title, message) {
        if (!innAutofillBox || !innAutofillTitle || !innAutofillSub) {
            return;
        }

        innAutofillBox.hidden = false;
        innAutofillBox.classList.remove('is-hidden');
        innAutofillBox.classList.remove('alert-success', 'alert-warning', 'alert-error');
        innAutofillBox.classList.add(type === 'success' ? 'alert-success' : (type === 'error' ? 'alert-error' : 'alert-warning'));
        innAutofillTitle.textContent = title || '';
        innAutofillSub.textContent = message || '';
    }

    function clearAutofillMessage() {
        if (!innAutofillBox || !innAutofillTitle || !innAutofillSub) {
            return;
        }

        innAutofillBox.hidden = true;
        innAutofillBox.classList.add('is-hidden');
        innAutofillBox.classList.remove('alert-success', 'alert-warning', 'alert-error');
        innAutofillBox.classList.add('alert-warning');
        innAutofillTitle.textContent = '';
        innAutofillSub.textContent = '';
    }

    function isAutofilledField(input) {
        return !!(input && input.dataset && input.dataset.autofilled === '1');
    }

    function markAutofilledField(input, value) {
        if (!input || !input.dataset) {
            return;
        }

        input.dataset.autofilled = '1';
        input.dataset.autofillValue = String(value || '');
        input.classList.add('is-autofilled');
    }

    function clearAutofilledField(input) {
        if (!input || !input.dataset) {
            return;
        }

        delete input.dataset.autofilled;
        delete input.dataset.autofillValue;
        input.classList.remove('is-autofilled');
    }

    function setFieldValue(name, value, options) {
        var input = byName(name);
        var opts = options || {};
        if (!input || !value) {
            return false;
        }

        if (input.tagName === 'SELECT') {
            if (!opts.overwrite && String(input.value || '').trim() !== '') {
                return false;
            }
            input.value = value;
            if (input.value === value) {
                markAutofilledField(input, value);
                return true;
            }
            return false;
        }

        if (!opts.overwrite && String(input.value || '').trim() !== '') {
            return false;
        }

        input.value = value;
        markAutofilledField(input, value);

        if (input.tagName === 'TEXTAREA') {
            input.style.height = 'auto';
            input.style.height = input.scrollHeight + 'px';
        }

        return true;
    }

    function applyInnLookupData(payload) {
        var data = payload && payload.data ? payload.data : {};
        var filledCount = 0;

        if (byName('inn')) {
            byName('inn').value = stripSpacesAndHyphens(data.inn || byName('inn').value);
        }

        var overwriteIfAutofilled = function (name) {
            return isAutofilledField(byName(name));
        };

        [
            ['name', data.name, overwriteIfAutofilled('name')],
            ['entity_type', data.entity_type, overwriteIfAutofilled('entity_type')],
            ['kpp', data.kpp, true],
            ['ogrn', data.ogrn, true],
            ['legal_address', data.legal_address, true],
            ['director_full_name', data.director_full_name, true],
            ['director_position', data.director_position, true]
        ].forEach(function (pair) {
            if (setFieldValue(pair[0], pair[1], { overwrite: !!pair[2] })) {
                filledCount += 1;
            }
        });

        return filledCount;
    }

    function clearClientErrors() {
        clearAutofillMessage();
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
        if (['jpg', 'jpeg', 'png', 'webp'].indexOf(ext) !== -1) return 'photo';
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
        bankAccountField.value = stripSpacesAndHyphens(bankAccountField.value);
        bankBikField.value = stripSpacesAndHyphens(bankBikField.value);
        bankCorrAccountField.value = stripSpacesAndHyphens(bankCorrAccountField.value);
        bankNameField.value = String(bankNameField.value || '').trim();
        commentsField.value = String(commentsField.value || '').trim();

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

        var meaningfulContactRows = [];
        Array.prototype.forEach.call(getContactRows(), function (row) {
            Array.prototype.forEach.call(row.querySelectorAll('.contact-input-suffix'), function(w) { w.classList.remove('is-error'); });
            Array.prototype.forEach.call(row.querySelectorAll('.field-input.is-error'), function(i) { i.classList.remove('is-error'); });
            Array.prototype.forEach.call(row.querySelectorAll('.contact-cell-wrap'), function(w) { w.classList.remove('is-error'); });
            Array.prototype.forEach.call(row.querySelectorAll('[data-contact-error]'), function(e) { e.textContent = ''; });

            var personField = row.querySelector('[data-contact-person]');
            var phoneField = row.querySelector('[data-contact-phone]');
            var emailField = row.querySelector('[data-contact-email]');
            var commentField = row.querySelector('[data-contact-comment]');
            var primaryField = row.querySelector('[data-contact-primary]');
            var documentEmailField = row.querySelector('[data-contact-document-email]');

            personField.value = normalizeSpaces(personField.value);
            phoneField.value = normalizeSpaces(phoneField.value);
            emailField.value = String(emailField.value || '').trim();
            commentField.value = String(commentField.value || '').trim();

            var meaningful = personField.value !== '' || phoneField.value !== '' || emailField.value !== '' || commentField.value !== '';
            if (!meaningful) {
                primaryField.checked = false;
                documentEmailField.checked = false;
                return;
            }

            meaningfulContactRows.push(row);

            var normalizedPhone = normalizePhone(phoneField.value);
            phoneField.value = normalizedPhone.value;
            if (normalizedPhone.error) {
                setContactFieldError(phoneField, normalizedPhone.error);
                ok = false;
            }

            if (emailField.value !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailField.value)) {
                setContactFieldError(emailField, 'Некорректный email');
                ok = false;
            }

            if (documentEmailField.checked && emailField.value === '') {
                documentEmailField.checked = false;
            }
        });

        var primaryRow = null;
        Array.prototype.forEach.call(meaningfulContactRows, function (row) {
            if (primaryRow) {
                return;
            }
            var checkbox = row.querySelector('[data-contact-primary]');
            if (checkbox && checkbox.checked) {
                primaryRow = row;
            }
        });
        if (!primaryRow && meaningfulContactRows.length > 0) {
            meaningfulContactRows[0].querySelector('[data-contact-primary]').checked = true;
        } else if (primaryRow) {
            ensureSinglePrimary(primaryRow.querySelector('[data-contact-primary]'));
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
        var entityTypeField = byName('entity_type');
        var kppField = byName('kpp');
        var ogrnField = byName('ogrn');
        var legalAddressField = byName('legal_address');
        var physicalAddressField = byName('physical_address');
        var bankNameField = byName('bank_name');
        var directorFullNameField = byName('director_full_name');
        var directorPositionField = byName('director_position');
        var commentsField = byName('comments');

        nameField.addEventListener('blur', function () {
            nameField.value = normalizeSpaces(nameField.value);
        });
        nameField.addEventListener('input', function () {
            clearAutofilledField(nameField);
        });
        entityTypeField.addEventListener('change', function () {
            clearAutofilledField(entityTypeField);
        });
        legalAddressField.addEventListener('blur', function () {
            legalAddressField.value = String(legalAddressField.value || '').trim();
        });
        legalAddressField.addEventListener('input', function () {
            clearAutofilledField(legalAddressField);
            legalAddressField.style.height = 'auto';
            legalAddressField.style.height = legalAddressField.scrollHeight + 'px';
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

        directorFullNameField.addEventListener('blur', function () {
            directorFullNameField.value = String(directorFullNameField.value || '').trim();
        });
        directorFullNameField.addEventListener('input', function () {
            clearAutofilledField(directorFullNameField);
        });

        directorPositionField.addEventListener('blur', function () {
            directorPositionField.value = String(directorPositionField.value || '').trim();
        });
        directorPositionField.addEventListener('input', function () {
            clearAutofilledField(directorPositionField);
        });

        Array.prototype.forEach.call([
            byName('inn'),
            kppField,
            ogrnField,
            byName('bank_account'),
            byName('bank_bik'),
            byName('bank_corr_account')
        ], function (input) {
            input.addEventListener('blur', function () {
                input.value = stripSpacesAndHyphens(input.value);
            });
        });
        kppField.addEventListener('input', function () {
            clearAutofilledField(kppField);
        });
        ogrnField.addEventListener('input', function () {
            clearAutofilledField(ogrnField);
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
            '<input type="file" id="' + inputId + '" class="file-input-hidden js-custom-file-input" name="custom_doc_file[]" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx" data-label="' + metaId + '" data-badge="' + badgeId + '" data-button-label="' + btnLabelId + '">';

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

    function runInnLookup() {
        var innField = byName('inn');
        var rawInn = stripSpacesAndHyphens(innField ? innField.value : '');

        clearAutofillMessage();
        setFieldState('inn', '');

        if (innField) {
            innField.value = rawInn;
        }

        if (rawInn === '') {
            setFieldState('inn', 'Укажите ИНН');
            setAutofillMessage('warning', 'Автозаполнение недоступно', 'Сначала введите ИНН.');
            if (innField && typeof innField.focus === 'function') {
                innField.focus();
            }
            return;
        }

        if (!/^\d+$/.test(rawInn) || (rawInn.length !== 10 && rawInn.length !== 12)) {
            setFieldState('inn', 'ИНН: 10 или 12 цифр');
            setAutofillMessage('warning', 'Некорректный ИНН', 'Укажите ИНН длиной 10 или 12 цифр.');
            if (innField && typeof innField.focus === 'function') {
                innField.focus();
            }
            return;
        }

        if (!innAutofillBtn) {
            return;
        }

        innAutofillBtn.disabled = true;
        innAutofillBtn.textContent = 'Поиск...';

        fetch('/company/requisites/lookup-by-inn', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ inn: rawInn })
        })
            .then(function (response) {
                return response.json().catch(function () {
                    return {
                        ok: false,
                        message: 'Не удалось получить данные. Заполните реквизиты вручную.'
                    };
                });
            })
            .then(function (payload) {
                if (!payload || payload.ok !== true) {
                    setAutofillMessage('warning', 'Автозаполнение не выполнено', payload && payload.message ? payload.message : 'Не удалось получить данные. Заполните реквизиты вручную.');
                    return;
                }

                applyInnLookupData(payload);
            })
            .catch(function () {
                setAutofillMessage('error', 'Сервис временно недоступен', 'Не удалось получить данные. Заполните реквизиты вручную.');
            })
            .finally(function () {
                innAutofillBtn.disabled = false;
                innAutofillBtn.textContent = 'Заполнить автоматически';
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
    Array.prototype.forEach.call(getContactRows(), bindContactRow);
    renameContactRows();

    if (innAutofillBtn) {
        innAutofillBtn.addEventListener('click', function (event) {
            event.preventDefault();
            runInnLookup();
        });
    }

    if (addContactBtn) {
        addContactBtn.addEventListener('click', function (event) {
            event.preventDefault();
            addContactRow();
        });
    }

    if (addDocBtn) {
        addDocBtn.addEventListener('click', function (event) {
            event.preventDefault();
            buildCustomDocRow();
        });
    }

    createForm.addEventListener('submit', function (event) {
        if (typeof window.erpCheckUploadSize === 'function') {
            var uc = window.erpCheckUploadSize(createForm);
            if (!uc.ok) { event.preventDefault(); return; }
        }
        if (!validateForm()) {
            event.preventDefault();
            var firstError = createForm.querySelector('.field.is-error .field-input, .field.is-error .field-select, .field.is-error .field-textarea, .file-item.is-error');
            if (firstError && typeof firstError.focus === 'function') {
                firstError.focus();
            }
            return;
        }

        createForm.dataset.submitting = '1';

        // Loading state на заполненных документах (как у водителя)
        Array.prototype.forEach.call(
            createForm.querySelectorAll('.js-predef-file-input'),
            function (input) {
                if (input.files && input.files.length > 0) {
                    var badge = input.getAttribute('data-badge') ? document.getElementById(input.getAttribute('data-badge')) : null;
                    var btnLabel = input.getAttribute('data-button-label') ? document.getElementById(input.getAttribute('data-button-label')) : null;
                    if (badge) {
                        badge.className = 'file-type-badge is-loading';
                        badge.textContent = '';
                    }
                    if (btnLabel) {
                        btnLabel.textContent = 'Загрузка...';
                    }
                }
            }
        );
        Array.prototype.forEach.call(
            createForm.querySelectorAll('.js-custom-file-input'),
            function (input) {
                if (input.files && input.files.length > 0) {
                    var badge = input.getAttribute('data-badge') ? document.getElementById(input.getAttribute('data-badge')) : null;
                    if (badge) {
                        badge.className = 'file-type-badge is-loading';
                        badge.textContent = '';
                    }
                }
            }
        );

        var submitBtn = createForm.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Создание...';
        }
    });
})();
</script>

<?php endif; ?>
