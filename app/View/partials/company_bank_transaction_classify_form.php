<?php
$amount=(float)($tx['credit_amount']??0)>0?($tx['credit_amount']??'0.00'):($tx['debit_amount']??'0.00');
$amountFormatted=number_format((float)$amount,2,',',' ');
$directionLabel=(float)($tx['credit_amount']??0)>0?'Поступление':'Списание';
$selectedCfu=(int)($tx['cash_flow_center_id']??0);
$selectedDds=(int)($tx['dds_category_id']??0);
$allowedMap=$ddsAllowedMap??[];
?>
<style>
#tx-detail-modal .modal{width:880px;max-height:88vh;}#tx-detail-modal .modal-body{padding:0;}
#tx-detail-modal .tx-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));column-gap:26px;padding:12px 18px 10px;background:var(--surface-strong);}
#tx-detail-modal .tx-detail-row{display:grid;grid-template-columns:118px minmax(0,1fr);align-items:center;min-height:31px;gap:10px;border-bottom:1px solid var(--line-hair);}
#tx-detail-modal .tx-detail-row-wide{grid-column:1/-1;grid-template-columns:118px minmax(0,1fr);padding-top:2px;}#tx-detail-modal .tx-detail-label{color:var(--text-faint);font-size:10px;font-weight:700;letter-spacing:.045em;text-transform:uppercase;}#tx-detail-modal .tx-detail-value{min-width:0;color:var(--text-main);font-size:12px;font-weight:600;overflow-wrap:anywhere;}#tx-detail-modal .tx-detail-purpose{padding:7px 0;line-height:1.4;font-weight:500;}#tx-detail-modal .tx-detail-classification{margin:0!important;}#tx-detail-modal .tx-allocation-form{margin:0;}#tx-detail-modal .tx-allocation{padding:14px 18px 0;border-top:1px solid var(--line-soft);background:var(--surface-form);}#tx-detail-modal .tx-allocation-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:12px;}#tx-detail-modal .tx-allocation-title{color:var(--text-main);font-size:13px;font-weight:700;}#tx-detail-modal .tx-allocation-meta{margin-top:2px;color:var(--text-faint);font-size:11px;}#tx-detail-modal .tx-allocation-amount{flex:0 0 auto;padding:4px 8px;border:1px solid var(--line-soft);background:var(--surface-strong);color:var(--text-main);font-size:11px;font-weight:700;border-radius:2px;}#tx-detail-modal .tx-allocation-fields{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:12px;}#tx-detail-modal .tx-allocation-fields .field,#tx-detail-modal .tx-rule-fields .field{margin:0;gap:4px;}#tx-detail-modal .tx-allocation-fields .field-select,#tx-detail-modal .tx-rule-fields .field-input{width:100%;}#tx-detail-modal .tx-rule-card{margin-top:12px;padding:11px 12px 10px;border:1px solid var(--line-soft);background:var(--surface-strong);border-radius:2px;}#tx-detail-modal .tx-rule-toggle{display:flex;align-items:center;gap:8px;color:var(--text-main);font-size:12px;font-weight:700;cursor:pointer;}#tx-detail-modal .tx-rule-toggle input{width:15px;height:15px;margin:0;accent-color:var(--accent);}#tx-detail-modal .tx-rule-note{margin-top:5px;color:var(--text-faint);font-size:10.5px;line-height:1.35;}#tx-detail-modal .tx-rule-fields{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(150px,.4fr);gap:12px;margin-top:9px;padding-top:9px;border-top:1px solid var(--line-hair);}#tx-detail-modal .tx-rule-card:has(input[name="create_rule"]:not(:checked)) .tx-rule-fields{opacity:.45;}#tx-detail-modal .tx-allocation-actions{display:flex;justify-content:flex-end;align-items:center;gap:8px;margin:14px -18px 0;padding:10px 18px;border-top:1px solid var(--line-soft);background:var(--surface-strong);}#tx-detail-modal .tx-allocation-actions .btn{min-width:116px;}#tx-detail-modal .tx-allocation-hint{margin-top:4px;color:var(--text-faint);font-size:10px;}@media (max-width:1100px){#tx-detail-modal .modal{width:min(880px,calc(100vw - 32px));}#tx-detail-modal .tx-detail-grid,#tx-detail-modal .tx-allocation-fields{grid-template-columns:1fr;}#tx-detail-modal .tx-detail-row-wide{grid-column:auto;}}
</style>
<?php if(!empty($tx['is_internal_transfer'])): ?>
<div class="form-alert alert-info tx-allocation-error">Эта операция уже определена как внутренний перевод Банк → Касса.</div>
<?php else: ?>
<form class="tx-allocation-form" method="post" action="<?= app_url('/company/finance/bank-transactions/'.(int)$tx['id'].'/classify') ?>">
 <?= csrfField() ?>
 <section class="tx-allocation" aria-label="Разнесение банковской операции">
  <div class="tx-allocation-head"><div><div class="tx-allocation-title">Разнесение</div><div class="tx-allocation-meta">Операция #<?= (int)$tx['id'] ?> · <?= e($directionLabel) ?></div></div><div class="tx-allocation-amount"><?= e($amountFormatted) ?> ₽</div></div>
  <div class="tx-allocation-fields">
   <div class="field"><label class="field-label">ЦФУ <span class="field-required">*</span></label><select class="field-select" name="cash_flow_center_id" id="bank-cfu-select" required><option value="">— Выберите ЦФУ —</option><?php foreach($cfu as $row): ?><option value="<?= (int)$row['id'] ?>" <?= $selectedCfu===(int)$row['id']?'selected':'' ?>><?= e($row['name']) ?></option><?php endforeach; ?></select></div>
   <div class="field"><label class="field-label">Статья ДДС <span class="field-required">*</span></label><select class="field-select" name="dds_category_id" id="bank-dds-select" required <?= $selectedCfu<=0?'disabled':'' ?>><option value=""><?= $selectedCfu>0?'— Выберите статью —':'— Сначала выберите ЦФУ —' ?></option><?php foreach($dds as $row): ?><option value="<?= (int)$row['id'] ?>" data-dds-id="<?= (int)$row['id'] ?>" <?= $selectedDds===(int)$row['id']?'selected':'' ?>><?= e($row['name']) ?></option><?php endforeach; ?></select><div class="tx-allocation-hint" id="bank-dds-hint">Показываются только статьи, разрешённые для выбранного ЦФУ.</div></div>
  </div>
  <div class="tx-rule-card"><label class="tx-rule-toggle"><input type="checkbox" name="create_rule" value="1" checked><span>Создать правило для будущих операций этого контрагента</span></label><div class="tx-rule-note">Базовое условие: ИНН <?= e($tx['counterparty_inn']??'не указан') ?>. Дополнительные условия применяются вместе по строгой AND-логике.</div><div class="tx-rule-fields"><div class="field"><label class="field-label">Назначение содержит</label><input class="field-input" name="rule_purpose_contains" placeholder="Необязательно"></div><div class="field"><label class="field-label">Приоритет</label><input class="field-input" type="number" name="rule_priority" min="1" max="100000" value="100"></div></div></div>
  <div class="tx-allocation-actions"><button type="button" class="btn btn-ghost" data-close-modal="tx-detail-modal">Отмена</button><button type="submit" class="btn btn-primary">Сохранить</button></div>
 </section>
