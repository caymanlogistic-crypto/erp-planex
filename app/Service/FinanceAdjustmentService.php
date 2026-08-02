<?php

namespace App\Service;

use PDO;
use RuntimeException;
use Throwable;

final class FinanceAdjustmentService
{
    public static function createAdjustment(
        PDO $pdo,
        int $moneyAccountId,
        string $amount,
        string $reason,
        int $userId,
        string $roleCode
    ): int {
        $amount = FinanceOperationService::normalizeMoneyInput($amount);
        if ($amount === null) {
            throw new RuntimeException('Сумма корректировки должна быть больше нуля.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Укажите причину корректировки.');
        }
        $reasonLength = function_exists('mb_strlen') ? mb_strlen($reason, 'UTF-8') : strlen($reason);
        if ($reasonLength > 1000) {
            throw new RuntimeException('Причина корректировки слишком длинная.');
        }
        if ($moneyAccountId <= 0 || $userId < 0 || trim($roleCode) === '') {
            throw new RuntimeException('Некорректный контекст корректировки.');
        }

        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $stmt = $pdo->prepare('SELECT id, type FROM finance_money_accounts WHERE id = ? FOR UPDATE');
            $stmt->execute([$moneyAccountId]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$account) {
                throw new RuntimeException('Денежный счёт не найден.');
            }

            $ins = $pdo->prepare(
                "INSERT INTO finance_operations
                    (operation_type, status, source, money_account_id, operation_date,
                     amount, currency, purpose,
                     created_by_user_id, created_by_role,
                     posted_by_user_id, posted_by_role, posted_at)
                 VALUES
                    ('ADJUSTMENT', 'POSTED', 'MANUAL', :money_account_id, CURDATE(),
                     :amount, 'RUR', :purpose,
                     :user_id, :role, :user_id, :role, NOW())"
            );
            $ins->execute([
                ':money_account_id' => $moneyAccountId,
                ':amount' => $amount,
                ':purpose' => 'Корректировка: ' . $reason,
                ':user_id' => $userId,
                ':role' => $roleCode,
            ]);
            $operationId = (int)$pdo->lastInsertId();
            if ($operationId <= 0) {
                throw new RuntimeException('Не удалось получить идентификатор корректировки.');
            }

            FinanceAuditLogService::log(
                $pdo,
                'finance_operation',
                $operationId,
                'adjustment_create',
                null,
                ['operation_type' => 'ADJUSTMENT', 'amount' => $amount, 'reason' => $reason, 'money_account_id' => $moneyAccountId],
                $userId,
                $roleCode
            );
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return $operationId;
        } catch (Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
