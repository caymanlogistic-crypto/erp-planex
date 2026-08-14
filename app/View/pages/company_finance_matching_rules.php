<?php
$directionLabel = static fn($d): string => match ($d) { 'INCOME'=>'Поступление','EXPENSE'=>'Расход',default=>'Любое направление' };
$activeRules = array_values(array_filter($rules ?? [], static fn(array $r): bool => !empty($r['active'])));
$inactiveRules = count($rules ?? []) - count($activeRules);
$autoRules = count(array_filter($rules ?? [], static fn(array $r): bool => !empty($r['auto_apply'])));
$ruleCondition = static function(array $r) use ($directionLabel): string {
    $parts=[];
    if (!empty($r['counterparty_inn'])) $parts[]='ИНН '.(string)$r['counterparty_inn'];
    if (!empty($r['purpose_contains'])) $parts[]='назначение содержит «'.(string)$r['purpose_contains'].'»';
    if (!empty($r['invoice_number_pattern'])) $parts[]='номер счёта совпадает';
    if (!empty($r['amount_from']) || !empty($r['amount_to'])) {
        $from=!empty($r['amount_from'])?number_format((float)$r['amount_from'],0,',',' ').' ₽':'0 ₽';
        $to=!empty($r['amount_to'])?number_format((float)$r['amount_to'],0,',',' ').' ₽':'∞';
        $parts[]='сумма '.$from.'–'.$to;
    }
    if (empty($parts)) $parts[]='все подходящие операции';
    return $directionLabel($r['direction'] ?? '').' · '.implode(' · ',$parts);
};
$ruleResult = static function(array $r): string {
    $parts=[];
    if (!empty($r['target_cash_flow_center_id'])) $parts[]='ЦФУ';
    if (!empty($r['target_dds_category_id'])) $parts[]='статья ДДС';
    if (!empty($r['target_cash_account_id'])) $parts[]='касса';
    if (($r['action_type'] ?? '')==='match_invoice') $parts[]='счёт';
    if (($r['action_type'] ?? '')==='match_counterparty') $parts[]='контрагент';
    if (($r['action_type'] ?? '')==='transfer_to_cash') $parts[]='перевод в кассу';
    return $parts ? implode(' + ',$parts) : 'классификация операции';
};
?>
<?php if ($company === null): ?><div class="notice warn">Компания не найдена.</div>
<?php elseif (($company['status'] ?? '') !== 'active'): ?>
<div class="page-head"><div class="page-head-left"><h1 class="page-title">Правила разнесения</h1><div class="page-summary"><span>Компания находится в неактивном статусе.</span></div></div></div><div class="notice warn">Работа с правилами недоступна.</div>
<?php elseif ($dbError !== null): ?>
<div class="page-head"><div class="page-head-left"><h1 class="page-title">Правила разнесения</h1></div></div><div class="notice warn"><?= e($dbError) ?></div>
<?php else: ?>
<div class="ux-shell" data-ux-page="matching-rules">
 <div class="page-head">
  <div class="page-head-left"><h1 class="page-title">Правила разнесения</h1><div class="page-summary"><span>Автоматизируйте повторяющиеся банковские операции: условие → ЦФУ и статья ДДС.</span></div></div>
  <div class="page-head-right"><a href="<?= app_url('/company/finance/bank-accounts') ?>" class="btn btn-secondary btn--toolbar">Назад к выпискам</a><button type="button" class="btn btn-primary btn--toolbar" id="matching-rule-create-btn">+ Правило</button></div>
 </div>
 <?php if (!empty($successFlash)): ?><div class="notice success"><?= e($successFlash) ?></div><?php endif; ?>
 <?php if (!empty($errorFlash)): ?><div class="notice warn"><?= e($errorFlash) ?></div><?php endif; ?>

 <div class="ux-summary-strip"><div><strong>Логика:</strong> все заданные условия правила должны совпасть одновременно. При одинаковом максимальном приоритете операция остаётся на проверку.</div><div><?= count($rules ?? []) ?> правил</div></div>
 <div class="ux-kpi-grid cols-3">
  <div class="ux-kpi is-accent"><div class="ux-kpi-label">Активные правила</div><div class="ux-kpi-value"><?= count($activeRules) ?></div><div class="ux-kpi-note">участвуют в автоматическом разнесении</div></div>
  <div class="ux-kpi"><div class="ux-kpi-label">Автоприменение</div><div class="ux-kpi-value"><?= $autoRules ?></div><div class="ux-kpi-note">правил применяются без ручного действия</div></div>
  <div class="ux-kpi is-muted"><div class="ux-kpi-label">Отключены</div><div class="ux-kpi-value"><?= $inactiveRules ?></div><div class="ux-kpi-note">сохранены, но не участвуют в обработке</div></div>
 </div>

 <div class="ux-filter-panel">
  <div class="ux-filter-head"><div class="ux-filter-title">Быстрый поиск по правилам</div><div class="ux-table-meta">Поиск работает без перезагрузки</div></div>
  <div class="ux-filter-body"><div class="ux-filter-field is-search"><label>Название, ИНН или условие</label><input type="search" class="field-input" id="rules-search" placeholder="Например: связь, 7707..., оплата перевозчику"></div><div class="ux-filter-field"><label>Статус</label><select class="field-select" id="rules-status"><option value="all">Все правила</option><option value="active">Активные</option><option value="inactive">Отключённые</option></select></div></div>
 </div>

 <div class="ux-section">
  <div class="ux-section-head"><div><div class="ux-section-title">Сценарии автоматического разнесения</div><div class="ux-section-sub">Чем выше приоритет, тем раньше правило получает право на результат.</div></div><div class="ux-section-actions"><span class="ux-table-meta" id="rules-visible-count">Показано: <?= count($rules ?? []) ?></span></div></div>
  <?php if (empty($rules)): ?><div class="ux-empty"><strong>Правил пока нет</strong>Создайте первое правило для повторяющейся банковской операции.</div>
  <?php else: ?><div class="ux-rule-list" id="rules-list">
   <?php foreach ($rules as $rule): $active=!empty($rule['active']); $hay=mb_strtolower(($rule['name']??'').' '.($rule['counterparty_inn']??'').' '.($rule['purpose_contains']??'').' '.$ruleCondition($rule)); ?>
   <div class="ux-rule-row" data-rule-row data-status="<?= $active?'active':'inactive' ?>" data-search="<?= e($hay) ?>">
    <div class="ux-rule-priority"><b><?= (int)($rule['priority']??0) ?></b><small>приоритет</small></div>
    <div><div class="ux-rule-name"><?= e($rule['name'] ?? 'Без названия') ?></div><div class="ux-rule-meta"><?= e($ruleCondition($rule)) ?></div></div>
    <div class="ux-flow"><div class="ux-flow-box"><span>ЕСЛИ</span><strong><?= e($directionLabel($rule['direction'] ?? '')) ?></strong></div><span class="ux-flow-arrow">→</span><div class="ux-flow-box"><span>ТО</span><strong><?= e($ruleResult($rule)) ?></strong></div></div>
    <div><span class="<?= $active?'badge badge-ok':'badge badge-neutral' ?>"><span class="dot"></span><?= $active?'Активно':'Отключено' ?></span><?php if (!empty($rule['auto_apply'])): ?><div class="ux-rule-meta">автоматически</div><?php endif; ?></div>
    <div class="ux-actions"><button type="button" class="btn btn-ghost btn-sm" data-preview-id="<?= (int)$rule['id'] ?>">Проверить</button><button type="button" class="btn btn-secondary btn-sm" data-edit-id="<?= (int)$rule['id'] ?>">Изменить</button><form method="post" action="<?= app_url('/company/finance/settings/matching-rules/toggle') ?>" class="inline-form" data-confirm="<?= $active?'Отключить правило?':'Включить правило?' ?>"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int)$rule['id'] ?>"><button type="submit" class="btn btn-ghost btn-sm"><?= $active?'Отключить':'Включить' ?></button></form></div>
   </div>
   <?php endforeach; ?>
  </div><div class="ux-empty ux-hidden" id="rules-empty"><strong>Ничего не найдено</strong>Измените запрос или фильтр статуса.</div><?php endif; ?>
 </div>
