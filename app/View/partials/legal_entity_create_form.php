<?php
require_once base_path('app/View/components/contact_fields.php');
$leEntityType      = $leEntityType ?? 'contractor';
$leFormAction      = $leFormAction ?? '/company/contractors/create';
$leFormId          = $leFormId ?? 'le-create-form';
$leOld             = $leOld ?? [];
$leErrors          = $leErrors ?? [];
$leFormError       = $leFormError ?? null;
$leDocTypes        = $leDocTypes ?? [];
$leContactValues   = $leContactValues ?? [];
$leContactErrors   = $leContactErrors ?? [];
$leIsModal         = $leIsModal ?? true;
$leShowContacts    = $leShowContacts ?? true;
$leShowBankDetails = $leShowBankDetails ?? true;
$leShowDocuments   = $leShowDocuments ?? true;
$leSubmitLabel     = $leSubmitLabel ?? 'Создать';

$isCompany = $leEntityType === 'company' || $leEntityType === 'expeditor';

$leTypeFieldName = 'contractor_type';
if ($leEntityType === 'client') {
    $leTypeFieldName = 'entity_type';
}

if ($isCompany) {
    $leTitle = 'ЭКСПЕДИТОРА';
    $leLabel = 'экспедитора';
} elseif ($leEntityType === 'client') {
    $leTitle = 'КЛИЕНТА';
    $leLabel = 'клиента';
} else {
    $leTitle = 'ПЕРЕВОЗЧИКА';
    $leLabel = 'перевозчика';
}

$lePredefDocs = $lePredefDocs ?? [
    ['name' => 'Карточка предприятия', 'code' => 'company_card'],
    ['name' => 'Свидетельство ИНН', 'code' => 'inn_cert'],
    ['name' => 'Свидетельство ОГРН', 'code' => 'ogrn_cert'],
    ['name' => 'Договор', 'code' => 'contract'],
];

$leTypeOptions = [
    '' => '— Не указан —',
    'legal_entity' => 'Юридическое лицо',
    'individual' => 'Индивидуальный предприниматель',
    'self_employed' => 'Самозанятый',
    'private_person' => 'Физическое лицо',
];
?>
<form id="<?= e($leFormId) ?>" method="post" action="<?= e($leFormAction) ?>" class="" enctype="multipart/form-data" data-le-create-form="<?= e($leEntityType) ?>" data-inn-lookup-url="<?= e($leInnLookupUrl ?? app_url('/company/requisites/lookup-by-inn')) ?>">
<?php if ($leIsModal): ?>
<input type="hidden" name="is_modal" value="1">
<?php endif; ?>

<div class="entity-form-layout driver-layout">

    <div class="entity-form-main driver-layout-main">
        <div class="driver-fields">

<?php if ($leFormError): ?>
            <div class="form-alert alert-error">
                <div class="alert-mark">
                    <svg width="11" height="11" viewBox="0 0 18 18" fill="none"><path d="M9 2L16.5 15H1.5L9 2Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 7V11M9 13V13.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                </div>
                <div class="alert-body">
                    <div class="alert-body-title">Ошибка при сохранении</div>
                    <div class="alert-body-sub"><?= e($leFormError) ?></div>
                </div>
            </div>
<?php endif; ?>

            <div class="form-alert alert-warning is-hidden" data-inn-autofill-message>
                <div class="alert-body">
                    <div class="alert-body-title" data-inn-autofill-title></div>
                    <div class="alert-body-sub" data-inn-autofill-sub></div>
                </div>
            </div>

            <div class="driver-contact-top-row">
                <div class="field field-w-name<?= !empty($leErrors['name']) ? ' is-error' : '' ?>" data-field="name">
                    <label class="field-label">Наименование <span class="req">*</span></label>
                    <input type="text" name="name" class="field-input" placeholder='ООО "РОМАШКА"' value="<?= e($leOld['name'] ?? '') ?>">
                    <div class="field-msg"><?= !empty($leErrors['name']) ? e($leErrors['name']) : '' ?></div>
                </div>
                <div class="driver-contact-stack">
                    <div class="driver-contact-main-row">
                        <div class="field field-w-phone<?= !empty($leErrors['inn']) ? ' is-error' : '' ?>" data-field="inn">
                            <label class="field-label">ИНН <span class="req">*</span></label>
                            <input type="text" name="inn" class="field-input" inputmode="numeric" placeholder="7701234567" value="<?= e($leOld['inn'] ?? '') ?>">
                            <div class="field-msg"><?= !empty($leErrors['inn']) ? e($leErrors['inn']) : '' ?></div>
                        </div>
                        <button type="button" class="btn btn-ghost phone-add-btn" data-inn-autofill-btn>Заполнить по ИНН</button>
                    </div>
                </div>
            </div>

            <div class="form-grid-3 mt-3">
