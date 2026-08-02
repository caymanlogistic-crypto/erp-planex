<?php

namespace App\Service;

use PDO;

final class FinanceDashboardService
{
    public static function formatAmount(mixed $value): string
    {
        return FinanceOperationService::formatAmount($value);
    }

    public static function computeDashboard(PDO $localPdo): array
    {
        $bankAccounts = self::fetchBankAccountBalances($localPdo);
        $cashAccounts = self::fetchCashAccountBalances($localPdo);
        $totalCash = self::add('0.00', $bankAccounts['total']);
        $totalCash = self::add($totalCash, $cashAccounts['total']);
        $expectedInflow = self::fetchExpectedInflow($localPdo);
        $expectedOutflow = self::fetchExpectedOutflow($localPdo);
        $overdueReceivables = self::fetchOverdueReceivables($localPdo);
        $overduePayables = self::fetchOverduePayables($localPdo);
        $unallocatedCount = self::fetchUnallocatedOperationCount($localPdo);
        $cashGap = self::fetchNearestCashGap($localPdo, $totalCash, $expectedInflow, $expectedOutflow);

        return [
            'bank_accounts' => $bankAccounts['accounts'],
            'bank_total' => $bankAccounts['total'],
            'cash_accounts' => $cashAccounts['accounts'],
            'cash_total' => $cashAccounts['total'],
            'total_cash' => $totalCash,
            'expected_inflow' => $expectedInflow,
            'expected_outflow' => $expectedOutflow,
            'overdue_receivables' => $overdueReceivables,
            'overdue_payables' => $overduePayables,
            'unallocated_count' => $unallocatedCount,
            'cash_gap_date' => $cashGap['date'],
            'cash_gap_amount' => $cashGap['amount'],
        ];
    }

    private static function fetchBankAccountBalances(PDO $localPdo): array
    {
        $allBalances = FinanceBalanceService::allBalances($localPdo);
        $accounts = [];
        $total = '0.00';
        foreach ($allBalances as $a) {
            if ($a['type'] !== 'BANK') {
                continue;
            }
            $accounts[] = [
                'id' => $a['id'],
                'name' => $a['name'],
                'balance' => $a['balance'],
            ];
            $total = self::add($total, $a['balance']);
        }
        return ['accounts' => $accounts, 'total' => $total];
    }

    private static function fetchCashAccountBalances(PDO $localPdo): array
    {
        $allBalances = FinanceBalanceService::allBalances($localPdo);
        $accounts = [];
        $total = '0.00';
        foreach ($allBalances as $a) {
            if ($a['type'] !== 'CASH') {
                continue;
            }
            $accounts[] = [
                'id' => $a['id'],
                'name' => $a['name'],
                'balance' => $a['balance'],
            ];
            $total = self::add($total, $a['balance']);
        }
        return ['accounts' => $accounts, 'total' => $total];
    }

