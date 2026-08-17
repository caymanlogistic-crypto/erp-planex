<?php
use App\Service\FinanceCashService;

$employeeInvoicePayments = $employeeInvoicePayments ?? [];
$employeePersonalExpenses = $employeePersonalExpenses ?? [];
$carrierInvoices = $carrierInvoices ?? [];
$selectedRef = (string)($selectedRef ?? '');
$selectedEmployeeName = (string)($selectedEmployee['full_name'] ?? '');
$fmtDate = static fn($d): string => $d ? date('d.m.Y', strtotime((string)$d)) : '—';
$invoiceOptions = [];
foreach ($carrierInvoices as $invoice) {
    $invoiceOptions[(int)$invoice['id']] = $invoice;
}
?>
<?php if (!empty($selectedEmployee) && $selectedRef !== ''): ?>
<style>
.employee-money-actions{width:min(1280px,100%);margin:12px auto 0}.employee-money-toolbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.employee-money-caption{font-size:11px;color:var(--text-muted);margin-top:5px}.employee-event-actions{display:flex;gap:6px;justify-content:flex-end}.employee-event-actions .btn{height:24px;min-height:24px;padding:0 8px;font-size:10px}.employee-invoice-row.is-cancelled{opacity:.55}.employee-event-section{margin-top:14px}.employee-event-section:first-child{margin-top:0}.employee-event-table td:last-child,.employee-event-table th:last-child{text-align:right}.employee-invoice-modal .field-note{margin-top:4px}.employee-event-kind{font-weight:700}
</style>
<div class="employee-money-actions">
    <div class="table-card table-card--standard">
        <div class="table-toolbar">
            <div class="table-toolbar-left"><strong>Операции за компанию</strong></div>
            <div class="employee-money-toolbar">
                <button type="button" class="btn btn-primary btn--toolbar" id="employee-invoice-payment-open" <?= $carrierInvoices === [] ? 'disabled' : '' ?> title="<?= $carrierInvoices === [] ? 'Нет открытых входящих счетов перевозчиков' : 'Оплатить входящий счёт из денег, находящихся у сотрудника' ?>">Оплатил счёт</button>
                <button type="button" class="btn btn-secondary btn--toolbar" id="employee-personal-expense-open">Прочий расход</button>
            </div>
        </div>
        <div class="panel-body" style="padding:9px 12px">
            <div class="employee-money-caption">«Оплатил счёт» закрывает входящий счёт перевозчика и связанные обязательства рейса. «Прочий расход» используется для ATI, Контур, канцтоваров и других расходов по ЦФУ / статье ДДС.</div>
        </div>
    </div>

    <div class="employee-event-section">
        <div class="section-title">Оплаты счетов сотрудником</div>
        <?php if ($employeeInvoicePayments === []): ?>
            <div class="panel"><div class="panel-body"><div class="empty-state"><p class="empty-title">Оплат счетов пока нет.</p><p class="empty-desc">Используйте «Оплатил счёт», когда сотрудник передал наличные перевозчику по входящему счёту.</p></div></div></div>
        <?php else: ?>
            <div class="table-card table-card--standard"><div class="table-scroll"><table class="table employee-event-table">
                <thead><tr><th>Дата</th><th>Сотрудник</th><th>Счёт</th><th>Перевозчик</th><th>Сумма</th><th>Статус</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($employeeInvoicePayments as $event):
                    $isCancelled = (string)($event['status'] ?? '') === 'CANCELLED';
                    $payload = [
                        'id'=>(int)$event['id'],
                        'invoice_id'=>(int)$event['invoice_id'],
                        'invoice_number'=>(string)($event['invoice_number_snapshot'] ?? ''),
                        'counterparty_name'=>(string)($event['counterparty_name_snapshot'] ?? ''),
                        'operation_date'=>(string)($event['operation_date'] ?? ''),
                        'amount'=>(string)($event['amount'] ?? ''),
                        'comment'=>(string)($event['comment'] ?? ''),
                    ];
                    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: '{}';
                ?>
                    <tr class="employee-invoice-row <?= $isCancelled ? 'is-cancelled' : '' ?>">
                        <td class="col-mono"><?= e($fmtDate($event['operation_date'] ?? null)) ?></td>
                        <td><?= e($event['employee_name_snapshot'] ?? $selectedEmployeeName) ?></td>
                        <td><strong><?= e((string)($event['invoice_number_snapshot'] ?? ('#'.(int)$event['invoice_id']))) ?></strong></td>
                        <td><?= e((string)($event['counterparty_name_snapshot'] ?? '—')) ?></td>
                        <td class="col-mono"><strong><?= e(FinanceCashService::formatAmount($event['amount'] ?? null)) ?> ₽</strong></td>
                        <td><?= $isCancelled ? '<span class="badge badge-neutral"><span class="dot"></span>Отменена</span>' : '<span class="badge badge-ok"><span class="dot"></span>Проведена</span>' ?></td>
                        <td>
                            <?php if (!$isCancelled): ?>
                            <div class="employee-event-actions">
                                <button type="button" class="btn btn-secondary" data-invoice-payment-edit="<?= e($payloadJson) ?>">Изменить</button>
                                <button type="button" class="btn btn-ghost fs-delete-action" data-invoice-payment-cancel="<?= (int)$event['id'] ?>">Отменить</button>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div></div>
        <?php endif; ?>
    </div>

    <div class="employee-event-section">
        <div class="section-title">Прочие расходы сотрудника</div>
        <?php if ($employeePersonalExpenses === []): ?>
            <div class="panel"><div class="panel-body"><div class="empty-state"><p class="empty-title">Прочих расходов пока нет.</p><p class="empty-desc">ATI, Контур и другие расходы без счёта рейса оформляются через «Прочий расход».</p></div></div></div>
        <?php else: ?>
            <div class="table-card table-card--standard"><div class="table-scroll"><table class="table employee-event-table">
                <thead><tr><th>Дата</th><th>Получатель / назначение</th><th>ЦФУ</th><th>Статья ДДС</th><th>Рейс</th><th>Сумма</th><th>Статус</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($employeePersonalExpenses as $event): $cancelled=(string)($event['status']??'')==='CANCELLED'; ?>
                    <tr class="<?= $cancelled?'is-muted':'' ?>">
                        <td class="col-mono"><?= e($fmtDate($event['operation_date']??null)) ?></td>
                        <td><strong><?= e((string)($event['counterparty_name']??'—')) ?></strong><div class="text-muted"><?= e((string)($event['purpose']??'')) ?></div></td>
                        <td><?= e((string)($event['cfu_name']??$event['cash_flow_center_name_snapshot']??'—')) ?></td>
                        <td><?= e((string)($event['dds_name']??$event['dds_category_name_snapshot']??'—')) ?></td>
                        <td><?= !empty($event['linear_route_id'])?'Рейс #'.(int)$event['linear_route_id']:'—' ?></td>
                        <td class="col-mono"><strong><?= e(FinanceCashService::formatAmount($event['amount']??null)) ?> ₽</strong></td>
                        <td><?= $cancelled?'Отменена':'Проведена' ?></td>
                        <td><?php if(!$cancelled): ?><button type="button" class="btn btn-secondary" data-personal-expense-edit="<?= (int)$event['id'] ?>">Изменить</button><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div></div>
        <?php endif; ?>
    </div>
