<?php

use App\Service\FinanceInvoiceService;

$invoice = $invoice ?? [];
$isEdit = $isEdit ?? false;
$formAction = $isEdit ? app_url('/company/finance/invoices/' . (int)($invoice['id'] ?? 0) . '/modal-edit') : app_url('/company/finance/invoices/create');
$invDirection=$invoice['direction']??($_POST['direction']??FinanceInvoiceService::DIRECTION_OUTGOING);
$invNumber=$invoice['number']??($_POST['number']??''); $invDate=$invoice['invoice_date']??($_POST['invoice_date']??date('Y-m-d'));
$invCpartyType=$invoice['counterparty_entity_type']??($_POST['counterparty_entity_type']??''); $invCpartyId=$invoice['counterparty_entity_id']??($_POST['counterparty_entity_id']??'');
$invCpartyName=$invoice['counterparty_name']??($_POST['counterparty_name']??''); $invCpartyInn=$invoice['counterparty_inn']??($_POST['counterparty_inn']??'');
$invAmount=$invoice['amount']??($_POST['amount']??''); $invVatRate=$invoice['vat_rate']??($_POST['vat_rate']??'');
$invBasis=$invoice['basis']??($_POST['basis']??''); $invPlanned=$invoice['planned_payment_date']??($_POST['planned_payment_date']??'');
$invComment=$invoice['comment']??($_POST['comment']??''); $invStatus=$invoice['status']??($_POST['status']??'draft');
?>
<form action="<?= e($formAction) ?>" method="post" class="invoice-form" data-invoice-id="<?= (int)($invoice['id']??0) ?>">
<?= csrfField() ?>
<div class="modal-body">
<?php if (!empty($formError)): ?><div class="form-alert alert-error"><?= e($formError) ?></div><?php endif; ?>
<div class="form-grid-3">
 <div class="field"><label class="field-label">Направление</label><select name="direction" class="field-select js-inv-direction" required><option value="OUTGOING"<?= $invDirection==='OUTGOING'?' selected':'' ?>>Выставленный (клиенту)</option><option value="INCOMING"<?= $invDirection==='INCOMING'?' selected':'' ?>>Полученный (от перевозчика)</option></select></div>
 <div class="field"><label class="field-label">Номер счёта</label><input type="text" name="number" class="field-input" value="<?= e($invNumber) ?>" required></div>
 <div class="field"><label class="field-label">Дата счёта</label><input type="date" name="invoice_date" class="field-input" value="<?= e($invDate) ?>" required></div>
</div>
<div class="section-title mt-section">Контрагент</div>
<div class="form-grid-3">
 <div class="field"><label class="field-label">Тип контрагента</label><select name="counterparty_entity_type" class="field-select js-inv-cparty-type"><option value="">— Не выбран —</option><option value="client"<?= $invCpartyType==='client'?' selected':'' ?>>Клиент</option><option value="contractor"<?= $invCpartyType==='contractor'?' selected':'' ?>>Перевозчик</option></select></div>
 <div class="field"><label class="field-label">Контрагент</label><select name="counterparty_entity_id" class="field-select js-inv-cparty-select"><option value="">— Выберите тип —</option><?php $source=$invCpartyType==='client'?($clients??[]):($invCpartyType==='contractor'?($contractors??[]):[]); foreach($source as $c): ?><option value="<?= (int)$c['id'] ?>" data-name="<?= e($c['name']) ?>" data-inn="<?= e($c['inn']??'') ?>"<?= (int)$invCpartyId===(int)$c['id']?' selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
 <div class="field"><label class="field-label">ИНН</label><input type="text" name="counterparty_inn" class="field-input js-inv-cparty-inn" value="<?= e($invCpartyInn) ?>" readonly></div>
</div>
<div class="form-grid-3"><div class="field"><label class="field-label">Наименование (вручную)</label><input type="text" name="counterparty_name" class="field-input js-inv-cparty-name" value="<?= e($invCpartyName) ?>"></div></div>

<div class="section-title mt-section">Финансовые данные</div>
<div class="form-grid-3">
 <div class="field"><label class="field-label">Сумма</label><input type="text" name="amount" class="field-input" value="<?= e($invAmount) ?>" required placeholder="0.00"></div>
 <div class="field"><label class="field-label">Ставка НДС</label><select name="vat_rate" class="field-select"><option value=""<?= $invVatRate===''||$invVatRate===null?' selected':'' ?>>Без НДС</option><?php foreach([0,5,7,20,22] as $rate): ?><option value="<?= $rate ?>"<?= (string)$invVatRate===(string)$rate?' selected':'' ?>><?= $rate ?>%</option><?php endforeach; ?></select></div>
 <div class="field"><label class="field-label">Плановая дата оплаты</label><input type="date" name="planned_payment_date" class="field-input" value="<?= e($invPlanned) ?>"></div>
</div>
<div class="field"><label class="field-label">Основание</label><textarea name="basis" class="field-textarea" rows="2"><?= e($invBasis) ?></textarea></div>

