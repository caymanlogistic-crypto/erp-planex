<?php
require_once __DIR__.'/../app/Service/FinanceMatchingRuleService.php';
use App\Service\FinanceMatchingRuleService as S;
function ok(bool $v,string $m):void{if(!$v)throw new RuntimeException('FAIL: '.$m);}
$tx=['id'=>10,'account_id'=>2,'credit_amount'=>'0.00','debit_amount'=>'1500.00','counterparty_inn'=>'7701234567','purpose'=>'Оплата услуг перевозки август'];
$base=['id'=>1,'active'=>1,'priority'=>100,'direction'=>'EXPENSE','bank_account_id'=>2,'counterparty_inn'=>'7701234567','purpose_contains'=>'перевозки','amount_from'=>'1000.00','amount_to'=>'2000.00','action_type'=>'categorize','auto_apply'=>1,'deleted_at'=>null];
ok(S::applyRuleToTransaction(null,$base,$tx)['matched']===true,'all AND conditions match');
ok(S::applyRuleToTransaction(null,$base,$tx)['result']==='auto_apply','active rule applies automatically');
$r=$base;$r['counterparty_inn']='7700000000';ok(S::applyRuleToTransaction(null,$r,$tx)['matched']===false,'INN mismatch blocks rule');
$r=$base;$r['purpose_contains']='комиссия';ok(S::applyRuleToTransaction(null,$r,$tx)['matched']===false,'purpose mismatch blocks rule');
$r=$base;$r['amount_from']='1600.00';ok(S::applyRuleToTransaction(null,$r,$tx)['matched']===false,'amount lower bound blocks rule');
$r=$base;$r['amount_to']='1400.00';ok(S::applyRuleToTransaction(null,$r,$tx)['matched']===false,'amount upper bound blocks rule');
$r=$base;$r['bank_account_id']=9;ok(S::applyRuleToTransaction(null,$r,$tx)['matched']===false,'bank mismatch blocks rule');
$r=$base;$r['direction']='INCOME';ok(S::applyRuleToTransaction(null,$r,$tx)['matched']===false,'direction mismatch blocks rule');
$low=$base;$low['id']=2;$low['priority']=10;$high=$base;$high['id']=3;$high['priority']=500;$best=S::selectBestMatchingRule([$low,$high],$tx);ok(($best['rule']['id']??0)===3,'maximum numeric priority wins');
$tie=$base;$tie['id']=4;$tie['priority']=500;$conflict=S::selectBestMatchingRule([$high,$tie],$tx);ok(!empty($conflict['conflict']),'equal maximum priority conflicts');
$off=$high;$off['active']=0;$best=S::selectBestMatchingRule([$off,$low],$tx);ok(($best['rule']['id']??0)===2,'inactive rule ignored');
$legacy=$base;$legacy['auto_apply']=0;ok(S::applyRuleToTransaction(null,$legacy,$tx)['result']==='auto_apply','legacy auto_apply flag no longer overrides active status');
ok(S::isManualProtected(['classification_locked'=>1,'classification_status'=>'AUTO']),'manual lock protects');
ok(S::isManualProtected(['classification_locked'=>0,'classification_status'=>'MANUAL']),'manual status protects');
ok(S::classificationStatusLabel('NEEDS_REVIEW')==='Конфликт / требует проверки','Russian review label');
ok(S::classificationBadgeClass('UNALLOCATED')==='badge badge-danger','unallocated is red');
ok(S::classificationBadgeClass('AUTO')==='badge badge-neutral','automatic is blue-violet neutral');
ok(S::classificationBadgeClass('MANUAL')==='badge badge-ok','manual is green');
ok(S::classificationBadgeClass('NEEDS_REVIEW')==='badge badge-warning','review is warning');

$migration=file_get_contents(__DIR__.'/../database/migrations-local/063_finance_matching_classification.sql');
ok(str_contains($migration,'finance_cash_flow_centers')&&str_contains($migration,'classification_locked')&&str_contains($migration,'linked_cash_transaction_id'),'historical matching schema remains readable');

$crud=file_get_contents(__DIR__.'/../app/Service/FinanceMatchingRuleCrudTrait.php');
ok(str_contains($crud,"classification_rule_id=?")&&str_contains($crud,"classification_status='UNALLOCATED'")&&str_contains($crud,"='AUTO'"),'rule deletion reverts only rule-owned AUTO classifications');
ok(str_contains($crud,"operation_type,''))<>'TRANSFER'"),'rule deletion preserves executed historical transfers');

$manual=file_get_contents(__DIR__.'/../app/Service/FinanceMatchingRuleManualTrait.php');
ok(str_contains($manual,'clearBankTransactionClassification')&&str_contains($manual,"['AUTO','MANUAL']")&&str_contains($manual,"classification_status='UNALLOCATED'")&&str_contains($manual,'is_internal_transfer'),'explicit classification removal remains guarded');

$validation=file_get_contents(__DIR__.'/../app/Service/FinanceMatchingRuleValidationTrait.php');
ok(str_contains($validation,"'auto_apply'=>1"),'saved rules normalize to automatic application');
ok(str_contains($validation,'$cash=null;'),'employee settlement strips cash target');

$classification=file_get_contents(__DIR__.'/../app/Service/FinanceMatchingRuleClassificationTrait.php');
ok(str_contains($classification,'legacy_cash_rule_suppressed'),'legacy transfer-to-cash is suppressed');
ok(str_contains($classification,'classification_auto_legacy_cash_suppressed'),'legacy categorize-to-cash becomes classification only');
ok(str_contains($classification,"'cash_operation_created'=>false"),'active classification records no new cash operation');
ok(!str_contains($classification,'convertBankOperationToCashTransfer($pdo,$op,$rule)'),'active classifier does not call cash transfer execution');

$service=file_get_contents(__DIR__.'/../app/Service/FinanceMatchingRuleService.php');
ok(str_contains($service,"'categorize_to_cash'"),'legacy combined action stays accepted for stored-rule compatibility');

$form=file_get_contents(__DIR__.'/../app/View/partials/company_finance_matching_rule_form.php');
ok(!str_contains($form,'id="matching-rule-cash"'),'active matching UI has no cash selector');
ok(str_contains($form,'name="target_cash_account_id" value=""'),'active matching UI clears legacy cash target');

$cashRoutes=file_get_contents(__DIR__.'/../app/Http/Routes/company_finance_cash.php');
ok(!str_contains($cashRoutes,'FinanceCashController'),'cash controller is retired from active routes');
ok(str_contains($cashRoutes,'Создание и изменение кассовых операций отключено'),'cash write endpoints are blocked');

echo "FINANCE_MATCHING_CLASSIFICATION_OK\n";
