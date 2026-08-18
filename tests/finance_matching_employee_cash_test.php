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
ok(!empty($r['matched']) && ($r['result'] ?? '') === 'auto_apply', 'employee compatibility rule keeps strict matching semantics');

$execution = file_get_contents(__DIR__ . '/../app/Service/FinanceMatchingRuleEmployeeExecutionTrait.php');
ok(str_contains($execution, 'FinanceEmployeeBankSettlementService::settleBankTransaction'), 'employee rule delegates to direct bank settlement');
ok(!str_contains($execution, 'finance_cash_resolutions'), 'employee rule never creates cash resolution');
ok(!str_contains($execution, 'target_cash_account_id'), 'employee rule execution has no cash target dependency');

$bank = file_get_contents(__DIR__ . '/../app/Service/FinanceEmployeeBankSettlementService.php');
ok(str_contains($bank, 'FinanceEmployeeMoneyAccountService::accountId'), 'bank settlement uses employee money account');
ok(str_contains($bank, "'BANK'"), 'employee movement keeps bank provenance');
ok(str_contains($bank, "'cash_account_used' => false"), 'bank settlement explicitly records no cash usage');
ok(!str_contains($bank, 'INSERT INTO finance_cash_resolutions'), 'direct bank settlement cannot create cash resolution');

$transfer = file_get_contents(__DIR__ . '/../app/Service/FinanceEmployeeDirectTransferService.php');
ok(str_contains($transfer, "'EMPLOYEE'"), 'employee transfer uses employee source type');
ok(str_contains($transfer, "'cash_account_used' => false"), 'employee transfer explicitly records no cash usage');
ok(!str_contains($transfer, 'finance_cash_resolutions'), 'employee transfer cannot create cash resolution');

$validation = file_get_contents(__DIR__ . '/../app/Service/FinanceMatchingRuleValidationTrait.php');
ok(str_contains($validation, '$cash=null;'), 'employee rule forcibly clears legacy cash target');
ok(str_contains($validation, "if(\$action==='employee_cash_settlement'&&(\$cfu===null||\$dds===null))"), 'employee rule requires classification but not cash');

$form = file_get_contents(__DIR__ . '/../app/View/partials/company_finance_matching_rule_form.php');
ok(str_contains($form, 'name="target_cash_account_id" value=""'), 'employee rule form always submits empty cash target');
ok(!str_contains($form, 'id="matching-rule-cash"'), 'active rule form exposes no cash selector');
ok(str_contains($form, 'Техническая касса не используется'), 'employee rule UI explains direct settlement');

$classifier = file_get_contents(__DIR__ . '/../app/Service/FinanceMatchingRuleClassificationTrait.php');
ok(str_contains($classifier, 'legacy_cash_rule_suppressed'), 'legacy transfer-to-cash rule is suppressed');
ok(str_contains($classifier, "'cash_operation_created'=>false"), 'classifier records zero cash creation');
ok(!str_contains($classifier, 'convertBankOperationToCashTransfer($pdo,$op,$rule)'), 'active classifier no longer executes cash transfer helper');

$cashRoutes = file_get_contents(__DIR__ . '/../app/Http/Routes/company_finance_cash.php');
ok(!str_contains($cashRoutes, 'FinanceCashController'), 'cash controller is not loaded by active routes');
ok(str_contains($cashRoutes, 'Создание и изменение кассовых операций отключено'), 'legacy cash POST routes are blocked');

$employeeRoutes = file_get_contents(__DIR__ . '/../app/Http/Routes/company_finance_employee_payments.php');
ok(str_contains($employeeRoutes, 'Старая кассовая операция сотрудника отключена'), 'generic employee cash create route is blocked');
ok(str_contains($employeeRoutes, 'FinanceEmployeeDirectActions/transfer_submit.php'), 'employee transfer create uses direct route');
ok(str_contains($employeeRoutes, 'FinanceEmployeeDirectActions/invoice_payment_create.php'), 'employee invoice create uses direct route');
ok(str_contains($employeeRoutes, 'FinanceEmployeeDirectActions/personal_expense_create.php'), 'employee personal expense create uses direct route');

$migration = file_get_contents(__DIR__ . '/../database/migrations-local/076_finance_employee_money_accounts.sql');
ok(str_contains($migration, 'finance_employee_money_accounts'), 'tenant migration creates employee account mapping');
ok(str_contains($migration, 'UNIQUE KEY uq_finance_employee_money_accounts_identity'), 'one employee has one direct money account');
ok(!file_exists(__DIR__ . '/../database/migrations/20260818_finance_employee_money_accounts.sql'), 'no misplaced global migration remains');

$historical = file_get_contents(__DIR__ . '/../database/migrations-local/069_backfill_employee_rule_cash_resolutions.sql');
ok(str_contains($historical, 'classification_rule_id'), 'historical cash backfill remains preserved');

echo "FINANCE_MATCHING_EMPLOYEE_DIRECT_OK\n";