<div class="section-title mt-section">Платёжные обязательства</div>
<div class="form-hint" style="margin-bottom:8px">Можно включить в один счёт несколько событий рейса или выставить несколько счетов на одно событие. Сумма выбранных связей должна равняться сумме счёта.</div>
<div class="js-obligations-box" style="border:1px solid var(--border,#d3cec3);max-height:250px;overflow:auto;background:var(--surface,#fff)"><div style="padding:12px;color:var(--muted,#746f66)">Выберите контрагента — система покажет его открытые обязательства.</div></div>

<div class="form-grid-3 mt-section">
 <div class="field"><label class="field-label">Статус</label><select name="status" class="field-select"><?php foreach(FinanceInvoiceService::STATUSES as $key=>$label): ?><option value="<?= e($key) ?>"<?= $invStatus===$key?' selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
 <div class="field"><label class="field-label">Комментарий</label><textarea name="comment" class="field-textarea" rows="2"><?= e($invComment) ?></textarea></div>
</div>
</div>
<div class="modal-foot is-spaced"><div class="modal-foot-actions"><button type="button" class="btn btn-ghost" data-close-modal>Отмена</button></div><div class="modal-foot-actions"><button type="submit" class="btn btn-primary"><?= $isEdit?'Сохранить':'Создать счёт' ?></button></div></div>
</form>
<script>
(function(){
 var script=document.currentScript, form=script.previousElementSibling; if(!form||!form.classList.contains('invoice-form'))return;
 var type=form.querySelector('.js-inv-cparty-type'), cp=form.querySelector('.js-inv-cparty-select'), name=form.querySelector('.js-inv-cparty-name'), inn=form.querySelector('.js-inv-cparty-inn'), dir=form.querySelector('.js-inv-direction'), box=form.querySelector('.js-obligations-box');
 var base=window.getErpBasePath?window.getErpBasePath():'';
 function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
 function loadCp(){var t=type.value; cp.innerHTML='<option value="">— Загрузка —</option>'; fetch(base+'/company/finance/invoices/counterparty-list?type='+encodeURIComponent(t)).then(function(r){return r.json()}).then(function(data){cp.innerHTML='<option value="">— Выберите контрагента —</option>';data.forEach(function(x){var o=document.createElement('option');o.value=x.id;o.textContent=x.name;o.dataset.name=x.name;o.dataset.inn=x.inn||'';cp.appendChild(o);}); loadOb();}).catch(function(){cp.innerHTML='<option value="">— Ошибка загрузки —</option>';});}
 function loadOb(){var t=type.value,id=cp.value;if(!t||!id){box.innerHTML='<div style="padding:12px;color:var(--muted,#746f66)">Выберите контрагента — система покажет его открытые обязательства.</div>';return;}var invoiceId=form.dataset.invoiceId||'';var url=base+'/company/finance/invoices/obligations?direction='+encodeURIComponent(dir.value)+'&counterparty='+encodeURIComponent(t+':'+id)+(invoiceId?'&invoice_id='+encodeURIComponent(invoiceId):'');box.innerHTML='<div style="padding:12px">Загрузка…</div>';fetch(url).then(function(r){return r.json()}).then(function(data){if(!Array.isArray(data)||!data.length){box.innerHTML='<div style="padding:12px;color:var(--muted,#746f66)">Открытых обязательств для этого контрагента нет.</div>';return;}var h='<table style="width:100%;border-collapse:collapse"><thead><tr><th style="padding:7px;text-align:left">Выбрать</th><th style="padding:7px;text-align:left">Рейс / событие</th><th style="padding:7px;text-align:right">Свободно</th><th style="padding:7px;text-align:right">В счёт</th></tr></thead><tbody>';data.forEach(function(x){var cur=parseFloat(x.current_amount||0),checked=cur>0, val=checked?x.current_amount:x.available;h+='<tr style="border-top:1px solid var(--border,#ddd)"><td style="padding:7px"><input type="checkbox" class="js-ob-check" '+(checked?'checked':'')+'></td><td style="padding:7px"><strong>Рейс #'+esc(x.route_id)+'</strong><div style="font-size:11px;color:var(--muted,#746f66)">'+esc(x.condition)+(x.due_date?' · срок '+esc(x.due_date):'')+'</div></td><td style="padding:7px;text-align:right;white-space:nowrap">'+esc(x.available)+' ₽</td><td style="padding:7px;text-align:right"><input type="hidden" name="obligation_id[]" value="'+esc(x.id)+'" '+(checked?'':'disabled')+'><input class="field-input js-ob-amount" style="width:120px;text-align:right" name="obligation_amount[]" value="'+esc(val)+'" '+(checked?'':'disabled')+'></td></tr>';});box.innerHTML=h+'</tbody></table>';box.querySelectorAll('.js-ob-check').forEach(function(ch){ch.addEventListener('change',function(){var tr=ch.closest('tr');tr.querySelectorAll('input[name]').forEach(function(i){i.disabled=!ch.checked;});});});}).catch(function(){box.innerHTML='<div class="form-alert alert-error" style="margin:8px">Не удалось загрузить обязательства.</div>';});}
 type.addEventListener('change',loadCp); dir.addEventListener('change',loadOb); cp.addEventListener('change',function(){var o=cp.options[cp.selectedIndex];if(o){name.value=o.dataset.name||name.value;inn.value=o.dataset.inn||'';}loadOb();});
 if(type.value&&cp.value)loadOb();
})();
</script>
