<?php

namespace App\Service;

use PDO;

final class FinanceOperationService
{
    public const OPERATION_TYPES = [
        'INCOME' => 'Поступление',
        'EXPENSE' => 'Списание',
        'TRANSFER' => 'Перевод',
        'ADJUSTMENT' => 'Корректировка',
    ];

    public const STATUSES = [
        'DRAFT' => 'Черновик',
        'PLANNED' => 'Запланирован',
        'POSTED' => 'Проведён',
        'PENDING_CONFIRMATION' => 'Ожидает подтверждения',
        'CANCELLED' => 'Отменён',
    ];

    public const SOURCES = [
        'BANK_STATEMENT' => 'Банковская выписка',
        'CASH' => 'Касса',
        'INVOICE' => 'Счёт',
        'MANUAL' => 'Вручную',
        'TRANSFER' => 'Перевод',
    ];

    public static function operationTypeLabel(?string $type): string
    {
        return self::OPERATION_TYPES[$type] ?? '—';
    }

    public static function statusLabel(?string $status): string
    {
        return self::STATUSES[$status] ?? '—';
    }

    public static function statusBadgeClass(?string $status): string
    {
        return match ($status) {
            'POSTED' => 'badge badge-ok',
            'PLANNED' => 'badge badge-warning',
            'PENDING_CONFIRMATION' => 'badge badge-warning',
            'DRAFT' => 'badge badge-neutral',
            'CANCELLED' => 'badge badge-neutral',
            default => 'badge badge-neutral',
        };
    }

    public static function sourceLabel(?string $source): string
    {
        return self::SOURCES[$source] ?? '—';
    }

    public const ALLOCATION_STATUSES = [
        'unallocated' => 'Не распределено',
        'partially_allocated' => 'Частично распределено',
        'allocated' => 'Распределено',
    ];

    public static function allocationStatusLabel(?string $status): string
    {
        return self::ALLOCATION_STATUSES[$status] ?? '—';
    }

    public static function allocationStatusBadgeClass(?string $status): string
    {
        return match ($status) {
            'allocated' => 'badge badge-ok',
            'partially_allocated' => 'badge badge-warning',
            default => 'badge badge-neutral',
        };
    }

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

    public static function ensureMoneyAccountsForBankAccounts(PDO $localPdo): void
    {
        $stmt = $localPdo->query(
            'SELECT ba.*, fma.id AS money_account_id
                FROM bank_accounts ba
           LEFT JOIN finance_money_accounts fma ON fma.bank_account_id = ba.id
               ORDER BY ba.id ASC'
        );
        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($accounts as $ba) {
            $name = $ba['bank_name'] && $ba['account_number']
                ? $ba['bank_name'] . ' ' . $ba['account_number']
                : ($ba['account_number'] ? 'Расчётный счёт ' . $ba['account_number'] : 'Банковский счёт #' . $ba['id']);

            if ($ba['money_account_id']) {
                $upd = $localPdo->prepare(
                    'UPDATE finance_money_accounts SET name = :name WHERE id = :id AND name != :name_check'
                );
                $upd->execute([
                    ':name' => $name,
                    ':name_check' => $name,
                    ':id' => $ba['money_account_id'],
                ]);
            } else {
                $ins = $localPdo->prepare(
                    'INSERT INTO finance_money_accounts
                        (type, name, bank_account_id, currency, opening_balance, opening_balance_date, is_active,
                         created_by_user_id, created_by_role, updated_by_user_id, updated_by_role)
                     VALUES
                        (\'BANK\', :name, :bank_account_id, :currency, 0.00, NULL, 1,
                         NULL, NULL, NULL, NULL)'
                );
                $ins->execute([
                    ':name' => $name,
                    ':bank_account_id' => $ba['id'],
                    ':currency' => $ba['currency'] ?? 'RUR',
                ]);
            }
        }
    }

    public static function createOperationFromBankTransaction(PDO $localPdo, int $bankTransactionId): ?int
    {
        $stmt = $localPdo->prepare(
            'SELECT bt.*, ba.id AS bank_account_id, ba.account_number
               FROM bank_transactions bt
               JOIN bank_accounts ba ON ba.id = bt.account_id
              WHERE bt.id = ?'
        );
        $stmt->execute([$bankTransactionId]);
        $tx = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tx) {
            return null;
        }

