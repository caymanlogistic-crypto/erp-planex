<?php
$rule=$rule??null;
$isEdit=$rule!==null;
$bankAccounts=$bankAccounts??[];
$ddsCategories=$ddsCategories??[];
$cashFlowCenters=$cashFlowCenters??[];
$cashAccounts=$cashAccounts??[];
$ddsAllowedMap=$ddsAllowedMap??[];
$action=$isEdit?app_url('/company/finance/settings/matching-rules/edit'):app_url('/company/finance/settings/matching-rules/create');
$v=static fn($k,$d='')=>$isEdit?($rule[$k]??$d):$d;
$ruleId=(int)($rule['id']??0);
$ruleActive=!$isEdit||!empty($rule['active']);
$actionType=(string)$v('action_type','categorize');
$isCategorize=in_array($actionType,['categorize','categorize_to_cash'],true);
$allowedMapJson=json_encode($ddsAllowedMap,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
if($allowedMapJson===false)$allowedMapJson='{}';
?>
<style>
.matching-rule-form{display:flex;flex-direction:column;flex:1;min-height:0;margin:0}.matching-rule-form>.modal-body{padding:14px 16px;background:var(--surface-form)}.matching-rule-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px 12px}.matching-rule-grid .field{margin:0}.matching-rule-section{margin-top:12px;padding-top:12px;border-top:1px solid var(--line-hair)}.matching-rule-section:first-child{margin-top:0;padding-top:0;border-top:0}.matching-rule-section-title{margin-bottom:8px;color:var(--text-faint);font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}.matching-rule-form>.modal-foot{margin:0;padding:10px 16px;background:var(--surface-muted);border-top:1px solid var(--line-hair)}.matching-rule-foot{display:flex;align-items:center;justify-content:space-between;gap:12px}.matching-rule-foot-left,.matching-rule-foot-right{display:flex;align-items:center;gap:5px}.matching-rule-foot .btn{min-width:0}.matching-rule-legacy-note{padding:8px 10px;background:var(--warning-bg);border:1px solid var(--warning-border);color:var(--warning-text);font-size:11px;line-height:1.4}.matching-rule-cash-field{padding:9px 10px;border:1px solid var(--line-hair);background:var(--surface-muted);border-radius:8px}.matching-rule-cash-field .field-label{font-weight:700}.matching-rule-cash-field .field-note{line-height:1.35}.matching-rule-cash-field.is-disabled{opacity:.6}@media(max-width:900px){.matching-rule-grid{grid-template-columns:1fr 1fr}}@media(max-width:620px){.matching-rule-grid{grid-template-columns:1fr}.matching-rule-foot{align-items:flex-start;flex-direction:column}.matching-rule-foot-right{align-self:flex-end}}
</style>
<form action="<?= e($action) ?>" method="post" class="matching-rule-form" data-matching-rule-form>
<?= csrfField() ?>
<?php if($isEdit): ?><input type="hidden" name="id" value="<?= $ruleId ?>"><?php endif; ?>
<input type="hidden" name="name" value="<?= e((string)$v('name','Правило разнесения')) ?>">
<input type="hidden" name="auto_apply" value="1">
<input type="hidden" name="action_type" value="<?= e($actionType) ?>">
<?php if($isCategorize): ?>
<input type="hidden" name="direction" id="matching-rule-direction" value="<?= e((string)$v('direction')) ?>">
<input type="hidden" name="purpose_regex" value="<?= e((string)$v('purpose_regex')) ?>">
<input type="hidden" name="invoice_number_pattern" value="<?= e((string)$v('invoice_number_pattern')) ?>">
<input type="hidden" name="counterparty_id" value="<?= e((string)$v('counterparty_id')) ?>">
<input type="hidden" name="counterparty_type" value="<?= e((string)$v('counterparty_type')) ?>">
<input type="hidden" name="target_counterparty_id" value="<?= e((string)$v('target_counterparty_id')) ?>">
<input type="hidden" name="target_counterparty_type" value="<?= e((string)$v('target_counterparty_type')) ?>">
<div class="modal-body">
 <section class="matching-rule-section">
  <div class="matching-rule-section-title">Куда разносить</div>
  <div class="matching-rule-grid">
   <div class="field"><label class="field-label">ЦФУ *</label><select class="field-select" name="target_cash_flow_center_id" id="matching-rule-cfu" required data-dds-map="<?= e($allowedMapJson) ?>"><option value="">— Выберите ЦФУ —</option><?php foreach($cashFlowCenters as $x): ?><option value="<?= (int)$x['id'] ?>" <?= (int)$v('target_cash_flow_center_id')===(int)$x['id']?'selected':'' ?>><?= e($x['name']) ?></option><?php endforeach; ?></select></div>
   <div class="field"><label class="field-label">Статья *</label><select class="field-select" name="target_dds_category_id" id="matching-rule-dds" required><option value="">— Выберите статью —</option><?php foreach($ddsCategories as $x): ?><option value="<?= (int)$x['id'] ?>" data-direction="<?= e((string)($x['direction']??'')) ?>" <?= (int)$v('target_dds_category_id')===(int)$x['id']?'selected':'' ?>><?= e($x['name']) ?></option><?php endforeach; ?></select></div>
   <div class="field matching-rule-cash-field" id="matching-rule-cash-field"><label class="field-label">После разнесения</label><select class="field-select" name="target_cash_account_id" id="matching-rule-cash"><option value="">Оставить на банковском счёте</option><?php foreach($cashAccounts as $x): ?><option value="<?= (int)$x['id'] ?>" <?= (int)$v('target_cash_account_id')===(int)$x['id']?'selected':'' ?>>Перевести в кассу «<?= e((string)($x['name']??('Касса #'.(int)$x['id']))) ?>»</option><?php endforeach; ?></select><div class="field-note" id="matching-rule-cash-note">Для расходной статьи можно автоматически создать внутренний перевод Банк → Касса. Повторный перевод не создаётся.</div></div>
  </div>
 </section>
 <section class="matching-rule-section">
  <div class="matching-rule-section-title">Когда применять</div>
  <div class="matching-rule-grid">
   <div class="field"><label class="field-label">ИНН</label><input class="field-input" name="counterparty_inn" value="<?= e((string)$v('counterparty_inn')) ?>" placeholder="10 или 12 цифр"></div>
   <div class="field"><label class="field-label">Назначение содержит</label><input class="field-input" name="purpose_contains" value="<?= e((string)$v('purpose_contains')) ?>" placeholder="Например: ЕНП"></div>
   <div class="field"><label class="field-label">Банковский счёт</label><select class="field-select" name="bank_account_id"><option value="">Любой счёт</option><?php foreach($bankAccounts as $x): ?><option value="<?= (int)$x['id'] ?>" <?= (int)$v('bank_account_id')===(int)$x['id']?'selected':'' ?>><?= e(($x['bank_name']??'').' — '.($x['account_number']??'')) ?></option><?php endforeach; ?></select></div>
   <div class="field"><label class="field-label">Сумма от</label><input class="field-input" type="number" step="0.01" min="0" name="amount_from" value="<?= e((string)$v('amount_from')) ?>"></div>
   <div class="field"><label class="field-label">Сумма до</label><input class="field-input" type="number" step="0.01" min="0" name="amount_to" value="<?= e((string)$v('amount_to')) ?>"></div>
   <div class="field"><label class="field-label">Приоритет</label><input class="field-input" type="number" name="priority" min="1" max="100000" value="<?= (int)$v('priority',100) ?>"><div class="field-note">Чем больше число, тем выше приоритет.</div></div>
  </div>
 </section>
