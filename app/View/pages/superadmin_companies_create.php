<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Создать экспедитора</h1>
        <div class="page-summary"><span>SUPERADMIN / Реестр компаний</span></div>
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
$leShowBankDetails = true;
$leShowDocuments = true;
$leShowInlineActions = true;
$leInnLookupUrl = app_url('/superadmin/requisites/lookup-by-inn');
require base_path('app/View/partials/legal_entity_create_form.php');
?>
<div class="field-hint">Данные руководителя используются в реквизитах, счетах и документах. Доступ в ERP для руководителя создаётся отдельно.</div>

</div><!-- /.page-content -->
