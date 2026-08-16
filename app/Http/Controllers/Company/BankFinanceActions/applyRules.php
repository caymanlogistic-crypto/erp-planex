<?php

requireRole(['company_owner']);
verifyCsrfRequest();

$companyId = (int) (getSessionCompanyId() ?? 0);
if ($companyId <= 0) {
    $_SESSION['bank_finance_error'] = 'Компания не найдена.';
    redirect_to('/company/finance/bank-accounts');
}

try {
    $centralPdo = $db->connection();
    $stmt = $centralPdo->prepare("SELECT * FROM companies WHERE id = ? AND status = 'active'");
    $stmt->execute([$companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$company) {
        throw new RuntimeException('Компания не найдена или неактивна.');
    }

    $localPdo = (new \App\Core\Database(companyDatabaseConfig($config, $company)))->connection();
    $summary = \App\Service\FinanceMatchingRuleService::applyRulesToUnallocatedBankOperations($localPdo);

    $localPdo->beginTransaction();
    try {
        // Self-heal allocations that may have been inserted by an earlier interrupted bank settlement.
        // Recalculation is idempotent and keeps invoice/route statuses consistent with active allocations.
        $repairIds = $localPdo->query(
            "SELECT a.id
               FROM finance_operation_allocations a
               JOIN finance_operations fo ON fo.id=a.operation_id
              WHERE a.cancelled_at IS NULL
                AND a.invoice_id IS NOT NULL
                AND fo.status='POSTED'
                AND fo.source='BANK_STATEMENT'
              ORDER BY a.id"
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ($repairIds as $allocationId) {
            \App\Service\FinanceSettlementCascadeService::cascadeAfterAllocationCreate($localPdo, (int)$allocationId);
        }

        $invoiceSummary = \App\Service\FinanceObligationService::autoAllocateIncomingCustomerReceipts(
            $localPdo,
            $_SESSION['user'] ?? []
        );
        $localPdo->commit();
    } catch (Throwable $e) {
        if ($localPdo->inTransaction()) {
            $localPdo->rollBack();
        }
        throw $e;
    }

    $_SESSION['bank_finance_success'] = sprintf(
        'Разнесение завершено: проверено — %d, автоматически разнесено — %d, на проверку — %d, конфликтов — %d, без подходящего правила — %d%s. По счетам клиентов: сопоставлено платежей — %d, создано распределений — %d, сумма — %s ₽.',
        (int) $summary['scanned'],
        (int) $summary['auto_applied'],
        (int) ($summary['suggested'] + $summary['needs_review']),
        (int) $summary['conflicts'],
        (int) $summary['unmatched'],
        (int) $summary['errors'] > 0 ? ', ошибок — ' . (int) $summary['errors'] : '',
        (int) $invoiceSummary['operations'],
        (int) $invoiceSummary['allocations'],
        number_format((float)$invoiceSummary['amount'], 2, ',', ' ')
    );
} catch (Throwable $e) {
    error_log('Batch bank matching error: ' . $e->getMessage());
    $_SESSION['bank_finance_error'] = 'Не удалось выполнить правила разнесения: ' . $e->getMessage();
}

redirect_to('/company/finance/bank-accounts');
