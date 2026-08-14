<?php
function ep_ok(bool $value,string $message):void{if(!$value)throw new RuntimeException('FAIL: '.$message);}
$service=file_get_contents(__DIR__.'/../app/Service/FinanceEmployeePaymentService.php');
$migration=file_get_contents(__DIR__.'/../database/migrations-local/065_finance_employee_movements.sql');
$page=file_get_contents(__DIR__.'/../app/View/pages/company_finance_employee_payments.php');
$form=file_get_contents(__DIR__.'/../app/View/partials/company_finance_employee_payment_form.php');
$bankForm=file_get_contents(__DIR__.'/../app/View/partials/company_bank_transaction_classify_form.php');
$routes=file_get_contents(__DIR__.'/../app/Http/Routes/company_finance_employee_payments.php');
$runtimeUi=file_get_contents(__DIR__.'/../app/Support/runtime_ui_hotfixes.php');

ep_ok(str_contains($migration,'finance_employee_movements'),'employee movement table exists');
ep_ok(str_contains($migration,'UNIQUE KEY `uk_fem_finance_operation`'),'one employee link per finance operation');
ep_ok(str_contains($migration,'UNIQUE KEY `uk_fem_bank_transaction`'),'one employee link per bank transaction');
ep_ok(str_contains($migration,'FOREIGN KEY (`employee_user_id`) REFERENCES `users`'),'employee references real system user');
ep_ok(str_contains($service,"fo.status = 'POSTED'"),'balances only count posted money movements');
ep_ok(str_contains($service,'FinanceCashService::createCashOperation'),'cash settlement uses real cash ledger');
ep_ok(str_contains($service,'COALESCE(bt.is_internal_transfer,0)=0'),'internal bank transfers excluded');
ep_ok(str_contains($service,'$actualType !== $movementType'),'bank direction must match payment/return');
ep_ok(str_contains($service,"status='active' AND deleted_at IS NULL"),'new settlements only select active ERP accounts');
ep_ok(str_contains($page,'Сотрудник')&&str_contains($page,'Выплачено')&&str_contains($page,'Возвращено')&&str_contains($page,'Сальдо'),'summary table has agreed columns');
ep_ok(str_contains($page,'data-employee-payment-row')&&str_contains($page,"addEventListener('dblclick'"),'double click opens employee ledger');
ep_ok(str_contains($form,'Касса')&&str_contains($form,'Расчётный счёт'),'create flow supports both money sources');
ep_ok(str_contains($bankForm,'Взаиморасчёты с сотрудником'),'bank operation can be linked to employee');
ep_ok(str_contains($routes,'/company/finance/employee-payments'),'employee payment routes registered');
ep_ok(str_contains($runtimeUi,'Выплаты сотрудникам'),'finance navigation exposes employee payments');
echo "FINANCE_EMPLOYEE_PAYMENTS_OK\n";