        $creditAmount = (string)($tx['credit_amount'] ?? '0.00');
        $debitAmount = (string)($tx['debit_amount'] ?? '0.00');

        $creditPositive = self::isPositiveDecimalString($creditAmount);
        $debitPositive = self::isPositiveDecimalString($debitAmount);

        if ($creditPositive && $debitPositive) {
            return null;
        }
        if (!$creditPositive && !$debitPositive) {
            return null;
        }

        $operationType = $creditPositive ? 'INCOME' : 'EXPENSE';
        $amount = $creditPositive ? $creditAmount : $debitAmount;

        $moneyAccountId = self::resolveMoneyAccountForBank($localPdo, $tx['bank_account_id']);
        if (!$moneyAccountId) {
            return null;
        }

        $dedupeHash = hash('sha256', 'bank_tx:' . $bankTransactionId . ':' . ($tx['dedupe_hash'] ?? ''));

        $check = $localPdo->prepare('SELECT id FROM finance_operations WHERE dedupe_hash = ?');
        $check->execute([$dedupeHash]);
        $existingId = $check->fetchColumn();
        if ($existingId) {
            return (int) $existingId;
        }

        $ins = $localPdo->prepare(
            'INSERT INTO finance_operations
                (operation_type, status, source, money_account_id, operation_date, amount, currency,
                 counterparty_entity_type, counterparty_entity_id, counterparty_name, counterparty_inn,
                 purpose, bank_transaction_id, dedupe_hash,
                 created_by_user_id, created_by_role, posted_by_user_id, posted_by_role, posted_at)
             VALUES
                (:operation_type, \'POSTED\', \'BANK_STATEMENT\', :money_account_id, :operation_date, :amount, :currency,
                 :counterparty_entity_type, :counterparty_entity_id, :counterparty_name, :counterparty_inn,
                 :purpose, :bank_transaction_id, :dedupe_hash,
                 :created_by_user_id, :created_by_role, :posted_by_user_id, :posted_by_role, NOW())'
        );
        $ins->execute([
            ':operation_type' => $operationType,
            ':money_account_id' => $moneyAccountId,
            ':operation_date' => $tx['operation_date'],
            ':amount' => $amount,
            ':currency' => $tx['currency'] ?? 'RUR',
            ':counterparty_entity_type' => null,
            ':counterparty_entity_id' => null,
            ':counterparty_name' => $tx['counterparty_name'] ?? null,
            ':counterparty_inn' => $tx['counterparty_inn'] ?? null,
            ':purpose' => $tx['purpose'] ?? null,
            ':bank_transaction_id' => $bankTransactionId,
            ':dedupe_hash' => $dedupeHash,
            ':created_by_user_id' => null,
            ':created_by_role' => null,
            ':posted_by_user_id' => null,
            ':posted_by_role' => null,
        ]);