    private static function fetchExpectedInflow(PDO $localPdo): string
    {
        $stmt = $localPdo->query("
            SELECT COALESCE(SUM(fi.amount - COALESCE(
                (SELECT SUM(foa.amount) FROM finance_operation_allocations foa
                 WHERE foa.invoice_id = fi.id AND foa.cancelled_at IS NULL), 0
            )), 0) AS total
            FROM finance_invoices fi
            WHERE fi.direction = 'OUTGOING'
              AND fi.status NOT IN ('cancelled', 'paid')
              AND (fi.amount - COALESCE(
                (SELECT SUM(foa.amount) FROM finance_operation_allocations foa
                 WHERE foa.invoice_id = fi.id AND foa.cancelled_at IS NULL), 0
              )) > 0
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $total = self::normalizeAmount($row['total'] ?? '0.00');

        $rpStmt = $localPdo->query("
            SELECT COALESCE(SUM(lrp.amount - COALESCE((SELECT SUM(foa.amount) FROM finance_operation_allocations foa WHERE foa.linear_route_payment_id = lrp.id AND foa.cancelled_at IS NULL), 0)), 0) AS total
            FROM linear_route_payments lrp
            JOIN linear_routes lr ON lr.id = lrp.linear_route_id AND lr.deleted_at IS NULL
            WHERE lrp.party_role = 'customer'
              AND lrp.deleted_at IS NULL
              AND (lrp.amount - COALESCE((SELECT SUM(foa.amount) FROM finance_operation_allocations foa WHERE foa.linear_route_payment_id = lrp.id AND foa.cancelled_at IS NULL), 0)) > 0
              AND NOT EXISTS (
                  SELECT 1 FROM finance_invoice_links fil WHERE fil.linear_route_payment_id = lrp.id
              )
        ");
        $rpRow = $rpStmt->fetch(PDO::FETCH_ASSOC);
        $total = self::add($total, self::normalizeAmount($rpRow['total'] ?? '0.00'));

        return $total;
    }

    private static function fetchExpectedOutflow(PDO $localPdo): string
    {
        $stmt = $localPdo->query("
            SELECT COALESCE(SUM(fi.amount - COALESCE(
                (SELECT SUM(foa.amount) FROM finance_operation_allocations foa
                 WHERE foa.invoice_id = fi.id AND foa.cancelled_at IS NULL), 0
            )), 0) AS total
            FROM finance_invoices fi
            WHERE fi.direction = 'INCOMING'
              AND fi.status NOT IN ('cancelled', 'paid')
              AND (fi.amount - COALESCE(
                (SELECT SUM(foa.amount) FROM finance_operation_allocations foa
                 WHERE foa.invoice_id = fi.id AND foa.cancelled_at IS NULL), 0
              )) > 0
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $total = self::normalizeAmount($row['total'] ?? '0.00');

        $rpStmt = $localPdo->query("
            SELECT COALESCE(SUM(lrp.amount - COALESCE((SELECT SUM(foa.amount) FROM finance_operation_allocations foa WHERE foa.linear_route_payment_id = lrp.id AND foa.cancelled_at IS NULL), 0)), 0) AS total
            FROM linear_route_payments lrp
            JOIN linear_routes lr ON lr.id = lrp.linear_route_id AND lr.deleted_at IS NULL
            WHERE lrp.party_role IN ('carrier', 'principal')
              AND lrp.deleted_at IS NULL
              AND (lrp.amount - COALESCE((SELECT SUM(foa.amount) FROM finance_operation_allocations foa WHERE foa.linear_route_payment_id = lrp.id AND foa.cancelled_at IS NULL), 0)) > 0
              AND NOT EXISTS (
                  SELECT 1 FROM finance_invoice_links fil WHERE fil.linear_route_payment_id = lrp.id
              )
        ");
        $rpRow = $rpStmt->fetch(PDO::FETCH_ASSOC);
        $total = self::add($total, self::normalizeAmount($rpRow['total'] ?? '0.00'));

        return $total;
    }

    private static function fetchOverdueReceivables(PDO $localPdo): string
    {
        $today = date('Y-m-d');
        $stmt = $localPdo->prepare("
            SELECT COALESCE(SUM(fi.amount - COALESCE(
                (SELECT SUM(foa.amount) FROM finance_operation_allocations foa
                 WHERE foa.invoice_id = fi.id AND foa.cancelled_at IS NULL), 0
            )), 0) AS total
            FROM finance_invoices fi
            WHERE fi.direction = 'OUTGOING'
              AND fi.status NOT IN ('cancelled', 'paid')
              AND (fi.amount - COALESCE(
                (SELECT SUM(foa.amount) FROM finance_operation_allocations foa
                 WHERE foa.invoice_id = fi.id AND foa.cancelled_at IS NULL), 0
              )) > 0
              AND COALESCE(fi.planned_payment_date, fi.invoice_date) < ?
        ");
        $stmt->execute([$today]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $total = self::normalizeAmount($row['total'] ?? '0.00');

        $rpStmt = $localPdo->prepare("
            SELECT COALESCE(SUM(lrp.amount - COALESCE((SELECT SUM(foa.amount) FROM finance_operation_allocations foa WHERE foa.linear_route_payment_id = lrp.id AND foa.cancelled_at IS NULL), 0)), 0) AS total
            FROM linear_route_payments lrp
            JOIN linear_routes lr ON lr.id = lrp.linear_route_id AND lr.deleted_at IS NULL
            WHERE lrp.party_role = 'customer'
              AND lrp.deleted_at IS NULL
              AND (lrp.amount - COALESCE((SELECT SUM(foa.amount) FROM finance_operation_allocations foa WHERE foa.linear_route_payment_id = lrp.id AND foa.cancelled_at IS NULL), 0)) > 0
              AND lrp.calculated_due_date IS NOT NULL
              AND lrp.calculated_due_date < ?
              AND NOT EXISTS (
                  SELECT 1 FROM finance_invoice_links fil WHERE fil.linear_route_payment_id = lrp.id
              )
        ");
        $rpStmt->execute([$today]);
        $rpRow = $rpStmt->fetch(PDO::FETCH_ASSOC);
        $total = self::add($total, self::normalizeAmount($rpRow['total'] ?? '0.00'));

        return $total;
    }

    private static function fetchOverduePayables(PDO $localPdo): string
    {
        $today = date('Y-m-d');
        $stmt = $localPdo->prepare("
            SELECT COALESCE(SUM(fi.amount - COALESCE(
                (SELECT SUM(foa.amount) FROM finance_operation_allocations foa
                 WHERE foa.invoice_id = fi.id AND foa.cancelled_at IS NULL), 0
            )), 0) AS total
            FROM finance_invoices fi
            WHERE fi.direction = 'INCOMING'
              AND fi.status NOT IN ('cancelled', 'paid')
              AND (fi.amount - COALESCE(
                (SELECT SUM(foa.amount) FROM finance_operation_allocations foa
                 WHERE foa.invoice_id = fi.id AND foa.cancelled_at IS NULL), 0
              )) > 0
              AND COALESCE(fi.planned_payment_date, fi.invoice_date) < ?
        ");
        $stmt->execute([$today]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $total = self::normalizeAmount($row['total'] ?? '0.00');

        $rpStmt = $localPdo->prepare("
            SELECT COALESCE(SUM(lrp.amount - COALESCE((SELECT SUM(foa.amount) FROM finance_operation_allocations foa WHERE foa.linear_route_payment_id = lrp.id AND foa.cancelled_at IS NULL), 0)), 0) AS total
            FROM linear_route_payments lrp
            JOIN linear_routes lr ON lr.id = lrp.linear_route_id AND lr.deleted_at IS NULL
            WHERE lrp.party_role IN ('carrier', 'principal')
              AND lrp.deleted_at IS NULL
              AND (lrp.amount - COALESCE((SELECT SUM(foa.amount) FROM finance_operation_allocations foa WHERE foa.linear_route_payment_id = lrp.id AND foa.cancelled_at IS NULL), 0)) > 0
              AND lrp.calculated_due_date IS NOT NULL
              AND lrp.calculated_due_date < ?
              AND NOT EXISTS (
                  SELECT 1 FROM finance_invoice_links fil WHERE fil.linear_route_payment_id = lrp.id
              )
        ");
        $rpStmt->execute([$today]);
        $rpRow = $rpStmt->fetch(PDO::FETCH_ASSOC);
        $total = self::add($total, self::normalizeAmount($rpRow['total'] ?? '0.00'));

        return $total;
    }

    private static function fetchUnallocatedOperationCount(PDO $localPdo): int
    {
        $stmt = $localPdo->query("
            SELECT COUNT(*) AS cnt
            FROM finance_operations fo
            WHERE fo.status = 'POSTED'
              AND (SELECT COALESCE(SUM(foa.amount), 0)
                   FROM finance_operation_allocations foa
                   WHERE foa.operation_id = fo.id AND foa.cancelled_at IS NULL) = 0
              AND fo.operation_type IN ('INCOME', 'EXPENSE')
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['cnt'] ?? 0);
    }

    private static function fetchNearestCashGap(PDO $localPdo, string $currentCash, string $expectedInflow, string $expectedOutflow): array
    {
        if (!self::isPositiveOrZero($currentCash)) {
            return ['date' => date('Y-m-d'), 'amount' => self::abs($currentCash)];
        }

        $rows = [];

        $invStmt = $localPdo->query("
            SELECT fi.id, 'invoice' AS source,
                   COALESCE(fi.planned_payment_date, fi.invoice_date) AS due_date,
                   CASE WHEN fi.direction = 'OUTGOING' THEN 'INFLOW' ELSE 'OUTFLOW' END AS flow,
                   (fi.amount - COALESCE(
                       (SELECT SUM(foa.amount) FROM finance_operation_allocations foa
                        WHERE foa.invoice_id = fi.id AND foa.cancelled_at IS NULL), 0
                   )) AS remaining
            FROM finance_invoices fi
            WHERE fi.status NOT IN ('cancelled', 'paid')
              AND (fi.amount - COALESCE(
                  (SELECT SUM(foa.amount) FROM finance_operation_allocations foa
                   WHERE foa.invoice_id = fi.id AND foa.cancelled_at IS NULL), 0
              )) > 0
              AND COALESCE(fi.planned_payment_date, fi.invoice_date) IS NOT NULL
            ORDER BY due_date ASC
        ");
        $invRows = $invStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($invRows as $r) {
            $rows[] = $r;
        }

        $rpStmt = $localPdo->query("
            SELECT lrp.id, 'route_payment' AS source,
                   lrp.calculated_due_date AS due_date,
                   CASE WHEN lrp.party_role = 'customer' THEN 'INFLOW' ELSE 'OUTFLOW' END AS flow,
                   (lrp.amount - COALESCE((SELECT SUM(foa.amount) FROM finance_operation_allocations foa WHERE foa.linear_route_payment_id = lrp.id AND foa.cancelled_at IS NULL), 0)) AS remaining
            FROM linear_route_payments lrp
            JOIN linear_routes lr ON lr.id = lrp.linear_route_id AND lr.deleted_at IS NULL
            WHERE lrp.deleted_at IS NULL
              AND lrp.calculated_due_date IS NOT NULL
              AND (lrp.amount - COALESCE((SELECT SUM(foa.amount) FROM finance_operation_allocations foa WHERE foa.linear_route_payment_id = lrp.id AND foa.cancelled_at IS NULL), 0)) > 0
              AND NOT EXISTS (
                  SELECT 1 FROM finance_invoice_links fil WHERE fil.linear_route_payment_id = lrp.id
              )
            ORDER BY lrp.calculated_due_date ASC
        ");
        $rpRows = $rpStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rpRows as $r) {
            $rows[] = $r;
        }

        usort($rows, function ($a, $b) {
            return strcmp((string) $a['due_date'], (string) $b['due_date']);
        });

        $runningBalance = $currentCash;
        foreach ($rows as $r) {
            $amount = self::normalizeAmount($r['remaining']);
            if ($r['flow'] === 'INFLOW') {
                $runningBalance = self::add($runningBalance, $amount);
            } else {
                $runningBalance = self::sub($runningBalance, $amount);
            }
            if (!self::isPositiveOrZero($runningBalance)) {
                return [
                    'date' => $r['due_date'],
                    'amount' => self::abs($runningBalance),
                ];
            }
        }

        return ['date' => null, 'amount' => '0.00'];
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

    private static function add(string $a, string $b): string
    {
        return self::formatCents(self::parseCents($a) + self::parseCents($b));
    }

    private static function sub(string $a, string $b): string
    {
        return self::formatCents(self::parseCents($a) - self::parseCents($b));
    }

    private static function isPositiveOrZero(string $amount): bool
    {
        return self::parseCents($amount) >= 0;
    }

    private static function abs(string $amount): string
    {
        $cents = self::parseCents($amount);
        return self::formatCents($cents < 0 ? -$cents : $cents);
    }
}
