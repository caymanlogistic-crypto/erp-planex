<?php
$fmt=static fn($v)=>\App\Service\FinanceEmployeePaymentService::formatMoney($v);$toCents=static function($v):int{$s=str_replace([' ', ','],['','.'],trim((string)$v));if(!preg_match('/^-?\d+(?:\.\d{1,2})?$/D',$s))return 0;$neg=str_starts_with($s,'-');if($neg)$s=substr($s,1);[$r,$k]=array_pad(explode('.',$s,2),2,'');$c=((int)$r*100)+(int)str_pad(substr($k,0,2),2,'0');return $neg?-$c:$c;};$fromCents=static fn(int $c):string=>($c<0?'-':'').intdiv(abs($c),100).'.'.str_pad((string)(abs($c)%100),2,'0',STR_PAD_LEFT);$months=[];$totalPaid=0;$totalReturned=0;foreach($ledger??[] as $row){$month=substr((string)$row['operation_date'],0,7);$months[$month]??=['paid'=>0,'returned'=>0,'rows'=>[]];$months[$month]['rows'][]=$row;if(($row['status']??'')==='POSTED'){$c=$toCents($row['amount']);if(($row['movement_type']??'')==='PAYMENT'){$months[$month]['paid']+=$c;$totalPaid+=$c;}else{$months[$month]['returned']+=$c;$totalReturned+=$c;}}}krsort($months);$net=$totalPaid-$totalReturned;
?>
<style>
.employee-report-card .table-toolbar{align-items:flex-end}.employee-report-filter{min-width:340px;margin-left:auto}.employee-report-summary{display:flex;align-items:center;gap:22px;flex-wrap:wrap;padding:10px 12px;border-bottom:1px solid var(--line-soft);background:var(--surface-strong);font-size:11px}.employee-report-summary strong{font-size:12px}.employee-month-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:9px 12px;border-top:1px solid var(--line-soft);border-bottom:1px solid var(--line-soft);background:var(--surface-form);font-size:11px}.employee-month-head:first-child{border-top:0}.employee-month-title{font-weight:700}.employee-report-empty{padding:18px}.employee-report-card .table-scroll+.employee-month-head{border-top:1px solid var(--line-soft)}
</style>
<div class="employee-report">
    <div class="page-head">
        <div class="page-head-left">
            <h1 class="page-title">Выплаты сотрудникам</h1>
            <div class="page-summary"><span>Лицевой счёт сотрудника: все выплаты из кассы и возвраты компании. Прямые выплаты с расчётного счёта не используются.</span></div>
        </div>
    </div>

    <div class="table-card table-card--standard employee-report-card">
        <div class="table-toolbar">
            <div class="table-toolbar-left"><strong>Взаиморасчёты с сотрудником</strong></div>
            <form method="get" class="employee-report-filter">
                <label class="field-label">Сотрудник</label>
                <select class="field-select" name="employee_ref" onchange="this.form.submit()">
                    <option value="">— Выберите сотрудника —</option>
                    <?php foreach($employees as $employee): ?>
                        <option value="<?= e($employee['ref']) ?>" <?= ($selectedRef??'')===$employee['ref']?'selected':'' ?>><?= e($employee['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if(!$selectedEmployee): ?>
            <div class="employee-report-empty"><div class="empty-state"><p class="empty-title">Нет данных.</p><p class="empty-desc">Выберите сотрудника для просмотра взаиморасчётов.</p></div></div>
        <?php else: ?>
            <div class="employee-report-summary">
                <div>Получено от компании: <strong><?= e($fmt($fromCents($totalPaid))) ?> ₽</strong></div>
                <div>Возвращено компании: <strong><?= e($fmt($fromCents($totalReturned))) ?> ₽</strong></div>
                <div>Чисто получено: <strong><?= e($fmt($fromCents($net))) ?> ₽</strong></div>
            </div>

            <?php if(empty($months)): ?>
                <div class="employee-report-empty"><div class="empty-state"><p class="empty-title">Операций нет.</p><p class="empty-desc">По выбранному сотруднику движения пока отсутствуют.</p></div></div>
            <?php else: ?>
                <?php foreach($months as $month=>$bucket): $monthNet=$bucket['paid']-$bucket['returned']; ?>
                    <div class="employee-month-head">
                        <div class="employee-month-title"><?= e(date('m.Y',strtotime($month.'-01'))) ?></div>
                        <div>Получено: <strong><?= e($fmt($fromCents($bucket['paid']))) ?> ₽</strong> · Возвращено: <strong><?= e($fmt($fromCents($bucket['returned']))) ?> ₽</strong> · Итого: <strong><?= e($fmt($fromCents($monthNet))) ?> ₽</strong></div>
                    </div>
                    <div class="table-scroll">
                        <table class="table">
                            <thead><tr><th>Дата</th><th>Операция</th><th>Источник</th><th>Основание</th><th>Получено</th><th>Возвращено</th><th>Сальдо</th></tr></thead>
                            <tbody>
                            <?php foreach($bucket['rows'] as $row):$cancelled=($row['status']??'')==='CANCELLED';$payment=($row['movement_type']??'')==='PAYMENT'; ?>
                                <tr class="<?= $cancelled?'is-muted':'' ?>">
                                    <td><?= e(date('d.m.Y',strtotime($row['operation_date']))) ?></td>
                                    <td><?= $payment?'Выплата':'Возврат' ?></td>
                                    <td><?= ($row['source_type']??'')==='BANK'?'Расчётный счёт (история)':'Касса' ?></td>
                                    <td><?= e($row['purpose']?:($row['note']?:'—')) ?></td>
                                    <td class="col-mono"><?= $payment&&!$cancelled?e($fmt($row['amount'])).' ₽':'—' ?></td>
                                    <td class="col-mono"><?= !$payment&&!$cancelled?e($fmt($row['amount'])).' ₽':'—' ?></td>
                                    <td class="col-mono"><strong><?= e($fmt($row['running_balance'])) ?> ₽</strong></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
