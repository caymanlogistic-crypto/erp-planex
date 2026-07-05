<?php
require_once base_path('app/View/components/contractor_contact_fields.php');

$contractorContactValues = $old['contacts'] ?? $contacts ?? [];
$contractorContactErrors = $errors['contacts'] ?? [];
?>
<div class="modal-body">
  <form id="contractor-edit-form" method="post" action="/company/contractors/<?= (int) ($contractor['id'] ?? 0) ?>/modal-edit" enctype="multipart/form-data" data-contractor-modal-edit-form>
    <input type="hidden" name="status" value="<?= e((string) ($old['status'] ?? $contractor['status'] ?? 'active')) ?>">

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
      <input type="text" name="name" class="field-input" value="<?= e($old['name'] ?? $contractor['name'] ?? '') ?>">
      <div class="field-msg"><?= !empty($errors['name']) ? e($errors['name']) : '' ?></div>
    </div>

    <div class="form-grid-4 mt-3">
      <div class="field<?= !empty($errors['inn']) ? ' is-error' : '' ?>">
        <label class="field-label">ИНН <span class="req">*</span></label>
        <input type="text" name="inn" class="field-input" value="<?= e($old['inn'] ?? $contractor['inn'] ?? '') ?>">
        <div class="field-msg"><?= !empty($errors['inn']) ? e($errors['inn']) : '' ?></div>
      </div>
      <div class="field">
        <label class="field-label">КПП</label>
        <input type="text" name="kpp" class="field-input" value="<?= e($old['kpp'] ?? $contractor['kpp'] ?? '') ?>">
      </div>
      <div class="field">
        <label class="field-label">ОГРН</label>
        <input type="text" name="ogrn" class="field-input" value="<?= e($old['ogrn'] ?? $contractor['ogrn'] ?? '') ?>">
      </div>
      <div class="field">
        <label class="field-label">Тип перевозчика</label>
        <?php $contractorType = $old['contractor_type'] ?? $contractor['contractor_type'] ?? ''; ?>
        <select name="contractor_type" class="field-select">
          <option value="">— Не указан —</option>
          <option value="legal_entity" <?= $contractorType === 'legal_entity' ? 'selected' : '' ?>>Юридическое лицо</option>
          <option value="individual" <?= $contractorType === 'individual' ? 'selected' : '' ?>>ИП</option>
          <option value="self_employed" <?= $contractorType === 'self_employed' ? 'selected' : '' ?>>Самозанятый</option>
          <option value="private_person" <?= $contractorType === 'private_person' ? 'selected' : '' ?>>Физическое лицо</option>
        </select>
      </div>
    </div>

    <div class="form-grid-2 mt-3">
      <div class="field">
        <label class="field-label">Юридический адрес</label>
        <textarea name="legal_address" class="field-textarea driver-textarea" rows="2"><?= e($old['legal_address'] ?? $contractor['legal_address'] ?? '') ?></textarea>
      </div>
      <div class="field">
        <label class="field-label">Фактический адрес</label>
        <textarea name="physical_address" class="field-textarea driver-textarea" rows="2"><?= e($old['physical_address'] ?? $contractor['physical_address'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="mt-3">
      <div class="section-title">Контакты</div>
      <?php renderContractorContactFields($contractorContactValues, $contractorContactErrors); ?>
    </div>

    <div class="form-grid-4 mt-3">
      <div class="field">
        <label class="field-label">Расчётный счёт</label>
        <input type="text" name="bank_account" class="field-input" value="<?= e($old['bank_account'] ?? $contractor['bank_account'] ?? '') ?>">
      </div>
      <div class="field">
        <label class="field-label">БИК</label>
        <input type="text" name="bank_bik" class="field-input" value="<?= e($old['bank_bik'] ?? $contractor['bank_bik'] ?? '') ?>">
      </div>
      <div class="field">
        <label class="field-label">Банк</label>
        <input type="text" name="bank_name" class="field-input" value="<?= e($old['bank_name'] ?? $contractor['bank_name'] ?? '') ?>">
      </div>
      <div class="field">
        <label class="field-label">Корр. счёт</label>
        <input type="text" name="bank_corr_account" class="field-input" value="<?= e($old['bank_corr_account'] ?? $contractor['bank_corr_account'] ?? '') ?>">
      </div>
    </div>

    <div class="field mt-3">
      <label class="field-label">Комментарий</label>
      <textarea name="comments" class="field-textarea driver-textarea" rows="2"><?= e($old['comments'] ?? $contractor['comments'] ?? '') ?></textarea>
    </div>

    </div><!-- /.driver-fields -->
  </form>
</div>

<div class="modal-foot is-spaced">
  <div class="modal-required-note"><span class="req">*</span> — обязательные поля</div>
  <div class="modal-foot-actions">
    <button type="button" class="btn btn-ghost" data-contractor-cancel-edit-btn>Отмена</button>
    <button type="submit" form="contractor-edit-form" class="btn btn-primary">Сохранить</button>
  </div>
</div>

<script>
(function () {
    var form = document.querySelector('[data-contractor-modal-edit-form]');
    if (!form || !window.initContactFields || form.dataset.contractorModalReady === '1') {
        return;
    }
    form.dataset.contractorModalReady = '1';
    window.initContactFields(form, { fieldPrefix: 'contacts' });
})();
</script>
