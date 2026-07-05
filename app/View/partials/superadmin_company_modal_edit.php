<div class="modal-body">
<?php
    $leEntityType = 'company';
    $leFormAction = app_url('/superadmin/companies/' . ((int) ($company['id'] ?? 0)) . '/modal-edit');
    $leFormId = 'company-edit-form';
    $leIsModal = true;
    $leOld = $old ?? $company ?? [];
    $leErrors = $errors ?? [];
    $leFormError = $formError ?? null;
    $leSubmitLabel = 'Сохранить';
    $leShowContacts = false;
    $leShowBankDetails = true;
    $leShowDocuments = true;
    $leShowStatus = true;
    $leShowInlineActions = false;
    $leInnLookupUrl = app_url('/superadmin/requisites/lookup-by-inn');
    require base_path('app/View/partials/legal_entity_create_form.php');
?>
</div>

<div class="modal-foot is-spaced">
  <div class="modal-required-note"><span class="req">*</span> — обязательные поля</div>
  <div class="modal-foot-actions">
    <button type="button" class="btn btn-ghost" data-company-cancel-edit-btn>Отмена</button>
    <button type="submit" form="company-edit-form" class="btn btn-primary">Сохранить</button>
  </div>
</div>
