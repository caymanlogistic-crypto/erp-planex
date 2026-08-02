<?php

namespace App\Service;

use PDO;

final class FinanceBalanceService
{
    public static function balanceForMoneyAccount(PDO $localPdo, int $accountId, ?string $asOfDate = null): string
    {
        $stmt = $localPdo->prepare('SELECT type, bank_account_id FROM finance_money_accounts WHERE id = ?');
        $stmt->execute([$accountId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$account) {
            return '0.00';
        }

        $asOfDate = $asOfDate ?? date('Y-m-d');

        if ($account['type'] === 'BANK') {
            $result = self::bankBalance($localPdo, $account['bank_account_id'], $asOfDate);
            return $result['balance'];
        }

        return self::cashOnlyBalance($localPdo, $accountId, $asOfDate);
    }

    public static function allBalances(PDO $localPdo, ?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? date('Y-m-d');

        $stmt = $localPdo->query(
            'SELECT id, type, name, bank_account_id FROM finance_money_accounts WHERE is_active = 1 ORDER BY type, name'
        );
        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($accounts as $a) {
            $aid = (int) $a['id'];
            if ($a['type'] === 'BANK') {
                $bal = self::bankBalance($localPdo, $a['bank_account_id'], $asOfDate);
                $result[] = [
                    'id' => $aid,
                    'name' => $a['name'],
                    'type' => $a['type'],
                    'balance' => $bal['balance'],
                    'balance_source' => $bal['source'],
                    'snapshot_date' => $bal['snapshot_date'] ?? null,
                ];
            } else {
                $balance = self::cashOnlyBalance($localPdo, $aid, $asOfDate);
                $result[] = [
                    'id' => $aid,
                    'name' => $a['name'],
                    'type' => $a['type'],
                    'balance' => $balance,
                    'balance_source' => 'cash_only',
                    'snapshot_date' => null,
                ];
            }
        }

        return $result;
    }

    public static function bankReconciliation(PDO $localPdo): array
    {
        $recon = FinanceBankReconciliationService::reconcileAll($localPdo);
        $rows = [];

        foreach ($recon['accounts'] as $account) {
            $balanceData = $account['current_balance'];

            $rows[] = [
                'account_id' => $account['account_id'],
                'account_number' => $account['account_number'],
                'account_name' => $account['account_name'],
                'snapshot_date' => $balanceData['snapshot_date'] ?? null,
                'statement_closing_balance' => $balanceData['statement_confirmed_balance'] ?? '0.00',
                'erp_calculated_balance' => $balanceData['current_calculated_balance'] ?? '0.00',
                'difference' => '0.00',
                'operation_count' => $balanceData['post_snapshot_operation_count'] ?? 0,
                'reconciliation_status' => $account['status'],
            ];
        }

        return $rows;
    }

    private static function bankBalance(PDO $localPdo, ?int $bankAccountId, string $asOfDate): array
    {
        $result = FinanceBankReconciliationService::bankBalanceWithPostSnapshot($localPdo, $bankAccountId, $asOfDate);

        if (!isset($result['snapshot_date']) && $bankAccountId) {
            $stmt = $localPdo->prepare('SELECT closing_balance FROM bank_accounts WHERE id = ?');
            $stmt->execute([$bankAccountId]);
            $ba = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($ba && $ba['closing_balance'] !== null && $ba['closing_balance'] !== '') {
                return [
                    'balance' => $ba['closing_balance'],
                    'source' => 'bank_accounts_fallback',
                    'snapshot_date' => null,
                ];
            }
        }

        return $result;
    }

    public static function cashOnlyBalance(PDO $localPdo, int $accountId, string $asOfDate): string
    {
        $stmt = $localPdo->prepare(
            "SELECT COALESCE(opening_balance, 0) AS opening_balance FROM finance_money_accounts WHERE id = ?"
        );
        $stmt->execute([$accountId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $balance = $row['opening_balance'] ?? '0.00';

        $stmt = $localPdo->prepare(
            "SELECT
                SUM(CASE WHEN operation_type = 'INCOME' AND status = 'POSTED' AND operation_date <= ? THEN amount ELSE 0 END) AS income,
                SUM(CASE WHEN operation_type = 'EXPENSE' AND status = 'POSTED' AND operation_date <= ? THEN amount ELSE 0 END) AS expense,
                SUM(CASE WHEN operation_type = 'TRANSFER' AND transfer_direction = 'in' AND status = 'POSTED' AND operation_date <= ? THEN amount ELSE 0 END) AS transfer_in,
                SUM(CASE WHEN operation_type = 'TRANSFER' AND transfer_direction = 'out' AND status = 'POSTED' AND operation_date <= ? THEN amount ELSE 0 END) AS transfer_out,
                SUM(CASE WHEN operation_type = 'ADJUSTMENT' AND status = 'POSTED' AND operation_date <= ? THEN amount ELSE 0 END) AS adjustment
             FROM finance_operations
             WHERE money_account_id = ?"
        );
        $stmt->execute([$asOfDate, $asOfDate, $asOfDate, $asOfDate, $asOfDate, $accountId]);
        $ops = $stmt->fetch(PDO::FETCH_ASSOC);

        $balance = self::add($balance, $ops['income'] ?? '0.00');
        $balance = self::sub($balance, $ops['expense'] ?? '0.00');
        $balance = self::add($balance, $ops['transfer_in'] ?? '0.00');
        $balance = self::sub($balance, $ops['transfer_out'] ?? '0.00');
        $balance = self::add($balance, $ops['adjustment'] ?? '0.00');

        return $balance;
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
}
