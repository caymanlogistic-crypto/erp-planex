<?php

namespace App\Service;

require_once __DIR__ . '/FinanceMatchingRuleUtilityTrait.php';
require_once __DIR__ . '/FinanceMatchingRuleCrudInputTrait.php';
require_once __DIR__ . '/FinanceMatchingRuleCrudTrait.php';
require_once __DIR__ . '/FinanceMatchingRuleMatcherTrait.php';
require_once __DIR__ . '/FinanceMatchingRuleAutoApplyTrait.php';
require_once __DIR__ . '/FinanceMatchingRuleClassificationTrait.php';
require_once __DIR__ . '/FinanceMatchingRuleDirectoryTrait.php';

final class FinanceMatchingRuleService
{
    use FinanceMatchingRuleUtilityTrait;
    use FinanceMatchingRuleCrudInputTrait;
    use FinanceMatchingRuleCrudTrait;
    use FinanceMatchingRuleMatcherTrait;
    use FinanceMatchingRuleAutoApplyTrait;
    use FinanceMatchingRuleClassificationTrait;
    use FinanceMatchingRuleDirectoryTrait;

    private const ALLOWED_DIRECTIONS = ['INCOME', 'EXPENSE'];
    private const ALLOWED_ACTION_TYPES = ['categorize', 'match_invoice', 'match_counterparty', 'classify', 'bank_to_cash'];
    private const CLASSIFICATION_STATUSES = ['unclassified', 'auto', 'manual', 'needs_review'];
}
