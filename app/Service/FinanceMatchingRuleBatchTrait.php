<?php
namespace App\Service;
use PDO;

trait FinanceMatchingRuleBatchTrait
{
    public static function applyRulesToUnallocatedBankOperations(PDO $pdo): array
    {
        $sql = "SELECT DISTINCT fo.id
                FROM finance_operations fo
                INNER JOIN bank_transactions bt ON bt.id = fo.bank_transaction_id
                WHERE COALESCE(bt.is_internal_transfer, 0) = 0
                  AND COALESCE(bt.classification_locked, 0) = 0
                  AND UPPER(COALESCE(bt.classification_status, 'UNALLOCATED')) IN ('UNALLOCATED', 'NEEDS_REVIEW')
                  AND UPPER(COALESCE(fo.classification_status, 'UNALLOCATED')) <> 'MANUAL'
                ORDER BY fo.id ASC";
        $operationIds = array_map('intval', $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN));

        $summary = [
            'scanned' => count($operationIds),
            'auto_applied' => 0,
            'suggested' => 0,
            'conflicts' => 0,
            'needs_review' => 0,
            'unmatched' => 0,
            'protected' => 0,
            'errors' => 0,
        ];

        foreach ($operationIds as $operationId) {
            $savepoint = 'batch_rule_' . $operationId;
            try {
                if (!$pdo->inTransaction()) {
                    $pdo->beginTransaction();
                }
                $pdo->exec('SAVEPOINT ' . $savepoint);
                $result = self::applyAutoMatchToOperation($pdo, $operationId);
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);

                if (!empty($result['protected'])) {
                    $summary['protected']++;
                } elseif (!empty($result['conflict'])) {
                    $summary['conflicts']++;
                } elseif (!empty($result['needs_review'])) {
                    $summary['needs_review']++;
                } elseif (!empty($result['matched']) && ($result['result'] ?? '') === 'auto_apply') {
                    $summary['auto_applied']++;
                } elseif (!empty($result['matched']) && ($result['result'] ?? '') === 'suggest') {
                    $summary['suggested']++;
                } else {
                    $summary['unmatched']++;
                }
            } catch (\Throwable $e) {
                try {
                    if ($pdo->inTransaction()) {
                        $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                    }
                } catch (\Throwable) {
                }
                $summary['errors']++;
                error_log('Batch finance matching failed for operation #' . $operationId . ': ' . $e->getMessage());
            }
        }

        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        return $summary;
    }
}
