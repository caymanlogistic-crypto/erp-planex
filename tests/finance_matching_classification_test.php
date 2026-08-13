<?php
require_once __DIR__.'/../app/Service/FinanceMatchingRuleService.php';
use App\Service\FinanceMatchingRuleService as S;
function ok(bool $v,string $m):void{if(!$v)throw new RuntimeException('FAIL: '.$m);}
$tx=['id'=>10,'account_id'=>2,'credit_amount'=>'0.00','debit_amount'=>'1500.00','counterparty_inn'=>'7701234567','purpose'=>'Оплата услуг перевозки август'];
$base=['id'=>1,'active'=>1,'priority'=>100,'direction'=>'EXPENSE','bank_account_id'=>2,'counterparty_inn'=>'7701234567','purpose_contains'=>'перевозки','amount_from'=>'1000.00','amount_to'=>'2000.00','action_type'=>'categorize','auto_apply'=>1,'deleted_at'=>null];
ok(S::applyRuleToTransaction(null,$base,$tx)['matched']===true,'all AND conditions match');
$r=$base;$r['counterparty_inn']='7700000000';ok(S::applyRuleToTransaction(null,$r,$tx)['matched']===false,'INN mismatch blocks rule');
$r=$base;$r['purpose_contains']='комиссия';ok(S::applyRuleToTransaction(null,$r,$tx)['matched']===false,'purpose mismatch blocks rule');
$r=$base;$r['amount_from']='1600.00';ok(S::applyRuleToTransaction(null,$r,$tx)['matched']===false,'amount lower bound blocks rule');
$r=$base;$r['amount_to']='1400.00';ok(S::applyRuleToTransaction(null,$r,$tx)['matched']===false,'amount upper bound blocks rule');
$r=$base;$r['bank_account_id']=9;ok(S::applyRuleToTransaction(null,$r,$tx)['matched']===false,'bank mismatch blocks rule');
$r=$base;$r['direction']='INCOME';ok(S::applyRuleToTransaction(null,$r,$tx)['matched']===false,'direction mismatch blocks rule');
$low=$base;$low['id']=2;$low['priority']=10;$high=$base;$high['id']=3;$high['priority']=500;$best=S::selectBestMatchingRule([$low,$high],$tx);ok(($best['rule']['id']??0)===3,'maximum numeric priority wins');
$tie=$base;$tie['id']=4;$conflict=S::selectBestMatchingRule([$high,$tie+['priority'=>500]],$tx);ok(!empty($conflict['conflict']),'equal maximum priority conflicts');
$off=$high;$off['active']=0;$best=S::selectBestMatchingRule([$off,$low],$tx);ok(($best['rule']['id']??0)===2,'inactive rule ignored');
$suggest=$base;$suggest['auto_apply']=0;ok(S::applyRuleToTransaction(null,$suggest,$tx)['result']==='suggest','non-auto rule is suggestion');
ok(S::isManualProtected(['classification_locked'=>1,'classification_status'=>'AUTO']),'manual lock protects');
ok(S::isManualProtected(['classification_locked'=>0,'classification_status'=>'MANUAL']),'manual status protects');
ok(S::classificationStatusLabel('NEEDS_REVIEW')==='Конфликт / требует проверки','Russian review label');
$migration=file_get_contents(__DIR__.'/../database/migrations-local/063_finance_matching_classification.sql');ok(str_contains($migration,'finance_cash_flow_centers')&&str_contains($migration,'classification_locked')&&str_contains($migration,'linked_cash_transaction_id'),'migration contains CFU, lock and transfer link');
$transfer=file_get_contents(__DIR__.'/../app/Service/FinanceMatchingRuleTransferExecutionTrait.php');ok(str_contains($transfer,'rule_cash_transfer:')&&str_contains($transfer,"transfer_direction='out'")&&str_contains($transfer,"'in'"),'bank-to-cash uses idempotent paired transfer representation');
echo "FINANCE_MATCHING_CLASSIFICATION_OK\n";
