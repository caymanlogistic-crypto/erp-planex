<div class="modal-body">
<?php
    $leEntityType = 'contractor';
    $leFormAction = app_url('/company/contractors/' . ((int) ($contractor['id'] ?? 0)) . '/modal-edit');
    $leFormId = 'contractor-edit-form';
    $leIsModal = true;
    $leOld = $old ?? $contractor ?? [];
    $leErrors = $errors ?? [];
    $leFormError = $formError ?? null;
    $leDocTypes = $leDocTypes ?? [];
    $leContactValues = $leContactValues ?? $leOld['contacts'] ?? [];
    $leContactErrors = $leContactErrors ?? $leErrors['contacts'] ?? [];
    $leSubmitLabel = 'Сохранить';
    $leShowContacts = true;
    $leShowBankDetails = true;
    $leShowDocuments = true;
    $leShowStatus = false;
    $leShowInlineActions = false;
    $leInnLookupUrl = app_url('/company/requisites/lookup-by-inn');
    $leExistingDocs = $leExistingDocs ?? [];
    $leHiddenFields = ['status' => $leOld['status'] ?? 'active'];
    require base_path('app/View/partials/legal_entity_create_form.php');
?>
</div>

<div class="modal-foot is-spaced">
  <div class="modal-required-note"><span class="req">*</span> — обязательные поля</div>
  <div class="modal-foot-actions">
    <button type="button" class="btn btn-ghost" data-contractor-cancel-edit-btn>Отмена</button>
    <button type="submit" form="contractor-edit-form" class="btn btn-primary">Сохранить</button>
  </div>
</div>
