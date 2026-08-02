<?php

$formError = $formError ?? null;
?>
<?php if ($formError): ?>
<div class="form-alert alert-error"><?= e($formError) ?></div>
<?php endif; ?>
<form action="<?= app_url('/company/finance/cash/account-create') ?>" method="post" class="cash-form">
    <?= csrfField() ?>
    <div class="modal-body">
        <div class="section-title">Новая касса</div>
        <div class="form-grid-3">
            <div class="field">
                <label class="field-label">Название <span class="field-required">*</span></label>
                <input type="text" name="name" class="field-input" placeholder="Основная касса" required>
            </div>
            <div class="field">
                <label class="field-label">Начальный остаток</label>
                <input type="text" name="opening_balance" class="field-input" placeholder="0.00">
            </div>
            <div class="field">
                <label class="field-label">Дата начального остатка</label>
                <input type="date" name="opening_balance_date" class="field-input">
            </div>
        </div>
        <div class="field">
            <label class="field-label">Комментарий</label>
            <textarea name="comment" class="field-textarea" rows="2" placeholder="Необязательный комментарий"></textarea>
        </div>
    </div>
    <div class="modal-foot is-spaced">
        <div class="modal-foot-actions">
            <button type="button" class="btn btn-ghost" data-close-modal="cash-account-create-modal">Отмена</button>
        </div>
        <div class="modal-foot-actions">
            <button type="submit" class="btn btn-primary">Создать кассу</button>
        </div>
    </div>
</form>
