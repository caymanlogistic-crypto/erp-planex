<?php

namespace App\Service;

use PDO;

/**
 * Manual finance facts that cannot be inferred from bank matching.
 *
 * 1) Client cash receipt: real company CASH money + explicit route/payment allocation.
 * 2) Employee personal-funded expense: economic fact only; never changes company money
 *    balances and never creates employee settlement/debt balances.
 */
final class FinanceManualFactService
{
    public static function fetchClientPaymentTargets(PDO $pdo): array
    {
        $stmt = $pdo->query(
            "SELECT lrp.id AS payment_id,
                    lrp.linear_route_id,
                    lrp.amount,
                    COALESCE(lrp.paid_amount, 0) AS paid_amount,
                    lrp.payment_status,
                    lr.planned_loading_date,
                    lr.actual_loading_date,
                    lr.client_id,
                    c.name AS client_name
               FROM linear_route_payments lrp
               JOIN linear_routes lr ON lr.id = lrp.linear_route_id AND lr.deleted_at IS NULL
               JOIN clients c ON c.id = lr.client_id AND c.deleted_at IS NULL
              WHERE lrp.deleted_at IS NULL
                AND lrp.party_role = 'customer'
                AND COALESCE(lrp.cancelled_at, '') = ''
                AND COALESCE(lrp.payment_status, '') <> 'paid'
              ORDER BY COALESCE(lr.actual_loading_date, lr.planned_loading_date) DESC, lr.id DESC, lrp.sort_order ASC"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['remaining_amount'] = FinanceSettlementCascadeService::getRoutePaymentRemainingAmount(
                $pdo,
                (int)$row['payment_id'],
                (string)$row['amount']
            );
        }
        unset($row);
        return array_values(array_filter($rows, static fn(array $row): bool => self::toCents((string)$row['remaining_amount']) > 0));
    }

    public static function createClientCashReceipt(PDO $pdo, array $data, array $user): array
    {
        $paymentId = (int)($data['linear_route_payment_id'] ?? 0);
        if ($paymentId <= 0) {
            throw new \InvalidArgumentException('Выберите рейс и платёж клиента.');
        }
        $amount = FinanceCashService::normalizeMoneyInput((string)($data['amount'] ?? ''));
        if ($amount === null) {
            throw new \InvalidArgumentException('Укажите корректную сумму наличной оплаты.');
        }
        $operationDate = self::validateDate((string)($data['operation_date'] ?? ''));
        $ddsCategoryId = !empty($data['dds_category_id']) ? (int)$data['dds_category_id'] : null;
        $comment = trim((string)($data['comment'] ?? '')) ?: null;

        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "SELECT lrp.*, lr.client_id, c.name AS client_name
                   FROM linear_route_payments lrp
                   JOIN linear_routes lr ON lr.id = lrp.linear_route_id AND lr.deleted_at IS NULL
                   JOIN clients c ON c.id = lr.client_id AND c.deleted_at IS NULL
                  WHERE lrp.id = ? AND lrp.deleted_at IS NULL
                  FOR UPDATE"
            );
            $stmt->execute([$paymentId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$payment || (string)$payment['party_role'] !== 'customer') {
                throw new \RuntimeException('Платёж клиента не найден или больше недоступен.');
            }
            if (!empty($payment['cancelled_at'])) {
                throw new \RuntimeException('Нельзя принять оплату по отменённому платежу рейса.');
            }

            $remaining = FinanceSettlementCascadeService::getRoutePaymentRemainingAmount(
                $pdo,
                $paymentId,
                (string)$payment['amount']
            );
            if (self::toCents($amount) > self::toCents($remaining)) {
                throw new \RuntimeException('Сумма превышает неоплаченный остаток по выбранному платежу: ' . FinanceCashService::formatAmount($remaining) . ' ₽.');
            }

            $mainCash = FinanceCashResolutionService::findMainCashAccount($pdo, true);
            if (!$mainCash) {
                throw new \RuntimeException('Основная касса не найдена или неактивна.');
            }

            $purpose = 'Оплата клиента наличными за рейс #' . (int)$payment['linear_route_id'];
            $operationId = FinanceCashService::createCashOperation($pdo, [
                'operation_type' => 'INCOME',
                'money_account_id' => (int)$mainCash['id'],
                'amount' => $amount,
                'operation_date' => $operationDate,
                'dds_category_id' => $ddsCategoryId,
                'purpose' => $purpose,
                'comment' => $comment,
            ], self::cashUser($user));

            $allocationStmt = $pdo->prepare(
                "INSERT INTO finance_operation_allocations
                    (operation_id, invoice_id, linear_route_id, linear_route_payment_id,
                     amount, allocation_date, method, comment,
                     created_by_user_id, created_by_role)
                 VALUES (?, NULL, ?, ?, ?, ?, 'manual', ?, ?, ?)"
            );
            $allocationStmt->execute([
                $operationId,
                (int)$payment['linear_route_id'],
                $paymentId,
                $amount,
                $operationDate,
                $comment,
                self::userId($user) ?: null,
                self::role($user),
            ]);
            $allocationId = (int)$pdo->lastInsertId();

            FinanceAuditLogService::log($pdo, 'finance_allocation', $allocationId, 'allocation_create', null, [
                'amount' => $amount,
                'operation_id' => $operationId,
                'linear_route_id' => (int)$payment['linear_route_id'],
                'linear_route_payment_id' => $paymentId,
                'manual_cash_receipt' => true,
            ], self::userId($user), self::role($user));

            FinanceSettlementCascadeService::cascadeAfterAllocationCreate($pdo, $allocationId);

            $traceStmt = $pdo->prepare(
                'INSERT INTO finance_cash_route_receipts
                    (finance_operation_id, finance_allocation_id, linear_route_id, linear_route_payment_id, client_id,
                     created_by_user_id, created_by_role)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $traceStmt->execute([
                $operationId,
                $allocationId,
                (int)$payment['linear_route_id'],
                $paymentId,
                (int)$payment['client_id'],
                self::userId($user) ?: null,
                self::role($user),
            ]);
            $receiptId = (int)$pdo->lastInsertId();

            FinanceAuditLogService::log($pdo, 'finance_cash_route_receipt', $receiptId, 'create', null, [
                'finance_operation_id' => $operationId,
                'finance_allocation_id' => $allocationId,
                'linear_route_id' => (int)$payment['linear_route_id'],
                'linear_route_payment_id' => $paymentId,
                'client_id' => (int)$payment['client_id'],
                'amount' => $amount,
                'operation_date' => $operationDate,
            ], self::userId($user), self::role($user));

            if ($ownsTransaction) $pdo->commit();
            return [
                'receipt_id' => $receiptId,
                'finance_operation_id' => $operationId,
                'finance_allocation_id' => $allocationId,
                'linear_route_id' => (int)$payment['linear_route_id'],
                'client_name' => (string)$payment['client_name'],
                'amount' => $amount,
            ];
        } catch (\Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function createEmployeePersonalExpense(PDO $pdo, array $data, array $user, array $employee): int
    {
        self::assertEmployee($employee);
        $amount = FinanceCashService::normalizeMoneyInput((string)($data['amount'] ?? ''));
        if ($amount === null) {
            throw new \InvalidArgumentException('Укажите корректную сумму расхода.');
        }
        $operationDate = self::validateDate((string)($data['operation_date'] ?? ''));
        $cfuId = (int)($data['cash_flow_center_id'] ?? 0);
        $ddsId = (int)($data['dds_category_id'] ?? 0);
        if ($cfuId <= 0) throw new \InvalidArgumentException('Выберите ЦФУ.');
        if ($ddsId <= 0) throw new \InvalidArgumentException('Выберите статью ДДС.');
        FinanceStructureService::assertAllowedPair($pdo, $cfuId, $ddsId, 'EXPENSE');

        $purpose = trim((string)($data['purpose'] ?? ''));
        if ($purpose === '') throw new \InvalidArgumentException('Укажите назначение расхода.');
        $counterpartyName = trim((string)($data['counterparty_name'] ?? '')) ?: null;
        $comment = trim((string)($data['comment'] ?? '')) ?: null;
        $routeId = !empty($data['linear_route_id']) ? (int)$data['linear_route_id'] : null;
        if ($routeId !== null) {
            $routeStmt = $pdo->prepare('SELECT id FROM linear_routes WHERE id = ? AND deleted_at IS NULL');
            $routeStmt->execute([$routeId]);
            if (!$routeStmt->fetchColumn()) throw new \InvalidArgumentException('Выбранный рейс не найден или удалён.');
        }

        $stmt = $pdo->prepare(
            "INSERT INTO finance_employee_personal_expenses
                (operation_date, employee_identity_type, employee_identity_id,
                 employee_name_snapshot, employee_role_snapshot, amount, currency,
                 cash_flow_center_id, dds_category_id, linear_route_id,
                 counterparty_name, purpose, comment, status,
                 created_by_user_id, created_by_role)
             VALUES (?, ?, ?, ?, ?, ?, 'RUR', ?, ?, ?, ?, ?, ?, 'POSTED', ?, ?)"
        );
        $stmt->execute([
            $operationDate,
            (string)$employee['identity_type'],
            (int)$employee['identity_id'],
            (string)$employee['full_name'],
            (string)($employee['role_code'] ?? ''),
            $amount,
            $cfuId,
            $ddsId,
            $routeId,
            $counterpartyName,
            $purpose,
            $comment,
            self::userId($user) ?: null,
            self::role($user),
        ]);
        $id = (int)$pdo->lastInsertId();

        FinanceAuditLogService::log($pdo, 'finance_employee_personal_expense', $id, 'create', null, [
            'operation_date' => $operationDate,
            'employee_ref' => (string)$employee['ref'],
            'amount' => $amount,
            'cash_flow_center_id' => $cfuId,
            'dds_category_id' => $ddsId,
            'linear_route_id' => $routeId,
            'counterparty_name' => $counterpartyName,
            'purpose' => $purpose,
            'affects_company_money_balance' => false,
            'creates_employee_debt' => false,
        ], self::userId($user), self::role($user));

        return $id;
    }

    public static function fetchRecentPersonalExpenses(PDO $pdo, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $pdo->prepare(
            "SELECT pe.*, cfu.name AS cfu_name, dds.name AS dds_name,
                    c.name AS route_client_name
               FROM finance_employee_personal_expenses pe
               JOIN finance_cash_flow_centers cfu ON cfu.id = pe.cash_flow_center_id
               JOIN finance_dds_categories dds ON dds.id = pe.dds_category_id
          LEFT JOIN linear_routes lr ON lr.id = pe.linear_route_id
          LEFT JOIN clients c ON c.id = lr.client_id
              WHERE pe.status = 'POSTED'
              ORDER BY pe.operation_date DESC, pe.id DESC
              LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function validateDate(string $date): string
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($parsed === false || (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) || $parsed->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException('Дата обязательна и должна быть корректной.');
        }
        return $date;
    }

    private static function assertEmployee(array $employee): void
    {
        if (empty($employee['identity_type']) || (int)($employee['identity_id'] ?? 0) <= 0 || trim((string)($employee['full_name'] ?? '')) === '') {
            throw new \InvalidArgumentException('Выберите сотрудника.');
        }
    }

    private static function cashUser(array $user): array
    {
        return ['id' => self::userId($user) ?: null, 'role' => self::role($user)];
    }

    private static function userId(array $user): int
    {
        return (int)($user['user_id'] ?? $user['id'] ?? 0);
    }

    private static function role(array $user): string
    {
        return (string)($user['role_code'] ?? $user['role'] ?? 'company_owner');
    }

    private static function toCents(string $value): int
    {
        $normalized = str_replace(',', '.', trim($value));
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized)) return 0;
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        return ((int)$whole * 100) + (int)str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
