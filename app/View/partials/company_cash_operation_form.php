<?php

$cashAccounts = $cashAccounts ?? [];
$incomeDdsCategories = $incomeDdsCategories ?? ($ddsCategories ?? []);
$expenseDdsCategories = $expenseDdsCategories ?? [];
$clientPaymentTargets = $clientPaymentTargets ?? [];
$employees = $employees ?? [];
$cashFlowCenters = $cashFlowCenters ?? [];
$allowedExpenseDdsMap = $allowedExpenseDdsMap ?? [];
$routes = $routes ?? [];
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
                <option value="EMPLOYEE_PERSONAL_EXPENSE">Сотрудник оплатил расход компании из личных средств</option>
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
                        <option value="<?= (int)$acc['id'] ?>">
                            <?= e($acc['name']) ?> (остаток: <?= \App\Service\FinanceCashService::formatAmount($acc['computed_balance'] ?? null) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">Статья ДДС</label>
                    <select name="dds_category_id" class="field-select" id="cash-dds-category">
                        <option value="">— Не выбрана —</option>
                        <?php foreach ($allDds as $dc): ?>
                        <option value="<?= (int)$dc['id'] ?>" data-direction="<?= e((string)$dc['direction']) ?>">
                            <?= e($dc['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div data-scenario-block="CLIENT_CASH_RECEIPT" hidden>
            <div class="form-alert alert-info" style="margin-bottom:12px;">
                Деньги поступят в «Основную кассу». Выбранный платёж рейса будет закрыт вручную на указанную сумму.
            </div>
            <div class="field">
                <label class="field-label">Рейс / платёж клиента <span class="field-required">*</span></label>
                <select name="linear_route_payment_id" class="field-select">
                    <option value="">— Выберите рейс —</option>
                    <?php foreach ($clientPaymentTargets as $target): ?>
                    <option value="<?= (int)$target['payment_id'] ?>">
                        Рейс #<?= (int)$target['linear_route_id'] ?> · <?= e($target['client_name']) ?> · остаток <?= \App\Service\FinanceCashService::formatAmount($target['remaining_amount']) ?> ₽
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($clientPaymentTargets === []): ?>
                <div class="field-hint">Нет активных неоплаченных платежей клиентов по рейсам.</div>
                <?php endif; ?>
            </div>
            <div class="field">
                <label class="field-label">Статья ДДС</label>
                <select name="client_dds_category_id" class="field-select" id="client-dds-category">
                    <option value="">— Не выбрана —</option>
                    <?php foreach ($incomeDdsCategories as $dc): ?>
                    <option value="<?= (int)$dc['id'] ?>"><?= e($dc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div data-scenario-block="EMPLOYEE_PERSONAL_EXPENSE" hidden>
            <div class="form-alert alert-info" style="margin-bottom:12px;">
                Фиксируется только факт расхода компании. Остатки кассы/банка и взаиморасчёты с сотрудником не изменяются.
            </div>
            <div class="form-grid-3">
                <div class="field">
                    <label class="field-label">Сотрудник <span class="field-required">*</span></label>
                    <select name="employee_ref" class="field-select">
                        <option value="">— Выберите сотрудника —</option>
                        <?php foreach ($employees as $employee): ?>
                        <option value="<?= e($employee['ref']) ?>"><?= e($employee['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">ЦФУ <span class="field-required">*</span></label>
                    <select name="cash_flow_center_id" class="field-select" id="personal-expense-cfu">
                        <option value="">— Выберите ЦФУ —</option>
                        <?php foreach ($cashFlowCenters as $center): ?>
                        <option value="<?= (int)$center['id'] ?>"><?= e($center['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">Статья ДДС <span class="field-required">*</span></label>
                    <select name="personal_dds_category_id" class="field-select" id="personal-expense-dds">
                        <option value="">— Сначала выберите ЦФУ —</option>
                        <?php foreach ($expenseDdsCategories as $dc): ?>
                        <option value="<?= (int)$dc['id'] ?>" hidden><?= e($dc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">Получатель / контрагент</label>
                    <input type="text" name="counterparty_name" class="field-input" placeholder="Например: ATI.SU">
                </div>
                <div class="field">
                    <label class="field-label">Рейс</label>
                    <select name="personal_linear_route_id" class="field-select">
                        <option value="">— Не относится к рейсу —</option>
                        <?php foreach ($routes as $route): ?>
                        <option value="<?= (int)$route['id'] ?>">Рейс #<?= (int)$route['id'] ?> · <?= e($route['client_name'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-grid-3">
            <div class="field">
                <label class="field-label">Сумма <span class="field-required">*</span></label>
                <input type="text" name="amount" class="field-input" placeholder="0.00" required>
            </div>
            <div class="field">
                <label class="field-label">Дата <span class="field-required">*</span></label>
                <input type="date" name="operation_date" class="field-input" value="<?= e(date('Y-m-d')) ?>" required>
            </div>
        </div>

        <div class="field">
            <label class="field-label">Назначение <span id="purpose-required-mark" class="field-required" hidden>*</span></label>
            <input type="text" name="purpose" class="field-input" placeholder="Например: доступ ATI за август">
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

<script>
(() => {
    const form = document.getElementById('finance-cash-manual-form');
    if (!form) return;
    const scenario = form.querySelector('#cash-scenario');
    const blocks = [...form.querySelectorAll('[data-scenario-block]')];
    const hint = form.querySelector('#scenario-hint');
    const purpose = form.querySelector('[name="purpose"]');
    const purposeMark = form.querySelector('#purpose-required-mark');
    const normalType = form.querySelector('#cash-operation-type');
    const normalDds = form.querySelector('#cash-dds-category');
    const clientDds = form.querySelector('#client-dds-category');
    const personalCfu = form.querySelector('#personal-expense-cfu');
    const personalDds = form.querySelector('#personal-expense-dds');
    const allowedMap = <?= json_encode($allowedExpenseDdsMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    const setRequired = (block, required) => {
        block.querySelectorAll('select, input, textarea').forEach(el => {
            const name = el.getAttribute('name');
            const requiredNames = required === 'CLIENT_CASH_RECEIPT'
                ? ['linear_route_payment_id']
                : required === 'EMPLOYEE_PERSONAL_EXPENSE'
                    ? ['employee_ref', 'cash_flow_center_id', 'personal_dds_category_id']
                    : required === 'CASH_OPERATION'
                        ? ['operation_type', 'money_account_id']
                        : [];
            el.required = requiredNames.includes(name);
        });
    };

    const syncNormalDds = () => {
        if (!normalDds || !normalType) return;
        const type = normalType.value;
        [...normalDds.options].forEach((opt, index) => {
            if (index === 0) return;
            const dir = opt.dataset.direction || 'BOTH';
            opt.hidden = !(dir === type || dir === 'BOTH');
            opt.disabled = opt.hidden;
            if (opt.hidden && opt.selected) normalDds.value = '';
        });
    };

    const syncPersonalDds = () => {
        if (!personalCfu || !personalDds) return;
        const cfu = personalCfu.value;
        const allowed = (allowedMap[cfu] || []).map(Number);
        [...personalDds.options].forEach((opt, index) => {
            if (index === 0) return;
            const visible = cfu !== '' && allowed.includes(Number(opt.value));
            opt.hidden = !visible;
            opt.disabled = !visible;
            if (!visible && opt.selected) personalDds.value = '';
        });
        personalDds.options[0].textContent = cfu === '' ? '— Сначала выберите ЦФУ —' : '— Выберите статью —';
    };

    const syncScenario = () => {
        const value = scenario.value;
        blocks.forEach(block => {
            const active = block.dataset.scenarioBlock === value;
            block.hidden = !active;
            setRequired(block, active ? value : '');
            block.querySelectorAll('select, input, textarea').forEach(el => { el.disabled = !active; });
        });
        purpose.required = value === 'EMPLOYEE_PERSONAL_EXPENSE';
        purposeMark.hidden = !purpose.required;
        hint.textContent = value === 'CLIENT_CASH_RECEIPT'
            ? 'Реальный приход в Основную кассу с ручной привязкой к выбранному рейсу.'
            : value === 'EMPLOYEE_PERSONAL_EXPENSE'
                ? 'Только факт расхода: без движения корпоративных денег и без долга сотруднику.'
                : 'Обычная операция изменяет остаток выбранной кассы.';
        if (value === 'CLIENT_CASH_RECEIPT' && clientDds) clientDds.disabled = false;
        syncNormalDds();
        syncPersonalDds();
    };

    scenario.addEventListener('change', syncScenario);
    normalType?.addEventListener('change', syncNormalDds);
    personalCfu?.addEventListener('change', syncPersonalDds);

    form.addEventListener('submit', () => {
        if (scenario.value === 'CLIENT_CASH_RECEIPT' && clientDds) {
            let hidden = form.querySelector('input[name="dds_category_id"][data-client-copy]');
            if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'dds_category_id';
                hidden.dataset.clientCopy = '1';
                form.appendChild(hidden);
            }
            hidden.value = clientDds.value;
        }
        if (scenario.value === 'EMPLOYEE_PERSONAL_EXPENSE') {
            let ddsHidden = form.querySelector('input[name="dds_category_id"][data-personal-copy]');
            if (!ddsHidden) {
                ddsHidden = document.createElement('input');
                ddsHidden.type = 'hidden';
                ddsHidden.name = 'dds_category_id';
                ddsHidden.dataset.personalCopy = '1';
                form.appendChild(ddsHidden);
            }
            ddsHidden.value = personalDds.value;
            let routeHidden = form.querySelector('input[name="linear_route_id"][data-personal-copy]');
            if (!routeHidden) {
                routeHidden = document.createElement('input');
                routeHidden.type = 'hidden';
                routeHidden.name = 'linear_route_id';
                routeHidden.dataset.personalCopy = '1';
                form.appendChild(routeHidden);
            }
            routeHidden.value = form.querySelector('[name="personal_linear_route_id"]')?.value || '';
        }
    });

    syncScenario();
})();
</script>
