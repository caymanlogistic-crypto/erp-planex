<?php
namespace App\Service;
require_once __DIR__.'/FinanceEmployeeMoneyAccountService.php';
require_once __DIR__.'/FinanceEmployeeBankSettlementService.php';
require_once __DIR__.'/FinanceMatchingRuleUtilityTrait.php';
require_once __DIR__.'/FinanceMatchingRuleValidationTrait.php';
require_once __DIR__.'/FinanceMatchingRuleCrudTrait.php';
require_once __DIR__.'/FinanceMatchingRuleCfuTrait.php';
require_once __DIR__.'/FinanceMatchingRuleEngineTrait.php';
require_once __DIR__.'/FinanceMatchingRuleClassificationTrait.php';
require_once __DIR__.'/FinanceMatchingRuleManualTrait.php';
require_once __DIR__.'/FinanceMatchingRuleTransferExecutionTrait.php';
require_once __DIR__.'/FinanceMatchingRuleEmployeeExecutionTrait.php';
require_once __DIR__.'/FinanceMatchingRuleBatchTrait.php';
final class FinanceMatchingRuleService{
 private const ALLOWED_DIRECTIONS=['INCOME','EXPENSE'];
 // employee_cash_settlement is retained as a storage/API compatibility name.
 // Its execution no longer uses CASH; it resolves BANK <-> EMPLOYEE directly.
 private const ALLOWED_ACTION_TYPES=['categorize','categorize_to_cash','match_invoice','match_counterparty','transfer_to_cash','employee_cash_settlement'];
 use FinanceMatchingRuleUtilityTrait,FinanceMatchingRuleValidationTrait,FinanceMatchingRuleCrudTrait,FinanceMatchingRuleCfuTrait,FinanceMatchingRuleEngineTrait,FinanceMatchingRuleClassificationTrait,FinanceMatchingRuleManualTrait,FinanceMatchingRuleTransferExecutionTrait,FinanceMatchingRuleEmployeeExecutionTrait,FinanceMatchingRuleBatchTrait;
}
