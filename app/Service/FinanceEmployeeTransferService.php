<?php

namespace App\Service;

use PDO;

/**
 * Internal employee-to-employee money handoff.
 *
 * The handoff is represented as one technical cash lifecycle:
 * employee A -> Основная касса -> employee B.
 * Both employee balances move, while company income/expense does not.
 */
final class FinanceEmployeeTransferService
{
    /**
     * @return array{amount:string,source_employee:array,target_employee:array,source_movement_id:int,target_movement_id:int,source_finance_operation_id:int,target_finance_operation_id:int,resolution_id:int,transfer_group_id:string}
     */
    public static function transfer(
        PDO $localPdo,
        PDO $centralPdo,
        int $companyId,
        array $data,
        array $user
    ): array {
        $sourceRef = trim((string)($data['source_employee_ref'] ?? ''));
        $targetRef = trim((string)($data['target_employee_ref'] ?? ''));
        if ($sourceRef === '' || $targetRef === '') {
            throw new \InvalidArgumentException('Выберите сотрудника-отправителя и сотрудника-получателя.');
        }
        if ($sourceRef === $targetRef) {
            throw new \InvalidArgumentException('Нельзя передать деньги самому себе.');
        }

        $amountCents = self::toCents((string)($data['amount'] ?? ''));
        if ($amountCents <= 0) {
            throw new \InvalidArgumentException('Сумма перевода должна быть больше нуля.');
        }
        $amount = self::fromCents($amountCents);

        $operationDate = trim((string)($data['operation_date'] ?? ''));
        self::assertOperationDate($operationDate);

        $sourceEmployee = FinanceEmployeePaymentService::resolveActiveEmployee(
            $localPdo,
            $centralPdo,
            $companyId,
            $sourceRef
        );
        $targetEmployee = FinanceEmployeePaymentService::resolveActiveEmployee(
            $localPdo,
            $centralPdo,
            $companyId,
            $targetRef
        );

        $purpose = trim((string)($data['purpose'] ?? ''));
        if ($purpose === '') {
            $purpose = self::defaultPurpose($sourceEmployee, $targetEmployee);
        }
        $comment = trim((string)($data['comment'] ?? ''));
        $technicalComment = self::technicalComment($comment);

        [$userId, $userRole] = self::actor($user);

        $started = !$localPdo->inTransaction();
        if ($started) {
            $localPdo->beginTransaction();
        }

        try {
            $mainCash = FinanceCashResolutionService::findMainCashAccount($localPdo, true);
            if (!$mainCash) {
                throw new \RuntimeException('Техническая касса «' . FinanceCashResolutionService::MAIN_CASH_NAME . '» не найдена или неактивна.');
            }
            $mainCashId = (int)$mainCash['id'];

            // Deliberately do not limit the transfer by the sender's current balance.
            // Employee settlements are allowed to become negative: the transfer is an
            // accounting responsibility handoff, not a physical cash availability check.

            // First leg: employee A returns the money into the technical main cash.
            $sourceMovement = FinanceEmployeePaymentService::createCashMovement(
                $localPdo,
                [
                    'movement_type' => 'RETURN',
                    'money_account_id' => $mainCashId,
                    'amount' => $amount,
                    'operation_date' => $operationDate,
                    'purpose' => $purpose,
                    'comment' => $technicalComment,
                ],
                ['id' => $userId, 'role' => $userRole],
                $sourceEmployee
            );

            // Second leg: the same amount leaves the technical main cash to employee B.
            $targetMovement = FinanceEmployeePaymentService::createCashMovement(
                $localPdo,
                [
                    'movement_type' => 'PAYMENT',
                    'money_account_id' => $mainCashId,
                    'amount' => $amount,
                    'operation_date' => $operationDate,
                    'purpose' => $purpose,
                    'comment' => $technicalComment,
                ],
                ['id' => $userId, 'role' => $userRole],
                $targetEmployee
            );

            $sourceOperationId = (int)$sourceMovement['finance_operation_id'];
            $targetOperationId = (int)$targetMovement['finance_operation_id'];
            $groupId = 'EMPLOYEE-' . date('YmdHis') . '-' . bin2hex(random_bytes(8));

            // This handoff changes employee balances only. Convert both cash legs
            // to technical TRANSFER rows before commit so they never become
            // company income/expense in DDS reporting.
            $technicalize = $localPdo->prepare(
                "UPDATE finance_operations
                    SET operation_type = 'TRANSFER',
                        source = 'TRANSFER',
                        transfer_account_id = NULL,
                        transfer_group_id = ?,
                        transfer_direction = ?
                  WHERE id = ? AND status = 'POSTED'"
            );
            $technicalize->execute([$groupId, 'in', $sourceOperationId]);
            if ($technicalize->rowCount() !== 1) {
                throw new \RuntimeException('Не удалось сформировать входящую часть перевода сотрудника.');
            }
            $technicalize->execute([$groupId, 'out', $targetOperationId]);
            if ($technicalize->rowCount() !== 1) {
                throw new \RuntimeException('Не удалось сформировать исходящую часть перевода сотрудника.');
            }

            $insertResolution = $localPdo->prepare(
                "INSERT INTO finance_cash_resolutions
                    (source_finance_operation_id, resolution_type,
                     target_identity_type, target_identity_id, target_name_snapshot,
                     outflow_finance_operation_id, employee_movement_id,
                     created_by_user_id, created_by_role)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $insertResolution->execute([
                $sourceOperationId,
                FinanceCashResolutionService::RESOLUTION_EMPLOYEE,
                (string)$targetEmployee['identity_type'],
                (int)$targetEmployee['identity_id'],
                (string)$targetEmployee['full_name'],
                $targetOperationId,
                (int)$targetMovement['movement_id'],
                $userId,
                $userRole,
            ]);
            $resolutionId = (int)$localPdo->lastInsertId();

            FinanceAuditLogService::log(
                $localPdo,
                'finance_operation',
                $sourceOperationId,
                'employee_transfer',
                ['operation_type' => 'INCOME', 'employee_ref' => $sourceRef],
                [
                    'operation_type' => 'TRANSFER',
                    'transfer_direction' => 'in',
                    'transfer_group_id' => $groupId,
                    'target_employee_ref' => $targetRef,
                    'amount' => $amount,
                ],
                $userId,
                $userRole
            );
            FinanceAuditLogService::log(
                $localPdo,
                'finance_operation',
                $targetOperationId,
                'employee_transfer',
                ['operation_type' => 'EXPENSE', 'employee_ref' => $targetRef],
                [
                    'operation_type' => 'TRANSFER',
                    'transfer_direction' => 'out',
                    'transfer_group_id' => $groupId,
                    'source_employee_ref' => $sourceRef,
                    'amount' => $amount,
                ],
                $userId,
                $userRole
            );

            if ($started) {
                $localPdo->commit();
            }

            return [
                'amount' => $amount,
                'source_employee' => $sourceEmployee,
                'target_employee' => $targetEmployee,
                'source_movement_id' => (int)$sourceMovement['movement_id'],
                'target_movement_id' => (int)$targetMovement['movement_id'],
                'source_finance_operation_id' => $sourceOperationId,
                'target_finance_operation_id' => $targetOperationId,
                'resolution_id' => $resolutionId,
                'transfer_group_id' => $groupId,
            ];
        } catch (\Throwable $e) {
            if ($started && $localPdo->inTransaction()) {
                $localPdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Adds edit metadata to active employee-to-employee transfer rows and removes
     * transfers that were soft-deleted (cancelled) from the employee report.
     */
    public static function decorateLedger(PDO $localPdo, array $ledger): array
    {
        if ($ledger === []) {
            return [];
        }

        $operationIds = [];
        foreach ($ledger as $row) {
            $operationId = (int)($row['finance_operation_id'] ?? 0);
            if ($operationId > 0) {
                $operationIds[$operationId] = $operationId;
            }
        }
        if ($operationIds === []) {
            return $ledger;
        }

        $placeholders = implode(',', array_fill(0, count($operationIds), '?'));
        $stmt = $localPdo->prepare(
            "SELECT id, transfer_group_id
               FROM finance_operations
              WHERE id IN ({$placeholders})
                AND operation_type = 'TRANSFER'
                AND source = 'TRANSFER'
                AND transfer_group_id LIKE 'EMPLOYEE-%'"
        );
        $stmt->execute(array_values($operationIds));

        $operationToGroup = [];
        $groups = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $groupId = (string)($row['transfer_group_id'] ?? '');
            if ($groupId === '') {
                continue;
            }
            $operationToGroup[(int)$row['id']] = $groupId;
            $groups[$groupId] = true;
        }
        if ($groups === []) {
            return $ledger;
        }

        $detailsByGroup = [];
        foreach (array_keys($groups) as $groupId) {
            try {
                $group = self::loadGroup($localPdo, $groupId, false);
            } catch (\Throwable) {
                continue;
            }

            $source = $group['source'];
            $target = $group['target'];
            $bothCancelled = ($source['status'] ?? '') === 'CANCELLED' && ($target['status'] ?? '') === 'CANCELLED';
            $bothPosted = ($source['status'] ?? '') === 'POSTED' && ($target['status'] ?? '') === 'POSTED';

            $detailsByGroup[$groupId] = [
                'deleted' => $bothCancelled,
                'editable' => $bothPosted,
                'data' => $bothPosted ? [
                    'transfer_group_id' => $groupId,
                    'display_id' => (int)$source['finance_operation_id'],
                    'source_employee_ref' => self::employeeRef($source),
                    'source_employee_name' => (string)$source['employee_name_snapshot'],
                    'target_employee_ref' => self::employeeRef($target),
                    'target_employee_name' => (string)$target['employee_name_snapshot'],
                    'operation_date' => (string)$source['operation_date'],
                    'amount' => (string)$source['amount'],
                    'purpose' => self::editablePurpose((string)($source['purpose'] ?? ''), $source, $target),
                    'comment' => self::userComment((string)($source['comment'] ?? '')),
                ] : null,
            ];
        }

        $result = [];
        foreach ($ledger as $row) {
            $operationId = (int)($row['finance_operation_id'] ?? 0);
            $groupId = $operationToGroup[$operationId] ?? null;
            if ($groupId !== null && isset($detailsByGroup[$groupId])) {
                $details = $detailsByGroup[$groupId];
                if ($details['deleted']) {
                    continue;
                }
                if ($details['editable']) {
                    $row['employee_transfer'] = $details['data'];
                }
            }
            $result[] = $row;
        }

        return $result;
    }

    /**
     * Edit all user-facing fields of an employee-to-employee transfer atomically.
     */
    public static function updateTransfer(
        PDO $localPdo,
        PDO $centralPdo,
        int $companyId,
        string $groupId,
        array $data,
        array $user
    ): array {
        self::assertGroupId($groupId);
        [$userId, $userRole] = self::actor($user);

        $sourceRef = trim((string)($data['source_employee_ref'] ?? ''));
        $targetRef = trim((string)($data['target_employee_ref'] ?? ''));
        if ($sourceRef === '' || $targetRef === '') {
            throw new \InvalidArgumentException('Выберите сотрудника-отправителя и сотрудника-получателя.');
        }
        if ($sourceRef === $targetRef) {
            throw new \InvalidArgumentException('Нельзя передать деньги самому себе.');
        }

        $amountCents = self::toCents((string)($data['amount'] ?? ''));
        if ($amountCents <= 0) {
            throw new \InvalidArgumentException('Сумма перевода должна быть больше нуля.');
        }
        $amount = self::fromCents($amountCents);

        $operationDate = trim((string)($data['operation_date'] ?? ''));
        self::assertOperationDate($operationDate);

        $sourceEmployee = FinanceEmployeePaymentService::resolveActiveEmployee($localPdo, $centralPdo, $companyId, $sourceRef);
        $targetEmployee = FinanceEmployeePaymentService::resolveActiveEmployee($localPdo, $centralPdo, $companyId, $targetRef);

        $purpose = trim((string)($data['purpose'] ?? ''));
        if ($purpose === '') {
            $purpose = self::defaultPurpose($sourceEmployee, $targetEmployee);
        }
        $comment = trim((string)($data['comment'] ?? ''));
        $technicalComment = self::technicalComment($comment);

        $started = !$localPdo->inTransaction();
        if ($started) {
            $localPdo->beginTransaction();
        }

        try {
            $group = self::loadGroup($localPdo, $groupId, true);
            $source = $group['source'];
            $target = $group['target'];

            if (($source['status'] ?? '') !== 'POSTED' || ($target['status'] ?? '') !== 'POSTED') {
                throw new \RuntimeException('Редактировать можно только действующую передачу денег.');
            }

            $old = [
                'source_employee_ref' => self::employeeRef($source),
                'target_employee_ref' => self::employeeRef($target),
                'operation_date' => (string)$source['operation_date'],
                'amount' => (string)$source['amount'],
                'purpose' => (string)($source['purpose'] ?? ''),
                'comment' => self::userComment((string)($source['comment'] ?? '')),
            ];

            self::updateMovementEmployee(
                $localPdo,
                (int)$source['movement_id'],
                $sourceEmployee,
                $technicalComment
            );
            self::updateMovementEmployee(
                $localPdo,
                (int)$target['movement_id'],
                $targetEmployee,
                $technicalComment
            );

            $updateOperation = $localPdo->prepare(
                "UPDATE finance_operations
                    SET operation_date = ?, amount = ?, purpose = ?, comment = ?
                  WHERE id = ?
                    AND operation_type = 'TRANSFER'
                    AND source = 'TRANSFER'
                    AND status = 'POSTED'"
            );
            foreach ([(int)$source['finance_operation_id'], (int)$target['finance_operation_id']] as $operationId) {
                $updateOperation->execute([$operationDate, $amount, $purpose, $technicalComment, $operationId]);
            }

            $resolution = $localPdo->prepare(
                "UPDATE finance_cash_resolutions
                    SET target_identity_type = ?, target_identity_id = ?, target_name_snapshot = ?
                  WHERE source_finance_operation_id = ?
                    AND outflow_finance_operation_id = ?"
            );
            $resolution->execute([
                (string)$targetEmployee['identity_type'],
                (int)$targetEmployee['identity_id'],
                (string)$targetEmployee['full_name'],
                (int)$source['finance_operation_id'],
                (int)$target['finance_operation_id'],
            ]);

            $new = [
                'source_employee_ref' => $sourceRef,
                'target_employee_ref' => $targetRef,
                'operation_date' => $operationDate,
                'amount' => $amount,
                'purpose' => $purpose,
                'comment' => $comment,
            ];
            foreach ([(int)$source['finance_operation_id'], (int)$target['finance_operation_id']] as $operationId) {
                FinanceAuditLogService::log(
                    $localPdo,
                    'finance_operation',
                    $operationId,
                    'employee_transfer_edit',
                    $old,
                    $new,
                    $userId,
                    $userRole
                );
            }

            if ($started) {
                $localPdo->commit();
            }

            return [
                'amount' => $amount,
                'source_employee' => $sourceEmployee,
                'target_employee' => $targetEmployee,
                'source_movement_id' => (int)$source['movement_id'],
                'target_movement_id' => (int)$target['movement_id'],
                'source_finance_operation_id' => (int)$source['finance_operation_id'],
                'target_finance_operation_id' => (int)$target['finance_operation_id'],
                'transfer_group_id' => $groupId,
            ];
        } catch (\Throwable $e) {
            if ($started && $localPdo->inTransaction()) {
                $localPdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Soft-delete an employee transfer. Both technical legs are cancelled atomically;
     * the employee report hides the cancelled transfer while the audit trail remains.
     */
    public static function deleteTransfer(PDO $localPdo, string $groupId, array $user): array
    {
        self::assertGroupId($groupId);
        [$userId, $userRole] = self::actor($user);

        $started = !$localPdo->inTransaction();
        if ($started) {
            $localPdo->beginTransaction();
        }

        try {
            $group = self::loadGroup($localPdo, $groupId, true);
            $source = $group['source'];
            $target = $group['target'];

            if (($source['status'] ?? '') === 'CANCELLED' && ($target['status'] ?? '') === 'CANCELLED') {
                throw new \RuntimeException('Передача денег уже удалена.');
            }
            if (($source['status'] ?? '') !== 'POSTED' || ($target['status'] ?? '') !== 'POSTED') {
                throw new \RuntimeException('Удалить можно только действующую передачу денег.');
            }

            $cancel = $localPdo->prepare(
                "UPDATE finance_operations
                    SET status = 'CANCELLED', cancelled_at = NOW()
                  WHERE id = ?
                    AND operation_type = 'TRANSFER'
                    AND source = 'TRANSFER'
                    AND status = 'POSTED'"
            );

            foreach ([$source, $target] as $leg) {
                $cancel->execute([(int)$leg['finance_operation_id']]);
                if ($cancel->rowCount() !== 1) {
                    throw new \RuntimeException('Не удалось удалить передачу денег целиком.');
                }
                FinanceAuditLogService::log(
                    $localPdo,
                    'finance_operation',
                    (int)$leg['finance_operation_id'],
                    'employee_transfer_delete',
                    [
                        'status' => 'POSTED',
                        'transfer_group_id' => $groupId,
                        'amount' => (string)$leg['amount'],
                    ],
                    [
                        'status' => 'CANCELLED',
                        'transfer_group_id' => $groupId,
                    ],
                    $userId,
                    $userRole
                );
            }

            if ($started) {
                $localPdo->commit();
            }

            return [
                'transfer_group_id' => $groupId,
                'source_employee_ref' => self::employeeRef($source),
                'target_employee_ref' => self::employeeRef($target),
                'source_finance_operation_id' => (int)$source['finance_operation_id'],
                'target_finance_operation_id' => (int)$target['finance_operation_id'],
            ];
        } catch (\Throwable $e) {
            if ($started && $localPdo->inTransaction()) {
                $localPdo->rollBack();
            }
            throw $e;
        }
    }

    private static function loadGroup(PDO $localPdo, string $groupId, bool $forUpdate): array
    {
        self::assertGroupId($groupId);
        $sql = "SELECT fo.id AS finance_operation_id,
                       fo.status, fo.operation_date, fo.amount, fo.purpose, fo.comment,
                       fo.transfer_group_id, fo.transfer_direction, fo.money_account_id,
                       fem.id AS movement_id, fem.movement_type,
                       fem.employee_identity_type, fem.employee_identity_id,
                       fem.employee_name_snapshot, fem.employee_role_snapshot
                  FROM finance_operations fo
                  JOIN finance_employee_movements fem ON fem.finance_operation_id = fo.id
                 WHERE fo.transfer_group_id = ?
                   AND fo.operation_type = 'TRANSFER'
                   AND fo.source = 'TRANSFER'
                 ORDER BY fo.id ASC";
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $localPdo->prepare($sql);
        $stmt->execute([$groupId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== 2) {
            throw new \RuntimeException('Передача денег повреждена: ожидаются две связанные операции.');
        }

        $source = null;
        $target = null;
        foreach ($rows as $row) {
            if (($row['movement_type'] ?? '') === 'RETURN' && ($row['transfer_direction'] ?? '') === 'in') {
                $source = $row;
            } elseif (($row['movement_type'] ?? '') === 'PAYMENT' && ($row['transfer_direction'] ?? '') === 'out') {
                $target = $row;
            }
        }
        if (!$source || !$target) {
            throw new \RuntimeException('Передача денег повреждена: не удалось определить отправителя и получателя.');
        }
        if ((int)$source['money_account_id'] !== (int)$target['money_account_id']) {
            throw new \RuntimeException('Передача денег повреждена: технические операции относятся к разным кассам.');
        }

        return ['source' => $source, 'target' => $target];
    }

    private static function updateMovementEmployee(PDO $localPdo, int $movementId, array $employee, string $note): void
    {
        $localUserId = ($employee['identity_type'] ?? '') === FinanceEmployeePaymentService::IDENTITY_TENANT_USER
            ? (int)$employee['identity_id']
            : null;
        $stmt = $localPdo->prepare(
            "UPDATE finance_employee_movements
                SET employee_user_id = ?,
                    employee_identity_type = ?,
                    employee_identity_id = ?,
                    employee_name_snapshot = ?,
                    employee_role_snapshot = ?,
                    note = ?
              WHERE id = ?"
        );
        $stmt->execute([
            $localUserId,
            (string)$employee['identity_type'],
            (int)$employee['identity_id'],
            (string)$employee['full_name'],
            (string)$employee['role_code'],
            $note,
            $movementId,
        ]);
    }

    private static function actor(array $user): array
    {
        $userId = (int)($user['id'] ?? 0);
        $userRole = trim((string)($user['role'] ?? 'company_owner')) ?: 'company_owner';
        if ($userId <= 0) {
            throw new \RuntimeException('Пользователь для финансовой операции не определён.');
        }
        return [$userId, $userRole];
    }

    private static function assertGroupId(string $groupId): void
    {
        if (!preg_match('/^EMPLOYEE-[A-Za-z0-9-]{8,80}$/D', $groupId)) {
            throw new \InvalidArgumentException('Передача денег не найдена.');
        }
    }

    private static function assertOperationDate(string $operationDate): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $operationDate)) {
            throw new \InvalidArgumentException('Укажите корректную дату перевода.');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $operationDate);
        if (!$date || $date->format('Y-m-d') !== $operationDate) {
            throw new \InvalidArgumentException('Укажите корректную дату перевода.');
        }
    }

    private static function defaultPurpose(array $sourceEmployee, array $targetEmployee): string
    {
        return 'Передача денежных средств: ' . $sourceEmployee['full_name'] . ' → ' . $targetEmployee['full_name'];
    }

    private static function editablePurpose(string $purpose, array $source, array $target): string
    {
        $default = 'Передача денежных средств: '
            . (string)$source['employee_name_snapshot']
            . ' → '
            . (string)$target['employee_name_snapshot'];
        return trim($purpose) === $default ? '' : trim($purpose);
    }

    private static function technicalComment(string $comment): string
    {
        $technical = 'Перевод между сотрудниками через техническую «' . FinanceCashResolutionService::MAIN_CASH_NAME . '».';
        if ($comment !== '') {
            $technical .= ' ' . $comment;
        }
        return $technical;
    }

    private static function userComment(string $comment): string
    {
        $prefix = 'Перевод между сотрудниками через техническую «' . FinanceCashResolutionService::MAIN_CASH_NAME . '».';
        $comment = trim($comment);
        if (str_starts_with($comment, $prefix)) {
            return trim(substr($comment, strlen($prefix)));
        }
        return $comment;
    }

    private static function employeeRef(array $row): string
    {
        return FinanceEmployeePaymentService::makeEmployeeRef(
            (string)$row['employee_identity_type'],
            (int)$row['employee_identity_id']
        );
    }

    private static function toCents(string $value): int
    {
        $normalized = str_replace([' ', ','], ['', '.'], trim($value));
        if (!preg_match('/^-?\d+(?:\.\d{1,2})?$/D', $normalized)) {
            return 0;
        }
        $negative = str_starts_with($normalized, '-');
        if ($negative) {
            $normalized = substr($normalized, 1);
        }
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $cents = ((int)$whole * 100) + (int)str_pad(substr($fraction, 0, 2), 2, '0');
        return $negative ? -$cents : $cents;
    }

    private static function fromCents(int $cents): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);
        $value = intdiv($cents, 100) . '.' . str_pad((string)($cents % 100), 2, '0', STR_PAD_LEFT);
        return $negative ? '-' . $value : $value;
    }
}
