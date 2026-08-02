<?php
declare(strict_types=1);
$recon=file_get_contents(__DIR__.'/../app/Service/FinanceBankReconciliationService.php');
$matching=file_get_contents(__DIR__.'/../app/Service/FinanceMatchingRuleService.php');
$checks=[
 'window alias filtered in outer query'=>str_contains($recon,'SELECT duplicate_rows.* FROM (')&&str_contains($recon,'WHERE duplicate_rows.hash_count > 1'),
 'matching lower bound uses unique native PDO placeholders'=>str_contains($matching,':amount_from_credit')&&str_contains($matching,':amount_from_debit'),
 'matching upper bound uses unique native PDO placeholders'=>str_contains($matching,':amount_to_credit')&&str_contains($matching,':amount_to_debit'),
];
foreach($checks as $n=>$p)echo($p?'PASS ':'FAIL '),$n,PHP_EOL;
exit(in_array(false,$checks,true)?1:0);
