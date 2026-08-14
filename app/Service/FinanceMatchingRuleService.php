<?php
namespace App\Service;
require_once __DIR__.'/FinanceMatchingRuleUtilityTrait.php';
require_once __DIR__.'/FinanceMatchingRuleValidationTrait.php';
require_once __DIR__.'/FinanceMatchingRuleCrudTrait.php';
require_once __DIR__.'/FinanceMatchingRuleCfuTrait.php';
require_once __DIR__.'/FinanceMatchingRuleEngineTrait.php';
require_once __DIR__.'/FinanceMatchingRuleClassificationTrait.php';
require_once __DIR__.'/FinanceMatchingRuleManualTrait.php';
require_once __DIR__.'/FinanceMatchingRuleTransferExecutionTrait.php';
require_once __DIR__.'/FinanceMatchingRuleBatchTrait.php';
final class FinanceMatchingRuleService{
 private const ALLOWED_DIRECTIONS=['INCOME','EXPENSE'];
 private const ALLOWED_ACTION_TYPES=['categorize','match_invoice','match_counterparty','transfer_to_cash'];
 use FinanceMatchingRuleUtilityTrait,FinanceMatchingRuleValidationTrait,FinanceMatchingRuleCrudTrait,FinanceMatchingRuleCfuTrait,FinanceMatchingRuleEngineTrait,FinanceMatchingRuleClassificationTrait,FinanceMatchingRuleManualTrait,FinanceMatchingRuleTransferExecutionTrait,FinanceMatchingRuleBatchTrait;
}