        return (int) $localPdo->lastInsertId();
    }

    private static function isPositiveDecimalString(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        return (bool) preg_match('/^[1-9]\d*(\.\d+)?$|^0\.\d*[1-9]\d*$/', $value);
    }

    private static function resolveMoneyAccountForBank(PDO $localPdo, int $bankAccountId): ?int
    {
        $stmt = $localPdo->prepare('SELECT id FROM finance_money_accounts WHERE bank_account_id = ?');
        $stmt->execute([$bankAccountId]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int) $id;
        }
        self::ensureMoneyAccountsForBankAccounts($localPdo);
        $stmt->execute([$bankAccountId]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    public static function backfillBankOperations(PDO $localPdo): array
    {
        $created = 0;
        $skipped = 0;
        $errors = 0;

        $stmt = $localPdo->query(
            'SELECT bt.id
               FROM bank_transactions bt
          LEFT JOIN finance_operations fo ON fo.bank_transaction_id = bt.id
              WHERE fo.id IS NULL'
        );
        $txIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($txIds as $txId) {
            try {
                $result = self::createOperationFromBankTransaction($localPdo, (int) $txId);
                if ($result !== null) {
                    $created++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $errors++;
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    public static function fetchOperations(PDO $localPdo, array $filters = []): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['date_from'])) {
            $conditions[] = 'fo.operation_date >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $conditions[] = 'fo.operation_date <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }
        if (!empty($filters['operation_type'])) {
            $conditions[] = 'fo.operation_type = :operation_type';
            $params[':operation_type'] = $filters['operation_type'];
        }
        if (!empty($filters['status'])) {
            $conditions[] = 'fo.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['source'])) {
            $conditions[] = 'fo.source = :source';
            $params[':source'] = $filters['source'];
        }
        if (!empty($filters['search'])) {
            $conditions[] = '(fo.counterparty_name LIKE :search OR fo.purpose LIKE :search2)';
            $params[':search'] = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
        }

        $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(500, (int) ($filters['per_page'] ?? 100)));
        $offset = ($page - 1) * $perPage;

        $countSql = "SELECT COUNT(*) FROM finance_operations fo
                     JOIN finance_money_accounts fma ON fma.id = fo.money_account_id
                     {$where}";
        $countStmt = $localPdo->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT fo.*, fma.name AS account_name, fma.type AS account_type,
                       COALESCE((SELECT SUM(foa.amount)
                                   FROM finance_operation_allocations foa
                                  WHERE foa.operation_id = fo.id
                                    AND foa.cancelled_at IS NULL), 0) AS allocated_amount,
                       (fo.amount - COALESCE((SELECT SUM(foa.amount)
                                                 FROM finance_operation_allocations foa
                                                WHERE foa.operation_id = fo.id
                                                  AND foa.cancelled_at IS NULL), 0)) AS remaining_amount
                   FROM finance_operations fo
                   JOIN finance_money_accounts fma ON fma.id = fo.money_account_id
                   {$where}
                   ORDER BY fo.operation_date DESC, fo.id DESC
                   LIMIT :limit OFFSET :offset";

        $stmt = $localPdo->prepare($sql);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $allocated = (string) ($row['allocated_amount'] ?? '0.00');
            $remaining = (string) ($row['remaining_amount'] ?? $row['amount']);
            if (self::stringCompare($remaining, '0.00') <= 0) {
                $row['allocation_status'] = 'allocated';
            } elseif (self::stringCompare($allocated, '0.00') > 0) {
                $row['allocation_status'] = 'partially_allocated';
            } else {
                $row['allocation_status'] = 'unallocated';
            }
        }
        unset($row);

        return [
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => (int) ceil($total / max(1, $perPage)),
        ];
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

    public static function getSummaryTotals(PDO $localPdo, array $filters = []): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['date_from'])) {
            $conditions[] = 'fo.operation_date >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $conditions[] = 'fo.operation_date <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }
        if (!empty($filters['operation_type'])) {
            $conditions[] = 'fo.operation_type = :operation_type';
            $params[':operation_type'] = $filters['operation_type'];
        }
        if (!empty($filters['source'])) {
            $conditions[] = 'fo.source = :source';
            $params[':source'] = $filters['source'];
        }
        if (!empty($filters['search'])) {
            $conditions[] = '(fo.counterparty_name LIKE :search OR fo.purpose LIKE :search2)';
            $params[':search'] = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
        }

        $conditions[] = 'fo.status = \'POSTED\'';
        $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT
                    COALESCE(SUM(CASE WHEN fo.operation_type = 'INCOME' AND fo.source != 'TRANSFER' THEN fo.amount ELSE 0 END), 0) AS total_income,
                    COALESCE(SUM(CASE WHEN fo.operation_type = 'EXPENSE' AND fo.source != 'TRANSFER' THEN fo.amount ELSE 0 END), 0) AS total_expense
                FROM finance_operations fo
                {$where}";

        $stmt = $localPdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_income' => (string)($row['total_income'] ?? '0.00'),
            'total_expense' => (string)($row['total_expense'] ?? '0.00'),
        ];
    }

    public static function cancelOperation(PDO $pdo, int $operationId, array $user, string $reason, ?bool $skipAllocations = false): void
    {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT * FROM finance_operations WHERE id = ? FOR UPDATE");
            $stmt->execute([$operationId]);
            $operation = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$operation) {
                throw new \RuntimeException('Операция не найдена.');
            }
            if ($operation['status'] === 'CANCELLED') {
                throw new \RuntimeException('Операция уже отменена. Повторная отмена запрещена.');
            }
            if (!in_array($operation['status'], ['POSTED', 'PENDING_CONFIRMATION'])) {
                throw new \RuntimeException('Отменять можно только проведённые операции или ожидающие подтверждения.');
            }
            $reason = trim($reason);
            if ($reason === '') {
                throw new \RuntimeException('Укажите причину отмены.');
            }

            $userId = (int) ($user['user_id'] ?? 0);
            $roleCode = $user['role_code'] ?? '';

            if ($operation['operation_type'] === 'TRANSFER') {
                self::cancelTransferAtomic($pdo, $operation, $user, $reason);
                $pdo->commit();
                return;
            }

            if (!$skipAllocations) {
                $allocStmt = $pdo->prepare(
                    "SELECT id FROM finance_operation_allocations
                      WHERE operation_id = ? AND cancelled_at IS NULL
                      FOR UPDATE"
                );
                $allocStmt->execute([$operationId]);
                $activeAllocations = $allocStmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($activeAllocations as $allocId) {
                    FinanceAllocationService::cancelAllocation($pdo, (int) $allocId, $user, 'Автоотмена при отмене операции: ' . $reason);
                }
            }

            $oldStatus = $operation['status'];

            $upd = $pdo->prepare(
                "UPDATE finance_operations
                    SET status = 'CANCELLED',
                        cancelled_at = NOW(),
                        cancelled_by_user_id = :user_id,
                        cancelled_by_role = :role,
                        cancellation_reason = :reason
                  WHERE id = :id"
            );
            $upd->execute([
                ':user_id' => $userId,
                ':role' => $roleCode,
                ':reason' => $reason,
                ':id' => $operationId,
            ]);

            FinanceAuditLogService::log($pdo, 'finance_operation', $operationId, 'cancel', [
                'status' => $oldStatus,
                'operation_type' => $operation['operation_type'],
                'amount' => $operation['amount'],
            ], [
                'status' => 'CANCELLED',
                'cancelled_at' => date('Y-m-d H:i:s'),
                'cancellation_reason' => $reason,
            ], $userId, $roleCode);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function cancelTransferAtomic(PDO $pdo, array $operation, array $user, string $reason): void
    {
        $transferGroupId = $operation['transfer_group_id'] ?? null;
        if ($transferGroupId === null) {
            throw new \RuntimeException('Перевод не имеет transfer_group_id. Отмена невозможна.');
        }

        $stmt = $pdo->prepare(
            "SELECT * FROM finance_operations
              WHERE transfer_group_id = ?
                AND operation_type = 'TRANSFER'
              ORDER BY id ASC
              FOR UPDATE"
        );
        $stmt->execute([$transferGroupId]);
        $legs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($legs) !== 2) {
            throw new \RuntimeException('Перевод должен содержать ровно 2 legs (out/in). Найдено: ' . count($legs));
        }

        $userId = (int) ($user['user_id'] ?? 0);
        $roleCode = $user['role_code'] ?? '';

        foreach ($legs as $leg) {
            if ($leg['status'] === 'CANCELLED') {
                throw new \RuntimeException('Одна из ног перевода уже отменена. Отмена неатомарна.');
            }
            if (!in_array($leg['status'], ['POSTED', 'PENDING_CONFIRMATION'])) {
                throw new \RuntimeException('Нога перевода не проведена. Отмена невозможна.');
            }

            $upd = $pdo->prepare(
                "UPDATE finance_operations
                    SET status = 'CANCELLED',
                        cancelled_at = NOW(),
                        cancelled_by_user_id = :user_id,
                        cancelled_by_role = :role,
                        cancellation_reason = :reason
                  WHERE id = :id"
            );
            $upd->execute([
                ':user_id' => $userId,
                ':role' => $roleCode,
                ':reason' => $reason,
                ':id' => $leg['id'],
            ]);

            FinanceAuditLogService::log($pdo, 'finance_operation', $leg['id'], 'cancel', [
                'status' => $leg['status'],
                'operation_type' => $leg['operation_type'],
                'amount' => $leg['amount'],
                'transfer_direction' => $leg['transfer_direction'],
            ], [
                'status' => 'CANCELLED',
                'cancelled_at' => date('Y-m-d H:i:s'),
                'cancellation_reason' => $reason,
            ], $userId, $roleCode);
        }
    }

    public static function findMatchingTransferForBankTransaction(PDO $localPdo, int $bankTransactionId): ?array
    {
        $stmt = $localPdo->prepare(
            'SELECT bt.*, ba.id AS bank_account_id, ba.account_number
               FROM bank_transactions bt
               JOIN bank_accounts ba ON ba.id = bt.account_id
              WHERE bt.id = ?'
        );
        $stmt->execute([$bankTransactionId]);
        $tx = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tx) {
            return null;
        }

        // Bank transaction reuse guard: already linked to a non-cancelled finance operation
        $reuseStmt = $localPdo->prepare(
            "SELECT id, status FROM finance_operations WHERE bank_transaction_id = ? AND status != 'CANCELLED' LIMIT 1"
        );
        $reuseStmt->execute([$bankTransactionId]);
        $alreadyLinked = $reuseStmt->fetch(PDO::FETCH_ASSOC);
        if ($alreadyLinked) {
            return null;
        }

        $creditAmount = (string)($tx['credit_amount'] ?? '0.00');
        $debitAmount = (string)($tx['debit_amount'] ?? '0.00');
        $creditPositive = self::isPositiveDecimalString($creditAmount);
        $debitPositive = self::isPositiveDecimalString($debitAmount);

        if ($creditPositive && $debitPositive) {
            return null;
        }
        if (!$creditPositive && !$debitPositive) {
            return null;
        }

        $moneyAccountId = self::resolveMoneyAccountForBank($localPdo, $tx['bank_account_id']);
        if (!$moneyAccountId) {
            return null;
        }

        $transferDirection = $creditPositive ? 'in' : 'out';
        $amount = $creditPositive ? $creditAmount : $debitAmount;
        $txDate = $tx['operation_date'];
        $dateFrom = date('Y-m-d', strtotime($txDate . ' -3 days'));
        $dateTo = date('Y-m-d', strtotime($txDate . ' +3 days'));
        $purpose = $tx['purpose'] ?? '';

        $stmt = $localPdo->prepare(
            'SELECT fo.*, fma.name AS account_name, fma.type AS account_type
               FROM finance_operations fo
               JOIN finance_money_accounts fma ON fma.id = fo.money_account_id
              WHERE fo.operation_type = \'TRANSFER\'
                AND fo.status = \'PENDING_CONFIRMATION\'
                AND fo.money_account_id = :money_account_id
                AND fo.transfer_direction = :transfer_direction
                AND fo.amount = :amount
                AND fo.operation_date BETWEEN :date_from AND :date_to
                AND fo.bank_transaction_id IS NULL
              ORDER BY fo.operation_date ASC, fo.id ASC'
        );
        $stmt->execute([
            ':money_account_id' => $moneyAccountId,
            ':transfer_direction' => $transferDirection,
            ':amount' => $amount,
            ':date_from' => $dateFrom,
            ':date_to' => $dateTo,
        ]);
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($candidates) === 0) {
            return null;
        }

        $scored = [];
        foreach ($candidates as $candidate) {
            $score = 0;
            if (self::stringCompare($candidate['amount'], $amount) === 0) {
                $score += 10;
            }
            if ($candidate['operation_date'] === $txDate) {
                $score += 5;
            }
            $candPurpose = $candidate['purpose'] ?? '';
            if ($candPurpose !== '' && $purpose !== '' && (
                stripos($candPurpose, $purpose) !== false || stripos($purpose, $candPurpose) !== false
            )) {
                $score += 3;
            }
            $scored[] = [
                'transfer' => $candidate,
                'score' => $score,
            ];
        }

        usort($scored, function ($a, $b) {
            return $b['score'] - $a['score'];
        });

        $bestScore = $scored[0]['score'] ?? 0;
        $bestCandidates = array_filter($scored, function ($s) use ($bestScore) {
            return $s['score'] === $bestScore;
        });

        if (count($bestCandidates) === 1) {
            $result = reset($bestCandidates);
            $result['match_quality'] = 'exact';
            $result['candidates_count'] = count($candidates);
            return $result;
        }

        return [
            'match_quality' => 'ambiguous',
            'candidates_count' => count($candidates),
            'candidates' => array_map(function ($s) { return $s['transfer']; }, $bestCandidates),
            'transfer' => null,
        ];
    }

    public static function confirmTransfer(PDO $localPdo, string $transferGroupId, int $bankTransactionId, array $user): array
    {
        $startedTransaction = false;

        // Transaction ownership pattern: only begin if not already inside a transaction
        if (!$localPdo->inTransaction()) {
            $localPdo->beginTransaction();
            $startedTransaction = true;
        }

        try {
            // Lock the bank transaction row first (inside active transaction)
            $stmtTx = $localPdo->prepare(
                'SELECT bt.*, ba.id AS bank_account_id
                   FROM bank_transactions bt
                   JOIN bank_accounts ba ON ba.id = bt.account_id
                  WHERE bt.id = ?
                  FOR UPDATE'
            );
            $stmtTx->execute([$bankTransactionId]);
            $bankTx = $stmtTx->fetch(PDO::FETCH_ASSOC);
            if (!$bankTx) {
                throw new \RuntimeException('Банковская транзакция не найдена.');
            }

            // Authoritative bank transaction reuse guard — inside the active transaction
            $reuseStmt = $localPdo->prepare(
                "SELECT id, status FROM finance_operations WHERE bank_transaction_id = ? AND status != 'CANCELLED' LIMIT 1"
            );
            $reuseStmt->execute([$bankTransactionId]);
            $alreadyLinked = $reuseStmt->fetch(PDO::FETCH_ASSOC);
            if ($alreadyLinked) {
                throw new \RuntimeException(
                    'Банковская транзакция уже привязана к операции #' . $alreadyLinked['id']
                    . ' (' . $alreadyLinked['status'] . '). Повторное использование запрещено.'
                );
            }

            // Lock transfer legs with FOR UPDATE
            $stmt = $localPdo->prepare(
                "SELECT * FROM finance_operations
                  WHERE transfer_group_id = ?
                    AND operation_type = 'TRANSFER'
                  ORDER BY id ASC
                  FOR UPDATE"
            );
            $stmt->execute([$transferGroupId]);
            $legs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($legs) !== 2) {
                throw new \RuntimeException('Перевод должен содержать ровно 2 legs (out/in). Найдено: ' . count($legs));
            }

            $creditPositive = self::isPositiveDecimalString((string)($bankTx['credit_amount'] ?? '0.00'));
            $matchingDirection = $creditPositive ? 'in' : 'out';

            $moneyAccountId = self::resolveMoneyAccountForBank($localPdo, $bankTx['bank_account_id']);
            if (!$moneyAccountId) {
                throw new \RuntimeException('Не удалось найти money_account для банковского счёта.');
            }

            $matchingLeg = null;
            foreach ($legs as $leg) {
                if ((int)$leg['money_account_id'] === $moneyAccountId && $leg['transfer_direction'] === $matchingDirection) {
                    $matchingLeg = $leg;
                    break;
                }
            }

            if (!$matchingLeg) {
                throw new \RuntimeException('Не удалось найти соответствующую ногу перевода для банковской транзакции.');
            }

            // Race avoidance: verify no bank_transaction_id already set on matching leg
            if ($matchingLeg['bank_transaction_id'] !== null) {
                throw new \RuntimeException('Нога перевода уже привязана к банковской транзакции #' . $matchingLeg['bank_transaction_id'] . '.');
            }

            foreach ($legs as $leg) {
                if (!in_array($leg['status'], ['PENDING_CONFIRMATION', 'POSTED'])) {
                    throw new \RuntimeException('Нога перевода имеет недопустимый статус: ' . $leg['status']);
                }
            }

            $userId = (int) ($user['user_id'] ?? 0);
            $roleCode = $user['role_code'] ?? '';
            $now = date('Y-m-d H:i:s');

            foreach ($legs as $leg) {
                if ($leg['status'] === 'PENDING_CONFIRMATION') {
                    $upd = $localPdo->prepare(
                        "UPDATE finance_operations
                            SET status = 'POSTED',
                                posted_at = :posted_at,
                                posted_by_user_id = :user_id,
                                posted_by_role = :role,
                                bank_transaction_id = CASE WHEN id = :matching_leg_id THEN :bank_tx_id ELSE bank_transaction_id END,
                                updated_at = NOW()
                          WHERE id = :id
                            AND (id != :matching_leg_id2 OR bank_transaction_id IS NULL)"
                    );
                    $upd->execute([
                        ':posted_at' => $now,
                        ':user_id' => $userId,
                        ':role' => $roleCode,
                        ':matching_leg_id' => $matchingLeg['id'],
                        ':bank_tx_id' => $bankTransactionId,
                        ':id' => $leg['id'],
                        ':matching_leg_id2' => $matchingLeg['id'],
                    ]);

                    // Verify the matching leg update affected a row (race avoidance)
                    if ((int)$leg['id'] === (int)$matchingLeg['id'] && $upd->rowCount() === 0) {
                        throw new \RuntimeException('Не удалось обновить ногу перевода: bank_transaction_id уже установлен (race).');
                    }

                    FinanceAuditLogService::log($localPdo, 'finance_operation', (int)$leg['id'], 'confirm', [
                        'status' => $leg['status'],
                        'operation_type' => $leg['operation_type'],
                        'amount' => $leg['amount'],
                        'transfer_direction' => $leg['transfer_direction'],
                    ], [
                        'status' => 'POSTED',
                        'posted_at' => $now,
                        'bank_transaction_id' => $bankTransactionId,
                    ], $userId, $roleCode);
                }
            }

            // Only commit if this method started the transaction
            if ($startedTransaction) {
                $localPdo->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTransaction) {
                $localPdo->rollBack();
            }
            throw $e;
        }

        return [
            'transfer_group_id' => $transferGroupId,
            'matching_leg_id' => (int)$matchingLeg['id'],
            'bank_transaction_id' => $bankTransactionId,
            'amount' => $matchingLeg['amount'],
        ];
    }

    public static function fetchAuditLog(PDO $pdo, string $entityType, int $entityId, int $limit = 50): array
    {
        $stmt = $pdo->prepare(
            "SELECT * FROM finance_audit_log
              WHERE entity_type = :entity_type AND entity_id = :entity_id
              ORDER BY created_at DESC
              LIMIT :limit"
        );
        $stmt->bindValue(':entity_type', $entityType, PDO::PARAM_STR);
        $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function calculateMoneyAccountBalances(PDO $localPdo): array
    {
        $allBalances = FinanceBalanceService::allBalances($localPdo);

        $stmt = $localPdo->query(
            'SELECT id, name, type, opening_balance, currency FROM finance_money_accounts ORDER BY name ASC'
        );
        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $balMap = [];
        foreach ($allBalances as $b) {
            $balMap[$b['id']] = $b['balance'];
        }

        $result = [];
        foreach ($accounts as $row) {
            $aid = (int) $row['id'];
            $result[] = [
                'id' => $aid,
                'name' => $row['name'],
                'type' => $row['type'],
                'currency' => $row['currency'],
                'opening_balance' => (string) ($row['opening_balance'] ?? '0.00'),
                'total_income' => '0.00',
                'total_expense' => '0.00',
                'total_transfer_in' => '0.00',
                'total_transfer_out' => '0.00',
                'balance' => $balMap[$aid] ?? '0.00',
            ];
        }

        return $result;
    }
}
