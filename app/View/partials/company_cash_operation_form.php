<?php

$cashAccounts = $cashAccounts ?? [];
$incomeDdsCategories = $incomeDdsCategories ?? ($ddsCategories ?? []);
$expenseDdsCategories = $expenseDdsCategories ?? [];
$clientInvoices = $clientInvoices ?? [];
$carrierInvoices = $carrierInvoices ?? [];
$cashEmployees = $cashEmployees ?? [];
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
                <option value="CLIENT_CASH_INVOICE">Получено от клиента по счёту</option>
                <option value="MAIN_CASH_INVOICE_PAYMENT">Оплатить счёт перевозчика из Основной кассы</option>
                <option value="MAIN_CASH_EMPLOYEE_TRANSFER">Передать наличные сотруднику</option>
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
                    <select name="money_account_id" class="field-select" data-required-for="CASH_OPERATION">
                        <option value="">— Выберите кассу —</option>
                        <?php foreach ($cashAccounts as $acc): ?>
                        <option value="<?= (int)$acc['id'] ?>"><?= e($acc['name']) ?> (остаток: <?= \App\Service\FinanceCashService::formatAmount($acc['computed_balance'] ?? null) ?> ₽)</option>
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

        <div data-scenario-block="CLIENT_CASH_INVOICE" hidden>
            <div class="form-alert alert-info" style="margin-bottom:12px;">Фактически полученная сумма полностью поступит в <strong>Основную кассу</strong>. На счёт клиента можно зачесть всю сумму или только часть; остаток останется наличными в кассе.</div>
            <div class="field">
                <label class="field-label">Исходящий счёт клиента <span class="field-required">*</span></label>
                <select name="invoice_id" class="field-select" data-required-for="CLIENT_CASH_INVOICE">
                    <option value="">— Выберите счёт —</option>
                    <?php foreach ($clientInvoices as $invoice): ?>
                    <option value="<?= (int)$invoice['id'] ?>">№<?= e((string)($invoice['number'] ?? $invoice['id'])) ?> · <?= e((string)$invoice['counterparty_display_name']) ?> · остаток <?= \App\Service\FinanceCashService::formatAmount($invoice['remaining_amount'] ?? null) ?> ₽</option>
                    <?php endforeach; ?>
                </select>
                <?php if ($clientInvoices === []): ?><div class="field-hint">Нет открытых исходящих счетов клиентов.</div><?php endif; ?>
            </div>
            <div class="field">
                <label class="field-label">Зачесть в оплату счёта <span class="field-required">*</span></label>
                <input type="text" name="invoice_amount" class="field-input" inputmode="decimal" placeholder="0,00" data-required-for="CLIENT_CASH_INVOICE">
                <div class="field-hint">Может быть меньше фактически полученной суммы.</div>
            </div>
        </div>

        <div data-scenario-block="MAIN_CASH_INVOICE_PAYMENT" hidden>
            <div class="form-alert alert-info" style="margin-bottom:12px;">Деньги физически спишутся из <strong>Основной кассы</strong>. Входящий счёт перевозчика и связанные обязательства рейса будут закрыты на указанную сумму.</div>
            <div class="field">
                <label class="field-label">Входящий счёт перевозчика <span class="field-required">*</span></label>
                <select name="invoice_id" class="field-select" data-required-for="MAIN_CASH_INVOICE_PAYMENT" disabled>
                    <option value="">— Выберите счёт —</option>
                    <?php foreach ($carrierInvoices as $invoice): ?>
                    <option value="<?= (int)$invoice['id'] ?>">№<?= e((string)($invoice['number'] ?? $invoice['id'])) ?> · <?= e((string)$invoice['counterparty_display_name']) ?> · остаток <?= \App\Service\FinanceCashService::formatAmount($invoice['remaining_amount'] ?? null) ?> ₽</option>
                    <?php endforeach; ?>
                </select>
                <?php if ($carrierInvoices === []): ?><div class="field-hint">Нет открытых входящих счетов перевозчиков.</div><?php endif; ?>
            </div>
        </div>

        <div data-scenario-block="MAIN_CASH_EMPLOYEE_TRANSFER" hidden>
            <div class="form-alert alert-info" style="margin-bottom:12px;">Это внутренний перевод: Основная касса уменьшится, сальдо сотрудника увеличится. В БДДС расход не создаётся.</div>
            <div class="field">
                <label class="field-label">Сотрудник <span class="field-required">*</span></label>
                <select name="employee_ref" class="field-select" data-required-for="MAIN_CASH_EMPLOYEE_TRANSFER">
                    <option value="">— Выберите сотрудника —</option>
                    <?php foreach ($cashEmployees as $employee): ?><option value="<?= e($employee['ref']) ?>"><?= e($employee['full_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-grid-3">
            <div class="field"><label class="field-label"><span id="cash-amount-label">Сумма</span> <span class="field-required">*</span></label><input type="text" name="amount" class="field-input" inputmode="decimal" placeholder="0,00" required></div>
            <div class="field"><label class="field-label">Дата <span class="field-required">*</span></label><input type="date" name="operation_date" class="field-input" value="<?= e(date('Y-m-d')) ?>" required></div>
        </div>
        <div class="field" data-common-purpose><label class="field-label">Назначение</label><input type="text" name="purpose" class="field-input" placeholder="Назначение операции"></div>
        <div class="field"><label class="field-label">Комментарий</label><textarea name="comment" class="field-textarea" rows="2" placeholder="Необязательный комментарий"></textarea></div>
    </div>
    <div class="modal-foot is-spaced"><div class="modal-foot-actions"><button type="button" class="btn btn-ghost" data-close-modal="cash-operation-create-modal">Отмена</button></div><div class="modal-foot-actions"><button type="submit" class="btn btn-primary">Провести</button></div></div>
</form>
<script>
(function(){
    const form=document.getElementById('finance-cash-manual-form');
    const scenario=document.getElementById('cash-scenario');
    const opType=document.getElementById('cash-operation-type');
    const dds=document.getElementById('cash-dds-category');
    const hint=document.getElementById('scenario-hint');
    const amountLabel=document.getElementById('cash-amount-label');
    const purpose=document.querySelector('[data-common-purpose]');
    if(!form||!scenario)return;
    const hints={
        CASH_OPERATION:'Обычная операция изменяет остаток выбранной кассы.',
        CLIENT_CASH_INVOICE:'Наличные остаются в Основной кассе; выбранная часть закрывает исходящий счёт клиента.',
        MAIN_CASH_INVOICE_PAYMENT:'Наличные списываются из Основной кассы и закрывают входящий счёт перевозчика.',
        MAIN_CASH_EMPLOYEE_TRANSFER:'Наличные переходят из Основной кассы сотруднику как внутренний перевод.'
    };
    const syncDds=()=>{
        if(!dds||!opType)return;
        const type=opType.value;
        Array.from(dds.options).forEach(option=>{
            if(!option.value)return;
            const direction=option.dataset.direction||'';
            option.hidden=direction!==type&&direction!=='BOTH';
            if(option.hidden&&option.selected)dds.value='';
        });
    };
    const sync=()=>{
        const value=scenario.value;
        form.querySelectorAll('[data-scenario-block]').forEach(block=>{block.hidden=block.dataset.scenarioBlock!==value;});
        form.querySelectorAll('[data-required-for]').forEach(field=>{
            const active=field.dataset.requiredFor===value;
            field.required=active;
            field.disabled=!active;
        });
        if(opType)opType.disabled=value!=='CASH_OPERATION';
        if(dds)dds.disabled=value!=='CASH_OPERATION';
        if(purpose)purpose.hidden=value!=='CASH_OPERATION';
        hint.textContent=hints[value]||'';
        amountLabel.textContent=value==='CLIENT_CASH_INVOICE'?'Фактически получено наличными':'Сумма';
        syncDds();
    };
    if(opType)opType.addEventListener('change',syncDds);
    scenario.addEventListener('change',sync);
    sync();
})();
</script>
