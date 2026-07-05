<?php
require_once base_path('app/View/components/client_contact_fields.php');

$clientContactValues = $old['contacts'] ?? $contacts ?? [];
$clientContactErrors = $errors['contacts'] ?? [];
?>
<div class="modal-body">
  <form id="client-edit-form" method="post" action="/company/clients/<?= (int) ($client['id'] ?? 0) ?>/modal-edit" enctype="multipart/form-data" data-client-modal-edit-form>
    <input type="hidden" name="status" value="<?= e((string) ($old['status'] ?? $client['status'] ?? 'active')) ?>">

    <div class="driver-fields">

    <?php if (!empty($formError)): ?>
    <div class="form-alert alert-error">
      <div class="alert-body">
        <div class="alert-body-title">Ошибка при сохранении</div>
        <div class="alert-body-sub"><?= e($formError) ?></div>
      </div>
    </div>
    <?php endif; ?>

    <div class="field<?= !empty($errors['name']) ? ' is-error' : '' ?>">
      <label class="field-label">Наименование <span class="req">*</span></label>
      <input type="text" name="name" class="field-input" value="<?= e($old['name'] ?? $client['name'] ?? '') ?>">
      <div class="field-msg"><?= !empty($errors['name']) ? e($errors['name']) : '' ?></div>
    </div>

    <div class="form-grid-3 mt-3">
      <div class="field<?= !empty($errors['inn']) ? ' is-error' : '' ?>">
        <label class="field-label">ИНН <span class="req">*</span></label>
        <input type="text" name="inn" class="field-input" value="<?= e($old['inn'] ?? $client['inn'] ?? '') ?>">
        <div class="field-msg"><?= !empty($errors['inn']) ? e($errors['inn']) : '' ?></div>
      </div>
      <div class="field">
        <label class="field-label">КПП</label>
        <input type="text" name="kpp" class="field-input" value="<?= e($old['kpp'] ?? $client['kpp'] ?? '') ?>">
      </div>
      <div class="field">
        <label class="field-label">ОГРН</label>
        <input type="text" name="ogrn" class="field-input" value="<?= e($old['ogrn'] ?? $client['ogrn'] ?? '') ?>">
      </div>
    </div>

    <div class="form-grid-2 mt-3">
      <div class="field">
        <label class="field-label">Юридический адрес</label>
        <textarea name="legal_address" class="field-textarea driver-textarea" rows="2"><?= e($old['legal_address'] ?? $client['legal_address'] ?? '') ?></textarea>
      </div>
      <div class="field">
        <label class="field-label">Фактический адрес</label>
        <textarea name="physical_address" class="field-textarea driver-textarea" rows="2"><?= e($old['physical_address'] ?? $client['physical_address'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="mt-3">
      <div class="section-title">Контакты</div>
      <?php renderClientContactFields($clientContactValues, $clientContactErrors); ?>
    </div>

    <div class="field mt-3">
      <label class="field-label">Комментарий</label>
      <textarea name="comments" class="field-textarea driver-textarea" rows="2"><?= e($old['comments'] ?? $client['comments'] ?? '') ?></textarea>
    </div>

    </div><!-- /.driver-fields -->
  </form>
</div>

<div class="modal-foot is-spaced">
  <div class="modal-required-note"><span class="req">*</span> — обязательные поля</div>
  <div class="modal-foot-actions">
    <button type="button" class="btn btn-ghost" data-client-cancel-edit-btn>Отмена</button>
    <button type="submit" form="client-edit-form" class="btn btn-primary">Сохранить</button>
  </div>
</div>

<script>
(function () {
    var form = document.querySelector('[data-client-modal-edit-form]');
    if (!form || !window.initContactFields || form.dataset.clientModalReady === '1') {
        return;
    }
    form.dataset.clientModalReady = '1';
    window.initContactFields(form, { fieldPrefix: 'contacts' });
})();
</script>