<?php if (!$isCompany): ?>
                <div class="field" data-field="<?= e($leTypeFieldName) ?>">
                    <label class="field-label">Тип <?= $leLabel ?></label>
                    <select name="<?= e($leTypeFieldName) ?>" class="field-select">
                        <?php foreach ($leTypeOptions as $val => $lbl): ?>
                        <option value="<?= e($val) ?>" <?= ($leOld[$leTypeFieldName] ?? '') === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
<?php else: ?>
                <div class="field" data-field="">
                    <label class="field-label">Тип</label>
                    <input type="text" class="field-input" value="Экспедитор" disabled>
                </div>
<?php endif; ?>
                <div class="field<?= !empty($leErrors['kpp']) ? ' is-error' : '' ?>" data-field="kpp">
                    <label class="field-label">КПП</label>
                    <input type="text" name="kpp" class="field-input" inputmode="numeric" placeholder="770101001" value="<?= e($leOld['kpp'] ?? '') ?>">
                    <div class="field-msg"><?= !empty($leErrors['kpp']) ? e($leErrors['kpp']) : '' ?></div>
                </div>
                <div class="field<?= !empty($leErrors['ogrn']) ? ' is-error' : '' ?>" data-field="ogrn">
                    <label class="field-label">ОГРН</label>
                    <input type="text" name="ogrn" class="field-input" inputmode="numeric" placeholder="1027700132195" value="<?= e($leOld['ogrn'] ?? '') ?>">
                    <div class="field-msg"><?= !empty($leErrors['ogrn']) ? e($leErrors['ogrn']) : '' ?></div>
                </div>
            </div>

            <div class="form-grid-2 mt-3">
                <div class="field" data-field="director_full_name">
                    <label class="field-label">Руководитель</label>
                    <input type="text" name="director_full_name" class="field-input" placeholder="ФИО руководителя" value="<?= e($leOld['director_full_name'] ?? '') ?>">
                </div>
                <div class="field" data-field="director_position">
                    <label class="field-label">Должность</label>
                    <input type="text" name="director_position" class="field-input" placeholder="Должность" value="<?= e($leOld['director_position'] ?? '') ?>">
                </div>
            </div>

            <div class="form-grid-2 mt-3">
                <div class="field" data-field="legal_address">
                    <label class="field-label">Юридический адрес</label>
                    <textarea name="legal_address" class="field-textarea driver-textarea" rows="2" placeholder="Юридический адрес"><?= e($leOld['legal_address'] ?? '') ?></textarea>
                </div>
                <div class="field" data-field="physical_address">
                    <label class="field-label">Фактический адрес</label>
                    <textarea name="physical_address" class="field-textarea driver-textarea" rows="2" placeholder="Фактический адрес"><?= e($leOld['physical_address'] ?? '') ?></textarea>
                </div>
            </div>

<?php if ($leShowContacts && !$isCompany): ?>
            <div class="mt-3">
                <div class="section-title">Контакты</div>
                <?php renderContactFields([
                    'entity_type' => $leEntityType,
                    'field_prefix' => 'contacts',
                    'contacts' => $leContactValues,
                    'errors' => $leContactErrors,
                    'allow_primary' => true,
                    'allow_document_email' => true,
                ]); ?>
            </div>
<?php endif; ?>

