<div class="modal-body">
  <form id="company-edit-form" method="post" action="<?= app_url('/superadmin/companies/' . ((int) ($company['id'] ?? 0)) . '/modal-edit') ?>" data-company-modal-edit-form>
    <div class="driver-fields">

    <?php if (!empty($formError)): ?>
    <div class="form-alert alert-error">
      <div class="alert-body">
        <div class="alert-body-title">Ошибка при сохранении</div>
        <div class="alert-body-sub"><?= e($formError) ?></div>
      </div>
    </div>
    <?php endif; ?>

    <div class="driver-contact-top-row">
      <div class="field field-w-name<?= !empty($errors['name']) ? ' is-error' : '' ?>">
        <label class="field-label">Наименование <span class="req">*</span></label>
        <input type="text" name="name" class="field-input" value="<?= e($old['name'] ?? $company['name'] ?? '') ?>">
        <div class="field-msg"><?= !empty($errors['name']) ? e($errors['name']) : '' ?></div>
      </div>
      <div class="field<?= !empty($errors['status']) ? ' is-error' : '' ?>">
        <label class="field-label">Статус <span class="req">*</span></label>
        <select name="status" class="field-select">
          <?php $currentStatus = $old['status'] ?? $company['status'] ?? 'active'; ?>
          <option value="active" <?= $currentStatus === 'active' ? 'selected' : '' ?>>Активен</option>
          <option value="inactive" <?= $currentStatus === 'inactive' ? 'selected' : '' ?>>Неактивен</option>
          <option value="blocked" <?= $currentStatus === 'blocked' ? 'selected' : '' ?>>Заблокирован</option>
          <option value="archived" <?= $currentStatus === 'archived' ? 'selected' : '' ?>>Архивирован</option>
          <option value="provisioning" <?= $currentStatus === 'provisioning' ? 'selected' : '' ?>>Настройка</option>
          <option value="error" <?= $currentStatus === 'error' ? 'selected' : '' ?>>Ошибка</option>
        </select>
        <div class="field-msg"><?= !empty($errors['status']) ? e($errors['status']) : '' ?></div>
      </div>
    </div>

    <div class="form-grid-3 mt-3">
      <div class="field<?= !empty($errors['inn']) ? ' is-error' : '' ?>">
        <label class="field-label">ИНН <span class="req">*</span></label>
        <input type="text" name="inn" class="field-input" value="<?= e($old['inn'] ?? $company['inn'] ?? '') ?>">
        <div class="field-msg"><?= !empty($errors['inn']) ? e($errors['inn']) : '' ?></div>
      </div>
      <div class="field">
        <label class="field-label">КПП</label>
        <input type="text" name="kpp" class="field-input" value="<?= e($old['kpp'] ?? $company['kpp'] ?? '') ?>">
      </div>
      <div class="field">
        <label class="field-label">ОГРН</label>
        <input type="text" name="ogrn" class="field-input" value="<?= e($old['ogrn'] ?? $company['ogrn'] ?? '') ?>">
      </div>
    </div>

    <div class="form-grid-2 mt-3">
      <div class="field">
        <label class="field-label">Руководитель</label>
        <input type="text" name="director_full_name" class="field-input" value="<?= e($old['director_full_name'] ?? $company['director_full_name'] ?? '') ?>">
      </div>
      <div class="field">
        <label class="field-label">Должность</label>
        <input type="text" name="director_position" class="field-input" value="<?= e($old['director_position'] ?? $company['director_position'] ?? '') ?>">
      </div>
    </div>

    <div class="form-grid-2 mt-3">
      <div class="field">
        <label class="field-label">Юридический адрес</label>
        <textarea name="legal_address" class="field-textarea driver-textarea" rows="2"><?= e($old['legal_address'] ?? $company['legal_address'] ?? '') ?></textarea>
      </div>
      <div class="field">
        <label class="field-label">Фактический адрес</label>
        <textarea name="physical_address" class="field-textarea driver-textarea" rows="2"><?= e($old['physical_address'] ?? $company['physical_address'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="field mt-3">
      <label class="field-label">Комментарий</label>
      <textarea name="comments" class="field-textarea driver-textarea" rows="2"><?= e($old['comments'] ?? $company['comments'] ?? '') ?></textarea>
    </div>

    </div>
  </form>
</div>

<div class="modal-foot is-spaced">
  <div class="modal-required-note"><span class="req">*</span> — обязательные поля</div>
  <div class="modal-foot-actions">
    <button type="button" class="btn btn-ghost" data-company-cancel-edit-btn>Отмена</button>
    <button type="submit" form="company-edit-form" class="btn btn-primary">Сохранить</button>
  </div>
</div>
