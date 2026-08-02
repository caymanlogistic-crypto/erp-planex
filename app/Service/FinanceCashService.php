<?php

namespace App\Service;

use PDO;

final class FinanceCashService
{
    public static function normalizeMoneyInput(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $normalized = str_replace([' ', ','], ['', '.'], $value);
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $normalized)) {
            return null;
        }
        $parts = explode('.', $normalized);
        $intPart = ltrim($parts[0], '0');
        if ($intPart === '') {
            $intPart = '0';
        }
        $decPart = str_pad($parts[1] ?? '0', 2, '0');
        $result = $intPart . '.' . $decPart;
        if ($result === '0.00') {
            return null;
        }
        return $result;
    }

    public static function formatAmount(mixed $value): string
    {
        if ($value === null || $value === '' || $value === false) {
            return '—';
        }
        $normalized = str_replace(',', '.', (string) $value);
        if (!preg_match('/^-?\d+(\.\d+)?$/', $normalized)) {
            return '—';
        }
        $parts = explode('.', $normalized);
        $intPart = ltrim($parts[0], '0');
        if ($intPart === '' || $intPart === '-') {
            $intPart = $intPart === '-' ? '-0' : '0';
        }
        $decPart = str_pad(substr(($parts[1] ?? ''), 0, 2), 2, '0');
        $formattedInt = preg_replace('/\B(?=(\d{3})+(?!\d))/', ' ', $intPart);
        return $formattedInt . ',' . $decPart;
    }

    private static function stringCompare(string $left, string $right): int
    {
        $leftCents = self::toCents($left);
        $rightCents = self::toCents($right);
        if ($leftCents > $rightCents) return 1;
        if ($leftCents < $rightCents) return -1;
        return 0;
    }

    private static function toCents(string $value): int
    {
        $parts = explode('.', $value);
        $intPart = ltrim($parts[0], '0');
        if ($intPart === '' || $intPart === '-') {
            $intPart = $intPart === '-' ? '-0' : '0';
        }
        $decPart = str_pad(substr(($parts[1] ?? ''), 0, 2), 2, '0');
        return (int)($intPart . $decPart);
    }

    public static function fetchMoneyAccounts(PDO $localPdo, ?string $type = null, bool $activeOnly = true): array
    {
        $conditions = [];
        $params = [];

        if ($type !== null) {
            $conditions[] = 'fma.type = :type';
            $params[':type'] = $type;
        }
        if ($activeOnly) {
            $conditions[] = 'fma.is_active = 1';
        }

        $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT fma.*,
                       COALESCE(fma.opening_balance, 0)
                       + COALESCE(SUM(CASE WHEN fo.operation_type = 'INCOME'  AND fo.status = 'POSTED' THEN fo.amount ELSE 0 END), 0)
                       + COALESCE(SUM(CASE WHEN fo.operation_type = 'TRANSFER' AND fo.transfer_direction = 'in'  AND fo.status = 'POSTED' THEN fo.amount ELSE 0 END), 0)
                       - COALESCE(SUM(CASE WHEN fo.operation_type = 'EXPENSE' AND fo.status = 'POSTED' THEN fo.amount ELSE 0 END), 0)
                       - COALESCE(SUM(CASE WHEN fo.operation_type = 'TRANSFER' AND fo.transfer_direction = 'out' AND fo.status = 'POSTED' THEN fo.amount ELSE 0 END), 0) AS computed_balance
                  FROM finance_money_accounts fma
             LEFT JOIN finance_operations fo ON fo.money_account_id = fma.id
                  {$where}
                  GROUP BY fma.id, fma.type, fma.name, fma.bank_account_id, fma.currency,
                           fma.opening_balance, fma.opening_balance_date, fma.is_active,
                           fma.created_by_user_id, fma.created_by_role, fma.updated_by_user_id,
                           fma.updated_by_role, fma.created_at, fma.updated_at
                  ORDER BY fma.name ASC";

        $stmt = $localPdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            if ($row['type'] === 'BANK') {
                $row['computed_balance'] = FinanceBalanceService::balanceForMoneyAccount($localPdo, (int) $row['id']);
            }
        }
        unset($row);

        return $rows;
    }

    public static function getCashAccountBalance(PDO $localPdo, int $accountId): string
    {
        $accounts = self::fetchMoneyAccounts($localPdo, 'CASH', false);
        foreach ($accounts as $a) {
            if ((int) $a['id'] === $accountId) {
                return (string) ($a['computed_balance'] ?? '0.00');
            }
        }
        return '0.00';
    }

    public static function createCashAccount(PDO $localPdo, array $data, array $user): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Название кассы обязательно.');
        }

        $openingBalance = '0.00';
        if (!empty($data['opening_balance'])) {
            $normalized = self::normalizeMoneyInput((string) $data['opening_balance']);
            if ($normalized === null) {
                throw new \InvalidArgumentException('Некорректная сумма начального остатка.');
            }
            $openingBalance = $normalized;
        }

        $openingBalanceDate = !empty($data['opening_balance_date']) ? $data['opening_balance_date'] : null;
        $comment = !empty($data['comment']) ? trim((string) $data['comment']) : null;

        $stmt = $localPdo->prepare(
            'INSERT INTO finance_money_accounts
                (type, name, currency, opening_balance, opening_balance_date, is_active,
                 created_by_user_id, created_by_role, updated_by_user_id, updated_by_role)
             VALUES
                (\'CASH\', :name, \'RUR\', :opening_balance, :opening_balance_date, 1,
                 :created_by_user_id, :created_by_role, :updated_by_user_id, :updated_by_role)'
        );
        $stmt->execute([
            ':name' => $name,
            ':opening_balance' => $openingBalance,
            ':opening_balance_date' => $openingBalanceDate,
            ':created_by_user_id' => $user['id'] ?? null,
            ':created_by_role' => $user['role'] ?? null,
            ':updated_by_user_id' => $user['id'] ?? null,
            ':updated_by_role' => $user['role'] ?? null,
        ]);

        return (int) $localPdo->lastInsertId();
    }

    public static function createCashOperation(PDO $localPdo, array $data, array $user): int
    {
        $operationType = $data['operation_type'] ?? '';
        if (!in_array($operationType, ['INCOME', 'EXPENSE'], true)) {
            throw new \InvalidArgumentException('Тип операции должен быть INCOME или EXPENSE.');
        }

        $accountId = (int) ($data['money_account_id'] ?? 0);
        if ($accountId <= 0) {
            throw new \InvalidArgumentException('Не выбран кассовый счёт.');
        }

        $accounts = self::fetchMoneyAccounts($localPdo, 'CASH', true);
        $found = false;
        foreach ($accounts as $a) {
            if ((int) $a['id'] === $accountId) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new \InvalidArgumentException('Кассовый счёт не найден или неактивен.');
        }

        $amount = self::normalizeMoneyInput((string) ($data['amount'] ?? ''));
        if ($amount === null) {
            throw new \InvalidArgumentException('Некорректная сумма операции.');
        }

        $operationDate = $data['operation_date'] ?? '';
        if ($operationDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $operationDate)) {
            throw new \InvalidArgumentException('Дата операции обязательна и должна быть в формате ГГГГ-ММ-ДД.');
        }

        if ($operationType === 'EXPENSE') {
            $balance = self::getCashAccountBalance($localPdo, $accountId);
            if (self::stringCompare($balance, $amount) < 0) {
                throw new \RuntimeException('Недостаточно средств на кассовом счёте. Текущий остаток: ' . self::formatAmount($balance) . ' ₽');
            }
        }

        $ddsCategoryId = null;
        if (!empty($data['dds_category_id'])) {
            $ddsCategoryId = (int) $data['dds_category_id'];
            $catStmt = $localPdo->prepare(
                'SELECT id, direction, is_active FROM finance_dds_categories WHERE id = :id'
            );
            $catStmt->execute([':id' => $ddsCategoryId]);
            $category = $catStmt->fetch(PDO::FETCH_ASSOC);
            if (!$category) {
                throw new \InvalidArgumentException('Выбранная статья ДДС не найдена.');
            }
            if (!(int) ($category['is_active'] ?? 0)) {
                throw new \InvalidArgumentException('Выбранная статья ДДС неактивна.');
            }
            $catDirection = $category['direction'];
            if ($catDirection !== $operationType && $catDirection !== 'BOTH') {
                throw new \InvalidArgumentException('Направление статьи ДДС не соответствует типу операции.');
            }
        }

        $purpose = !empty($data['purpose']) ? trim((string) $data['purpose']) : null;
        $comment = !empty($data['comment']) ? trim((string) $data['comment']) : null;

        $stmt = $localPdo->prepare(
            'INSERT INTO finance_operations
                (operation_type, status, source, money_account_id, operation_date, amount, currency,
                 dds_category_id, purpose, comment,
                 created_by_user_id, created_by_role, posted_by_user_id, posted_by_role, posted_at)
             VALUES
                (:operation_type, \'POSTED\', \'CASH\', :money_account_id, :operation_date, :amount, \'RUR\',
                 :dds_category_id, :purpose, :comment,
                 :created_by_user_id, :created_by_role, :posted_by_user_id, :posted_by_role, NOW())'
        );
        $stmt->execute([
            ':operation_type' => $operationType,
            ':money_account_id' => $accountId,
            ':operation_date' => $operationDate,
            ':amount' => $amount,
            ':dds_category_id' => $ddsCategoryId,
            ':purpose' => $purpose,
            ':comment' => $comment,
            ':created_by_user_id' => $user['id'] ?? null,
            ':created_by_role' => $user['role'] ?? null,
            ':posted_by_user_id' => $user['id'] ?? null,
            ':posted_by_role' => $user['role'] ?? null,
        ]);

        return (int) $localPdo->lastInsertId();
    }

    public static function createTransfer(PDO $localPdo, array $data, array $user): array
    {
        $fromAccountId = (int) ($data['from_account_id'] ?? 0);
        $toAccountId = (int) ($data['to_account_id'] ?? 0);

        if ($fromAccountId <= 0 || $toAccountId <= 0) {
            throw new \InvalidArgumentException('Выберите счёт отправителя и получателя.');
        }
        if ($fromAccountId === $toAccountId) {
            throw new \InvalidArgumentException('Счета отправителя и получателя должны быть разными.');
        }

        $allAccounts = self::fetchMoneyAccounts($localPdo, null, true);
        $fromAccount = null;
        $toAccount = null;
        foreach ($allAccounts as $a) {
            if ((int) $a['id'] === $fromAccountId) {
                $fromAccount = $a;
            }
            if ((int) $a['id'] === $toAccountId) {
                $toAccount = $a;
            }
        }
        if (!$fromAccount) {
            throw new \InvalidArgumentException('Счёт отправителя не найден или неактивен.');
        }
        if (!$toAccount) {
            throw new \InvalidArgumentException('Счёт получателя не найден или неактивен.');
        }

        $amount = self::normalizeMoneyInput((string) ($data['amount'] ?? ''));
        if ($amount === null) {
            throw new \InvalidArgumentException('Некорректная сумма перевода.');
        }

        $transferDate = $data['date'] ?? '';
        if ($transferDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $transferDate)) {
            throw new \InvalidArgumentException('Дата перевода обязательна и должна быть в формате ГГГГ-ММ-ДД.');
        }

        if ($fromAccount['type'] === 'CASH') {
            $balance = (string) ($fromAccount['computed_balance'] ?? '0.00');
            if (self::stringCompare($balance, $amount) < 0) {
                throw new \RuntimeException('Недостаточно средств на счету отправителя. Текущий остаток: ' . self::formatAmount($balance) . ' ₽');
            }
        }

        $purpose = !empty($data['purpose']) ? trim((string) $data['purpose']) : null;
        $comment = !empty($data['comment']) ? trim((string) $data['comment']) : null;

        $needsBankConfirmation = $fromAccount['type'] === 'BANK' || $toAccount['type'] === 'BANK';
        $status = $needsBankConfirmation ? 'PENDING_CONFIRMATION' : 'POSTED';
        $postedAtValue = $needsBankConfirmation ? null : date('Y-m-d H:i:s');
        $transferGroupId = 'TRF_' . bin2hex(random_bytes(12));

        $localPdo->beginTransaction();
        try {
            $insOut = $localPdo->prepare(
                "INSERT INTO finance_operations
                    (operation_type, status, source, money_account_id, transfer_account_id,
                     transfer_group_id, transfer_direction,
                     operation_date, amount, currency, purpose, comment,
                     created_by_user_id, created_by_role, posted_by_user_id, posted_by_role, posted_at)
                 VALUES
                    ('TRANSFER', :status, 'TRANSFER', :from_account_id, :to_account_id,
                     :transfer_group_id, 'out',
                     :transfer_date, :amount, 'RUR', :purpose, :comment,
                     :created_by_user_id, :created_by_role, :posted_by_user_id, :posted_by_role, :posted_at)"
            );
            $insOut->execute([
                ':status' => $status,
                ':from_account_id' => $fromAccountId,
                ':to_account_id' => $toAccountId,
                ':transfer_group_id' => $transferGroupId,
                ':transfer_date' => $transferDate,
                ':amount' => $amount,
                ':purpose' => $purpose,
                ':comment' => $comment,
                ':created_by_user_id' => $user['id'] ?? null,
                ':created_by_role' => $user['role'] ?? null,
                ':posted_by_user_id' => $needsBankConfirmation ? null : ($user['id'] ?? null),
                ':posted_by_role' => $needsBankConfirmation ? null : ($user['role'] ?? null),
                ':posted_at' => $postedAtValue,
            ]);
            $outId = (int) $localPdo->lastInsertId();

            $insIn = $localPdo->prepare(
                "INSERT INTO finance_operations
                    (operation_type, status, source, money_account_id, transfer_account_id,
                     transfer_group_id, transfer_direction,
                     operation_date, amount, currency, purpose, comment,
                     created_by_user_id, created_by_role, posted_by_user_id, posted_by_role, posted_at)
                 VALUES
                    ('TRANSFER', :status, 'TRANSFER', :to_account_id, :from_account_id,
                     :transfer_group_id, 'in',
                     :transfer_date, :amount, 'RUR', :purpose, :comment,
                     :created_by_user_id, :created_by_role, :posted_by_user_id, :posted_by_role, :posted_at)"
            );
            $insIn->execute([
                ':status' => $status,
                ':to_account_id' => $toAccountId,
                ':from_account_id' => $fromAccountId,
                ':transfer_group_id' => $transferGroupId,
                ':transfer_date' => $transferDate,
                ':amount' => $amount,
                ':purpose' => $purpose,
                ':comment' => $comment,
                ':created_by_user_id' => $user['id'] ?? null,
                ':created_by_role' => $user['role'] ?? null,
                ':posted_by_user_id' => $needsBankConfirmation ? null : ($user['id'] ?? null),
                ':posted_by_role' => $needsBankConfirmation ? null : ($user['role'] ?? null),
                ':posted_at' => $postedAtValue,
            ]);
            $inId = (int) $localPdo->lastInsertId();

            if ($needsBankConfirmation) {
                $userAuditId = (int) ($user['id'] ?? 0);
                $userAuditRole = $user['role'] ?? '';
                FinanceAuditLogService::log($localPdo, 'finance_operation', $outId, 'create_pending', [
                    'status' => 'PENDING_CONFIRMATION',
                    'operation_type' => 'TRANSFER',
                    'amount' => $amount,
                    'transfer_direction' => 'out',
                ], null, $userAuditId, $userAuditRole);
                FinanceAuditLogService::log($localPdo, 'finance_operation', $inId, 'create_pending', [
                    'status' => 'PENDING_CONFIRMATION',
                    'operation_type' => 'TRANSFER',
                    'amount' => $amount,
                    'transfer_direction' => 'in',
                ], null, $userAuditId, $userAuditRole);
            }

            $localPdo->commit();

            return [
                'out_operation_id' => $outId,
                'in_operation_id' => $inId,
                'transfer_group_id' => $transferGroupId,
                'amount' => $amount,
                'status' => $status,
            ];
        } catch (\Throwable $e) {
            $localPdo->rollBack();
            throw $e;
        }
    }

    public static function fetchRecentOperations(PDO $localPdo, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(500, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = "WHERE fo.source IN ('CASH', 'TRANSFER')
                   OR (fo.operation_type = 'TRANSFER' AND fo.source = 'TRANSFER')";

        $countSql = "SELECT COUNT(*) FROM finance_operations fo
                     JOIN finance_money_accounts fma ON fma.id = fo.money_account_id
                     {$where}";
        $countStmt = $localPdo->prepare($countSql);
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT fo.*, fma.name AS account_name, fma.type AS account_type
                  FROM finance_operations fo
                  JOIN finance_money_accounts fma ON fma.id = fo.money_account_id
                  {$where}
                  ORDER BY fo.created_at DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $localPdo->prepare($sql);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => (int) ceil($total / max(1, $perPage)),
        ];
    }
}
