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
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $operationDate)) {
            throw new \InvalidArgumentException('Укажите корректную дату перевода.');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $operationDate);
        if (!$date || $date->format('Y-m-d') !== $operationDate) {
            throw new \InvalidArgumentException('Укажите корректную дату перевода.');
        }

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
            $purpose = 'Передача денежных средств: ' . $sourceEmployee['full_name'] . ' → ' . $targetEmployee['full_name'];
        }
        $comment = trim((string)($data['comment'] ?? ''));
        $technicalComment = 'Перевод между сотрудниками через техническую «' . FinanceCashResolutionService::MAIN_CASH_NAME . '».';
        if ($comment !== '') {
            $technicalComment .= ' ' . $comment;
        }

        $userId = (int)($user['id'] ?? 0);
        $userRole = trim((string)($user['role'] ?? 'company_owner')) ?: 'company_owner';
        if ($userId <= 0) {
            throw new \RuntimeException('Пользователь для финансовой операции не определён.');
        }

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
