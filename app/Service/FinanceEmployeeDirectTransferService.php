<?php

namespace App\Service;

use PDO;

/**
 * Cashless employee-to-employee transfer.
 *
 * New model: employee A account -> employee B account. No CASH account and no
 * finance_cash_resolutions row are created. Historical CASH chains remain intact.
 */
final class FinanceEmployeeDirectTransferService
{
    public const GROUP_PREFIX = 'EMPLOYEE-DIRECT-';

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

        $amount = self::normalizeAmount((string)($data['amount'] ?? ''));
        $operationDate = trim((string)($data['operation_date'] ?? ''));
        self::assertDate($operationDate);

        $sourceEmployee = FinanceEmployeePaymentService::resolveActiveEmployee($localPdo, $centralPdo, $companyId, $sourceRef);
        $targetEmployee = FinanceEmployeePaymentService::resolveActiveEmployee($localPdo, $centralPdo, $companyId, $targetRef);
        $purpose = trim((string)($data['purpose'] ?? ''));
        if ($purpose === '') {
            $purpose = 'Передача денег: ' . $sourceEmployee['full_name'] . ' → ' . $targetEmployee['full_name'];
        }
        $comment = trim((string)($data['comment'] ?? ''));
        $actorId = (int)($user['id'] ?? 0);
        $actorRole = trim((string)($user['role'] ?? 'company_owner')) ?: 'company_owner';

        $started = !$localPdo->inTransaction();
        if ($started) {
            $localPdo->beginTransaction();
        }

        try {
            $sourceAccountId = FinanceEmployeeMoneyAccountService::accountId($localPdo, $sourceEmployee, $user);
            $targetAccountId = FinanceEmployeeMoneyAccountService::accountId($localPdo, $targetEmployee, $user);
            if ($sourceAccountId === $targetAccountId) {
                throw new \RuntimeException('Сотрудники не могут использовать один финансовый счёт.');
            }

            $groupId = self::GROUP_PREFIX . date('YmdHis') . '-' . bin2hex(random_bytes(8));
            $insert = $localPdo->prepare(
                "INSERT INTO finance_operations
                    (operation_type, status, source, money_account_id, transfer_account_id,
                     transfer_group_id, transfer_direction, operation_date, amount, currency,
                     classification_status, classification_locked, purpose, comment,
                     created_by_user_id, created_by_role, posted_by_user_id, posted_by_role, posted_at)
                 VALUES
                    ('TRANSFER','POSTED','TRANSFER',?,?,?,?,? ,?,'RUR',
                     'UNALLOCATED',0,?,?, ?,?,?,?,NOW())"
            );

            $insert->execute([
                $sourceAccountId,
                $targetAccountId,
                $groupId,
                'out',
                $operationDate,
                $amount,
                $purpose,
                $comment !== '' ? $comment : null,
                $actorId > 0 ? $actorId : null,
                $actorRole,
                $actorId > 0 ? $actorId : null,
                $actorRole,
            ]);
            $sourceOperationId = (int)$localPdo->lastInsertId();

            $insert->execute([
                $targetAccountId,
                $sourceAccountId,
                $groupId,
                'in',
                $operationDate,
                $amount,
                $purpose,
                $comment !== '' ? $comment : null,
                $actorId > 0 ? $actorId : null,
                $actorRole,
                $actorId > 0 ? $actorId : null,
                $actorRole,
            ]);
            $targetOperationId = (int)$localPdo->lastInsertId();

            $sourceMovementId = self::insertMovement(
                $localPdo,
                $sourceEmployee,
                'RETURN',
                $sourceOperationId,
                $comment !== '' ? $comment : 'Прямая передача денег другому сотруднику.',
                $actorId,
                $actorRole
            );
            $targetMovementId = self::insertMovement(
                $localPdo,
                $targetEmployee,
                'PAYMENT',
                $targetOperationId,
                $comment !== '' ? $comment : 'Прямая передача денег от другого сотрудника.',
                $actorId,
                $actorRole
            );

            FinanceAuditLogService::log(
                $localPdo,
                'finance_employee_transfer',
                $sourceOperationId,
                'employee_direct_transfer',
                null,
                [
                    'transfer_group_id' => $groupId,
                    'source_employee_ref' => $sourceRef,
                    'target_employee_ref' => $targetRef,
                    'amount' => $amount,
                    'operation_date' => $operationDate,
                    'cash_account_used' => false,
                ],
                $actorId,
                $actorRole
            );

            if ($started) {
                $localPdo->commit();
            }

            return [
                'amount' => $amount,
                'source_employee' => $sourceEmployee,
                'target_employee' => $targetEmployee,
                'source_movement_id' => $sourceMovementId,
                'target_movement_id' => $targetMovementId,
                'source_finance_operation_id' => $sourceOperationId,
                'target_finance_operation_id' => $targetOperationId,
                'transfer_group_id' => $groupId,
            ];
        } catch (\Throwable $e) {
            if ($started && $localPdo->inTransaction()) {
                $localPdo->rollBack();
            }
            throw $e;
        }
    }

    private static function insertMovement(
        PDO $pdo,
        array $employee,
        string $movementType,
        int $operationId,
        string $note,
        int $actorId,
        string $actorRole
    ): int {
        $employeeUserId = ($employee['identity_type'] ?? '') === FinanceEmployeePaymentService::IDENTITY_TENANT_USER
            ? (int)$employee['identity_id']
            : null;
        $stmt = $pdo->prepare(
            "INSERT INTO finance_employee_movements
                (employee_user_id, employee_identity_type, employee_identity_id,
                 employee_name_snapshot, employee_role_snapshot, movement_type,
                 source_type, finance_operation_id, bank_transaction_id, note,
                 created_by_user_id, created_by_role)
             VALUES (?, ?, ?, ?, ?, ?, 'EMPLOYEE', ?, NULL, ?, ?, ?)"
        );
        $stmt->execute([
            $employeeUserId,
            (string)$employee['identity_type'],
            (int)$employee['identity_id'],
            trim((string)$employee['full_name']),
            trim((string)($employee['role_code'] ?? '')) ?: null,
            $movementType,
            $operationId,
            $note !== '' ? $note : null,
            $actorId > 0 ? $actorId : null,
            $actorRole,
        ]);
        return (int)$pdo->lastInsertId();
    }

    private static function normalizeAmount(string $raw): string
    {
        $normalized = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim($raw));
        if ($normalized === '' || !preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized)) {
            throw new \InvalidArgumentException('Укажите корректную сумму.');
        }
        $cents = (int)round(((float)$normalized) * 100);
        if ($cents <= 0) {
            throw new \InvalidArgumentException('Сумма перевода должна быть больше нуля.');
        }
        return number_format($cents / 100, 2, '.', '');
    }

    private static function assertDate(string $date): void
    {
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$dt || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) || $dt->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException('Укажите корректную дату операции.');
        }
    }
}
