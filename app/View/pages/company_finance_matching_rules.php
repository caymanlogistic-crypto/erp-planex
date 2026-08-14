<?php
$directionLabel=static fn($d):string=>match($d){'INCOME'=>'Поступление','EXPENSE'=>'Расход',default=>'Любое направление'};
$activeRules=array_values(array_filter($rules??[],static fn(array $r):bool=>!empty($r['active'])));
$inactiveRules=count($rules??[])-count($activeRules);
$autoRules=count(array_filter($rules??[],static fn(array $r):bool=>!empty($r['auto_apply'])));
$cfuNames=[];foreach($cashFlowCenters??[] as $row){$cfuNames[(int)$row['id']]=(string)$row['name'];}
$ddsNames=[];foreach($ddsCategories??[] as $row){$ddsNames[(int)$row['id']]=(string)$row['name'];}
$cashNames=[];foreach($cashAccounts??[] as $row){$cashNames[(int)$row['id']]=(string)($row['name']??'Касса');}
$ruleCondition=static function(array $r)use($directionLabel):string{
 $parts=[];
 if(!empty($r['counterparty_inn']))$parts[]='ИНН '.(string)$r['counterparty_inn'];
 if(!empty($r['purpose_contains']))$parts[]='назначение содержит «'.(string)$r['purpose_contains'].'»';
 if(!empty($r['invoice_number_pattern']))$parts[]='номер счёта совпадает';
 if(!empty($r['amount_from'])||!empty($r['amount_to'])){
  $from=!empty($r['amount_from'])?number_format((float)$r['amount_from'],0,',',' ').' ₽':'0 ₽';
  $to=!empty($r['amount_to'])?number_format((float)$r['amount_to'],0,',',' ').' ₽':'∞';
  $parts[]='сумма '.$from.'–'.$to;
 }
 if(empty($parts))$parts[]='все подходящие операции';
 return $directionLabel($r['direction']??'').' · '.implode(' · ',$parts);
};
$ruleResult=static function(array $r)use($cfuNames,$ddsNames,$cashNames):string{
 $cfuId=(int)($r['target_cash_flow_center_id']??0);$ddsId=(int)($r['target_dds_category_id']??0);$cashId=(int)($r['target_cash_account_id']??0);
 if(($r['action_type']??'')==='categorize'&&($cfuId||$ddsId)){
  $parts=[];if($cfuId)$parts[]=$cfuNames[$cfuId]??('ЦФУ #'.$cfuId);if($ddsId)$parts[]=$ddsNames[$ddsId]??('Статья #'.$ddsId);return implode(' → ',$parts);
 }
 if(($r['action_type']??'')==='transfer_to_cash')return 'Перевод в '.($cashNames[$cashId]??'кассу');
 if(($r['action_type']??'')==='match_invoice')return 'Сопоставить со счётом';
 if(($r['action_type']??'')==='match_counterparty')return 'Сопоставить с контрагентом';
 return 'Классификация операции';
};
?>
<?php if($company===null): ?><div class="notice warn">Компания не найдена.</div>
<?php elseif(($company['status']??'')!=='active'): ?>
<div class="page-head"><div class="page-head-left"><h1 class="page-title">Правила разнесения</h1><div class="page-summary"><span>Компания находится в неактивном статусе.</span></div></div></div><div class="notice warn">Работа с правилами недоступна.</div>
<?php elseif($dbError!==null): ?>
<div class="page-head"><div class="page-head-left"><h1 class="page-title">Правила разнесения</h1></div></div><div class="notice warn"><?= e($dbError) ?></div>
<?php else: ?>
<div class="ux-shell" data-ux-page="matching-rules">
 <div class="page-head">
  <div class="page-head-left"><h1 class="page-title">Правила разнесения</h1><div class="page-summary"><span>Условия применяются одновременно по строгой AND-логике.</span></div></div>
  <div class="page-head-actions"><button type="button" class="btn btn-primary btn--toolbar" id="matching-rule-create-btn">+ Правило</button><a href="<?= app_url('/company/finance/bank-accounts') ?>" class="btn btn-secondary btn--toolbar">Назад к выпискам</a></div>
 </div>
 <?php if(!empty($successFlash)): ?><div class="notice success"><?= e($successFlash) ?></div><?php endif; ?>
 <?php if(!empty($errorFlash)): ?><div class="notice warn"><?= e($errorFlash) ?></div><?php endif; ?>

 <div class="ux-filter-panel">
  <div class="ux-filter-head"><div class="ux-filter-title">Поиск по правилам</div><div class="ux-table-meta">Без перезагрузки страницы</div></div>
  <div class="ux-filter-body"><div class="ux-filter-field is-search"><label>Название, ИНН, условие или результат</label><input type="search" class="field-input" id="rules-search" placeholder="Например: ЕНП, 7727406020, Налоги"></div><div class="ux-filter-field"><label>Статус</label><select class="field-select" id="rules-status"><option value="all">Все правила</option><option value="active">Активные</option><option value="inactive">Отключённые</option></select></div></div>
 </div>

 <div class="table-card table-card--standard">
  <div class="table-toolbar"><div class="table-toolbar-left"><strong>Сценарии автоматического разнесения</strong></div></div>
  <?php if(empty($rules)): ?><div class="ux-empty"><strong>Правил пока нет</strong>Создайте первое правило для повторяющейся банковской операции.</div>
  <?php else: ?><div class="table-scroll"><table class="table" id="rules-list"><thead><tr><th class="col-tight">Приоритет</th><th>Правило</th><th>Условия</th><th>Результат</th><th class="col-tight">Статус</th><th class="col-actions">Действия</th></tr></thead><tbody>
   <?php foreach($rules as $rule): $active=!empty($rule['active']);$condition=$ruleCondition($rule);$result=$ruleResult($rule);$hay=mb_strtolower(($rule['name']??'').' '.($rule['counterparty_inn']??'').' '.($rule['purpose_contains']??'').' '.$condition.' '.$result); ?>
   <tr data-rule-row data-status="<?= $active?'active':'inactive' ?>" data-search="<?= e($hay) ?>">
    <td class="col-mono"><strong><?= (int)($rule['priority']??0) ?></strong></td>
    <td><strong><?= e($rule['name']??'Без названия') ?></strong><div class="field-note"><?= !empty($rule['auto_apply'])?'Автоматическое применение':'Требует подтверждения' ?></div></td>
    <td><?= e($condition) ?></td>
    <td><strong><?= e($result) ?></strong></td>
    <td><span class="<?= $active?'badge badge-ok':'badge badge-neutral' ?>"><span class="dot"></span><?= $active?'Активно':'Отключено' ?></span></td>
    <td class="col-actions"><div class="ux-actions"><button type="button" class="btn btn-ghost btn-sm" data-preview-id="<?= (int)$rule['id'] ?>">Проверить</button><button type="button" class="btn btn-secondary btn-sm" data-edit-id="<?= (int)$rule['id'] ?>">Изменить</button><form method="post" action="<?= app_url('/company/finance/settings/matching-rules/toggle') ?>" class="inline-form" data-confirm="<?= $active?'Отключить правило?':'Включить правило?' ?>"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int)$rule['id'] ?>"><button type="submit" class="btn btn-ghost btn-sm"><?= $active?'Отключить':'Включить' ?></button></form><form method="post" action="<?= app_url('/company/finance/settings/matching-rules/remove') ?>" class="inline-form" data-rule-delete-form data-rule-name="<?= e($rule['name']??'Без названия') ?>"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int)$rule['id'] ?>"><button type="submit" class="btn btn-ghost btn-sm text-danger">Удалить</button></form></div></td>
   </tr>
   <?php endforeach; ?>
  </tbody></table></div><div class="ux-empty ux-hidden" id="rules-empty"><strong>Ничего не найдено</strong>Измените запрос или фильтр статуса.</div><?php endif; ?>
 </div>
