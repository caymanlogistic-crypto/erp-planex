<?php
require_once __DIR__ . '/../app/Service/FinanceMatchingRuleService.php';
require_once __DIR__ . '/../app/Service/FinanceAuditLogService.php';
use App\Service\FinanceMatchingRuleService as M;
$pass=0;$fail=0;
function eq(string $n,mixed $a,mixed $e):void{global $pass,$fail;if($a===$e){$pass++;}else{$fail++;fwrite(STDERR,"FAIL $n expected=".var_export($e,true)." actual=".var_export($a,true)."\n");}}
$tx=['credit_amount'=>'100.00','debit_amount'=>'0.00','purpose'=>'КОМИССИЯ БАНКА','counterparty_inn'=>'7701','account_id'=>1];
$base=['active'=>1,'direction'=>'INCOME','bank_account_id'=>1,'counterparty_inn'=>'7701','purpose_contains'=>'комиссия','auto_apply'=>1,'priority'=>10,'target_dds_category_id'=>1];
$r=M::applyRuleToTransaction(null,['id'=>1,'name'=>'a']+$base,$tx);
eq('cyrillic lower fallback',$r['result'],'auto_apply');
$best=M::selectBestMatchingRule([
 ['id'=>1,'name'=>'a']+$base,
 ['id'=>2,'name'=>'b']+$base,
],$tx);
eq('ambiguous fail closed',$best['result']['result'],'ambiguous');
eq('two candidates',$best['result']['candidate_rule_ids'],[1,2]);
$one=M::selectBestMatchingRule([['id'=>1,'name'=>'a']+$base],$tx);
eq('single auto allowed',$one['result']['result'],'auto_apply');
$none=M::selectBestMatchingRule([['id'=>3,'name'=>'off','active'=>0]+$base],$tx);
eq('inactive ignored',$none,null);

final class MNoopPdo extends PDO { public function __construct() {} }
function throwsRule(string $n, callable $fn):void{global $pass,$fail;try{$fn();$fail++;fwrite(STDERR,"FAIL $n no exception\n");}catch(InvalidArgumentException){$pass++;}}
$noop=new MNoopPdo();
throwsRule('malformed bound rejected',fn()=>M::createRule($noop,['name'=>'x','amount_from'=>'abc'],[]));
throwsRule('inverted range rejected',fn()=>M::createRule($noop,['name'=>'x','amount_from'=>'10.00','amount_to'=>'9.99'],[]));
throwsRule('invalid regex rejected',fn()=>M::createRule($noop,['name'=>'x','purpose_regex'=>'/[a-/'],[]));
throwsRule('invalid transaction money fails closed',fn()=>M::applyRuleToTransaction(null,['id'=>4,'name'=>'x','direction'=>'INCOME'],['credit_amount'=>'broken','debit_amount'=>'0']));
printf("MATCHING: %d passed, %d failed\n",$pass,$fail);exit($fail?1:0);
