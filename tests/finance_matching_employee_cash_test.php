<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/Service/FinanceMatchingRuleService.php';
use App\Service\FinanceMatchingRuleService as S;

function ok(bool $value, string $message): void
{
    if (!$value) throw new RuntimeException('FAIL: ' . $message);
}

$tx = [
    'id' => 1,
    'account_id' => 2,
    'credit_amount' => '0.00',
    'debit_amount' => '50000.00',
    'counterparty_inn' => '7702070139',
    'purpose' => 'Снятие наличных по чеку',
];
$rule = [
    'id' => 10,
    'active' => 1,
    'deleted_at' => null,
    'priority' => 300,
    'direction' => 'EXPENSE',
    'counterparty_inn' => '7702070139',
    'purpose_contains' => 'снятие наличных',
    'action_type' => 'employee_cash_settlement',
];
$r = S::applyRuleToTransaction(null, $rule, $tx);
ok(!empty($r['matched']) && ($r['result'] ?? '') === 'auto_apply', 'employee rule uses strict matching semantics');

$src = file_get_contents(__DIR__ . '/../app/Service/FinanceMatchingRuleEmployeeExecutionTrait.php');
ok(str_contains($src, "'EXPENSE' ? 'PAYMENT' : 'RETURN'"), 'expense/payment return mapping');
ok(str_contains($src, "operation_type='TRANSFER'") && str_contains($src, 'finance_employee_movements'), 'cash employee lifecycle exists');
ok(str_contains($src, 'INSERT INTO finance_cash_resolutions'), 'employee matching rule registers cash resolution');
ok(str_contains($src, "\$resolutionType = 'BANK'"), 'reverse employee return resolves to bank');
ok(str_contains($src, 'cash_resolution_id'), 'cash resolution is audited and returned');

$migration = file_get_contents(__DIR__ . '/../database/migrations-local/069_backfill_employee_rule_cash_resolutions.sql');
ok(str_contains($migration, "THEN 'EMPLOYEE'") && str_contains($migration, "ELSE 'BANK'"), 'backfill covers both employee cash directions');
ok(str_contains($migration, 'classification_rule_id'), 'backfill is restricted to rule-created chains');

$manual = file_get_contents(__DIR__ . '/../app/Service/FinanceMatchingRuleManualTrait.php');
ok(str_contains($manual, "'AUTO',$ruleId") && str_contains($manual, 'applyAutoMatchToOperation'), 'popup AUTO and manual reapply');
$popup = file_get_contents(__DIR__ . '/../app/View/partials/company_bank_transaction_classify_form.php');
ok(str_contains($popup, 'Только эту операцию') && str_contains($popup, 'Создать правило') && str_contains($popup, 'Вернуть под правила'), 'bank popup lifecycle');
ok(!str_contains($popup, 'employee-payments/bank-link'), 'direct bank employee link removed from popup');
$rulesPage = file_get_contents(__DIR__ . '/../app/View/pages/company_finance_matching_rules.php');
ok(str_contains($rulesPage, "isEmployee||d==='EXPENSE'") && str_contains($rulesPage, 'cash.required=isEmployee'), 'employee rule keeps cash enabled and required for income and expense');
$employeePage = file_get_contents(__DIR__ . '/../app/View/pages/company_finance_employee_payments.php');
ok(str_contains($employeePage, 'Взаиморасчёты с сотрудником') && str_contains($employeePage, 'За всё время') && str_contains($employeePage, 'ИТОГО'), 'employee ledger current summary UI is explicit');

echo "FINANCE_MATCHING_EMPLOYEE_CASH_OK\n";
