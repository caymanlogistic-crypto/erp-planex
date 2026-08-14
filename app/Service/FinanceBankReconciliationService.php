<?php

namespace App\Service;

use PDO;

final class FinanceBankReconciliationService
{
    public static function reconcileAll(PDO $localPdo, ?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? date('Y-m-d');
        $stmt = $localPdo->query(
            'SELECT id FROM bank_accounts ORDER BY bank_name, account_number'
        );
        $accounts = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $results = [];
        foreach ($accounts as $aid) {
            $results[] = self::reconcileAccount($localPdo, (int)$aid, $asOfDate);
        }

        return [
            'as_of_date' => $asOfDate,
            'accounts' => $results,
            'summary' => self::summarize($results),
        ];
    }

    public static function reconcileAccount(PDO $localPdo, int $accountId, ?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? date('Y-m-d');

        $stmt = $localPdo->prepare('SELECT * FROM bank_accounts WHERE id = ?');
        $stmt->execute([$accountId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$account) {
            return [
                'account_id' => $accountId,
                'account_number' => '',
                'account_name' => 'Unknown',
                'status' => 'NOT_FOUND',
                'statement_arithmetic' => [],
                'continuity' => null,
                'current_balance' => null,
            ];
        }

        $arithmeticResults = self::checkStatementArithmetic($localPdo, $accountId);
        $continuityResult = self::checkContinuity($localPdo, $accountId);
        $currentBalance = self::computeCurrentBalance($localPdo, $accountId, $asOfDate);
        $duplicateCheck = self::checkDuplicates($localPdo, $accountId);
        $transactionAggregateCheck = self::checkTransactionAggregates($localPdo, $accountId);

        $hasArithmeticError = false;
        foreach ($arithmeticResults as $r) {
            if ($r['status'] !== 'OK') {
                $hasArithmeticError = true;
                break;
            }
        }

        $hasContinuityError = $continuityResult && $continuityResult['status'] !== 'OK';
        $hasDuplicateError = !empty($duplicateCheck);
        $hasAggregateError = !empty($transactionAggregateCheck['mismatches']);

        if ($hasArithmeticError || $hasContinuityError || $hasDuplicateError || $hasAggregateError) {
            if ($hasArithmeticError) {
                $status = 'INVALID_ARITHMETIC';
            } elseif ($hasContinuityError) {
                $status = 'GAP';
            } elseif ($hasDuplicateError) {
                $status = 'DUPLICATE';
            } else {
                $status = 'MISMATCH';
            }
        } elseif (empty($arithmeticResults)) {
            $status = 'NO_STATEMENT';
        } else {
            $status = 'OK';
        }

        return [
            'account_id' => $accountId,
            'account_number' => $account['account_number'] ?? '',
            'account_name' => $account['bank_name'] ?? ('Банковский счёт #' . $accountId),
            'status' => $status,
            'statement_arithmetic' => $arithmeticResults,
            'continuity' => $continuityResult,
            'current_balance' => $currentBalance,
            'duplicate_check' => $duplicateCheck,
            'transaction_aggregates' => $transactionAggregateCheck,
        ];
    }

    public static function checkStatementArithmetic(PDO $localPdo, ?int $accountId = null): array
    {
        $sql = 'SELECT bdb.*, ba.account_number, ba.bank_name
                FROM bank_daily_balances bdb
                JOIN bank_accounts ba ON ba.id = bdb.account_id';
        $params = [];
        if ($accountId !== null) {
            $sql .= ' WHERE bdb.account_id = ?';
            $params[] = $accountId;
        }
        $sql .= ' ORDER BY ba.account_number, bdb.statement_date ASC, bdb.id ASC';

        $stmt = $localPdo->prepare($sql);
        $stmt->execute($params);
        $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($balances as $b) {
            $opening = self::normalizeAmount($b['opening_balance'] ?? '0.00');
            $debit = self::normalizeAmount($b['debit_turnover'] ?? '0.00');
            $credit = self::normalizeAmount($b['credit_turnover'] ?? '0.00');
            $closing = self::normalizeAmount($b['closing_balance'] ?? '0.00');

            $computedClosing = self::add(self::add($opening, $credit), self::sub('0.00', $debit));

            $match = self::compare($computedClosing, $closing) === 0;

            $results[] = [
                'balance_id' => (int)$b['id'],
                'account_id' => (int)$b['account_id'],
                'account_number' => $b['account_number'],
                'statement_date' => $b['statement_date'],
                'opening_balance' => $opening,
                'debit_turnover' => $debit,
                'credit_turnover' => $credit,
                'stated_closing_balance' => $closing,
                'computed_closing_balance' => $computedClosing,
                'status' => $match ? 'OK' : 'MISMATCH',
                'difference' => $match ? '0.00' : self::sub($computedClosing, $closing),
            ];
        }

        return $results;
    }

    public static function checkContinuity(PDO $localPdo, ?int $accountId = null): ?array
    {
        $sql = 'SELECT bdb.*, ba.account_number, ba.bank_name
                FROM bank_daily_balances bdb
                JOIN bank_accounts ba ON ba.id = bdb.account_id';
        $params = [];
        if ($accountId !== null) {
            $sql .= ' WHERE bdb.account_id = ?';
            $params[] = $accountId;
        }
        $sql .= ' ORDER BY ba.account_number, bdb.statement_date ASC, bdb.id ASC';

        $stmt = $localPdo->prepare($sql);
        $stmt->execute($params);
        $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($all as $i => $b) {
            if ($i === 0) {
                $results[] = [
                    'balance_id' => (int)$b['id'],
                    'account_id' => (int)$b['account_id'],
                    'account_number' => $b['account_number'],
                    'statement_date' => $b['statement_date'],
                    'status' => 'FIRST',
                    'opening_balance' => self::normalizeAmount($b['opening_balance'] ?? '0.00'),
                    'previous_closing_balance' => null,
                    'previous_date' => null,
                ];
                continue;
            }

            $prev = $all[$i - 1];
            $prevClosing = self::normalizeAmount($prev['closing_balance'] ?? '0.00');
            $currOpening = self::normalizeAmount($b['opening_balance'] ?? '0.00');
            $match = self::compare($prevClosing, $currOpening) === 0;

            $results[] = [
                'balance_id' => (int)$b['id'],
                'account_id' => (int)$b['account_id'],
                'account_number' => $b['account_number'],
                'statement_date' => $b['statement_date'],
                'status' => $match ? 'OK' : 'GAP',
                'opening_balance' => $currOpening,
                'previous_closing_balance' => $prevClosing,
                'previous_date' => $prev['statement_date'],
                'difference' => $match ? '0.00' : self::sub($prevClosing, $currOpening),
            ];
        }

        if (empty($results)) {
            return null;
        }

        $hasGap = false;
        foreach ($results as $r) {
            if ($r['status'] === 'GAP') {
                $hasGap = true;
                break;
            }
        }

        return [
            'status' => $hasGap ? 'GAP' : 'OK',
            'items' => $results,
        ];
    }

    public static function computeCurrentBalance(PDO $localPdo, int $accountId, ?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? date('Y-m-d');

        $stmt = $localPdo->prepare(
            'SELECT closing_balance, statement_date
             FROM bank_daily_balances
             WHERE account_id = ? AND statement_date <= ?
             ORDER BY statement_date DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute([$accountId, $asOfDate]);
        $snapshot = $stmt->fetch(PDO::FETCH_ASSOC);

        $snapshotBalance = '0.00';
        $snapshotDate = null;

        if ($snapshot) {
            $snapshotBalance = self::normalizeAmount($snapshot['closing_balance']);
            $snapshotDate = $snapshot['statement_date'];
        }

        $moneyAccountIdStmt = $localPdo->prepare('SELECT id FROM finance_money_accounts WHERE bank_account_id = ?');
        $moneyAccountIdStmt->execute([$accountId]);
        $moneyAccountId = $moneyAccountIdStmt->fetchColumn();

        $postSnapshotInflow = '0.00';
        $postSnapshotOutflow = '0.00';
        $postSnapshotCount = 0;

        if ($moneyAccountId && $snapshotDate) {
            $stmt = $localPdo->prepare(
                "SELECT
                    COALESCE(SUM(CASE WHEN fo.operation_type = 'INCOME' THEN fo.amount
                                      WHEN fo.operation_type = 'TRANSFER' AND fo.transfer_direction = 'in' THEN fo.amount
                                      ELSE 0 END), 0) AS total_inflow,
                    COALESCE(SUM(CASE WHEN fo.operation_type = 'EXPENSE' THEN fo.amount
                                      WHEN fo.operation_type = 'TRANSFER' AND fo.transfer_direction = 'out' THEN fo.amount
                                      ELSE 0 END), 0) AS total_outflow
                 FROM finance_operations fo
                 WHERE fo.money_account_id = (SELECT id FROM finance_money_accounts WHERE bank_account_id = ? LIMIT 1)
                   AND fo.status = 'POSTED'
                   AND fo.operation_date > ?
                   AND fo.operation_date <= ?
                   AND fo.cancelled_at IS NULL"
            );
            $stmt->execute([$accountId, $snapshotDate, $asOfDate]);
            $ops = $stmt->fetch(PDO::FETCH_ASSOC);
            $postSnapshotInflow = self::normalizeAmount($ops['total_inflow'] ?? '0.00');
            $postSnapshotOutflow = self::normalizeAmount($ops['total_outflow'] ?? '0.00');
            $postSnapshotCount = 0;

            $cntStmt = $localPdo->prepare(
                "SELECT COUNT(*) FROM finance_operations
                 WHERE money_account_id = (SELECT id FROM finance_money_accounts WHERE bank_account_id = ? LIMIT 1)
                   AND status = 'POSTED'
                   AND operation_date > ?
                   AND operation_date <= ?
                   AND cancelled_at IS NULL"
            );
            $cntStmt->execute([$accountId, $snapshotDate, $asOfDate]);
            $postSnapshotCount = (int)$cntStmt->fetchColumn();
        }

        $currentBalance = self::add(
            self::add($snapshotBalance, $postSnapshotInflow),
            self::sub('0.00', $postSnapshotOutflow)
        );

        $pendingStmt = $localPdo->prepare(
            "SELECT COALESCE(SUM(amount), 0) AS total
             FROM finance_operations
             WHERE money_account_id = (SELECT id FROM finance_money_accounts WHERE bank_account_id = ? LIMIT 1)
               AND status IN ('DRAFT', 'PLANNED')
               AND operation_date <= ?
               AND cancelled_at IS NULL"
        );
        $pendingTotal = '0.00';
        if ($moneyAccountId) {
            $pendingStmt->execute([$accountId, $asOfDate]);
            $pendingTotal = self::normalizeAmount($pendingStmt->fetchColumn());
        }

        return [
            'snapshot_date' => $snapshotDate,
            'statement_confirmed_balance' => $snapshotBalance,
            'post_snapshot_confirmed_inflows' => $postSnapshotInflow,
            'post_snapshot_confirmed_outflows' => $postSnapshotOutflow,
            'post_snapshot_operation_count' => $postSnapshotCount,
            'current_calculated_balance' => $currentBalance,
            'pending_unconfirmed_movements' => $pendingTotal,
            'forecast_balance' => self::add($currentBalance, $pendingTotal),
            'has_snapshot' => $snapshot !== false,
        ];
    }

    public static function checkDuplicates(PDO $localPdo, ?int $accountId = null): array
    {
        $where = '';
        $params = [];
        if ($accountId !== null) {
            $where = ' WHERE account_id = ?';
            $params[] = $accountId;
        }

        $sql = 'SELECT bt.id, bt.account_id, bt.operation_date, bt.document_number,
                       bt.debit_amount, bt.credit_amount, bt.counterparty_name,
                       bt.purpose, bt.dedupe_hash, dup.hash_count
                FROM bank_transactions bt
                JOIN (
                    SELECT dedupe_hash, COUNT(*) AS hash_count
                    FROM bank_transactions' . $where . '
                    GROUP BY dedupe_hash
                    HAVING COUNT(*) > 1
                ) dup ON dup.dedupe_hash <=> bt.dedupe_hash';
        if ($accountId !== null) {
            $sql .= ' WHERE bt.account_id = ?';
            $params[] = $accountId;
        }
        $sql .= ' ORDER BY bt.account_id, bt.operation_date, bt.id';

        $stmt = $localPdo->prepare($sql);
        $stmt->execute($params);
        $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($duplicates as $d) {
            $results[] = [
                'transaction_id' => (int)$d['id'],
                'account_id' => (int)$d['account_id'],
                'operation_date' => $d['operation_date'],
                'document_number' => $d['document_number'],
                'debit_amount' => $d['debit_amount'],
                'credit_amount' => $d['credit_amount'],
                'counterparty_name' => $d['counterparty_name'],
                'purpose' => $d['purpose'],
                'dedupe_hash' => $d['dedupe_hash'],
                'hash_count' => (int)$d['hash_count'],
            ];
        }

        return $results;
    }

    public static function checkTransactionAggregates(PDO $localPdo, ?int $accountId = null): array
    {
        $sql = 'SELECT
                    bdb.id AS balance_id,
                    bdb.account_id,
                    bdb.statement_date,
                    bdb.debit_turnover,
                    bdb.credit_turnover,
                    COALESCE(bt_sum.debit_sum, 0) AS tx_debit_sum,
                    COALESCE(bt_sum.credit_sum, 0) AS tx_credit_sum,
                    bt_sum.tx_count
                FROM bank_daily_balances bdb
                LEFT JOIN (
                    SELECT
                        bt.account_id,
                        bt.operation_date,
                        SUM(CAST(bt.debit_amount AS DECIMAL(15,2))) AS debit_sum,
                        SUM(CAST(bt.credit_amount AS DECIMAL(15,2))) AS credit_sum,
                        COUNT(*) AS tx_count
                    FROM bank_transactions bt
                    GROUP BY bt.account_id, bt.operation_date
                ) bt_sum ON bt_sum.account_id = bdb.account_id AND bt_sum.operation_date = bdb.statement_date';
        $params = [];
        if ($accountId !== null) {
            $sql .= ' WHERE bdb.account_id = ?';
            $params[] = $accountId;
        }
        $sql .= ' ORDER BY bdb.account_id, bdb.statement_date';

        $stmt = $localPdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        $mismatches = [];
        foreach ($rows as $r) {
            $balDebit = self::normalizeAmount($r['debit_turnover'] ?? '0.00');
            $balCredit = self::normalizeAmount($r['credit_turnover'] ?? '0.00');
            $txDebit = self::normalizeAmount($r['tx_debit_sum'] ?? '0.00');
            $txCredit = self::normalizeAmount($r['tx_credit_sum'] ?? '0.00');

            $debitMatch = self::compare($balDebit, $txDebit) === 0;
            $creditMatch = self::compare($balCredit, $txCredit) === 0;
            $ok = $debitMatch && $creditMatch;

            $item = [
                'balance_id' => (int)$r['balance_id'],
                'account_id' => (int)$r['account_id'],
                'statement_date' => $r['statement_date'],
                'stated_debit_turnover' => $balDebit,
                'stated_credit_turnover' => $balCredit,
                'aggregated_tx_debit' => $txDebit,
                'aggregated_tx_credit' => $txCredit,
                'transaction_count' => (int)($r['tx_count'] ?? 0),
                'status' => $ok ? 'OK' : 'MISMATCH',
            ];
            $results[] = $item;
            if (!$ok) {
                $mismatches[] = $item;
            }
        }

        return [
            'items' => $results,
            'mismatches' => $mismatches,
        ];
    }

    public static function bankBalanceWithPostSnapshot(PDO $localPdo, ?int $bankAccountId, string $asOfDate): array
    {
        if (!$bankAccountId) {
            return [
                'balance' => '0.00',
                'source' => 'no_data',
                'snapshot_date' => null,
            ];
        }

        $snapshotStmt = $localPdo->prepare(
            'SELECT closing_balance, statement_date
             FROM bank_daily_balances
             WHERE account_id = ? AND statement_date <= ?
             ORDER BY statement_date DESC, id DESC
             LIMIT 1'
        );
        $snapshotStmt->execute([$bankAccountId, $asOfDate]);
        $snapshot = $snapshotStmt->fetch(PDO::FETCH_ASSOC);

        $snapshotBalance = '0.00';
        $snapshotDate = null;

        if ($snapshot) {
            $snapshotBalance = self::normalizeAmount($snapshot['closing_balance']);
            $snapshotDate = $snapshot['statement_date'];
        }

        $stmt = $localPdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN fo.operation_type = 'INCOME' THEN fo.amount
                                  WHEN fo.operation_type = 'TRANSFER' AND fo.transfer_direction = 'in' THEN fo.amount
                                  ELSE 0 END), 0) AS total_inflow,
                COALESCE(SUM(CASE WHEN fo.operation_type = 'EXPENSE' THEN fo.amount
                                  WHEN fo.operation_type = 'TRANSFER' AND fo.transfer_direction = 'out' THEN fo.amount
                                  ELSE 0 END), 0) AS total_outflow
             FROM finance_operations fo
             WHERE fo.money_account_id = (SELECT id FROM finance_money_accounts WHERE bank_account_id = ? LIMIT 1)
               AND fo.status = 'POSTED'
               AND fo.operation_date > ?
               AND fo.operation_date <= ?
               AND fo.cancelled_at IS NULL"
        );