</form>
<script>
(function(){
 var cfu=document.getElementById('bank-cfu-select'),dds=document.getElementById('bank-dds-select');if(!cfu||!dds)return;
 var map=<?= json_encode($allowedMap,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
 var original=Array.from(dds.querySelectorAll('option[data-dds-id]')).map(function(o){return {id:Number(o.value),text:o.textContent,selected:o.selected};});
 function rebuild(){var id=Number(cfu.value||0),previous=Number(dds.value||<?= (int)$selectedDds ?>),allowed=(map[id]||[]).map(Number);dds.innerHTML='';var p=document.createElement('option');p.value='';p.textContent=id?(allowed.length?'— Выберите статью —':'— Для этого ЦФУ статьи не настроены —'):'— Сначала выберите ЦФУ —';dds.appendChild(p);dds.disabled=!id||!allowed.length;original.forEach(function(item){if(allowed.indexOf(item.id)===-1)return;var o=document.createElement('option');o.value=String(item.id);o.textContent=item.text;if(item.id===previous)o.selected=true;dds.appendChild(o);});if(previous&&allowed.indexOf(previous)===-1)dds.value='';}
 cfu.addEventListener('change',function(){var old=dds.value;dds.value='';rebuild();});rebuild();
}());
</script>
<?php endif; ?>
