<?php

$moneyAccounts = $moneyAccounts ?? [];
$formError = $formError ?? null;
?>
<?php if ($formError): ?>
<div class="form-alert alert-error"><?= e($formError) ?></div>
<?php endif; ?>
<form action="<?= app_url('/company/finance/cash/transfer-create') ?>" method="post" class="cash-form">
    <?= csrfField() ?>
    <div class="modal-body">
        <div class="section-title">Внутренний перевод</div>
        <div class="form-grid-3">
            <div class="field">
                <label class="field-label">Со счёта <span class="field-required">*</span></label>
                <select name="from_account_id" class="field-select" required>
                    <option value="">— Выберите счёт —</option>
                    <?php foreach ($moneyAccounts as $acc): ?>
                    <option value="<?= (int) $acc['id'] ?>">
                        <?= e($acc['name']) ?> (<?= $acc['type'] === 'CASH' ? 'Касса' : 'Банк' ?>, остаток: <?= \App\Service\FinanceCashService::formatAmount($acc['computed_balance'] ?? null) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label">На счёт <span class="field-required">*</span></label>
                <select name="to_account_id" class="field-select" required>
                    <option value="">— Выберите счёт —</option>
                    <?php foreach ($moneyAccounts as $acc): ?>
                    <option value="<?= (int) $acc['id'] ?>">
                        <?= e($acc['name']) ?> (<?= $acc['type'] === 'CASH' ? 'Касса' : 'Банк' ?>, остаток: <?= \App\Service\FinanceCashService::formatAmount($acc['computed_balance'] ?? null) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Сумма <span class="field-required">*</span></label>
                <input type="text" name="amount" class="field-input" placeholder="0.00" required>
            </div>
            <div class="field">
                <label class="field-label">Дата <span class="field-required">*</span></label>
                <input type="date" name="date" class="field-input" value="<?= e(date('Y-m-d')) ?>" required>
            </div>
        </div>
        <div class="field">
            <label class="field-label">Назначение</label>
            <input type="text" name="purpose" class="field-input" placeholder="Назначение перевода">
        </div>
        <div class="field">
            <label class="field-label">Комментарий</label>
            <textarea name="comment" class="field-textarea" rows="2" placeholder="Необязательный комментарий"></textarea>
        </div>
    </div>
    <div class="modal-foot is-spaced">
        <div class="modal-foot-actions">
            <button type="button" class="btn btn-ghost" data-close-modal="cash-transfer-create-modal">Отмена</button>
        </div>
        <div class="modal-foot-actions">
            <button type="submit" class="btn btn-primary">Выполнить перевод</button>
        </div>
    </div>
</form>
