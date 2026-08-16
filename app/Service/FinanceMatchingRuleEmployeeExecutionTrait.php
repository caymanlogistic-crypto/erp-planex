<?php
namespace App\Service;

use PDO;

trait FinanceMatchingRuleEmployeeExecutionTrait
{
    private static function executeEmployeeCashSettlement(PDO $pdo, array $op, array $rule): array
    {
        $originalType = strtoupper((string)($op['operation_type'] ?? ''));
        if (!in_array($originalType, ['INCOME', 'EXPENSE'], true) || ($op['source'] ?? '') !== 'BANK_STATEMENT') {
            throw new \RuntimeException('Взаиморасчёт с сотрудником можно создать только из банковского поступления или списания.');
        }

        $bankTxId = (int)($op['bank_transaction_id'] ?? 0);
        $cashId = self::nullableInt($rule['target_cash_account_id'] ?? null);
        $employeeType = (string)($rule['target_employee_identity_type'] ?? '');
        $employeeId = (int)($rule['target_employee_identity_id'] ?? 0);
        $employeeName = trim((string)($rule['target_employee_name_snapshot'] ?? ''));
        $employeeRole = trim((string)($rule['target_employee_role_snapshot'] ?? ''));
        $cfu = self::nullableInt($rule['target_cash_flow_center_id'] ?? null);
        $dds = self::nullableInt($rule['target_dds_category_id'] ?? null);

        if ($bankTxId <= 0 || $cashId === null || $employeeId <= 0 || $employeeName === '' || $cfu === null || $dds === null) {
            throw new \RuntimeException('Правило взаиморасчёта с сотрудником заполнено не полностью.');
        }
        if (!in_array($employeeType, [FinanceEmployeePaymentService::IDENTITY_TENANT_USER, FinanceEmployeePaymentService::IDENTITY_COMPANY_USER], true)) {
            throw new \RuntimeException('Некорректный тип сотрудника в правиле.');
        }
        self::assertCashAccount($pdo, $cashId);

        $own = !$pdo->inTransaction();
        if ($own) $pdo->beginTransaction();

        try {
            $lock = $pdo->prepare('SELECT * FROM bank_transactions WHERE id=? FOR UPDATE');
            $lock->execute([$bankTxId]);
            $bank = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$bank) throw new \RuntimeException('Банковская операция не найдена.');

            $existing = $pdo->prepare('SELECT id,finance_operation_id,movement_type FROM finance_employee_movements WHERE bank_transaction_id=? LIMIT 1');
            $existing->execute([$bankTxId]);
            $movement = $existing->fetch(PDO::FETCH_ASSOC);
            if ($movement) {
                if ($own) $pdo->commit();
                return [
                    'handled' => true,
                    'idempotent' => true,
                    'employee_movement_id' => (int)$movement['id'],
                    'employee_operation_id' => (int)$movement['finance_operation_id'],
                ];
            }
            if (!empty($bank['is_internal_transfer'])) {
                throw new \RuntimeException('Банковская операция уже оформлена как внутренний перевод.');
            }

            $movementType = $originalType === 'EXPENSE' ? 'PAYMENT' : 'RETURN';
            $employeeOperationType = $movementType === 'PAYMENT' ? 'EXPENSE' : 'INCOME';
            $ruleId = (int)$rule['id'];
            $amount = (string)$op['amount'];
            $date = (string)$op['operation_date'];
            $currency = (string)($op['currency'] ?? 'RUR');
            $purpose = (string)($op['purpose'] ?? '');
            $group = 'TRF_EMP_' . substr(hash('sha256', 'employee_transfer:' . $bankTxId), 0, 24);
            $cashTransferHash = hash('sha256', 'employee_rule_transfer_cash:' . $bankTxId);
            $employeeHash = hash('sha256', 'employee_rule_settlement:' . $bankTxId . ':' . $movementType);
            $bankAccountId = (int)$op['money_account_id'];
            $cashTransferDirection = $originalType === 'EXPENSE' ? 'in' : 'out';

            $insertTransfer = $pdo->prepare("INSERT INTO finance_operations
                (operation_type,status,source,money_account_id,transfer_account_id,transfer_group_id,transfer_direction,operation_date,amount,currency,purpose,comment,dedupe_hash,classification_status,classification_rule_id,classification_locked,classification_updated_at,created_by_user_id,created_by_role,posted_by_user_id,posted_by_role,posted_at)
                VALUES ('TRANSFER','POSTED','TRANSFER',?,?,?,?,?,?,?,?,?,?,'AUTO',?,0,NOW(),0,'system',0,'system',NOW())");
            $insertTransfer->execute([
                $cashId,
                $bankAccountId,
                $group,
                $cashTransferDirection,
                $date,
                $amount,
                $currency,
                $purpose,
                'Технический перевод кассы по правилу сотрудника #' . $ruleId,
                $cashTransferHash,
                $ruleId,
            ]);
            $cashTransferId = (int)$pdo->lastInsertId();

            $employeeComment = ($movementType === 'PAYMENT' ? 'Выплата сотруднику ' : 'Возврат от сотрудника ')
                . $employeeName . ' по правилу #' . $ruleId;
            $insertEmployee = $pdo->prepare("INSERT INTO finance_operations
                (operation_type,status,source,money_account_id,operation_date,amount,currency,dds_category_id,cash_flow_center_id,purpose,comment,dedupe_hash,classification_status,classification_rule_id,classification_locked,classification_updated_at,created_by_user_id,created_by_role,posted_by_user_id,posted_by_role,posted_at)
                VALUES (?,'POSTED','CASH',?,?,?,?,?,?,?,?,?,'AUTO',?,0,NOW(),0,'system',0,'system',NOW())");
            $insertEmployee->execute([
                $employeeOperationType,
                $cashId,
                $date,
                $amount,
                $currency,
                $dds,
                $cfu,
                $purpose,
                $employeeComment,
                $employeeHash,
                $ruleId,
            ]);
            $employeeOperationId = (int)$pdo->lastInsertId();

            $bankDirection = $originalType === 'EXPENSE' ? 'out' : 'in';
            $upd = $pdo->prepare("UPDATE finance_operations
                SET operation_type='TRANSFER',source='TRANSFER',transfer_account_id=?,transfer_group_id=?,transfer_direction=?,dds_category_id=?,cash_flow_center_id=?,classification_status='AUTO',classification_rule_id=?,classification_locked=0,classification_updated_at=NOW()
                WHERE id=?");
            $upd->execute([$cashId, $group, $bankDirection, $dds, $cfu, $ruleId, (int)$op['id']]);

            $upd = $pdo->prepare("UPDATE bank_transactions
                SET is_internal_transfer=1,linked_cash_transaction_id=?,dds_category_id=?,cash_flow_center_id=?,classification_status='AUTO',classification_rule_id=?,classification_locked=0,classification_updated_at=NOW()
                WHERE id=?");
            $upd->execute([$cashTransferId, $dds, $cfu, $ruleId, $bankTxId]);

            $employeeUserId = $employeeType === FinanceEmployeePaymentService::IDENTITY_TENANT_USER ? $employeeId : null;
            $insMovement = $pdo->prepare("INSERT INTO finance_employee_movements
                (employee_user_id,employee_identity_type,employee_identity_id,employee_name_snapshot,employee_role_snapshot,movement_type,source_type,finance_operation_id,bank_transaction_id,note,created_by_user_id,created_by_role)
                VALUES (?,?,?,?,?,?,?,?,?,?,0,'system')");
            $insMovement->execute([
                $employeeUserId,
                $employeeType,
                $employeeId,
                $employeeName,
                $employeeRole,
                $movementType,
                'CASH',
                $employeeOperationId,
                $bankTxId,
                $employeeComment,
            ]);
            $movementId = (int)$pdo->lastInsertId();

            // Register the whole employee rule chain as one completed technical-cash lifecycle.
            // PAYMENT: bank -> cash -> employee. RETURN: employee -> cash -> bank.
            $bankNameStmt = $pdo->prepare('SELECT name FROM finance_money_accounts WHERE id=? LIMIT 1');
            $bankNameStmt->execute([$bankAccountId]);
            $bankAccountName = trim((string)($bankNameStmt->fetchColumn() ?: 'Расчётный счёт'));

            if ($movementType === 'PAYMENT') {
                $resolutionType = FinanceCashResolutionService::RESOLUTION_EMPLOYEE;
                $resolutionSourceId = $cashTransferId;
                $resolutionOutflowId = $employeeOperationId;
                $targetIdentityType = $employeeType;
                $targetIdentityId = $employeeId;
                $targetName = $employeeName;
            } else {
                $resolutionType = 'BANK';
                $resolutionSourceId = $employeeOperationId;
                $resolutionOutflowId = $cashTransferId;
                $targetIdentityType = 'MONEY_ACCOUNT';
                $targetIdentityId = $bankAccountId;
                $targetName = $bankAccountName !== '' ? $bankAccountName : 'Расчётный счёт';
            }

            $insResolution = $pdo->prepare("INSERT INTO finance_cash_resolutions
                (source_finance_operation_id,resolution_type,target_identity_type,target_identity_id,target_name_snapshot,outflow_finance_operation_id,employee_movement_id,created_by_user_id,created_by_role)
                VALUES (?,?,?,?,?,?,?,NULL,'system')");
            $insResolution->execute([
                $resolutionSourceId,
                $resolutionType,
                $targetIdentityType,
                $targetIdentityId,
                $targetName,
                $resolutionOutflowId,
                $movementId,
            ]);
            $resolutionId = (int)$pdo->lastInsertId();

            FinanceAuditLogService::log($pdo, 'finance_employee_movement', $movementId, 'create_rule_settlement', null, [
                'employee_identity_type' => $employeeType,
                'employee_identity_id' => $employeeId,
                'movement_type' => $movementType,
                'finance_operation_id' => $employeeOperationId,
                'bank_transaction_id' => $bankTxId,
                'rule_id' => $ruleId,
                'cash_resolution_id' => $resolutionId,
            ], 0, 'system');
            FinanceAuditLogService::log($pdo, 'finance_operation', (int)$op['id'], 'matching_employee_cash_settlement', null, [
                'rule_id' => $ruleId,
                'cash_account_id' => $cashId,
                'employee_movement_id' => $movementId,
                'cash_resolution_id' => $resolutionId,
            ], 0, 'system');

            if ($own) $pdo->commit();
            return [
                'handled' => true,
                'rule_id' => $ruleId,
                'employee_movement_id' => $movementId,
                'employee_operation_id' => $employeeOperationId,
                'cash_transfer_operation_id' => $cashTransferId,
                'cash_resolution_id' => $resolutionId,
                'transfer_group_id' => $group,
                'movement_type' => $movementType,
            ];
        } catch (\Throwable $e) {
            if ($own && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
