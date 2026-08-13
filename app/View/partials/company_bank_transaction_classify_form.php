<?php
$amount=(float)($tx['credit_amount']??0)>0?($tx['credit_amount']??'0.00'):($tx['debit_amount']??'0.00');
?>
<?php if(!empty($tx['is_internal_transfer'])): ?>
<div class="form-alert alert-info">Эта операция уже определена как внутренний перевод Банк → Касса.</div>
<?php else: ?>
<form method="post" action="<?= app_url('/company/finance/bank-transactions/'.(int)$tx['id'].'/classify') ?>">
 <?= csrfField() ?>
 <div class="modal-body">
  <div class="section-title">Ручное разнесение операции #<?= (int)$tx['id'] ?></div>
  <div class="page-summary"><span><?= e($tx['operation_date']??'') ?> · <?= e($tx['counterparty_name']??'—') ?> · <?= e($amount) ?> ₽</span></div>
  <div class="field mt-section"><label class="field-label">Назначение платежа</label><div class="field-note"><?= e($tx['purpose']??'—') ?></div></div>
  <div class="form-grid-2 mt-section">
   <div class="field"><label class="field-label">ЦФУ <span class="field-required">*</span></label><select class="field-select" name="cash_flow_center_id" required><option value="">— Выберите ЦФУ —</option><?php foreach($cfu as $row): ?><option value="<?= (int)$row['id'] ?>" <?= (int)($tx['cash_flow_center_id']??0)===(int)$row['id']?'selected':'' ?>><?= e($row['name']) ?></option><?php endforeach; ?></select></div>
   <div class="field"><label class="field-label">Статья ДДС <span class="field-required">*</span></label><select class="field-select" name="dds_category_id" required><option value="">— Выберите статью —</option><?php foreach($dds as $row): ?><option value="<?= (int)$row['id'] ?>" <?= (int)($tx['dds_category_id']??0)===(int)$row['id']?'selected':'' ?>><?= e(($row['code']??'').' — '.$row['name']) ?></option><?php endforeach; ?></select></div>
  </div>
  <div class="panel mt-section"><label class="field-label"><input type="checkbox" name="create_rule" value="1" checked> Создать правило для будущих операций этого контрагента</label><div class="field-note">Базовое условие: ИНН <?= e($tx['counterparty_inn']??'не указан') ?>. Все заданные условия будущего правила будут применяться через строгую AND-логику.</div>
   <div class="form-grid-2 mt-section"><div class="field"><label class="field-label">Дополнительно: назначение содержит</label><input class="field-input" name="rule_purpose_contains" placeholder="Необязательно"></div><div class="field"><label class="field-label">Приоритет правила</label><input class="field-input" type="number" name="rule_priority" min="1" max="100000" value="100"></div></div>
  </div>
 </div>
 <div class="modal-foot is-spaced"><button type="button" class="btn btn-ghost" data-close-modal="bank-classify-modal">Отмена</button><button type="submit" class="btn btn-primary">Сохранить разнесение</button></div>
</form>
<?php endif; ?>