</div>
<?php else: ?>
<div class="modal-body"><div class="matching-rule-legacy-note">Это служебное правило другого типа. Его параметры сохранены без изменения; для обычного разнесения ЦФУ и ДДС создайте новое правило.</div><input type="hidden" name="priority" value="<?= (int)$v('priority',100) ?>"><input type="hidden" name="direction" value="<?= e((string)$v('direction')) ?>"><input type="hidden" name="bank_account_id" value="<?= e((string)$v('bank_account_id')) ?>"><input type="hidden" name="counterparty_inn" value="<?= e((string)$v('counterparty_inn')) ?>"><input type="hidden" name="counterparty_id" value="<?= e((string)$v('counterparty_id')) ?>"><input type="hidden" name="counterparty_type" value="<?= e((string)$v('counterparty_type')) ?>"><input type="hidden" name="invoice_number_pattern" value="<?= e((string)$v('invoice_number_pattern')) ?>"><input type="hidden" name="purpose_contains" value="<?= e((string)$v('purpose_contains')) ?>"><input type="hidden" name="purpose_regex" value="<?= e((string)$v('purpose_regex')) ?>"><input type="hidden" name="amount_from" value="<?= e((string)$v('amount_from')) ?>"><input type="hidden" name="amount_to" value="<?= e((string)$v('amount_to')) ?>"><input type="hidden" name="target_dds_category_id" value="<?= e((string)$v('target_dds_category_id')) ?>"><input type="hidden" name="target_cash_flow_center_id" value="<?= e((string)$v('target_cash_flow_center_id')) ?>"><input type="hidden" name="target_cash_account_id" value="<?= e((string)$v('target_cash_account_id')) ?>"><input type="hidden" name="target_counterparty_id" value="<?= e((string)$v('target_counterparty_id')) ?>"><input type="hidden" name="target_counterparty_type" value="<?= e((string)$v('target_counterparty_type')) ?>"></div>
<?php endif; ?>
<div class="modal-foot matching-rule-foot">
 <div class="matching-rule-foot-left">
  <?php if($isEdit): ?>
  <button type="button" class="btn btn-ghost" data-rule-preview-from-edit="<?= $ruleId ?>">Проверить</button>
  <button type="button" class="btn btn-ghost" data-rule-toggle-from-edit="<?= $ruleId ?>" data-rule-active="<?= $ruleActive?'1':'0' ?>"><?= $ruleActive?'Отключить':'Включить' ?></button>
  <button type="button" class="btn btn-ghost" data-rule-delete-from-edit="<?= $ruleId ?>">Удалить</button>
  <?php endif; ?>
 </div>
 <div class="matching-rule-foot-right"><button type="submit" class="btn btn-ghost"><?= $isEdit?'Сохранить':'Создать правило' ?></button><button type="button" class="btn btn-ghost" data-close-modal="<?= $isEdit?'matching-rule-edit-modal':'matching-rule-create-modal' ?>">Отмена</button></div>
</div>
</form>