<?php if ($leShowBankDetails && !$isCompany): ?>
            <div class="mt-3">
                <div class="section-title">Банковские реквизиты</div>
                <div class="form-grid-4">
                    <div class="field<?= !empty($leErrors['bank_account']) ? ' is-error' : '' ?>" data-field="bank_account">
                        <label class="field-label">Расчётный счёт</label>
                        <input type="text" name="bank_account" class="field-input" inputmode="numeric" placeholder="40702810..." value="<?= e($leOld['bank_account'] ?? '') ?>">
                        <div class="field-msg"><?= !empty($leErrors['bank_account']) ? e($leErrors['bank_account']) : '' ?></div>
                    </div>
                    <div class="field<?= !empty($leErrors['bank_bik']) ? ' is-error' : '' ?>" data-field="bank_bik">
                        <label class="field-label">БИК</label>
                        <input type="text" name="bank_bik" class="field-input" inputmode="numeric" placeholder="044525225" value="<?= e($leOld['bank_bik'] ?? '') ?>">
                        <div class="field-msg"><?= !empty($leErrors['bank_bik']) ? e($leErrors['bank_bik']) : '' ?></div>
                    </div>
                    <div class="field" data-field="bank_name">
                        <label class="field-label">Банк</label>
                        <input type="text" name="bank_name" class="field-input" placeholder='АО "БАНК"' value="<?= e($leOld['bank_name'] ?? '') ?>">
                    </div>
                    <div class="field<?= !empty($leErrors['bank_corr_account']) ? ' is-error' : '' ?>" data-field="bank_corr_account">
                        <label class="field-label">Корр. счёт</label>
                        <input type="text" name="bank_corr_account" class="field-input" inputmode="numeric" placeholder="30101810..." value="<?= e($leOld['bank_corr_account'] ?? '') ?>">
                        <div class="field-msg"><?= !empty($leErrors['bank_corr_account']) ? e($leErrors['bank_corr_account']) : '' ?></div>
                    </div>
                </div>
            </div>
<?php endif; ?>

            <div class="field mt-3" data-field="comments">
                <label class="field-label">Комментарий</label>
                <textarea name="comments" class="field-textarea driver-textarea" rows="2" placeholder="Примечания"><?= e($leOld['comments'] ?? '') ?></textarea>
            </div>

            <div class="form-actions mt-4">
                <button type="submit" class="btn btn-primary"><?= e($leSubmitLabel) ?></button>
            </div>

        </div>
    </div>

<?php if ($leShowDocuments && !$isCompany): ?>
    <div class="entity-form-docs driver-layout-docs">
        <div>
            <div class="section-title">Документы</div>
        </div>
        <div class="file-list">
            <?php foreach ($lePredefDocs as $pdoc): ?>
            <div class="file-item file-item-predef document-file-row is-empty">
                <div class="file-type-badge file-type-badge-empty" id="le-fbadge-<?= e($pdoc['code']) ?>">—</div>
                <div class="file-info">
                    <div class="file-name"><?= e($pdoc['name']) ?></div>
                    <div class="file-meta" id="le-fname-<?= e($pdoc['code']) ?>">Файл не выбран</div>
                </div>
                <button type="button" class="btn btn-secondary file-action-btn js-file-pick-btn" data-file-input="le-predef-file-<?= e($pdoc['code']) ?>">
                    <span id="le-fbtn-<?= e($pdoc['code']) ?>">Выбрать</span>
                </button>
                <button type="button" class="predef-file-clear is-hidden" id="le-fclear-<?= e($pdoc['code']) ?>" title="Очистить файл">&times;</button>
                <input type="file" id="le-predef-file-<?= e($pdoc['code']) ?>" class="file-input-hidden js-predef-file-input"
                       name="predef_doc[<?= e($pdoc['code']) ?>]"
                       accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                       data-label="le-fname-<?= e($pdoc['code']) ?>"
                       data-badge="le-fbadge-<?= e($pdoc['code']) ?>"
                       data-button-label="le-fbtn-<?= e($pdoc['code']) ?>"
                       data-clear="le-fclear-<?= e($pdoc['code']) ?>">
                <input type="hidden" name="predef_doc_type[<?= e($pdoc['code']) ?>]" value="<?= e($pdoc['name']) ?>">
            </div>
            <?php endforeach; ?>
        </div>
        <div id="le-custom-docs-container" class="file-list"></div>
        <button type="button" class="btn btn-ghost" id="le-add-custom-doc-btn">+ Добавить документ</button>
    </div>
<?php endif; ?>

</div>

<script type="application/json" data-doc-types><?= json_encode($leDocTypes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
</form>
