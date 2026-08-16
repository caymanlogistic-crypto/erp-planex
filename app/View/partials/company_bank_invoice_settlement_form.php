<?php
$cp=$settlement['counterparty'];
$isIncome=$settlement['operation_type']==='INCOME';
$directionLabel=$isIncome?'Поступление от клиента':'Оплата перевозчику';
$formatMoney=static fn(string $value):string=>number_format((float)$value,2,',',' ');
$formatDate=static function(?string $value):string{
    $value=trim((string)$value);
    if($value==='')return '—';
    $ts=strtotime($value);
    return $ts?date('d.m.Y',$ts):$value;
};
?>
<form method="post" action="<?= e(app_url('/company/finance/bank-transactions/'.(int)$settlement['bank_transaction_id'].'/settlement')) ?>" data-bank-settlement-form data-operation-remaining="<?= e((string)$settlement['remaining_amount']) ?>">
<?= csrfField() ?>
<div class="modal-body bank-settlement-body">
    <div class="bank-settlement-summary">
        <div><span>Операция</span><strong><?= e($directionLabel) ?> · <?= e($formatDate($settlement['operation_date'])) ?></strong></div>
        <div><span>Контрагент</span><strong><?= e($cp['name']) ?></strong><small>ИНН <?= e($cp['inn']?:'—') ?></small></div>
        <div><span>Сумма</span><strong><?= e($formatMoney($settlement['amount'])) ?> ₽</strong><small>Остаток: <?= e($formatMoney($settlement['remaining_amount'])) ?> ₽</small></div>
    </div>
    <?php if(trim((string)$settlement['purpose'])!==''): ?>
    <div class="bank-settlement-purpose"><span>Назначение платежа</span><div><?= e((string)$settlement['purpose']) ?></div></div>
    <?php endif; ?>

    <div class="section-title mt-section">Открытые счета</div>
    <div class="form-hint">Выберите один или несколько счетов. Оплата автоматически закроет связанные с ними платёжные обязательства рейсов.</div>
    <?php if($settlement['invoices']===[]): ?>
        <div class="empty-state compact"><p>У этого контрагента нет открытых счетов подходящего направления.</p></div>
    <?php else: ?>
    <div class="bank-settlement-table-wrap">
        <table class="bank-settlement-table">
            <thead><tr><th class="bank-settlement-check">Выбрать</th><th>Счёт / связь с рейсом</th><th class="num">Сумма счёта</th><th class="num">Остаток</th><th class="num">Распределить</th></tr></thead>
            <tbody>
            <?php foreach($settlement['invoices'] as $invoice):
                $links=$invoice['obligation_links']??[];
                $routeIds=[];$dueDates=[];
                foreach($links as $link){
                    $routeId=(int)($link['source_parent_id']??$link['linear_route_id']??0);
                    if($routeId>0)$routeIds[$routeId]=true;
                    $due=trim((string)($link['due_date']??$link['forecast_due_date']??''));
                    if($due!=='')$dueDates[$due]=true;
                }
                $suggested=(float)$invoice['remaining_amount']<(float)$settlement['remaining_amount']?(string)$invoice['remaining_amount']:(string)$settlement['remaining_amount'];
            ?>
            <tr data-settlement-invoice-row>
                <td class="bank-settlement-check"><input type="checkbox" class="js-settlement-check" aria-label="Выбрать счёт №<?= e((string)$invoice['number']) ?>"></td>
                <td>
                    <strong>Счёт №<?= e((string)$invoice['number']) ?></strong> <span class="muted">от <?= e($formatDate($invoice['invoice_date']??null)) ?></span>
                    <div class="bank-settlement-link-meta">
                        <?php if($routeIds!==[]): ?>Рейс<?= count($routeIds)>1?'ы':'' ?> #<?= e(implode(', #',array_keys($routeIds))) ?><?php else: ?>Без привязки к рейсу<?php endif; ?>
                        <?php if($dueDates!==[]): ?> · срок <?= e(implode(', ',array_map($formatDate,array_keys($dueDates)))) ?><?php endif; ?>
                    </div>
                </td>
                <td class="num"><?= e($formatMoney((string)$invoice['amount'])) ?> ₽</td>
                <td class="num"><strong><?= e($formatMoney((string)$invoice['remaining_amount'])) ?> ₽</strong></td>
                <td class="num">
                    <input type="hidden" name="invoice_id[]" value="<?= (int)$invoice['id'] ?>" disabled>
                    <input type="text" inputmode="decimal" name="invoice_amount[]" class="field-input js-settlement-amount" value="<?= e(number_format((float)$suggested,2,'.','')) ?>" data-max="<?= e((string)$invoice['remaining_amount']) ?>" disabled autocomplete="off">
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="bank-settlement-total"><span>Выбрано к распределению</span><strong data-settlement-total>0,00 ₽</strong><small>Доступно: <?= e($formatMoney($settlement['remaining_amount'])) ?> ₽</small></div>
    <div class="form-alert alert-error is-hidden" data-settlement-error></div>
    <?php endif; ?>
</div>
<div class="modal-foot is-spaced">
    <div class="modal-foot-actions"><button type="button" class="btn btn-ghost" data-close-modal="bank-invoice-settlement-modal">Отмена</button></div>
    <?php if($settlement['invoices']!==[]): ?><div class="modal-foot-actions"><button type="submit" class="btn btn-primary" data-settlement-submit disabled>Разнести по счетам</button></div><?php endif; ?>
</div>
</form>