</div>

<div id="matching-rule-create-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="0" data-close-on-escape="0"><div class="modal modal-lg"><div class="modal-head"><span class="modal-title">Новое правило разнесения</span><button type="button" class="modal-close" data-close-modal="matching-rule-create-modal">&times;</button></div><div class="modal-body" id="matching-rule-create-modal-body"></div></div></div>
<div id="matching-rule-edit-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="0" data-close-on-escape="0"><div class="modal modal-lg"><div class="modal-head"><span class="modal-title">Редактирование правила</span><button type="button" class="modal-close" data-close-modal="matching-rule-edit-modal">&times;</button></div><div class="modal-body" id="matching-rule-edit-modal-body"></div></div></div>
<div id="matching-rule-preview-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="1" data-close-on-escape="1"><div class="modal modal-lg"><div class="modal-head"><span class="modal-title">Проверка правила на операциях</span><button type="button" class="modal-close" data-close-modal="matching-rule-preview-modal">&times;</button></div><div class="modal-body" id="matching-rule-preview-body"><div class="empty-state compact"><p>Загрузка...</p></div></div></div></div>
<script>
document.addEventListener('DOMContentLoaded',function(){
 const search=document.getElementById('rules-search'),status=document.getElementById('rules-status'),rows=[...document.querySelectorAll('[data-rule-row]')],empty=document.getElementById('rules-empty'),count=document.getElementById('rules-visible-count');
 function filterRules(){let q=(search?search.value:'').trim().toLowerCase(),st=status?status.value:'all',shown=0;rows.forEach(r=>{let ok=(!q||(r.dataset.search||'').includes(q))&&(st==='all'||r.dataset.status===st);r.classList.toggle('ux-hidden',!ok);if(ok)shown++});if(empty)empty.classList.toggle('ux-hidden',shown!==0);if(count)count.textContent='Показано: '+shown}
 if(search)search.addEventListener('input',filterRules);if(status)status.addEventListener('change',filterRules);
 function load(btnId,modalId,bodyId,url){let btn=document.getElementById(btnId);if(!btn)return;btn.addEventListener('click',()=>{let body=document.getElementById(bodyId);body.innerHTML='<div class="empty-state compact"><p>Загрузка...</p></div>';window.openModal(modalId);fetch(window.getErpBasePath()+url).then(r=>r.text()).then(h=>body.innerHTML=h).catch(()=>body.innerHTML='<div class="form-alert alert-error">Не удалось загрузить форму.</div>')})}
 load('matching-rule-create-btn','matching-rule-create-modal','matching-rule-create-modal-body','/company/finance/settings/matching-rules/create');
 document.querySelectorAll('[data-edit-id]').forEach(btn=>btn.addEventListener('click',function(){let id=this.dataset.editId,body=document.getElementById('matching-rule-edit-modal-body');body.innerHTML='<div class="empty-state compact"><p>Загрузка...</p></div>';window.openModal('matching-rule-edit-modal');fetch(window.getErpBasePath()+'/company/finance/settings/matching-rules/edit?id='+id).then(r=>r.text()).then(h=>body.innerHTML=h).catch(()=>body.innerHTML='<div class="form-alert alert-error">Не удалось загрузить форму.</div>')}));
 document.querySelectorAll('[data-preview-id]').forEach(btn=>btn.addEventListener('click',function(){let id=this.dataset.previewId,body=document.getElementById('matching-rule-preview-body');body.innerHTML='<div class="empty-state compact"><p>Загрузка...</p></div>';window.openModal('matching-rule-preview-modal');fetch(window.getErpBasePath()+'/company/finance/settings/matching-rules/preview?id='+id).then(r=>r.json()).then(data=>{let h='<div class="ux-summary-strip"><div><strong>'+String(data.rule_name||'Правило')+'</strong></div><div>Совпадений: '+Number(data.total_matches||0)+'</div></div>';if(data.results&&data.results.length){h+='<div class="table-scroll"><table class="table"><thead><tr><th>Дата</th><th>Контрагент</th><th>Сумма</th><th>Назначение</th><th>Результат</th><th>Причина</th></tr></thead><tbody>';data.results.forEach(r=>h+='<tr><td>'+r.operation_date+'</td><td>'+r.counterparty_name+'</td><td class="col-mono">'+r.amount+'</td><td>'+r.purpose+'</td><td>'+r.result+'</td><td>'+r.reason+'</td></tr>');h+='</tbody></table></div>'}else h+='<div class="ux-empty"><strong>Совпадений нет</strong>Непроведённые операции не соответствуют этому правилу.</div>';body.innerHTML=h}).catch(()=>body.innerHTML='<div class="form-alert alert-error">Не удалось загрузить проверку правила.</div>')}));
 document.querySelectorAll('[data-confirm]').forEach(f=>f.addEventListener('submit',e=>{if(!confirm(f.dataset.confirm))e.preventDefault()}));
});
</script>
<?php endif; ?>