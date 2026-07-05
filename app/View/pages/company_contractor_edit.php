<?php

require_once __DIR__ . '/../components/status_badge.php';
require_once __DIR__ . '/../components/contractor_contact_fields.php';

$contactValues = $old['contacts'] ?? $contacts ?? [];
$contactErrors = $errors['contacts'] ?? [];

?>

<?php if ($company === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Компания не найдена. <a href="/company/contractors">← К списку</a>
        </div>
    </div>
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Редактировать перевозчика</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>».
</div>

<?php elseif (isset($dbError)): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice danger">
            <?= e($dbError) ?>
        </div>
        <div class="form-actions mt-4">
            <a href="/company/contractors" class="btn btn-ghost">← К списку</a>
        </div>
    </div>
</div>

<?php elseif ($contractor === null): ?>

<div class="page-head">
    <div>
        <h1>Перевозчик не найден</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Перевозчик с указанным ID не найден.
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Редактировать перевозчика</h1>
        <p class="text-muted"><?= e($contractor['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors/<?= $contractor['id'] ?>" class="btn btn-ghost">← К карточке</a>
    </div>
</div>

<?php if (!empty($formError)): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/company/contractors/<?= $contractor['id'] ?>/edit" class="panel" data-contractor-contact-form>
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Основные данные</h3>

            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label">Наименование <span class="req">*</span></label>
                    <input type="text" name="name" class="field-input<?= !empty($errors['name']) ? ' is-error' : '' ?>" required
                           value="<?= e($old['name'] ?? $contractor['name']) ?>">
                    <?php if (!empty($errors['name'])): ?>
                        <div class="field-msg is-error"><?= e($errors['name']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label class="field-label">Статус <span class="req">*</span></label>
                    <select name="status" class="field-select<?= !empty($errors['status']) ? ' is-error' : '' ?>">
                        <?php
                        $statuses = ['active' => 'Активен', 'inactive' => 'Неактивен', 'archived' => 'Архив'];
                        $currentStatus = $old['status'] ?? $contractor['status'];
                        foreach ($statuses as $val => $label):
                        ?>
                        <option value="<?= $val ?>" <?= $currentStatus === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($errors['status'])): ?>
                        <div class="field-msg is-error"><?= e($errors['status']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Реквизиты</h3>

            <div class="form-grid-4">
                <div class="field">
                    <label class="field-label">ИНН <span class="req">*</span></label>
                    <input type="text" name="inn" class="field-input<?= !empty($errors['inn']) ? ' is-error' : '' ?>" required
                           value="<?= e($old['inn'] ?? $contractor['inn']) ?>">
                    <?php if (!empty($errors['inn'])): ?>
                        <div class="field-msg is-error"><?= e($errors['inn']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label class="field-label">КПП</label>
                    <input type="text" name="kpp" class="field-input"
                           value="<?= e($old['kpp'] ?? $contractor['kpp'] ?? '') ?>">
                </div>

                <div class="field">
                    <label class="field-label">ОГРН</label>
                    <input type="text" name="ogrn" class="field-input"
                           value="<?= e($old['ogrn'] ?? $contractor['ogrn'] ?? '') ?>">
                </div>

                <div class="field">
                    <label class="field-label">Тип перевозчика</label>
                    <select name="contractor_type" class="field-select">
                        <?php $contractorType = $old['contractor_type'] ?? $contractor['contractor_type'] ?? ''; ?>
                        <option value="">— Не указан —</option>
                        <option value="legal_entity" <?= $contractorType === 'legal_entity' ? 'selected' : '' ?>>Юридическое лицо</option>
                        <option value="individual" <?= $contractorType === 'individual' ? 'selected' : '' ?>>Индивидуальный предприниматель</option>
                        <option value="self_employed" <?= $contractorType === 'self_employed' ? 'selected' : '' ?>>Самозанятый</option>
                        <option value="private_person" <?= $contractorType === 'private_person' ? 'selected' : '' ?>>Физическое лицо</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Адреса</h3>

            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label">Юридический адрес</label>
                    <textarea name="legal_address" class="field-textarea" rows="2"><?= e($old['legal_address'] ?? $contractor['legal_address'] ?? '') ?></textarea>
                </div>

                <div class="field">
                    <label class="field-label">Фактический адрес</label>
                    <textarea name="physical_address" class="field-textarea" rows="2"><?= e($old['physical_address'] ?? $contractor['physical_address'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Контакты</h3>
            <?php renderContractorContactFields($contactValues, $contactErrors); ?>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Банковские реквизиты</h3>

            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label">Расчётный счёт</label>
                    <input type="text" name="bank_account" class="field-input"
                           value="<?= e($old['bank_account'] ?? $contractor['bank_account'] ?? '') ?>">
                </div>

                <div class="field">
                    <label class="field-label">БИК</label>
                    <input type="text" name="bank_bik" class="field-input"
                           value="<?= e($old['bank_bik'] ?? $contractor['bank_bik'] ?? '') ?>">
                </div>
            </div>

            <div class="form-grid-2 mt-3">
                <div class="field">
                    <label class="field-label">Банк</label>
                    <input type="text" name="bank_name" class="field-input"
                           value="<?= e($old['bank_name'] ?? $contractor['bank_name'] ?? '') ?>">
                </div>

                <div class="field">
                    <label class="field-label">Корр. счёт</label>
                    <input type="text" name="bank_corr_account" class="field-input"
                           value="<?= e($old['bank_corr_account'] ?? $contractor['bank_corr_account'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Комментарий</h3>

            <div class="field">
                <textarea name="comments" class="field-textarea" rows="2"><?= e($old['comments'] ?? $contractor['comments'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Сохранить</button>
            <a href="/company/contractors/<?= $contractor['id'] ?>" class="btn btn-ghost">Отмена</a>
        </div>

    </div>
</form>

<script>
(function () {
    function bind() {
        var form = document.querySelector('[data-contractor-contact-form]');
        if (!form || !window.initContactFields) {
            return false;
        }
        window.initContactFields(form, { fieldPrefix: 'contacts' });
        return true;
    }
    if (bind()) {
        return;
    }
    window.addEventListener('load', bind, { once: true });
})();
</script>

<?php endif; ?>
