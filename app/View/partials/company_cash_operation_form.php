<?php

$cashAccounts = $cashAccounts ?? [];
$incomeDdsCategories = $incomeDdsCategories ?? ($ddsCategories ?? []);
$expenseDdsCategories = $expenseDdsCategories ?? [];
$clientPaymentTargets = $clientPaymentTargets ?? [];
$formError = $formError ?? null;

$allDds = [];
foreach (array_merge($incomeDdsCategories, $expenseDdsCategories) as $row) {
    $allDds[(int)$row['id']] = $row;
}
?>
<?php if ($formError): ?>
<div class="form-alert alert-error"><?= e($formError) ?></div>
<?php endif; ?>
<form action="<?= app_url('/company/finance/cash/operation-create') ?>" method="post" class="cash-form" id="finance-cash-manual-form">
    <?= csrfField() ?>
    <div class="modal-body">
        <div class="section-title">Новая финансовая операция</div>

        <div class="field">
            <label class="field-label">Что фиксируем <span class="field-required">*</span></label>
            <select name="scenario" class="field-select" id="cash-scenario" required>
                <option value="CASH_OPERATION">Обычный приход / расход по кассе</option>
                <option value="CLIENT_CASH_RECEIPT">Оплата клиента наличными за рейс</option>
            </select>
            <div class="field-hint" id="scenario-hint">Обычная операция изменяет остаток выбранной кассы.</div>
        </div>

        <div data-scenario-block="CASH_OPERATION">
            <div class="form-grid-3">
                <div class="field">
                    <label class="field-label">Тип <span class="field-required">*</span></label>
                    <select name="operation_type" class="field-select" id="cash-operation-type">
                        <option value="INCOME">Приход</option>
                        <option value="EXPENSE">Расход</option>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">Касса <span class="field-required">*</span></label>
                    <select name="money_account_id" class="field-select">
                        <option value="">— Выберите кассу —</option>
                        <?php foreach ($cashAccounts as $acc): ?>
                        <option value="<?= (int)$acc['id'] ?>"><?= e($acc['name']) ?> (остаток: <?= \App\Service\FinanceCashService::formatAmount($acc['computed_balance'] ?? null) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">Статья ДДС</label>
                    <select name="dds_category_id" class="field-select" id="cash-dds-category">
                        <option value="">— Не выбрана —</option>
                        <?php foreach ($allDds as $dc): ?>
                        <option value="<?= (int)$dc['id'] ?>" data-direction="<?= e((string)$dc['direction']) ?>"><?= e($dc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div data-scenario-block="CLIENT_CASH_RECEIPT" hidden>
            <div class="form-alert alert-info" style="margin-bottom:12px;">Деньги поступят в «Основную кассу». Выбранный платёж рейса будет закрыт вручную на указанную сумму.</div>
            <div class="field">
                <label class="field-label">Рейс / платёж клиента <span class="field-required">*</span></label>
                <select name="linear_route_payment_id" class="field-select">
                    <option value="">— Выберите рейс —</option>
                    <?php foreach ($clientPaymentTargets as $target): ?>
                    <option value="<?= (int)$target['payment_id'] ?>">Рейс #<?= (int)$target['linear_route_id'] ?> · <?= e($target['client_name']) ?> · остаток <?= \App\Service\FinanceCashService::formatAmount($target['remaining_amount']) ?> ₽</option>
                    <?php endforeach; ?>
                </select>
                <?php if ($clientPaymentTargets === []): ?><div class="field-hint">Нет активных неоплаченных платежей клиентов по рейсам.</div><?php endif; ?>
            </div>
            <div class="field">
                <label class="field-label">Статья ДДС</label>
                <select name="client_dds_category_id" class="field-select" id="client-dds-category">
                    <option value="">— Не выбрана —</option>
                    <?php foreach ($incomeDdsCategories as $dc): ?><option value="<?= (int)$dc['id'] ?>"><?= e($dc['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-grid-3">
            <div class="field"><label class="field-label">Сумма <span class="field-required">*</span></label><input type="text" name="amount" class="field-input" placeholder="0.00" required></div>
            <div class="field"><label class="field-label">Дата <span class="field-required">*</span></label><input type="date" name="operation_date" class="field-input" value="<?= e(date('Y-m-d')) ?>" required></div>
        </div>
        <div class="field"><label class="field-label">Назначение <span id="purpose-required-mark" class="field-required" hidden>*</span></label><input type="text" name="purpose" class="field-input" placeholder="Назначение операции"></div>
        <div class="field"><label class="field-label">Комментарий</label><textarea name="comment" class="field-textarea" rows="2" placeholder="Необязательный комментарий"></textarea></div>
    </div>
    <div class="modal-foot is-spaced"><div class="modal-foot-actions"><button type="button" class="btn btn-ghost" data-close-modal="cash-operation-create-modal">Отмена</button></div><div class="modal-foot-actions"><button type="submit" class="btn btn-primary">Провести</button></div></div>
</form>