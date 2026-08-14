<?php
use App\Service\FinanceManagementBalanceService;
$currentAsOf=$_GET['as_of']??date('Y-m-d');
$fmt=static fn($v):string=>FinanceManagementBalanceService::formatAmount($v);
?>
<?php if($company===null):?><div class="notice warn">Компания не найдена.</div>
<?php elseif(($company['status']??'')!=='active'):?><div class="page-head"><div class="page-head-left"><h1 class="page-title">Управленческий баланс</h1></div></div><div class="notice warn">Работа с отчётом недоступна.</div>
<?php elseif($dbError!==null):?><div class="page-head"><div class="page-head-left"><h1 class="page-title">Управленческий баланс</h1></div></div><div class="notice warn"><?=e($dbError)?></div>
<?php else:?>
<div class="ux-shell" data-ux-page="management-balance">
 <div class="page-head"><div class="page-head-left"><h1 class="page-title">Управленческий баланс</h1><div class="page-summary"><span>Что есть у компании, что ей должны и сколько она должна сама.</span></div></div></div>
 <div class="ux-summary-strip"><div><strong>Снимок на <?=e(date('d.m.Y',strtotime($balanceData['as_of_date'])))?>.</strong> Баланс показывает денежные средства и расчёты по неоплаченным обязательствам.</div><form method="get" class="ux-summary-actions"><label class="ux-table-meta">На дату</label><input type="date" name="as_of" value="<?=e($currentAsOf)?>" class="field-input" max="<?=date('Y-m-d')?>" style="width:150px;height:30px"><button type="submit" class="btn btn-primary btn-sm">Показать</button><a href="<?=app_url('/company/finance/reports/management-balance')?>" class="btn btn-ghost btn-sm">Сегодня</a></form></div>
 <div class="ux-kpi-grid cols-3">
  <div class="ux-kpi is-good"><div class="ux-kpi-label">Активы</div><div class="ux-kpi-value"><?=$fmt($balanceData['total_assets'])?></div><div class="ux-kpi-note">деньги + дебиторская задолженность</div></div>
  <div class="ux-kpi is-bad"><div class="ux-kpi-label">Обязательства</div><div class="ux-kpi-value"><?=$fmt($balanceData['total_liabilities'])?></div><div class="ux-kpi-note">кредиторская задолженность</div></div>
  <div class="ux-kpi is-accent"><div class="ux-kpi-label">Чистые активы</div><div class="ux-kpi-value"><?=$fmt($balanceData['net_assets'])?></div><div class="ux-kpi-note">активы минус обязательства</div></div>
 </div>
 <div class="ux-balance-grid">
  <section class="ux-balance-card is-assets">
   <div class="ux-balance-head"><div><h2>Активы</h2><div class="ux-section-sub">Ресурсы и требования компании</div></div><div class="ux-balance-total"><?=$fmt($balanceData['total_assets'])?></div></div>
   <div class="ux-balance-group">Денежные средства</div>
   <?php if(empty($balanceData['money_accounts'])):?><div class="ux-balance-row"><div><div class="ux-balance-name text-muted">Нет денежных средств на счетах</div></div><div class="ux-balance-amount">—</div></div><?php else:foreach($balanceData['money_accounts'] as $acc):?><div class="ux-balance-row"><div><div class="ux-balance-name"><?=e($acc['name'])?></div><div class="ux-balance-meta"><?=e($acc['type'])?></div></div><div class="ux-balance-amount"><?=$fmt($acc['balance'])?></div></div><?php endforeach;endif;?>
   <div class="ux-balance-subtotal"><div>Итого денежные средства</div><div class="ux-balance-amount"><?=$fmt($balanceData['total_cash'])?></div></div>
   <div class="ux-balance-group">Дебиторская задолженность</div>
   <?php if(empty($balanceData['receivables'])):?><div class="ux-balance-row"><div><div class="ux-balance-name text-muted">Задолженность отсутствует</div></div><div class="ux-balance-amount">—</div></div><?php else:foreach($balanceData['receivables'] as $r):?><div class="ux-balance-row"><div><div class="ux-balance-name"><a href="<?=e($r['drilldown_url'])?>" class="link"><?=e($r['name'])?></a></div><div class="ux-balance-meta"><?=e($r['counterparty'])?> · <?=e($r['source'])?></div></div><div class="ux-balance-amount"><?=$fmt($r['remaining'])?></div></div><?php endforeach;endif;?>
   <div class="ux-balance-subtotal"><div>Итого дебиторская задолженность</div><div class="ux-balance-amount"><?=$fmt($balanceData['total_receivables'])?></div></div>
  </section>
  <section class="ux-balance-card is-liabilities">
   <div class="ux-balance-head"><div><h2>Обязательства</h2><div class="ux-section-sub">Суммы, которые должна компания</div></div><div class="ux-balance-total"><?=$fmt($balanceData['total_liabilities'])?></div></div>
   <div class="ux-balance-group">Кредиторская задолженность</div>
   <?php if(empty($balanceData['payables'])):?><div class="ux-balance-row"><div><div class="ux-balance-name text-muted">Задолженность отсутствует</div></div><div class="ux-balance-amount">—</div></div><?php else:foreach($balanceData['payables'] as $p):?><div class="ux-balance-row"><div><div class="ux-balance-name"><a href="<?=e($p['drilldown_url'])?>" class="link"><?=e($p['name'])?></a></div><div class="ux-balance-meta"><?=e($p['counterparty'])?> · <?=e($p['source'])?></div></div><div class="ux-balance-amount"><?=$fmt($p['remaining'])?></div></div><?php endforeach;endif;?>
   <div class="ux-balance-subtotal"><div>Итого кредиторская задолженность</div><div class="ux-balance-amount"><?=$fmt($balanceData['total_payables'])?></div></div>
   <div class="ux-balance-group">Авансы</div>
   <div class="ux-balance-row"><div><div class="ux-balance-name">Авансы выданные</div><div class="ux-balance-meta">Будут учитываться после включения учёта авансов</div></div><div class="ux-balance-amount">0,00</div></div>
   <div class="ux-balance-row"><div><div class="ux-balance-name">Авансы полученные</div><div class="ux-balance-meta">Будут учитываться после включения учёта авансов</div></div><div class="ux-balance-amount">0,00</div></div>
  </section>
 </div>
 <div class="ux-tech-note"><strong>Чистые активы: <?=$fmt($balanceData['net_assets'])?>.</strong> Показатель рассчитывается как активы минус обязательства. Переход по задолженности открывает её первичный источник.</div>
</div>
<?php endif;?>