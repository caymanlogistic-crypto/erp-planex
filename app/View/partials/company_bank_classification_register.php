<?php
$statusOptions=[''=>'Все статусы','UNALLOCATED'=>'Не разнесено','AUTO'=>'Разнесено автоматически','MANUAL'=>'Разнесено вручную','NEEDS_REVIEW'=>'Конфликт / требует проверки'];
?>
<?php if($bankFinanceSuccess): ?><div class="notice success"><?= e($bankFinanceSuccess) ?></div><?php endif; ?>
<?php if($bankFinanceError): ?><div class="notice warn"><?= e($bankFinanceError) ?></div><?php endif; ?>
<div class="table-card table-card--standard" id="bank-classification-register">
 <div class="bank-controls">
  <form method="get" class="bank-controls-filter" action="<?= app_url('/company/finance/bank-accounts') ?>">
   <label class="bank-date-field"><span class="bank-control-label">Дата с</span><input type="date" name="date_from" class="bank-date-input" value="<?= e($_GET['date_from']??'') ?>"></label>
   <label class="bank-date-field"><span class="bank-control-label">Дата по</span><input type="date" name="date_to" class="bank-date-input" value="<?= e($_GET['date_to']??'') ?>"></label>
   <label class="bank-date-field"><span class="bank-control-label">Статус</span><select name="classification_status" class="field-select"><?php foreach($statusOptions as $value=>$label): ?><option value="<?= e($value) ?>" <?= $classificationStatus===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
   <label class="bank-date-field"><span class="bank-control-label">Поиск</span><input type="search" name="q" class="field-input" value="<?= e($search) ?>" placeholder="№, контрагент, ИНН, назначение, ЦФУ, ДДС"></label>
   <button type="submit" class="btn btn-secondary btn-toolbar">Применить</button><a href="<?= app_url('/company/finance/bank-accounts') ?>" class="btn btn-ghost btn-toolbar">Сброс</a>
  </form>
  <div class="bank-controls-right"><span class="bank-control-meta">Разнесение: <b><?= (int)$txTotal ?></b> операций</span></div>
 </div>
 <div class="table-scroll"><table class="table"><thead><tr><th>Дата</th><th>№</th><th>Контрагент</th><th>Назначение</th><th>Сумма</th><th>ЦФУ</th><th>Статья ДДС</th><th>Статус</th><th></th></tr></thead><tbody>
 <?php if(!$transactions): ?><tr><td colspan="9"><div class="empty-state compact"><p>Операций по выбранным условиям нет.</p></div></td></tr><?php endif; ?>
 <?php foreach($transactions as $tx): $income=(float)($tx['credit_amount']??0)>0;$amount=$income?($tx['credit_amount']??0):($tx['debit_amount']??0);$status=$tx['classification_status']??'UNALLOCATED'; ?>
  <tr><td class="col-mono"><?= e($tx['operation_date']??'—') ?></td><td class="col-mono"><?= e($tx['document_number']?:('ID '.(int)$tx['id'])) ?></td><td title="<?= e($tx['counterparty_name']??'') ?>"><?= e($tx['counterparty_name']?:'—') ?></td><td title="<?= e($tx['purpose']??'') ?>"><?= e($tx['purpose']?:'—') ?></td><td class="col-mono <?= $income?'text-success':'text-danger' ?>"><?= $income?'+':'−' ?><?= e((string)$amount) ?> ₽</td><td><?= e($tx['cash_flow_center_name']?:'—') ?></td><td><?= e(trim(($tx['dds_category_code']??'').' '.($tx['dds_category_name']??''))?:'—') ?></td><td><span class="<?= e(\App\Service\FinanceMatchingRuleService::classificationBadgeClass($status)) ?>"><span class="dot"></span><?= e(\App\Service\FinanceMatchingRuleService::classificationStatusLabel($status)) ?></span><?php if(!empty($tx['is_internal_transfer'])): ?><div class="field-note">Внутренний перевод</div><?php endif; ?></td><td><button type="button" class="btn btn-secondary btn-sm" data-classify-url="<?= e(app_url('/company/finance/bank-transactions/'.(int)$tx['id'].'/classify')) ?>"><?= !empty($tx['is_internal_transfer'])?'Открыть':'Разнести' ?></button></td></tr>
 <?php endforeach; ?>
 </tbody></table></div>
</div>
<div id="bank-classify-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="0" data-close-on-escape="0"><div class="modal modal-lg"><div class="modal-head"><span class="modal-title">Разнести банковскую операцию</span><button type="button" class="modal-close" data-close-modal="bank-classify-modal">&times;</button></div><div id="bank-classify-modal-body"><div class="modal-body"><div class="empty-state compact"><p>Загрузка...</p></div></div></div></div></div>
<script>document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('[data-classify-url]').forEach(function(btn){btn.addEventListener('click',function(){var body=document.getElementById('bank-classify-modal-body');body.innerHTML='<div class="modal-body"><div class="empty-state compact"><p>Загрузка...</p></div></div>';window.openModal('bank-classify-modal');fetch(this.dataset.classifyUrl,{credentials:'same-origin'}).then(function(r){return r.text()}).then(function(html){body.innerHTML=html}).catch(function(){body.innerHTML='<div class="modal-body"><div class="form-alert alert-error">Не удалось загрузить форму.</div></div>'});});});});</script>