</div>

<div id="matching-rule-create-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="0" data-close-on-escape="0"><div class="modal modal-lg"><div class="modal-head"><span class="modal-title">Новое правило разнесения</span><button type="button" class="modal-close" data-close-modal="matching-rule-create-modal">&times;</button></div><div class="modal-body" id="matching-rule-create-modal-body"></div></div></div>
<div id="matching-rule-edit-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="0" data-close-on-escape="0"><div class="modal modal-lg"><div class="modal-head"><span class="modal-title">Редактирование правила</span><button type="button" class="modal-close" data-close-modal="matching-rule-edit-modal">&times;</button></div><div class="modal-body" id="matching-rule-edit-modal-body"></div></div></div>
<div id="matching-rule-preview-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="1" data-close-on-escape="1"><div class="modal modal-lg"><div class="modal-head"><span class="modal-title">Проверка правила на операциях</span><button type="button" class="modal-close" data-close-modal="matching-rule-preview-modal">&times;</button></div><div class="modal-body" id="matching-rule-preview-body"><div class="empty-state compact"><p>Загрузка...</p></div></div></div></div>
<div id="matching-rule-delete-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="1" data-close-on-escape="1"><div class="modal modal-sm"><div class="modal-head"><span class="modal-title">Удалить правило</span><button type="button" class="modal-close" data-close-modal="matching-rule-delete-modal">&times;</button></div><div class="modal-body"><div class="driver-delete-confirm-title" id="matching-rule-delete-title">Удалить правило?</div><div class="driver-delete-confirm-text">Автоматическое разнесение, выполненное именно этим правилом, будет снято. Операции вернутся в статус «Не разнесено». Ручные разнесения и другие правила не изменятся.</div></div><div class="modal-foot"><button type="button" class="btn btn-ghost" data-close-modal="matching-rule-delete-modal">Отмена</button><button type="button" class="btn btn-danger" id="matching-rule-delete-confirm">Удалить</button></div></div></div>
<script>
document.addEventListener('DOMContentLoaded',function(){
 const search=document.getElementById('rules-search'),status=document.getElementById('rules-status'),rows=[...document.querySelectorAll('[data-rule-row]')],empty=document.getElementById('rules-empty');
 function filterRules(){let q=(search?search.value:'').trim().toLowerCase(),st=status?status.value:'all',shown=0;rows.forEach(r=>{let ok=(!q||(r.dataset.search||'').includes(q))&&(st==='all'||r.dataset.status===st);r.classList.toggle('ux-hidden',!ok);if(ok)shown++});if(empty)empty.classList.toggle('ux-hidden',shown!==0)}
 if(search)search.addEventListener('input',filterRules);if(status)status.addEventListener('change',filterRules);
 function load(btnId,modalId,bodyId,url){let btn=document.getElementById(btnId);if(!btn)return;btn.addEventListener('click',()=>{let body=document.getElementById(bodyId);body.innerHTML='<div class="empty-state compact"><p>Загрузка...</p></div>';window.openModal(modalId);fetch(window.getErpBasePath()+url).then(r=>r.text()).then(h=>body.innerHTML=h).catch(()=>body.innerHTML='<div class="form-alert alert-error">Не удалось загрузить форму.</div>')})}
 load('matching-rule-create-btn','matching-rule-create-modal','matching-rule-create-modal-body','/company/finance/settings/matching-rules/create');
 document.querySelectorAll('[data-edit-id]').forEach(btn=>btn.addEventListener('click',function(){let id=this.dataset.editId,body=document.getElementById('matching-rule-edit-modal-body');body.innerHTML='<div class="empty-state compact"><p>Загрузка...</p></div>';window.openModal('matching-rule-edit-modal');fetch(window.getErpBasePath()+'/company/finance/settings/matching-rules/edit?id='+id).then(r=>r.text()).then(h=>body.innerHTML=h).catch(()=>body.innerHTML='<div class="form-alert alert-error">Не удалось загрузить форму.</div>')}));
 document.querySelectorAll('[data-preview-id]').forEach(btn=>btn.addEventListener('click',function(){let id=this.dataset.previewId,body=document.getElementById('matching-rule-preview-body');body.innerHTML='<div class="empty-state compact"><p>Загрузка...</p></div>';window.openModal('matching-rule-preview-modal');fetch(window.getErpBasePath()+'/company/finance/settings/matching-rules/preview?id='+id).then(r=>r.json()).then(data=>{let h='<div class="ux-summary-strip"><div><strong>'+String(data.rule_name||'Правило')+'</strong></div><div>Совпадений: '+Number(data.total_matches||0)+'</div></div>';if(data.results&&data.results.length){h+='<div class="table-scroll"><table class="table"><thead><tr><th>Дата</th><th>Контрагент</th><th>Сумма</th><th>Назначение</th><th>Результат</th><th>Причина</th></tr></thead><tbody>';data.results.forEach(r=>h+='<tr><td>'+r.operation_date+'</td><td>'+r.counterparty_name+'</td><td class="col-mono">'+r.amount+'</td><td>'+r.purpose+'</td><td>'+r.result+'</td><td>'+r.reason+'</td></tr>');h+='</tbody></table></div>'}else h+='<div class="ux-empty"><strong>Совпадений нет</strong>Непроведённые операции не соответствуют этому правилу.</div>';body.innerHTML=h}).catch(()=>body.innerHTML='<div class="form-alert alert-error">Не удалось загрузить проверку правила.</div>')}));
 document.querySelectorAll('[data-confirm]').forEach(f=>f.addEventListener('submit',e=>{if(!confirm(f.dataset.confirm))e.preventDefault()}));
 let pendingDelete=null;document.querySelectorAll('[data-rule-delete-form]').forEach(f=>f.addEventListener('submit',e=>{e.preventDefault();pendingDelete=f;let title=document.getElementById('matching-rule-delete-title');if(title)title.textContent='Удалить правило «'+(f.dataset.ruleName||'Без названия')+'»?';window.openModal('matching-rule-delete-modal')}));
 const deleteConfirm=document.getElementById('matching-rule-delete-confirm');if(deleteConfirm)deleteConfirm.addEventListener('click',()=>{if(!pendingDelete)return;let f=pendingDelete;pendingDelete=null;deleteConfirm.disabled=true;f.submit()});
});
</script>
<?php endif; ?>
