<div class="modal-body">
<?php
    $leEntityType = 'client';
    $leFormAction = app_url('/company/clients/' . ((int) ($client['id'] ?? 0)) . '/modal-edit');
    $leFormId = 'client-edit-form';
    $leIsModal = true;
    $leOld = $old ?? $client ?? [];
    $leErrors = $errors ?? [];
    $leFormError = $formError ?? null;
    $leDocTypes = $leDocTypes ?? [];
    $leContactValues = $leContactValues ?? [];
    $leContactErrors = $leContactErrors ?? [];
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
    <button type="button" class="btn btn-ghost" data-client-cancel-edit-btn>Отмена</button>
    <button type="submit" form="client-edit-form" class="btn btn-primary">Сохранить</button>
  </div>
</div>
