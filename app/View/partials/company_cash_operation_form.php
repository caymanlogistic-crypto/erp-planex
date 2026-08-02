<?php

$cashAccounts = $cashAccounts ?? [];
$ddsCategories = $ddsCategories ?? [];
$formError = $formError ?? null;
?>
<?php if ($formError): ?>
<div class="form-alert alert-error"><?= e($formError) ?></div>
<?php endif; ?>
<form action="<?= app_url('/company/finance/cash/operation-create') ?>" method="post" class="cash-form">
    <?= csrfField() ?>
    <div class="modal-body">
        <div class="section-title">Новая кассовая операция</div>
        <div class="form-grid-3">
            <div class="field">
                <label class="field-label">Тип <span class="field-required">*</span></label>
                <select name="operation_type" class="field-select" required>
                    <option value="INCOME">Приход</option>
                    <option value="EXPENSE">Расход</option>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Касса <span class="field-required">*</span></label>
                <select name="money_account_id" class="field-select" required>
                    <option value="">— Выберите кассу —</option>
                    <?php foreach ($cashAccounts as $acc): ?>
                    <option value="<?= (int) $acc['id'] ?>">
                        <?= e($acc['name']) ?> (остаток: <?= \App\Service\FinanceCashService::formatAmount($acc['computed_balance'] ?? null) ?>)
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
                <input type="date" name="operation_date" class="field-input" value="<?= e(date('Y-m-d')) ?>" required>
            </div>
            <div class="field">
                <label class="field-label">Статья ДДС</label>
                <select name="dds_category_id" class="field-select">
                    <option value="">— Не выбрана —</option>
                    <?php foreach ($ddsCategories as $dc): ?>
                    <option value="<?= (int) $dc['id'] ?>">
                        <?= e($dc['code']) ?> — <?= e($dc['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="field">
            <label class="field-label">Назначение</label>
            <input type="text" name="purpose" class="field-input" placeholder="Назначение платежа">
        </div>
        <div class="field">
            <label class="field-label">Комментарий</label>
            <textarea name="comment" class="field-textarea" rows="2" placeholder="Необязательный комментарий"></textarea>
        </div>
    </div>
    <div class="modal-foot is-spaced">
        <div class="modal-foot-actions">
            <button type="button" class="btn btn-ghost" data-close-modal="cash-operation-create-modal">Отмена</button>
        </div>
        <div class="modal-foot-actions">
            <button type="submit" class="btn btn-primary">Провести</button>
        </div>
    </div>
</form>