</div>

<div id="employee-invoice-payment-modal" class="modal-overlay employee-invoice-modal" role="dialog" aria-modal="true" aria-labelledby="employee-invoice-payment-title">
    <div class="modal modal-md">
        <div class="modal-head"><span class="modal-title" id="employee-invoice-payment-title">Сотрудник оплатил счёт</span><button type="button" class="modal-close" data-invoice-payment-close>&times;</button></div>
        <form method="post" action="<?= app_url('/company/finance/employee-payments/invoice-payment/create') ?>" id="employee-invoice-payment-form">
            <?= csrfField() ?>
            <input type="hidden" name="employee_ref" value="<?= e($selectedRef) ?>">
            <input type="hidden" name="event_id" id="employee-invoice-event-id" value="">
            <div class="modal-body">
                <div class="form-alert alert-info" style="margin-bottom:12px;">Выберите входящий счёт перевозчика. Рейс и платёжные обязательства подтянутся из связей счёта автоматически. ЦФУ и статью ДДС выбирать не нужно.</div>
                <div class="field"><label class="field-label">Сотрудник</label><input class="field-input" value="<?= e($selectedEmployeeName) ?>" readonly></div>
                <div class="field">
                    <label class="field-label">Входящий счёт <span class="field-required">*</span></label>
                    <select class="field-select" name="invoice_id" id="employee-invoice-id" required>
                        <option value="">— Выберите счёт —</option>
                        <?php foreach ($carrierInvoices as $invoice): ?>
                            <option value="<?= (int)$invoice['id'] ?>" data-number="<?= e((string)($invoice['number']??'')) ?>" data-carrier="<?= e((string)($invoice['counterparty_display_name']??'')) ?>">№<?= e((string)($invoice['number']??$invoice['id'])) ?> · <?= e((string)$invoice['counterparty_display_name']) ?> · остаток <?= e(FinanceCashService::formatAmount($invoice['remaining_amount']??null)) ?> ₽</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-grid two-cols">
                    <div class="field"><label class="field-label">Дата <span class="field-required">*</span></label><input class="field-input" type="date" name="operation_date" id="employee-invoice-date" value="<?= e(date('Y-m-d')) ?>" required></div>
                    <div class="field"><label class="field-label">Сумма <span class="field-required">*</span></label><input class="field-input" name="amount" id="employee-invoice-amount" inputmode="decimal" autocomplete="off" placeholder="0,00" required></div>
                </div>
                <div class="field"><label class="field-label">Комментарий</label><textarea class="field-input" name="comment" id="employee-invoice-comment" rows="3" placeholder="Необязательно"></textarea></div>
            </div>
            <div class="modal-foot"><button type="button" class="btn btn-secondary" data-invoice-payment-close>Закрыть</button><button type="submit" class="btn btn-primary" id="employee-invoice-submit">Провести оплату</button></div>
        </form>
    </div>
