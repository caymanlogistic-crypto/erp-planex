<?php

namespace App\Service;

use PDO;

/**
 * One employee-funded company expense = one linked event:
 * employee ledger RETURN + technical TRANSFER IN to Main Cash + economic CASH EXPENSE.
 * The two cash legs have the same amount/date, so Main Cash net effect is zero.
 */
final class FinanceEmployeePersonalExpenseEventService
{
    public const RESOLUTION_TYPE = 'EMPLOYEE_PERSONAL_EXPENSE';

    public static function create(PDO $pdo, array $data, array $user, array $employee): array
    {
        $normalized = self::normalizeAndValidate($pdo, $data, $employee);
        $owns = !$pdo->inTransaction();
        if ($owns) $pdo->beginTransaction();
        try {
            $mainCash = FinanceCashResolutionService::findMainCashAccount($pdo, true);
            if (!$mainCash) throw new \RuntimeException('Основная касса не найдена или неактивна.');
            $eventGroup = 'EMP_PERSONAL_' . bin2hex(random_bytes(12));
            $employeeName = (string)$employee['full_name'];
            $receiptPurpose = 'Получено от сотрудника: ' . $employeeName . ' · оплата расхода компании из личных средств';

            $receiptId = self::insertTechnicalReceipt($pdo, (int)$mainCash['id'], $eventGroup, $normalized, $employee, $user, $receiptPurpose);
            $movementId = self::insertEmployeeReturn($pdo, $receiptId, $employee, $user, $normalized, $receiptPurpose);

            $expenseId = FinanceCashService::createCashOperation($pdo, [
                'operation_type' => 'EXPENSE',
                'money_account_id' => (int)$mainCash['id'],
                'amount' => $normalized['amount'],
                'operation_date' => $normalized['operation_date'],
                'dds_category_id' => $normalized['dds_category_id'],
                'purpose' => $normalized['purpose'],
                'comment' => $normalized['comment'],
            ], self::cashUser($user));
            $expenseUpdate = $pdo->prepare('UPDATE finance_operations SET cash_flow_center_id=?, counterparty_name=?, linear_route_id=?, transfer_group_id=? WHERE id=?');
            $expenseUpdate->execute([
                $normalized['cash_flow_center_id'],
                $normalized['counterparty_name'],
                $normalized['linear_route_id'],
                $eventGroup,
                $expenseId,
            ]);

            $insert = $pdo->prepare("INSERT INTO finance_employee_personal_expenses
                (event_group_id, employee_movement_id, receipt_finance_operation_id, expense_finance_operation_id,
                 operation_date, employee_identity_type, employee_identity_id, employee_name_snapshot, employee_role_snapshot,
                 amount, currency, cash_flow_center_id, cash_flow_center_name_snapshot,
                 dds_category_id, dds_category_name_snapshot, linear_route_id,
                 counterparty_name, purpose, comment, status, created_by_user_id, created_by_role)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'RUR', ?, ?, ?, ?, ?, ?, ?, ?, 'POSTED', ?, ?)");
            $insert->execute([
                $eventGroup, $movementId, $receiptId, $expenseId,
                $normalized['operation_date'], (string)$employee['identity_type'], (int)$employee['identity_id'],
                $employeeName, (string)($employee['role_code'] ?? ''), $normalized['amount'],
                $normalized['cash_flow_center_id'], $normalized['cfu_name'],
                $normalized['dds_category_id'], $normalized['dds_name'], $normalized['linear_route_id'],
                $normalized['counterparty_name'], $normalized['purpose'], $normalized['comment'],
                self::userId($user) ?: null, self::role($user),
            ]);
            $eventId = (int)$pdo->lastInsertId();

            $resolution = $pdo->prepare("INSERT INTO finance_cash_resolutions
                (source_finance_operation_id, resolution_type, target_identity_type, target_identity_id,
                 target_name_snapshot, outflow_finance_operation_id, employee_movement_id,
                 created_by_user_id, created_by_role)
                VALUES (?, ?, 'EMPLOYEE_PERSONAL_EXPENSE', ?, ?, NULL, ?, ?, ?)");
            $resolution->execute([
                $receiptId,
                self::RESOLUTION_TYPE,
                $eventId,
                $normalized['counterparty_name'] ?: $normalized['purpose'],
                $movementId,
                self::userId($user) ?: null,
                self::role($user),
            ]);
            $resolutionId = (int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE finance_employee_personal_expenses SET cash_resolution_id=? WHERE id=?')->execute([$resolutionId, $eventId]);

            FinanceAuditLogService::log($pdo, 'finance_employee_personal_expense', $eventId, 'linked_event_create', null, [
                'event_group_id' => $eventGroup,
                'employee_ref' => (string)$employee['ref'],
                'amount' => $normalized['amount'],
                'receipt_finance_operation_id' => $receiptId,
                'expense_finance_operation_id' => $expenseId,
                'employee_movement_id' => $movementId,
                'cash_net_effect' => '0.00',
            ], self::userId($user), self::role($user));

            if ($owns) $pdo->commit();
            return self::fetchOne($pdo, $eventId) ?? ['id' => $eventId];
        } catch (\Throwable $e) {
            if ($owns && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function update(PDO $pdo, int $eventId, array $data, array $user): array
    {
        if ($eventId <= 0) throw new \InvalidArgumentException('Операция не найдена.');
        $owns = !$pdo->inTransaction();
        if ($owns) $pdo->beginTransaction();
        try {
            $event = self::fetchOneForUpdate($pdo, $eventId);
            if (!$event) throw new \InvalidArgumentException('Операция не найдена.');
            if (($event['status'] ?? '') !== 'POSTED') throw new \RuntimeException('Отменённую операцию нельзя изменять.');
            self::assertLinked($event);
            $employee = [
                'identity_type' => (string)$event['employee_identity_type'],
                'identity_id' => (int)$event['employee_identity_id'],
                'full_name' => (string)$event['employee_name_snapshot'],
                'role_code' => (string)$event['employee_role_snapshot'],
                'ref' => FinanceEmployeePaymentService::makeEmployeeRef((string)$event['employee_identity_type'], (int)$event['employee_identity_id']),
            ];
            $normalized = self::normalizeAndValidate($pdo, $data, $employee);
            $before = self::auditSnapshot($event);
            $receiptPurpose = 'Получено от сотрудника: ' . (string)$event['employee_name_snapshot'] . ' · оплата расхода компании из личных средств';

            $receipt = $pdo->prepare("UPDATE finance_operations
                SET operation_date=?, amount=?, purpose=?, comment=?, counterparty_name=?
                WHERE id=? AND status='POSTED'");
            $receipt->execute([
                $normalized['operation_date'], $normalized['amount'], $receiptPurpose, $normalized['comment'],
                (string)$event['employee_name_snapshot'], (int)$event['receipt_finance_operation_id'],
            ]);
            if ($receipt->rowCount() === 0) self::assertPostedOperation($pdo, (int)$event['receipt_finance_operation_id']);

            $expense = $pdo->prepare("UPDATE finance_operations
                SET operation_date=?, amount=?, dds_category_id=?, cash_flow_center_id=?, counterparty_name=?,
                    linear_route_id=?, purpose=?, comment=?
                WHERE id=? AND status='POSTED'");
            $expense->execute([
                $normalized['operation_date'], $normalized['amount'], $normalized['dds_category_id'], $normalized['cash_flow_center_id'],
                $normalized['counterparty_name'], $normalized['linear_route_id'], $normalized['purpose'], $normalized['comment'],
                (int)$event['expense_finance_operation_id'],
            ]);
            if ($expense->rowCount() === 0) self::assertPostedOperation($pdo, (int)$event['expense_finance_operation_id']);

            $pdo->prepare('UPDATE finance_employee_movements SET note=? WHERE id=?')->execute([
                'Оплачено сотрудником за компанию: ' . ($normalized['counterparty_name'] ?: $normalized['purpose']),
                (int)$event['employee_movement_id'],
            ]);
            $pdo->prepare("UPDATE finance_employee_personal_expenses SET
                    operation_date=?, amount=?, cash_flow_center_id=?, cash_flow_center_name_snapshot=?,
                    dds_category_id=?, dds_category_name_snapshot=?, linear_route_id=?, counterparty_name=?, purpose=?, comment=?
                WHERE id=?")->execute([
                $normalized['operation_date'], $normalized['amount'], $normalized['cash_flow_center_id'], $normalized['cfu_name'],
                $normalized['dds_category_id'], $normalized['dds_name'], $normalized['linear_route_id'],
                $normalized['counterparty_name'], $normalized['purpose'], $normalized['comment'], $eventId,
            ]);
            if (!empty($event['cash_resolution_id'])) {
                $pdo->prepare('UPDATE finance_cash_resolutions SET target_name_snapshot=? WHERE id=?')->execute([
                    $normalized['counterparty_name'] ?: $normalized['purpose'], (int)$event['cash_resolution_id'],
                ]);
            }

            $after = self::fetchOne($pdo, $eventId);
            FinanceAuditLogService::log($pdo, 'finance_employee_personal_expense', $eventId, 'linked_event_update', $before, self::auditSnapshot($after ?: []), self::userId($user), self::role($user));
            if ($owns) $pdo->commit();
            return $after ?: ['id' => $eventId];
        } catch (\Throwable $e) {
            if ($owns && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function cancel(PDO $pdo, int $eventId, array $user, ?string $reason = null): array
    {
        if ($eventId <= 0) throw new \InvalidArgumentException('Операция не найдена.');
        $owns = !$pdo->inTransaction();
        if ($owns) $pdo->beginTransaction();
        try {
            $event = self::fetchOneForUpdate($pdo, $eventId);
            if (!$event) throw new \InvalidArgumentException('Операция не найдена.');
            if (($event['status'] ?? '') === 'CANCELLED') {
                if ($owns) $pdo->commit();
                return $event;
            }
            self::assertLinked($event);
            $reason = trim((string)$reason) ?: 'Отменено из раздела «Выплаты сотрудникам»';
            $uid = self::userId($user) ?: null;
            $role = self::role($user);

            $cancelOperation = $pdo->prepare("UPDATE finance_operations
                SET status='CANCELLED', cancelled_at=NOW(), cancelled_by_user_id=?, cancelled_by_role=?, cancellation_reason=?
                WHERE id=? AND status='POSTED'");
            foreach ([(int)$event['receipt_finance_operation_id'], (int)$event['expense_finance_operation_id']] as $operationId) {
                $cancelOperation->execute([$uid, $role, $reason, $operationId]);
                if ($cancelOperation->rowCount() === 0) self::assertCancelledOrPostedOperation($pdo, $operationId);
            }

            $pdo->prepare("UPDATE finance_employee_personal_expenses
                SET status='CANCELLED', cancelled_at=NOW(), cancelled_by_user_id=?, cancelled_by_role=?, cancellation_reason=?
                WHERE id=?")->execute([$uid, $role, $reason, $eventId]);

            $after = self::fetchOne($pdo, $eventId);
            FinanceAuditLogService::log($pdo, 'finance_employee_personal_expense', $eventId, 'linked_event_cancel', self::auditSnapshot($event), self::auditSnapshot($after ?: []), self::userId($user), $role);
            if ($owns) $pdo->commit();
            return $after ?: ['id' => $eventId, 'status' => 'CANCELLED'];
        } catch (\Throwable $e) {
            if ($owns && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function fetchForEmployee(PDO $pdo, string $employeeRef, int $limit = 100): array
    {
        [$type, $id] = FinanceEmployeePaymentService::parseEmployeeRef($employeeRef);
        $limit = max(1, min(200, $limit));
        $stmt = $pdo->prepare("SELECT pe.*, COALESCE(cfu.name, pe.cash_flow_center_name_snapshot) AS cfu_name,
                    COALESCE(dds.name, pe.dds_category_name_snapshot) AS dds_name, c.name AS route_client_name
                FROM finance_employee_personal_expenses pe
                LEFT JOIN finance_cash_flow_centers cfu ON cfu.id=pe.cash_flow_center_id
                LEFT JOIN finance_dds_categories dds ON dds.id=pe.dds_category_id
                LEFT JOIN linear_routes lr ON lr.id=pe.linear_route_id
                LEFT JOIN clients c ON c.id=lr.client_id
                WHERE pe.employee_identity_type=? AND pe.employee_identity_id=?
                  AND pe.event_group_id IS NOT NULL
                ORDER BY pe.operation_date DESC, pe.id DESC LIMIT :limit");
        $stmt->bindValue(1, $type);
        $stmt->bindValue(2, $id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function fetchOne(PDO $pdo, int $eventId): ?array
    {
        $stmt = $pdo->prepare("SELECT pe.*, COALESCE(cfu.name, pe.cash_flow_center_name_snapshot) AS cfu_name,
                    COALESCE(dds.name, pe.dds_category_name_snapshot) AS dds_name
                FROM finance_employee_personal_expenses pe
                LEFT JOIN finance_cash_flow_centers cfu ON cfu.id=pe.cash_flow_center_id
                LEFT JOIN finance_dds_categories dds ON dds.id=pe.dds_category_id
                WHERE pe.id=? LIMIT 1");
        $stmt->execute([$eventId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function fetchOneForUpdate(PDO $pdo, int $eventId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM finance_employee_personal_expenses WHERE id=? FOR UPDATE');
        $stmt->execute([$eventId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function normalizeAndValidate(PDO $pdo, array $data, array $employee): array
    {
        if (empty($employee['identity_type']) || (int)($employee['identity_id'] ?? 0) <= 0 || trim((string)($employee['full_name'] ?? '')) === '') {
            throw new \InvalidArgumentException('Выберите сотрудника.');
        }
        $amount = FinanceCashService::normalizeMoneyInput((string)($data['amount'] ?? ''));
        if ($amount === null) throw new \InvalidArgumentException('Укажите корректную сумму расхода.');
        $date = trim((string)($data['operation_date'] ?? ''));
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new \InvalidArgumentException('Укажите корректную дату.');
        $cfuId = (int)($data['cash_flow_center_id'] ?? 0);
        $ddsId = (int)($data['dds_category_id'] ?? 0);
        if ($cfuId <= 0) throw new \InvalidArgumentException('Выберите ЦФУ.');
        if ($ddsId <= 0) throw new \InvalidArgumentException('Выберите статью ДДС.');
        FinanceStructureService::assertAllowedPair($pdo, $cfuId, $ddsId, 'EXPENSE');
        $stmt = $pdo->prepare("SELECT cfu.name AS cfu_name, dds.name AS dds_name
            FROM finance_cash_flow_centers cfu JOIN finance_dds_categories dds ON dds.id=?
            WHERE cfu.id=? AND cfu.is_active=1 AND dds.is_active=1");
        $stmt->execute([$ddsId, $cfuId]);
        $class = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$class) throw new \InvalidArgumentException('Выбранные ЦФУ или статья ДДС неактивны.');
        $purpose = trim((string)($data['purpose'] ?? ''));
        if ($purpose === '') throw new \InvalidArgumentException('Укажите назначение расхода.');
        $counterparty = trim((string)($data['counterparty_name'] ?? '')) ?: null;
        $comment = trim((string)($data['comment'] ?? '')) ?: null;
        $routeId = !empty($data['linear_route_id']) ? (int)$data['linear_route_id'] : null;
        if ($routeId !== null) {
            $r = $pdo->prepare('SELECT id FROM linear_routes WHERE id=? AND deleted_at IS NULL');
            $r->execute([$routeId]);
            if (!$r->fetchColumn()) throw new \InvalidArgumentException('Выбранный рейс не найден или удалён.');
        }
        return [
            'amount'=>$amount, 'operation_date'=>$date, 'cash_flow_center_id'=>$cfuId, 'dds_category_id'=>$ddsId,
            'cfu_name'=>(string)$class['cfu_name'], 'dds_name'=>(string)$class['dds_name'], 'purpose'=>$purpose,
            'counterparty_name'=>$counterparty, 'comment'=>$comment, 'linear_route_id'=>$routeId,
        ];
    }

    private static function insertTechnicalReceipt(PDO $pdo, int $cashId, string $group, array $data, array $employee, array $user, string $purpose): int
    {
        $stmt = $pdo->prepare("INSERT INTO finance_operations
            (operation_type,status,source,money_account_id,transfer_account_id,transfer_group_id,transfer_direction,
             operation_date,amount,currency,counterparty_name,purpose,comment,
             created_by_user_id,created_by_role,posted_by_user_id,posted_by_role,posted_at)
            VALUES ('TRANSFER','POSTED','TRANSFER',?,NULL,?,'in',?,?,'RUR',?,?,?,?,?,?,?,NOW())");
        $uid = self::userId($user) ?: null; $role = self::role($user);
        $stmt->execute([$cashId,$group,$data['operation_date'],$data['amount'],(string)$employee['full_name'],$purpose,$data['comment'],$uid,$role,$uid,$role]);
        return (int)$pdo->lastInsertId();
    }

    private static function insertEmployeeReturn(PDO $pdo, int $operationId, array $employee, array $user, array $data, string $receiptPurpose): int
    {
        $localUserId = (string)$employee['identity_type'] === FinanceEmployeePaymentService::IDENTITY_TENANT_USER ? (int)$employee['identity_id'] : null;
        $stmt = $pdo->prepare("INSERT INTO finance_employee_movements
            (employee_user_id,employee_identity_type,employee_identity_id,employee_name_snapshot,employee_role_snapshot,
             movement_type,source_type,finance_operation_id,bank_transaction_id,note,created_by_user_id,created_by_role)
            VALUES (?,?,?,?,?,'RETURN','CASH',?,NULL,?,?,?)");
        $stmt->execute([$localUserId,(string)$employee['identity_type'],(int)$employee['identity_id'],(string)$employee['full_name'],
            (string)($employee['role_code']??''),$operationId,'Оплачено сотрудником за компанию: '.($data['counterparty_name'] ?: $data['purpose']),self::userId($user)?:null,self::role($user)]);
        return (int)$pdo->lastInsertId();
    }

    private static function assertLinked(array $event): void
    {
        foreach (['employee_movement_id','receipt_finance_operation_id','expense_finance_operation_id'] as $key) {
            if ((int)($event[$key] ?? 0) <= 0) throw new \RuntimeException('Операция создана в старом формате и не поддерживает связанное редактирование.');
        }
    }

    private static function assertPostedOperation(PDO $pdo, int $id): void
    {
        $stmt=$pdo->prepare('SELECT status FROM finance_operations WHERE id=?');$stmt->execute([$id]);
        if ($stmt->fetchColumn() !== 'POSTED') throw new \RuntimeException('Связанная кассовая операция недоступна для изменения.');
    }

    private static function assertCancelledOrPostedOperation(PDO $pdo, int $id): void
    {
        $stmt=$pdo->prepare('SELECT status FROM finance_operations WHERE id=?');$stmt->execute([$id]);$status=$stmt->fetchColumn();
        if (!in_array($status,['POSTED','CANCELLED'],true)) throw new \RuntimeException('Связанная кассовая операция имеет некорректный статус.');
    }

    private static function auditSnapshot(array $row): array
    {
        return array_intersect_key($row, array_flip(['status','operation_date','amount','cash_flow_center_id','dds_category_id','linear_route_id','counterparty_name','purpose','comment','event_group_id','employee_movement_id','receipt_finance_operation_id','expense_finance_operation_id']));
    }

    private static function cashUser(array $user): array { return ['id'=>self::userId($user)?:null,'role'=>self::role($user)]; }
    private static function userId(array $user): int { return (int)($user['user_id'] ?? $user['id'] ?? 0); }
    private static function role(array $user): string { return (string)($user['role_code'] ?? $user['role'] ?? 'company_owner'); }
}
