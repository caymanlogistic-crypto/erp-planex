<?php
$fmt=static fn($v)=>\App\Service\FinanceEmployeePaymentService::formatMoney($v);
$toCents=static function($v):int{$s=str_replace([' ', ','],['','.'],trim((string)$v));if(!preg_match('/^-?\d+(?:\.\d{1,2})?$/D',$s))return 0;$neg=str_starts_with($s,'-');if($neg)$s=substr($s,1);[$r,$k]=array_pad(explode('.',$s,2),2,'');$c=((int)$r*100)+(int)str_pad(substr($k,0,2),2,'0');return $neg?-$c:$c;};
$fromCents=static fn(int $c):string=>($c<0?'-':'').intdiv(abs($c),100).'.'.str_pad((string)(abs($c)%100),2,'0',STR_PAD_LEFT);
$shortName=static function(?string $fullName):string{
    $fullName=trim((string)$fullName);
    if($fullName==='')return '—';
    $parts=preg_split('/\s+/u',$fullName,-1,PREG_SPLIT_NO_EMPTY)?:[];
    if(!$parts)return '—';
    $last=array_shift($parts);
    $initials='';
    foreach(array_slice($parts,0,2) as $part){$initials.=mb_strtoupper(mb_substr($part,0,1)).'.';}
    return trim($last.' '.$initials);
};
$cleanBasis=static function(?string $purpose,?string $note):string{
    $value=trim((string)($purpose?:$note));
    if($value==='')return '—';
    $value=preg_replace('/^Передано сотруднику:\s*[^·]+·\s*/u','',$value)??$value;
    $value=preg_replace('/^Возврат от сотрудника:\s*[^·]+·\s*/u','',$value)??$value;
    return trim($value)!==''?trim($value):'—';
};
$months=[];$totalPaid=0;$totalReturned=0;
foreach($ledger??[] as $row){$month=substr((string)$row['operation_date'],0,7);$months[$month]??=['paid'=>0,'returned'=>0,'rows'=>[]];$months[$month]['rows'][]=$row;if(($row['status']??'')==='POSTED'){$c=$toCents($row['amount']);if(($row['movement_type']??'')==='PAYMENT'){$months[$month]['paid']+=$c;$totalPaid+=$c;}else{$months[$month]['returned']+=$c;$totalReturned+=$c;}}}
krsort($months);$net=$totalPaid-$totalReturned;
?>
<style>
.employee-report{width:min(1280px,100%);margin:0 auto}
.employee-report-card .table-toolbar{height:auto;min-height:var(--toolbar-h);align-items:center;overflow:visible}
.employee-report-filter{margin-left:auto;display:flex;align-items:center;gap:8px;min-width:0}
.employee-report-filter-label{font-size:10px;font-weight:600;color:var(--text-faint);white-space:nowrap}
.employee-report-filter .field-select{width:300px;max-width:32vw;height:26px}
.employee-report-summary{display:flex;align-items:center;gap:22px;flex-wrap:wrap;padding:10px 12px;border-bottom:1px solid var(--line-soft);background:var(--surface-strong);font-size:11px}
.employee-report-summary strong{font-size:12px}
.employee-month-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:9px 12px;border-top:1px solid var(--line-soft);border-bottom:1px solid var(--line-soft);background:var(--surface-form);font-size:11px}
.employee-month-head:first-child{border-top:0}.employee-month-title{font-weight:700}.employee-report-empty{padding:18px}.employee-report-card .table-scroll+.employee-month-head{border-top:1px solid var(--line-soft)}
.employee-report-card .table-scroll{overflow-x:hidden}
.employee-report-table{width:100%;table-layout:fixed}
.employee-report-table th,.employee-report-table td{min-width:0}
.employee-report-table .employee-basis{white-space:normal;overflow-wrap:anywhere;word-break:break-word;line-height:1.25}
.employee-report-table .employee-name{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.employee-report-table .money-cell{white-space:nowrap}
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
                <span class="employee-report-filter-label">Сотрудник</span>
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
                        <table class="table employee-report-table">
                            <colgroup>
                                <col style="width:82px"><col style="width:78px"><col style="width:82px"><col><col style="width:105px"><col style="width:105px"><col style="width:105px"><col style="width:118px">
                            </colgroup>
                            <thead><tr><th>Дата</th><th>Операция</th><th>Источник</th><th>Основание</th><th>Получено</th><th>Возвращено</th><th>Сальдо</th><th>Сотрудник</th></tr></thead>
                            <tbody>
                            <?php foreach($bucket['rows'] as $row):
                                $cancelled=($row['status']??'')==='CANCELLED';
                                $payment=($row['movement_type']??'')==='PAYMENT';
                                $employeeFullName=(string)($row['full_name']??($row['employee_name_snapshot']??($selectedEmployee['full_name']??'')));
                                $basis=$cleanBasis($row['purpose']??null,$row['note']??null);
                            ?>
                                <tr class="<?= $cancelled?'is-muted':'' ?>">
                                    <td><?= e(date('d.m.Y',strtotime($row['operation_date']))) ?></td>
                                    <td><?= $payment?'Выплата':'Возврат' ?></td>
                                    <td><?= ($row['source_type']??'')==='BANK'?'Расчётный счёт':'Касса' ?></td>
                                    <td class="employee-basis" title="<?= e($basis) ?>"><?= e($basis) ?></td>
                                    <td class="col-mono money-cell"><?= $payment&&!$cancelled?e($fmt($row['amount'])).' ₽':'—' ?></td>
                                    <td class="col-mono money-cell"><?= !$payment&&!$cancelled?e($fmt($row['amount'])).' ₽':'—' ?></td>
                                    <td class="col-mono money-cell"><strong><?= e($fmt($row['running_balance'])) ?> ₽</strong></td>
                                    <td class="employee-name" title="<?= e($employeeFullName) ?>"><?= e($shortName($employeeFullName)) ?></td>
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
