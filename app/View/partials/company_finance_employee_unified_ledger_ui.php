<?php
/**
 * One visual journal for all employee settlements.
 *
 * $ledger remains the only source of money and running balance. Linked invoice
 * and personal-expense events decorate their existing employee movement only,
 * therefore amounts are never appended or double-counted.
 */
$invoiceEventsByMovement = [];
foreach (($employeeInvoicePayments ?? []) as $event) {
    $movementId = (int)($event['employee_movement_id'] ?? 0);
    if ($movementId > 0) $invoiceEventsByMovement[$movementId] = $event;
}
$personalEventsByMovement = [];
foreach (($employeePersonalExpenses ?? []) as $event) {
    $movementId = (int)($event['employee_movement_id'] ?? 0);
    if ($movementId > 0) $personalEventsByMovement[$movementId] = $event;
}

$combineComment = static function (?string $basis, ?string $comment): string {
    $basis = trim((string)$basis);
    $comment = trim((string)$comment);
    if ($basis !== '' && $comment !== '') return $basis . ': ' . $comment;
    if ($basis !== '') return $basis;
    if ($comment !== '') return $comment;
    return '—';
};
$compactEmployeeName = static function (?string $fullName): string {
    $fullName = trim((string)$fullName);
    if ($fullName === '') return '';
    $parts = preg_split('/\s+/u', $fullName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if ($parts === []) return '';
    $last = array_shift($parts);
    $initials = [];
    foreach (array_slice($parts, 0, 2) as $part) $initials[] = mb_strtoupper(mb_substr($part, 0, 1)) . '.';
    return trim($last . ($initials ? ' ' . implode(' ', $initials) : ''));
};
$ordinaryBasis = static function (array $row) use ($compactEmployeeName): string {
    if (!empty($row['employee_transfer'])) {
        $transfer = $row['employee_transfer'];
        $from = $compactEmployeeName((string)($transfer['source_employee_name'] ?? ''));
        $to = $compactEmployeeName((string)($transfer['target_employee_name'] ?? ''));
        if ($from !== '' && $to !== '') return $from . ' → ' . $to;
    }
    $value = trim((string)($row['purpose'] ?? ''));
    if ($value === '') $value = trim((string)($row['note'] ?? ''));
    $value = preg_replace('/^Передано сотруднику:\s*[^·]+·\s*/u', '', $value) ?? $value;
    $value = preg_replace('/^Возврат от сотрудника:\s*[^·]+·\s*/u', '', $value) ?? $value;
    return trim($value);
};

/* Mirror the page's rendered order: newest month first, rows inside each month keep ledger order. */
$orderedRows = [];
$rowsByMonth = [];
foreach (($ledger ?? []) as $row) {
    $month = substr((string)($row['operation_date'] ?? ''), 0, 7);
    $rowsByMonth[$month][] = $row;
}
krsort($rowsByMonth);
foreach ($rowsByMonth as $rows) foreach ($rows as $row) $orderedRows[] = $row;

$unifiedRowMeta = [];
foreach ($orderedRows as $row) {
    $movementId = (int)($row['id'] ?? 0);
    $invoiceEvent = $invoiceEventsByMovement[$movementId] ?? null;
    $personalEvent = $personalEventsByMovement[$movementId] ?? null;
    $type = '';
    $source = '';
    $comment = '—';
    $eventKind = null;
    $eventId = null;

    if ($invoiceEvent) {
        $number = trim((string)($invoiceEvent['invoice_number_snapshot'] ?? ''));
        $number = preg_replace('/^[№#]\s*/u', '', $number) ?? $number;
        $basis = $number !== '' ? 'Счёт №' . $number : 'Счёт';
        $type = 'Оплатил счёт';
        $source = 'Личные средства';
        $comment = $combineComment($basis, (string)($invoiceEvent['comment'] ?? ''));
        $eventKind = 'invoice';
        $eventId = (int)($invoiceEvent['id'] ?? 0);
    } elseif ($personalEvent) {
        $basis = trim((string)($personalEvent['counterparty_name'] ?? ''));
        $purpose = trim((string)($personalEvent['purpose'] ?? ''));
        $extra = trim((string)($personalEvent['comment'] ?? ''));
        $detail = $purpose;
        if ($extra !== '') $detail = $detail !== '' ? $detail . ' · ' . $extra : $extra;
        $type = 'Прочий расход';
        $source = 'Личные средства';
        $comment = $combineComment($basis, $detail);
        $eventKind = 'personal';
        $eventId = (int)($personalEvent['id'] ?? 0);
    } else {
        $movementType = (string)($row['movement_type'] ?? '');
        $sourceType = (string)($row['source_type'] ?? '');
        if (!empty($row['employee_transfer'])) {
            $type = 'Передача сотруднику';
        } elseif ($movementType === 'PAYMENT') {
            $type = $sourceType === 'BANK' ? 'Выплата с расчётного счёта' : 'Выдача из кассы';
        } else {
            $type = $sourceType === 'BANK' ? 'Возврат на расчётный счёт' : 'Возврат в кассу';
        }
        $source = $sourceType === 'BANK' ? 'Расчётный счёт' : 'Касса';
        $basis = $ordinaryBasis($row);
        $userComment = !empty($row['employee_transfer'])
            ? trim((string)($row['employee_transfer']['comment'] ?? ''))
            : trim((string)($row['comment'] ?? ''));
        $comment = $combineComment($basis, $userComment);
    }

    $unifiedRowMeta[] = [
        'movement_id' => $movementId,
        'type' => $type,
        'source' => $source,
        'comment' => $comment,
        'event_kind' => $eventKind,
        'event_id' => $eventId,
        'cancelled' => (string)($row['status'] ?? '') === 'CANCELLED',
    ];
}
$unifiedRowMetaJson = json_encode($unifiedRowMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
?>
<style>
/* The old event sections stay in DOM only as modal/action hosts; visually there is one journal. */
.employee-money-actions{display:none!important}
.employee-report .page-head-right{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
/* This card has toolbar + summary + journal, so the generic 3-row table-card grid clips the journal into its 34px footer track. */
.employee-report-card{display:block!important;grid-template-rows:none!important;overflow:visible!important}
.employee-report-card .table-toolbar{min-height:36px!important}
.employee-report-card .table-scroll{display:block!important;overflow:visible!important;height:auto!important;max-height:none!important;min-height:40px!important}
.employee-report-table{display:table!important;width:100%!important;height:auto!important;min-height:0!important;table-layout:fixed!important}
.employee-report-table thead{display:table-header-group!important}
.employee-report-table tbody{display:table-row-group!important;height:auto!important;visibility:visible!important}
.employee-report-table tbody tr{display:table-row!important;height:auto!important;visibility:visible!important}
.employee-report-table tbody td{height:auto!important;min-height:30px!important;padding-top:7px!important;padding-bottom:7px!important}
.employee-report-table .employee-unified-comment{white-space:normal;overflow-wrap:anywhere;word-break:break-word;line-height:1.25}
.employee-report-table .employee-unified-comment-actions{display:flex;gap:5px;align-items:center;flex-wrap:wrap;margin-top:5px}
.employee-report-table .employee-unified-comment-actions .btn{height:22px;min-height:22px;padding:0 7px;font-size:9px}
.employee-report-table th:last-child,.employee-report-table td:last-child{display:none}
</style>
<script>
(function(){
    const metas=<?= $unifiedRowMetaJson ?>;
    const report=document.querySelector('.employee-report');
    if(!report)return;

    /* Keep all three actions together at the top of the single journal. */
    const headActions=report.querySelector('.page-head-right');
    const invoiceOpen=document.getElementById('employee-invoice-payment-open');
    const personalOpen=document.getElementById('employee-personal-expense-open');
    if(headActions){
        if(invoiceOpen)headActions.appendChild(invoiceOpen);
        if(personalOpen)headActions.appendChild(personalOpen);
    }

    const tables=Array.from(report.querySelectorAll('.employee-report-table'));
    const rows=[];
    tables.forEach(table=>{
        const headers=table.querySelectorAll('thead th');
        if(headers[1])headers[1].textContent='Тип платежа';
        if(headers[2])headers[2].textContent='Источник';
        if(headers[3])headers[3].textContent='Комментарий';
        if(headers[7])headers[7].style.display='none';
        const cols=table.querySelectorAll('colgroup col');
        if(cols[0])cols[0].style.width='82px';
        if(cols[1])cols[1].style.width='142px';
        if(cols[2])cols[2].style.width='118px';
        if(cols[3])cols[3].style.width='auto';
        if(cols[4])cols[4].style.width='105px';
        if(cols[5])cols[5].style.width='105px';
        if(cols[6])cols[6].style.width='105px';
        if(cols[7])cols[7].style.display='none';
        table.querySelectorAll('tbody tr').forEach(row=>rows.push(row));
    });

    /* Existing action nodes already have listeners bound by invoice_tools; move them, don't clone them. */
    const invoiceEdits=new Map();
    document.querySelectorAll('[data-invoice-payment-edit]').forEach(btn=>{
        try{const data=JSON.parse(btn.dataset.invoicePaymentEdit||'{}');if(data.id)invoiceEdits.set(String(data.id),btn);}catch(_e){}
    });
    const invoiceCancels=new Map();
    document.querySelectorAll('[data-invoice-payment-cancel]').forEach(btn=>invoiceCancels.set(String(btn.dataset.invoicePaymentCancel||''),btn));
    const personalEdits=new Map();
    document.querySelectorAll('[data-personal-expense-edit]').forEach(btn=>personalEdits.set(String(btn.dataset.personalExpenseEdit||''),btn));

    rows.forEach((row,index)=>{
        const meta=metas[index];
        if(!meta)return;
        const cells=row.querySelectorAll('td');
        if(cells[1])cells[1].textContent=meta.type||'—';
        if(cells[2])cells[2].textContent=meta.source||'—';
        if(cells[3]){
            cells[3].classList.add('employee-unified-comment');
            cells[3].textContent=meta.comment||'—';
            cells[3].title=meta.comment||'—';
            if(!meta.cancelled&&meta.event_kind&&meta.event_id){
                const actions=document.createElement('div');
                actions.className='employee-unified-comment-actions';
                if(meta.event_kind==='invoice'){
                    const edit=invoiceEdits.get(String(meta.event_id));
                    const cancel=invoiceCancels.get(String(meta.event_id));
                    if(edit)actions.appendChild(edit);
                    if(cancel)actions.appendChild(cancel);
                }else if(meta.event_kind==='personal'){
                    const edit=personalEdits.get(String(meta.event_id));
                    if(edit)actions.appendChild(edit);
                }
                if(actions.childNodes.length)cells[3].appendChild(actions);
            }
        }
        if(cells[7])cells[7].style.display='none';
    });

    /* The old view created one physical table per month. Merge them into one table. */
    if(tables.length){
        const firstTable=tables[0];
        const firstBody=firstTable.querySelector('tbody');
        tables.slice(1).forEach(table=>{
            const body=table.querySelector('tbody');
            if(firstBody&&body)Array.from(body.children).forEach(row=>firstBody.appendChild(row));
            const scroll=table.closest('.table-scroll');
            if(scroll)scroll.remove();else table.remove();
        });
        report.querySelectorAll('.employee-month-head').forEach(el=>el.remove());

        /* The unified journal is content-sized; never let the generic table-card grid/footer track clip it. */
        const reportCard=firstTable.closest('.employee-report-card');
        if(reportCard){
            reportCard.style.setProperty('display','block','important');
            reportCard.style.setProperty('grid-template-rows','none','important');
            reportCard.style.setProperty('overflow','visible','important');
        }
        const firstScroll=firstTable.closest('.table-scroll');
        if(firstScroll){
            firstScroll.style.setProperty('display','block','important');
            firstScroll.style.setProperty('height','auto','important');
            firstScroll.style.setProperty('max-height','none','important');
            firstScroll.style.setProperty('min-height','40px','important');
            firstScroll.style.setProperty('overflow','visible','important');
        }
        firstTable.style.setProperty('display','table','important');
        firstTable.style.setProperty('height','auto','important');
        if(firstBody){
            firstBody.style.setProperty('display','table-row-group','important');
            firstBody.style.setProperty('height','auto','important');
            Array.from(firstBody.rows).forEach(row=>{
                row.style.setProperty('display','table-row','important');
                row.style.setProperty('height','auto','important');
            });
        }
    }

    report.dataset.unifiedEmployeeLedger='ready';
})();
</script>
