<?php

namespace App\Service;

use PDO;

final class FinanceEmployeePaymentService
{
    public static function fetchActiveEmployees(PDO $pdo): array
    {
        $stmt = $pdo->query("SELECT id, full_name, login, role_code, status
                              FROM users
                             WHERE status = 'active' AND deleted_at IS NULL
                             ORDER BY full_name ASC, id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function fetchEmployeeSummaries(PDO $pdo, array $filters = []): array
    {
        $where = ["fo.status = 'POSTED'"];
        $params = [];
        if (!empty($filters['employee_user_id'])) {
            $where[] = 'fem.employee_user_id = :employee_user_id';
            $params[':employee_user_id'] = (int)$filters['employee_user_id'];
        }
        if (!empty($filters['source_type']) && in_array($filters['source_type'], ['BANK','CASH'], true)) {
            $where[] = 'fem.source_type = :source_type';
            $params[':source_type'] = $filters['source_type'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'fo.operation_date >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'fo.operation_date <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }
        $sql = "SELECT u.id AS employee_user_id, u.full_name, u.login, u.status,
                       SUM(CASE WHEN fem.movement_type='PAYMENT' THEN fo.amount ELSE 0 END) AS paid_amount,
                       SUM(CASE WHEN fem.movement_type='RETURN' THEN fo.amount ELSE 0 END) AS returned_amount,
                       SUM(CASE WHEN fem.movement_type='PAYMENT' THEN fo.amount ELSE -fo.amount END) AS balance_amount,
                       MAX(fo.operation_date) AS last_operation_date,
                       COUNT(*) AS movement_count
                  FROM finance_employee_movements fem
                  JOIN finance_operations fo ON fo.id = fem.finance_operation_id
                  JOIN users u ON u.id = fem.employee_user_id
                 WHERE ".implode(' AND ', $where)."
                 GROUP BY u.id, u.full_name, u.login, u.status
                 ORDER BY last_operation_date DESC, u.full_name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function fetchEmployeeLedger(PDO $pdo, int $employeeUserId): array
    {
        $stmt = $pdo->prepare("SELECT fem.id, fem.employee_user_id, fem.movement_type, fem.source_type, fem.note,
                                     fo.id AS finance_operation_id, fo.operation_date, fo.amount, fo.status,
                                     fo.purpose, fo.comment, fma.name AS money_account_name,
                                     bt.id AS bank_transaction_id, bt.counterparty_name, bt.document_number,
                                     u.full_name
                                FROM finance_employee_movements fem
                                JOIN finance_operations fo ON fo.id = fem.finance_operation_id
                                JOIN users u ON u.id = fem.employee_user_id
                           LEFT JOIN finance_money_accounts fma ON fma.id = fo.money_account_id
                           LEFT JOIN bank_transactions bt ON bt.id = fem.bank_transaction_id
                               WHERE fem.employee_user_id = ?
                            ORDER BY fo.operation_date ASC, fem.id ASC");
        $stmt->execute([$employeeUserId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $balance = 0.0;
        foreach ($rows as &$row) {
            if (($row['status'] ?? '') === 'POSTED') {
                $amount = (float)$row['amount'];
                $balance += ($row['movement_type'] === 'PAYMENT') ? $amount : -$amount;
            }
            $row['running_balance'] = $balance;
        }
        unset($row);
        return array_reverse($rows);
    }

    public static function fetchBankCandidates(PDO $pdo, string $movementType, int $limit = 200): array
    {
        self::assertMovementType($movementType);
        $amountCondition = $movementType === 'PAYMENT' ? 'bt.debit_amount > 0' : 'bt.credit_amount > 0';
        $stmt = $pdo->prepare("SELECT bt.id, bt.operation_date, bt.counterparty_name, bt.counterparty_inn,
                                     bt.debit_amount, bt.credit_amount, bt.purpose, ba.account_number,
                                     fo.id AS finance_operation_id
                                FROM bank_transactions bt
                                JOIN bank_accounts ba ON ba.id = bt.account_id
                                JOIN finance_operations fo ON fo.bank_transaction_id = bt.id AND fo.status = 'POSTED'
                           LEFT JOIN finance_employee_movements fem ON fem.bank_transaction_id = bt.id
                               WHERE {$amountCondition}
                                 AND COALESCE(bt.is_internal_transfer,0)=0
                                 AND fem.id IS NULL
                            ORDER BY bt.operation_date DESC, bt.id DESC
                               LIMIT :limit");
        $stmt->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function createCashMovement(PDO $pdo, array $data, array $user): array
    {
        $movementType = strtoupper(trim((string)($data['movement_type'] ?? '')));
        self::assertMovementType($movementType);
        $employee = self::requireActiveEmployee($pdo, (int)($data['employee_user_id'] ?? 0));
        $operationType = $movementType === 'PAYMENT' ? 'EXPENSE' : 'INCOME';
        $purpose = trim((string)($data['purpose'] ?? ''));
        if ($purpose === '') {
            $purpose = ($movementType === 'PAYMENT' ? 'Выплата сотруднику: ' : 'Возврат от сотрудника: ') . $employee['full_name'];
        }
        $started = !$pdo->inTransaction();
        if ($started) $pdo->beginTransaction();
        try {
            $operationId = FinanceCashService::createCashOperation($pdo, [
                'operation_type' => $operationType,
                'money_account_id' => (int)($data['money_account_id'] ?? 0),
                'amount' => (string)($data['amount'] ?? ''),
                'operation_date' => (string)($data['operation_date'] ?? ''),
                'purpose' => $purpose,
                'comment' => trim((string)($data['comment'] ?? '')),
            ], $user);
            $movementId = self::insertMovement($pdo, (int)$employee['id'], $movementType, 'CASH', $operationId, null, $data['comment'] ?? null, $user);
            if ($started) $pdo->commit();
            return ['movement_id'=>$movementId,'finance_operation_id'=>$operationId];
        } catch (\Throwable $e) {
            if ($started && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function linkBankTransaction(PDO $pdo, array $data, array $user): array
    {
        $movementType = strtoupper(trim((string)($data['movement_type'] ?? '')));
        self::assertMovementType($movementType);
        $employee = self::requireActiveEmployee($pdo, (int)($data['employee_user_id'] ?? 0));
        $bankTransactionId = (int)($data['bank_transaction_id'] ?? 0);
        if ($bankTransactionId <= 0) throw new \InvalidArgumentException('Выберите банковскую операцию.');

        $started = !$pdo->inTransaction();
        if ($started) $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT bt.*, fo.id AS finance_operation_id, fo.status AS finance_operation_status
                                     FROM bank_transactions bt
                                     JOIN finance_operations fo ON fo.bank_transaction_id = bt.id
                                    WHERE bt.id = ? FOR UPDATE");
            $stmt->execute([$bankTransactionId]);
            $tx = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$tx) throw new \RuntimeException('Банковская операция не найдена или не связана с финансовой операцией.');
            if (($tx['finance_operation_status'] ?? '') !== 'POSTED') throw new \RuntimeException('Можно связать только проведённую банковскую операцию.');
            if (!empty($tx['is_internal_transfer'])) throw new \RuntimeException('Внутренний перевод нельзя оформить как выплату сотруднику.');
            $actualType = (float)$tx['debit_amount'] > 0 ? 'PAYMENT' : ((float)$tx['credit_amount'] > 0 ? 'RETURN' : null);
            if ($actualType !== $movementType) throw new \RuntimeException('Направление банковской операции не соответствует типу взаиморасчёта.');
            $check = $pdo->prepare('SELECT id FROM finance_employee_movements WHERE bank_transaction_id = ? OR finance_operation_id = ? LIMIT 1');
            $check->execute([$bankTransactionId, (int)$tx['finance_operation_id']]);
            if ($check->fetchColumn()) throw new \RuntimeException('Эта банковская операция уже связана с сотрудником.');
            $movementId = self::insertMovement($pdo, (int)$employee['id'], $movementType, 'BANK', (int)$tx['finance_operation_id'], $bankTransactionId, $data['comment'] ?? null, $user);
            if ($started) $pdo->commit();
            return ['movement_id'=>$movementId,'finance_operation_id'=>(int)$tx['finance_operation_id'],'bank_transaction_id'=>$bankTransactionId];
        } catch (\Throwable $e) {
            if ($started && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function findByBankTransaction(PDO $pdo, int $bankTransactionId): ?array
    {
        $stmt = $pdo->prepare("SELECT fem.*, u.full_name, fo.operation_date, fo.amount, fo.status
                                FROM finance_employee_movements fem
                                JOIN users u ON u.id = fem.employee_user_id
                                JOIN finance_operations fo ON fo.id = fem.finance_operation_id
                               WHERE fem.bank_transaction_id = ? LIMIT 1");
        $stmt->execute([$bankTransactionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function unlinkBankTransaction(PDO $pdo, int $bankTransactionId, array $user): bool
    {
        $started = !$pdo->inTransaction();
        if ($started) $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT * FROM finance_employee_movements WHERE bank_transaction_id = ? FOR UPDATE");
            $stmt->execute([$bankTransactionId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                if ($started) $pdo->commit();
                return false;
            }
            $del = $pdo->prepare('DELETE FROM finance_employee_movements WHERE id = ?');
            $del->execute([(int)$row['id']]);
            FinanceAuditLogService::log($pdo, 'finance_employee_movement', (int)$row['id'], 'unlink_bank', $row, ['removed'=>true], (int)($user['id'] ?? 0), (string)($user['role'] ?? 'company_owner'));
            if ($started) $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            if ($started && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function unlinkByBankTransactionIfExists(PDO $pdo, int $bankTransactionId, array $user): void
    {
        self::unlinkBankTransaction($pdo, $bankTransactionId, $user);
    }

    private static function insertMovement(PDO $pdo, int $employeeId, string $movementType, string $sourceType, int $operationId, ?int $bankTxId, mixed $note, array $user): int
    {
        $stmt = $pdo->prepare("INSERT INTO finance_employee_movements
            (employee_user_id,movement_type,source_type,finance_operation_id,bank_transaction_id,note,created_by_user_id,created_by_role)
            VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$employeeId,$movementType,$sourceType,$operationId,$bankTxId,self::nullableText($note),$user['id'] ?? null,$user['role'] ?? null]);
        $id = (int)$pdo->lastInsertId();
        FinanceAuditLogService::log($pdo, 'finance_employee_movement', $id, 'create', null, [
            'employee_user_id'=>$employeeId,'movement_type'=>$movementType,'source_type'=>$sourceType,
            'finance_operation_id'=>$operationId,'bank_transaction_id'=>$bankTxId,
        ], (int)($user['id'] ?? 0), (string)($user['role'] ?? 'company_owner'));
        return $id;
    }

    private static function requireActiveEmployee(PDO $pdo, int $employeeId): array
    {
        if ($employeeId <= 0) throw new \InvalidArgumentException('Выберите сотрудника.');
        $stmt = $pdo->prepare("SELECT id,full_name,login,status FROM users WHERE id=? AND status='active' AND deleted_at IS NULL");
        $stmt->execute([$employeeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new \InvalidArgumentException('Сотрудник не найден или его аккаунт неактивен.');
        return $row;
    }

    private static function assertMovementType(string $movementType): void
    {
        if (!in_array($movementType, ['PAYMENT','RETURN'], true)) {
            throw new \InvalidArgumentException('Тип операции должен быть PAYMENT или RETURN.');
        }
    }

    private static function nullableText(mixed $value): ?string
    {
        $value = trim((string)($value ?? ''));
        return $value === '' ? null : mb_substr($value, 0, 1000);
    }
}
