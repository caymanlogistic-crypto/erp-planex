<?php
use App\Service\FinancePaymentCalendarService;
$fmtDate=static function($d){if(!$d||$d==='—')return '—';$ts=strtotime($d);return $ts===false?'—':date('d.m.Y',$ts);};
$currentDateFrom=$_GET['date_from']??'';$currentDateTo=$_GET['date_to']??'';$currentDirection=$_GET['direction']??'';$currentStatus=$_GET['status']??'';$currentSearch=$_GET['search']??'';
$overdueCount=count(array_filter($rows??[],static fn(array $r):bool=>($r['calendar_status']??'')==='overdue'));
?>
<?php if($company===null):?><div class="notice warn">Компания не найдена.</div>
<?php elseif(($company['status']??'')!=='active'):?><div class="page-head"><div class="page-head-left"><h1 class="page-title">Платёжный календарь</h1></div></div><div class="notice warn">Работа с платёжным календарём недоступна.</div>
<?php elseif($dbError!==null):?><div class="page-head"><div class="page-head-left"><h1 class="page-title">Платёжный календарь</h1></div></div><div class="notice warn"><?=e($dbError)?></div>
<?php else:?>
<div class="ux-shell" data-ux-page="payment-calendar">
 <div class="page-head"><div class="page-head-left"><h1 class="page-title">Платёжный календарь</h1><div class="page-summary"><span>Что компания должна получить и оплатить, когда и в каком объёме.</span></div></div></div>
 <div class="ux-summary-strip"><div><strong>Контроль ликвидности:</strong> сначала смотрите просрочки, затем ближайшие платежи и ожидаемые поступления.</div><div><?=count($rows??[])?> записей · <?=$overdueCount?> просрочено</div></div>
 <?php if(!empty($summary)):?>
 <div class="ux-kpi-grid cols-5">
  <div class="ux-kpi is-good"><div class="ux-kpi-label">Ожидаем поступления</div><div class="ux-kpi-value"><?=FinancePaymentCalendarService::formatAmount($summary['expected_income'])?></div><div class="ux-kpi-note">по текущему отбору</div></div>
  <div class="ux-kpi is-bad"><div class="ux-kpi-label">Предстоит оплатить</div><div class="ux-kpi-value"><?=FinancePaymentCalendarService::formatAmount($summary['expected_expense'])?></div><div class="ux-kpi-note">по текущему отбору</div></div>
  <div class="ux-kpi is-accent"><div class="ux-kpi-label">Нетто-план</div><div class="ux-kpi-value"><?=FinancePaymentCalendarService::formatAmount($summary['net_plan'])?></div><div class="ux-kpi-note">поступления минус платежи</div></div>
  <div class="ux-kpi<?=((float)$summary['overdue_income']>0)?' is-bad':' is-muted'?>"><div class="ux-kpi-label">Просрочено нам</div><div class="ux-kpi-value"><?=FinancePaymentCalendarService::formatAmount($summary['overdue_income'])?></div><div class="ux-kpi-note">дебиторская просрочка</div></div>
  <div class="ux-kpi<?=((float)$summary['overdue_expense']>0)?' is-bad':' is-muted'?>"><div class="ux-kpi-label">Просрочено нами</div><div class="ux-kpi-value"><?=FinancePaymentCalendarService::formatAmount($summary['overdue_expense'])?></div><div class="ux-kpi-note">кредиторская просрочка</div></div>
 </div>
 <?php endif;?>
 <form method="get" class="ux-filter-panel">
  <div class="ux-filter-head"><div class="ux-filter-title">Период и отбор</div><a href="<?=app_url('/company/finance/payment-calendar')?>" class="btn btn-ghost btn-sm">Сбросить</a></div>
  <div class="ux-filter-body">
   <div class="ux-filter-field"><label>С даты</label><input type="date" name="date_from" value="<?=e($currentDateFrom)?>" class="field-input"></div>
   <div class="ux-filter-field"><label>По дату</label><input type="date" name="date_to" value="<?=e($currentDateTo)?>" class="field-input"></div>
   <div class="ux-filter-field"><label>Направление</label><select name="direction" class="field-select"><option value="">Все</option><option value="INCOME"<?=$currentDirection==='INCOME'?' selected':''?>>Поступления</option><option value="EXPENSE"<?=$currentDirection==='EXPENSE'?' selected':''?>>Платежи</option></select></div>
   <div class="ux-filter-field"><label>Статус</label><select name="status" class="field-select"><option value="">Все</option><option value="planned"<?=$currentStatus==='planned'?' selected':''?>>Запланировано</option><option value="partial"<?=$currentStatus==='partial'?' selected':''?>>Частично</option><option value="overdue"<?=$currentStatus==='overdue'?' selected':''?>>Просрочено</option><option value="waiting_event"<?=$currentStatus==='waiting_event'?' selected':''?>>Ожидает события</option></select></div>
   <div class="ux-filter-field is-search"><label>Контрагент или источник</label><input type="search" name="search" value="<?=e($currentSearch)?>" class="field-input" placeholder="Поиск"></div>
   <div class="ux-filter-actions"><button type="submit" class="btn btn-primary">Применить</button></div>
  </div>
 </form>
 <div class="ux-table">
  <div class="ux-table-head"><div><div class="ux-table-title">Плановые движения</div><div class="ux-table-meta">Сортировка определяется датой обязательства.</div></div><div class="ux-table-meta">Показано: <?=count($rows??[])?></div></div>
  <?php if(empty($rows)):?><div class="ux-empty"><strong>Записей нет</strong>Создайте счета или рейсы с плановыми платежами.</div>
  <?php else:?><div class="table-scroll"><table class="table"><thead><tr><th>Дата</th><th>Сторона</th><th>Контрагент / источник</th><th>Рейс</th><th>План</th><th>Оплачено</th><th>Остаток</th><th>Статус</th></tr></thead><tbody>
   <?php foreach($rows as $r):$st=$r['calendar_status']??'';?><tr class="<?=$st==='overdue'?'ux-row-overdue':($st==='partial'?'ux-row-partial':'')?>"><td class="col-mono"><?=$r['due_date']!==null?$fmtDate($r['due_date']):'Ожидает события'?></td><td><?php if($r['direction']==='INCOME'):?><span class="badge badge-ok"><span class="dot"></span>Поступление</span><?php else:?><span class="badge badge-danger"><span class="dot"></span>Платёж</span><?php endif;?></td><td><div class="ux-primary-cell"><strong><?=e($r['counterparty']??'—')?></strong><span><?=e($r['source_label']??'—')?></span></div></td><td><?=e($r['route_label']??'—')?></td><td class="col-mono"><?=FinancePaymentCalendarService::formatAmount($r['amount']??null)?></td><td class="col-mono"><?=FinancePaymentCalendarService::formatAmount($r['paid']??null)?></td><td class="col-mono"><strong><?=FinancePaymentCalendarService::formatAmount($r['remaining']??null)?></strong></td><td><?php if($st==='overdue'):?><span class="badge badge-danger"><span class="dot"></span>Просрочено</span><?php elseif($st==='partial'):?><span class="badge badge-warning"><span class="dot"></span>Частично</span><?php elseif($st==='waiting_event'):?><span class="badge badge-neutral"><span class="dot"></span>Ожидает события</span><?php else:?><span class="badge badge-ok"><span class="dot"></span>Запланировано</span><?php endif;?></td></tr><?php endforeach;?>
  </tbody></table></div><?php endif;?>
 </div>
</div>
<?php endif;?>