        $postInflow = '0.00';
        $postOutflow = '0.00';

        if ($snapshotDate) {
            $stmt->execute([$bankAccountId, $snapshotDate, $asOfDate]);
            $ops = $stmt->fetch(PDO::FETCH_ASSOC);
            $postInflow = self::normalizeAmount($ops['total_inflow'] ?? '0.00');
            $postOutflow = self::normalizeAmount($ops['total_outflow'] ?? '0.00');
        }

        $currentBalance = self::add(
            self::add($snapshotBalance, $postInflow),
            self::sub('0.00', $postOutflow)
        );

        if (!$snapshot) {
            $baStmt = $localPdo->prepare('SELECT closing_balance FROM bank_accounts WHERE id = ?');
            $baStmt->execute([$bankAccountId]);
            $ba = $baStmt->fetch(PDO::FETCH_ASSOC);
            if ($ba && $ba['closing_balance'] !== null && $ba['closing_balance'] !== '') {
                $currentBalance = self::normalizeAmount($ba['closing_balance']);
                return [
                    'balance' => $currentBalance,
                    'source' => 'bank_accounts_fallback',
                    'snapshot_date' => null,
                ];
            }
        }

        return [
            'balance' => $currentBalance,
            'source' => $snapshotDate ? 'confirmed_snapshot_with_post_snapshot' : 'no_data',
            'snapshot_date' => $snapshotDate,
            'snapshot_balance' => $snapshotBalance,
            'post_snapshot_inflow' => $postInflow,
            'post_snapshot_outflow' => $postOutflow,
        ];
    }

    private static function normalizeAmount(mixed $value): string
    {
        if ($value === null || $value === '' || $value === false) {
            return '0.00';
        }
        $v = str_replace(',', '.', (string) $value);
        if (!preg_match('/^-?\d+(\.\d+)?$/', $v)) {
            return '0.00';
        }
        return $v;
    }

    private static function parseCents(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '0' || $value === '0.00') {
            return 0;
        }
        $neg = false;
        if ($value[0] === '-') {
            $neg = true;
            $value = substr($value, 1);
        }
        $parts = explode('.', $value, 2);
        $intPart = $parts[0] === '' ? '0' : $parts[0];
        $intPart = ltrim($intPart, '0');
        if ($intPart === '') {
            $intPart = '0';
        }
        $decPart = isset($parts[1]) ? str_pad(substr($parts[1] . '00', 0, 2), 2, '0') : '00';
        $cents = (int) $intPart * 100 + (int) $decPart;
        return $neg ? -$cents : $cents;
    }

    private static function formatCents(int $cents): string
    {
        if ($cents < 0) {
            return '-' . self::formatCents(-$cents);
        }
        $intResult = intdiv($cents, 100);
        $decResult = $cents % 100;
        return $intResult . '.' . str_pad((string) $decResult, 2, '0', STR_PAD_LEFT);
    }

    public static function add(string $a, string $b): string
    {
        return self::formatCents(self::parseCents($a) + self::parseCents($b));
    }

    public static function sub(string $a, string $b): string
    {
        return self::formatCents(self::parseCents($a) - self::parseCents($b));
    }

    public static function compare(string $a, string $b): int
    {
        return self::parseCents($a) <=> self::parseCents($b);
    }

    private static function summarize(array $results): array
    {
        $total = count($results);
        $ok = 0;
        $mismatch = 0;
        $gap = 0;
        $duplicate = 0;
        $invalidArithmetic = 0;
        $noStatement = 0;
        $notFound = 0;

        foreach ($results as $r) {
            switch ($r['status']) {
                case 'OK': $ok++; break;
                case 'MISMATCH': $mismatch++; break;
                case 'GAP': $gap++; break;
                case 'DUPLICATE': $duplicate++; break;
                case 'INVALID_ARITHMETIC': $invalidArithmetic++; break;
                case 'NO_STATEMENT': $noStatement++; break;
                case 'NOT_FOUND': $notFound++; break;
            }
        }

        return [
            'total_accounts' => $total,
            'ok' => $ok,
            'mismatch' => $mismatch,
            'gap' => $gap,
            'duplicate' => $duplicate,
            'invalid_arithmetic' => $invalidArithmetic,
            'no_statement' => $noStatement,
            'not_found' => $notFound,
            'all_ok' => ($mismatch + $gap + $duplicate + $invalidArithmetic) === 0,
        ];
    }
}
