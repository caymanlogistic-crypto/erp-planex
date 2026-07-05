<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">SUPERADMIN / Реестр компаний</span>
        <span class="page-title">Создать экспедитора</span>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/superadmin/companies') ?>" class="btn btn-ghost">&larr; К реестру</a>
    </div>
</div>

<div class="page-content">

<?php if (isset($formError) && $formError !== null): ?>
    <div class="notice warn">
        <?= e($formError) ?>
    </div>
<?php endif; ?>

<?php
$leEntityType = 'company';
$leFormAction = '/superadmin/companies/create';
$leFormId = 'le-sa-company-create-form';
$leIsModal = false;
$leOld = $old;
$leErrors = $errors;
$leFormError = $formError;
$leSubmitLabel = 'Создать компанию';
$leShowContacts = false;
$leShowBankDetails = false;
$leShowDocuments = false;
require base_path('app/View/partials/legal_entity_create_form.php');
?>
<div class="field-hint">Данные руководителя используются в реквизитах, счетах и документах. Доступ в ERP для руководителя создаётся отдельно.</div>

</div><!-- /.page-content -->
