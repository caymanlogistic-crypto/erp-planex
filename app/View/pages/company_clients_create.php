<?php
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
    <?php
    $leEntityType = 'client';
    $leFormAction = '/company/clients/create';
    $leFormId = 'le-client-create-form';
    $leIsModal = false;
    $leOld = $old;
    $leErrors = $errors;
    $leFormError = $formError;
    $leDocTypes = $docTypes ?? [];
    $leContactValues = $contactValues;
    $leContactErrors = $contactErrors;
    $leSubmitLabel = 'Создать клиента';
    require base_path('app/View/partials/legal_entity_create_form.php');
    ?>
</div>

<script>
(function () {
    var createForm = document.getElementById('le-client-create-form');
    if (!createForm) {
        return;
    }

    createForm.noValidate = true;

    if (window.initContactFields) {
        window.initContactFields(createForm, { fieldPrefix: 'contacts' });
    }
    if (window.initLegalEntityDocuments) {
        window.initLegalEntityDocuments(createForm);
    }

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

    function clearErrors() {
        createForm.querySelectorAll('[data-field]').forEach(function (field) {
            field.classList.remove('is-error');
        });
        createForm.querySelectorAll('[data-field] .field-msg').forEach(function (msg) {
            msg.textContent = '';
        });
    }

    function validateForm() {
        var ok = true;
        clearErrors();

        var nameField = byName('name');
        var innField = byName('inn');
        var kppField = byName('kpp');
        var ogrnField = byName('ogrn');
        var bankAccountField = byName('bank_account');
        var bankBikField = byName('bank_bik');
        var bankCorrAccountField = byName('bank_corr_account');

        nameField.value = normalizeSpaces(nameField.value);
        innField.value = stripSpacesAndHyphens(innField.value);
        kppField.value = stripSpacesAndHyphens(kppField.value);
        ogrnField.value = stripSpacesAndHyphens(ogrnField.value);
        bankAccountField.value = stripSpacesAndHyphens(bankAccountField.value);
        bankBikField.value = stripSpacesAndHyphens(bankBikField.value);
        bankCorrAccountField.value = stripSpacesAndHyphens(bankCorrAccountField.value);

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

        if (kppField.value !== '' && !/^\d{9}$/.test(kppField.value)) {
            setFieldState('kpp', 'КПП: 9 цифр');
            ok = false;
        }

        if (ogrnField.value !== '' && (!/^\d+$/.test(ogrnField.value) || (ogrnField.value.length !== 13 && ogrnField.value.length !== 15))) {
            setFieldState('ogrn', 'ОГРН: 13 или 15 цифр');
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

        if (window.validateLegalEntityDocuments && !window.validateLegalEntityDocuments(createForm)) {
            ok = false;
        }

        return ok;
    }

    createForm.addEventListener('submit', function (event) {
        if (typeof window.erpCheckUploadSize === 'function') {
            var uc = window.erpCheckUploadSize(createForm);
            if (!uc.ok) { event.preventDefault(); return; }
        }
        if (!validateForm()) {
            event.preventDefault();
            var firstError = createForm.querySelector('.field.is-error .field-input, .field.is-error .field-select, .file-item.is-error, .field.is-error .field-textarea');
            if (firstError && typeof firstError.focus === 'function') {
                firstError.focus();
            }
            return;
        }

        createForm.dataset.submitting = '1';
        createForm.querySelectorAll('.js-predef-file-input, .js-custom-file-input').forEach(function (input) {
            if (input.files && input.files.length > 0) {
                var badge = input.getAttribute('data-badge') ? document.getElementById(input.getAttribute('data-badge')) : null;
                if (badge) {
                    badge.className = 'file-type-badge is-loading';
                    badge.textContent = '';
                }
            }
        });
        var submitBtn = createForm.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Создание...';
        }
    });

    var innAutofillBtn = createForm.querySelector('[data-inn-autofill-btn]');
    if (innAutofillBtn && window.runLegalEntityInnLookup) {
        innAutofillBtn.dataset.leInnReady = '1';
        innAutofillBtn.addEventListener('click', function (event) {
            event.preventDefault();
            window.runLegalEntityInnLookup(createForm, {
                button: innAutofillBtn,
                typeFieldName: 'entity_type',
                idleButtonText: 'Заполнить автоматически',
                loadingButtonText: 'Поиск...'
            });
        });
    }

    createForm.querySelectorAll('.field-input, .field-textarea').forEach(function (input) {
        if (input.name === 'name') {
            input.addEventListener('blur', function () { input.value = normalizeSpaces(input.value); });
        }
    });
    [byName('inn'), byName('kpp'), byName('ogrn'), byName('bank_account'), byName('bank_bik'), byName('bank_corr_account')].forEach(function (input) {
        if (input) {
            input.addEventListener('blur', function () { input.value = stripSpacesAndHyphens(input.value); });
        }
    });
})();
</script>

<?php endif; ?>
