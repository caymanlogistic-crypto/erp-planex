<?php
$isReturn = $movementType === 'RETURN';
$today = date('Y-m-d');
?>
<form method="post" action="<?= app_url('/company/finance/employee-payments/create') ?>">
 <?= csrfField() ?><input type="hidden" name="movement_type" value="<?= e($movementType) ?>">
 <div class="modal-body">
  <div class="form-grid two-cols">
   <div class="field"><label class="field-label">Сотрудник <span class="field-required">*</span></label><select class="field-select" name="employee_ref" required><option value="">— Выберите сотрудника —</option><?php foreach($employees as $employee): ?><option value="<?= e($employee['ref']) ?>"><?= e($employee['full_name']) ?></option><?php endforeach; ?></select><div class="field-note">Все активные пользователи ERP текущей компании: руководители, логисты и другие роли.</div></div>
   <div class="field"><label class="field-label">Источник <span class="field-required">*</span></label><select class="field-select" name="source_type" required><option value="CASH">Касса</option><option value="BANK">Расчётный счёт</option></select></div>
  </div>
  <div data-source-panel="CASH">
   <div class="form-grid three-cols">
    <div class="field"><label class="field-label">Касса <span class="field-required">*</span></label><select class="field-select" name="money_account_id" required><option value="">— Выберите кассу —</option><?php foreach($cashAccounts as $account): ?><option value="<?= (int)$account['id'] ?>"><?= e($account['name']) ?> · <?= e(\App\Service\FinanceEmployeePaymentService::formatMoney($account['computed_balance']??'0.00')) ?> ₽</option><?php endforeach; ?></select></div>
    <div class="field"><label class="field-label">Дата <span class="field-required">*</span></label><input class="field-input" type="date" name="operation_date" value="<?= e($today) ?>" required></div>
    <div class="field"><label class="field-label">Сумма <span class="field-required">*</span></label><input class="field-input" name="amount" inputmode="decimal" placeholder="0,00" required></div>
   </div>
   <div class="field"><label class="field-label">Основание</label><input class="field-input" name="purpose" placeholder="<?= $isReturn?'Например: возврат подотчётных средств':'Например: денежные средства сотруднику' ?>"></div>
  </div>
  <div data-source-panel="BANK" class="is-hidden">
   <div class="field"><label class="field-label">Банковская операция <span class="field-required">*</span></label><select class="field-select" name="bank_transaction_id" required disabled><option value="">— Выберите операцию из выписки —</option><?php foreach($bankCandidates as $tx): $amount=$isReturn?($tx['credit_amount']??'0.00'):($tx['debit_amount']??'0.00'); ?><option value="<?= (int)$tx['id'] ?>"><?= e(date('d.m.Y',strtotime($tx['operation_date']))) ?> · <?= e(\App\Service\FinanceEmployeePaymentService::formatMoney($amount)) ?> ₽ · <?= e($tx['counterparty_name']?:'Без контрагента') ?> · <?= e(mb_strimwidth((string)($tx['purpose']??''),0,80,'…')) ?></option><?php endforeach; ?></select><div class="field-note">Показываются только проведённые, ещё не связанные с сотрудником <?= $isReturn?'поступления':'списания' ?>.</div></div>
  </div>
  <div class="field"><label class="field-label">Комментарий</label><textarea class="field-input" name="comment" rows="3" data-optional-always="1" placeholder="Необязательно"></textarea></div>
 </div>
 <div class="modal-foot"><button type="submit" class="btn btn-primary"><?= $isReturn?'Сохранить возврат':'Сохранить выплату' ?></button><button type="button" class="btn btn-ghost" data-close-modal="employee-payment-create-modal">Отмена</button></div>
</form>
