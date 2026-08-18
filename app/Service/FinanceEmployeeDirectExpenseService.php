<?php

namespace App\Service;

use PDO;

/**
 * Economic company expense paid directly by an employee.
 * No technical CASH receipt/outflow is created.
 */
final class FinanceEmployeeDirectExpenseService
{
    public static function create(PDO $pdo, array $data, array $user, array $employee): array
    {
        $normalized = self::normalizeAndValidate($pdo, $data, $employee);
        $owns = !$pdo->inTransaction();
        if ($owns) {
            $pdo->beginTransaction();
        }

        try {
            $accountId = FinanceEmployeeMoneyAccountService::accountId($pdo, $employee, $user);
            $eventGroup = 'EMP_PERSONAL_DIRECT_' . bin2hex(random_bytes(10));
            $uid = (int)($user['user_id'] ?? $user['id'] ?? 0);
            $role = (string)($user['role_code'] ?? $user['role'] ?? 'company_owner');

            $insertOperation = $pdo->prepare(
                "INSERT INTO finance_operations
                    (operation_type,status,source,money_account_id,transfer_account_id,transfer_group_id,transfer_direction,
                     operation_date,amount,currency,dds_category_id,cash_flow_center_id,
                     counterparty_name,purpose,comment,linear_route_id,
                     classification_status,classification_locked,
                     created_by_user_id,created_by_role,posted_by_user_id,posted_by_role,posted_at)
                 VALUES
                    ('EXPENSE','POSTED','EMPLOYEE',?,NULL,?,NULL,?,?,'RUR',?,?, ?,?,?,?,
                     'CLASSIFIED',1, ?,?,?,?,NOW())"
            );
            $insertOperation->execute([
                $accountId,
                $eventGroup,
                $normalized['operation_date'],
                $normalized['amount'],
                $normalized['dds_category_id'],
                $normalized['cash_flow_center_id'],
                $normalized['counterparty_name'],
                $normalized['purpose'],
                $normalized['comment'],
                $normalized['linear_route_id'],
                $uid > 0 ? $uid : null,
                $role,
                $uid > 0 ? $uid : null,
                $role,
            ]);
            $operationId = (int)$pdo->lastInsertId();

            $localUserId = (string)$employee['identity_type'] === FinanceEmployeePaymentService::IDENTITY_TENANT_USER
                ? (int)$employee['identity_id']
                : null;
            $movement = $pdo->prepare(
                "INSERT INTO finance_employee_movements
                    (employee_user_id,employee_identity_type,employee_identity_id,employee_name_snapshot,employee_role_snapshot,
                     movement_type,source_type,finance_operation_id,bank_transaction_id,note,created_by_user_id,created_by_role)
                 VALUES (?,?,?,?,?,'RETURN','EMPLOYEE',?,NULL,?,?,?)"
            );
            $movement->execute([
                $localUserId,
                (string)$employee['identity_type'],
                (int)$employee['identity_id'],
                (string)$employee['full_name'],
                (string)($employee['role_code'] ?? ''),
                $operationId,
                'Оплачено сотрудником за компанию: ' . ($normalized['counterparty_name'] ?: $normalized['purpose']),
                $uid > 0 ? $uid : null,
                $role,
            ]);
            $movementId = (int)$pdo->lastInsertId();

            // For direct events the economic operation is the only financial leg.
            // receipt_finance_operation_id deliberately points to the same canonical
            // operation so existing edit/cancel/history code remains backward compatible.
            $insertEvent = $pdo->prepare(
                "INSERT INTO finance_employee_personal_expenses
                    (event_group_id,employee_movement_id,receipt_finance_operation_id,expense_finance_operation_id,
                     operation_date,employee_identity_type,employee_identity_id,employee_name_snapshot,employee_role_snapshot,
                     amount,currency,cash_flow_center_id,cash_flow_center_name_snapshot,
                     dds_category_id,dds_category_name_snapshot,linear_route_id,
                     counterparty_name,purpose,comment,status,created_by_user_id,created_by_role)
                 VALUES (?,?,?,?,?,?,?,?,?,?,'RUR',?,?,?,?,?,?,?,?, 'POSTED',?,?)"
            );
            $insertEvent->execute([
                $eventGroup,
                $movementId,
                $operationId,
                $operationId,
                $normalized['operation_date'],
                (string)$employee['identity_type'],
                (int)$employee['identity_id'],
                (string)$employee['full_name'],
                (string)($employee['role_code'] ?? ''),
                $normalized['amount'],
                $normalized['cash_flow_center_id'],
                $normalized['cfu_name'],
                $normalized['dds_category_id'],
                $normalized['dds_name'],
                $normalized['linear_route_id'],
                $normalized['counterparty_name'],
                $normalized['purpose'],
                $normalized['comment'],
                $uid > 0 ? $uid : null,
                $role,
            ]);
            $eventId = (int)$pdo->lastInsertId();

            FinanceAuditLogService::log(
                $pdo,
                'finance_employee_personal_expense',
                $eventId,
                'direct_event_create',
                null,
                [
                    'event_group_id' => $eventGroup,
                    'employee_ref' => (string)$employee['ref'],
                    'amount' => $normalized['amount'],
                    'finance_operation_id' => $operationId,
                    'employee_movement_id' => $movementId,
                    'cash_account_used' => false,
                ],
                $uid,
                $role
            );

            if ($owns) {
                $pdo->commit();
            }

            return FinanceEmployeePersonalExpenseEventService::fetchOne($pdo, $eventId) ?? ['id' => $eventId];
        } catch (\Throwable $e) {
            if ($owns && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function normalizeAndValidate(PDO $pdo, array $data, array $employee): array
    {
        if (empty($employee['identity_type']) || (int)($employee['identity_id'] ?? 0) <= 0 || trim((string)($employee['full_name'] ?? '')) === '') {
            throw new \InvalidArgumentException('Выберите сотрудника.');
        }
        $amount = FinanceCashService::normalizeMoneyInput((string)($data['amount'] ?? ''));
        if ($amount === null) {
            throw new \InvalidArgumentException('Укажите корректную сумму расхода.');
        }
        $date = trim((string)($data['operation_date'] ?? ''));
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException('Укажите корректную дату.');
        }
        $cfuId = (int)($data['cash_flow_center_id'] ?? 0);
        $ddsId = (int)($data['dds_category_id'] ?? 0);
        if ($cfuId <= 0) {
            throw new \InvalidArgumentException('Выберите ЦФУ.');
        }
        if ($ddsId <= 0) {
            throw new \InvalidArgumentException('Выберите статью ДДС.');
        }
        FinanceStructureService::assertAllowedPair($pdo, $cfuId, $ddsId, 'EXPENSE');
        $stmt = $pdo->prepare(
            "SELECT cfu.name AS cfu_name, dds.name AS dds_name
               FROM finance_cash_flow_centers cfu
               JOIN finance_dds_categories dds ON dds.id=?
              WHERE cfu.id=? AND cfu.is_active=1 AND dds.is_active=1"
        );
        $stmt->execute([$ddsId, $cfuId]);
        $class = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$class) {
            throw new \InvalidArgumentException('Выбранные ЦФУ или статья ДДС неактивны.');
        }
        $purpose = trim((string)($data['purpose'] ?? ''));
        if ($purpose === '') {
            throw new \InvalidArgumentException('Укажите назначение расхода.');
        }
        $counterparty = trim((string)($data['counterparty_name'] ?? '')) ?: null;
        $comment = trim((string)($data['comment'] ?? '')) ?: null;
        $routeId = !empty($data['linear_route_id']) ? (int)$data['linear_route_id'] : null;
        if ($routeId !== null) {
            $route = $pdo->prepare('SELECT id FROM linear_routes WHERE id=? AND deleted_at IS NULL');
            $route->execute([$routeId]);
            if (!$route->fetchColumn()) {
                throw new \InvalidArgumentException('Выбранный рейс не найден или удалён.');
            }
        }
        return [
            'amount' => $amount,
            'operation_date' => $date,
            'cash_flow_center_id' => $cfuId,
            'dds_category_id' => $ddsId,
            'cfu_name' => (string)$class['cfu_name'],
            'dds_name' => (string)$class['dds_name'],
            'purpose' => $purpose,
            'counterparty_name' => $counterparty,
            'comment' => $comment,
            'linear_route_id' => $routeId,
        ];
    }
}