</div>

<form method="post" action="<?= app_url('/company/finance/employee-payments/invoice-payment/cancel') ?>" id="employee-invoice-cancel-form" hidden>
    <?= csrfField() ?><input type="hidden" name="employee_ref" value="<?= e($selectedRef) ?>"><input type="hidden" name="event_id" id="employee-invoice-cancel-id"><input type="hidden" name="reason" value="Отменено пользователем из раздела выплат сотрудникам">
</form>
<div id="employee-personal-expense-host"></div>
<script>
(function(){
    const invoiceModal=document.getElementById('employee-invoice-payment-modal');
    const invoiceForm=document.getElementById('employee-invoice-payment-form');
    const openInvoice=document.getElementById('employee-invoice-payment-open');
    const invoiceId=document.getElementById('employee-invoice-id');
    const eventId=document.getElementById('employee-invoice-event-id');
    const dateInput=document.getElementById('employee-invoice-date');
    const amountInput=document.getElementById('employee-invoice-amount');
    const commentInput=document.getElementById('employee-invoice-comment');
    const title=document.getElementById('employee-invoice-payment-title');
    const submit=document.getElementById('employee-invoice-submit');
    const createAction='<?= e(app_url('/company/finance/employee-payments/invoice-payment/create')) ?>';
    const updateAction='<?= e(app_url('/company/finance/employee-payments/invoice-payment/update')) ?>';
    const resetInvoice=()=>{invoiceForm.action=createAction;eventId.value='';invoiceId.value='';dateInput.value='<?= e(date('Y-m-d')) ?>';amountInput.value='';commentInput.value='';title.textContent='Сотрудник оплатил счёт';submit.textContent='Провести оплату';};
    const open=()=>{invoiceModal.classList.add('is-open');setTimeout(()=>invoiceId.focus(),0);};
    const close=()=>invoiceModal.classList.remove('is-open');
    if(openInvoice)openInvoice.addEventListener('click',()=>{resetInvoice();open();});
    invoiceModal.querySelectorAll('[data-invoice-payment-close]').forEach(btn=>btn.addEventListener('click',close));
    invoiceModal.addEventListener('click',e=>{if(e.target===invoiceModal)close();});
    document.querySelectorAll('[data-invoice-payment-edit]').forEach(btn=>btn.addEventListener('click',()=>{
        let data={};try{data=JSON.parse(btn.dataset.invoicePaymentEdit||'{}');}catch(e){console.error(e);return;}
        resetInvoice();invoiceForm.action=updateAction;eventId.value=data.id||'';dateInput.value=data.operation_date||'';amountInput.value=String(data.amount||'').replace('.',',');commentInput.value=data.comment||'';
        const wanted=String(data.invoice_id||'');let option=Array.from(invoiceId.options).find(o=>o.value===wanted);
        if(!option&&wanted){option=document.createElement('option');option.value=wanted;option.textContent='№'+String(data.invoice_number||wanted)+' · '+String(data.counterparty_name||'Перевозчик')+' · текущий счёт';invoiceId.appendChild(option);}
        invoiceId.value=wanted;title.textContent='Изменить оплату счёта';submit.textContent='Сохранить изменения';open();
    }));
    const cancelForm=document.getElementById('employee-invoice-cancel-form');const cancelId=document.getElementById('employee-invoice-cancel-id');
    document.querySelectorAll('[data-invoice-payment-cancel]').forEach(btn=>btn.addEventListener('click',()=>{if(confirm('Отменить оплату счёта? Счёт и обязательства будут пересчитаны.')){cancelId.value=btn.dataset.invoicePaymentCancel||'';cancelForm.submit();}}));

    const personalHost=document.getElementById('employee-personal-expense-host');
    const loadPersonal=async(url)=>{try{const response=await fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest'}});personalHost.innerHTML=await response.text();bindPersonal();}catch(error){console.error(error);alert('Не удалось открыть форму прочего расхода.');}};
    const bindPersonal=()=>{
        const overlay=personalHost.querySelector('.employee-personal-expense-modal');if(!overlay)return;
        overlay.querySelectorAll('[data-personal-expense-close]').forEach(btn=>btn.addEventListener('click',()=>personalHost.innerHTML=''));
        overlay.addEventListener('click',e=>{if(e.target===overlay)personalHost.innerHTML='';});
        const form=overlay.querySelector('[data-personal-expense-form]');const cfu=overlay.querySelector('[data-personal-expense-cfu]');const dds=overlay.querySelector('[data-personal-expense-dds]');
        if(form&&cfu&&dds){let map={};try{map=JSON.parse(form.dataset.allowedExpenseDdsMap||'{}');}catch(e){};const all=Array.from(dds.options).map(o=>({value:o.value,text:o.textContent}));const sync=()=>{const selected=dds.dataset.selectedDds||dds.value;dds.innerHTML='';const first=document.createElement('option');first.value='';first.textContent='— Выберите статью —';dds.appendChild(first);const allowed=(map[String(cfu.value)]||[]).map(String);all.forEach(item=>{if(item.value&&allowed.includes(String(item.value))){const o=document.createElement('option');o.value=item.value;o.textContent=item.text;if(String(item.value)===String(selected))o.selected=true;dds.appendChild(o);}});dds.dataset.selectedDds='';};cfu.addEventListener('change',sync);sync();}
        const cancel=overlay.querySelector('[data-personal-expense-cancel-event]');if(cancel)cancel.addEventListener('click',()=>{if(!confirm('Отменить прочий расход сотрудника?'))return;const f=document.createElement('form');f.method='post';f.action='<?= e(app_url('/company/finance/employee-payments/personal-expense/')) ?>'+cancel.dataset.personalExpenseCancelEvent+'/cancel';f.innerHTML='<?= str_replace(["\r","\n","'"],["","","\\'"],csrfField()) ?>'+'<input type="hidden" name="employee_ref" value="'+cancel.dataset.employeeRef+'"><input type="hidden" name="reason" value="Отменено пользователем">';document.body.appendChild(f);f.submit();});
    };
    const personalOpen=document.getElementById('employee-personal-expense-open');if(personalOpen)personalOpen.addEventListener('click',()=>loadPersonal('<?= e(app_url('/company/finance/employee-payments/personal-expense/create?employee_ref='.rawurlencode($selectedRef))) ?>'));
    document.querySelectorAll('[data-personal-expense-edit]').forEach(btn=>btn.addEventListener('click',()=>loadPersonal('<?= e(app_url('/company/finance/employee-payments/personal-expense/')) ?>'+btn.dataset.personalExpenseEdit+'/edit')));
})();
</script>
<?php endif; ?>
