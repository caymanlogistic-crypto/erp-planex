<?php

namespace App\Service;

final class FinanceMatchingRuleService
{
    private const ALLOWED_DIRECTIONS = ['INCOME', 'EXPENSE'];
    private const ALLOWED_ACTION_TYPES = ['categorize', 'match_invoice', 'match_counterparty', 'transfer_to_cash'];

    use FinanceMatchingRuleUtilityTrait;
    use FinanceMatchingRuleValidationTrait;
    use FinanceMatchingRuleCrudTrait;
    use FinanceMatchingRuleCfuTrait;
    use FinanceMatchingRuleEngineTrait;
    use FinanceMatchingRuleClassificationTrait;
    use FinanceMatchingRuleManualTrait;
    use FinanceMatchingRuleTransferTrait;
    use FinanceMatchingRuleTransferExecutionTrait;
}
