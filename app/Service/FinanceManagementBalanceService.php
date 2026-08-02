<?php

namespace App\Service;

use PDO;

final class FinanceManagementBalanceService
{
    public static function formatAmount(mixed $value): string
    {
        return FinanceOperationService::formatAmount($value);
    }

    public static function computeBalance(PDO $localPdo, string $asOfDate): array
    {
        $moneyAccounts = self::fetchMoneyAccountBalances($localPdo, $asOfDate);
        $receivables = self::fetchReceivables($localPdo, $asOfDate);
        $payables = self::fetchPayables($localPdo, $asOfDate);

        $totalCash = '0.00';
        foreach ($moneyAccounts as $acc) {
            $totalCash = self::stringAdd($totalCash, $acc['balance']);
        }

        $totalReceivables = '0.00';
        foreach ($receivables as $r) {
            $totalReceivables = self::stringAdd($totalReceivables, $r['remaining']);
        }

        $totalPayables = '0.00';
        foreach ($payables as $p) {
            $totalPayables = self::stringAdd($totalPayables, $p['remaining']);
        }

        $totalAssets = self::stringAdd($totalCash, $totalReceivables);
        $netAssets = self::stringSub($totalAssets, $totalPayables);

        return [
            'as_of_date' => $asOfDate,
            'money_accounts' => $moneyAccounts,
            'receivables' => $receivables,
            'payables' => $payables,
            'total_cash' => $totalCash,
            'total_receivables' => $totalReceivables,
            'total_payables' => $totalPayables,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalPayables,
            'net_assets' => $netAssets,
            'advances_given' => '0.00',
            'advances_received' => '0.00',
        ];
    }

    private static function fetchMoneyAccountBalances(PDO $localPdo, string $asOfDate): array
    {
        $allBalances = FinanceBalanceService::allBalances($localPdo, $asOfDate);

        $accounts = [];
        foreach ($allBalances as $a) {
            $accountType = $a['type'] === 'CASH' ? 'Касса' : 'Расчётный счёт';

            $accounts[] = [
                'id' => $a['id'],
                'name' => $a['name'],
                'type' => $accountType,
                'balance' => $a['balance'],
            ];
        }

        return $accounts;
    }

    private static function fetchReceivables(PDO $localPdo, string $asOfDate): array
    {
        $rows = [];

        $invStmt = $localPdo->prepare("
            SELECT id, number, counterparty_name,
                   amount,
                   COALESCE((SELECT SUM(foa.amount) FROM finance_operation_allocations foa WHERE foa.invoice_id = finance_invoices.id AND foa.cancelled_at IS NULL), 0) AS paid_amount,
                   (amount - COALESCE((SELECT SUM(foa.amount) FROM finance_operation_allocations foa WHERE foa.invoice_id = finance_invoices.id AND foa.cancelled_at IS NULL), 0)) AS remaining
            FROM finance_invoices
            WHERE direction = 'OUTGOING'
              AND status NOT IN ('cancelled', 'paid')
              AND (amount - COALESCE((SELECT SUM(foa.amount) FROM finance_operation_allocations foa WHERE foa.invoice_id = finance_invoices.id AND foa.cancelled_at IS NULL), 0)) > 0
              AND (invoice_date IS NULL OR invoice_date <= ?)
            ORDER BY number
        ");
        $invStmt->execute([$asOfDate]);
        $invRows = $invStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($invRows as $r) {
            $rows[] = [
                'group' => 'Дебиторская задолженность',
                'name' => 'Счёт ' . ($r['number'] ?? '№' . $r['id']),
                'counterparty' => $r['counterparty_name'] ?? '—',
                'source' => 'Счёт на оплату',
                'remaining' => $r['remaining'],
                'drilldown_url' => app_url('/company/finance/invoices'),
            ];
        }

        $rpStmt = $localPdo->prepare("
            SELECT lrp.id,
                   ct.name AS counterparty_name,
                   lrp.amount,
                   COALESCE((SELECT SUM(foa.amount) FROM finance_operation_allocations foa WHERE foa.linear_route_payment_id = lrp.id AND foa.cancelled_at IS NULL), 0) AS paid_amount,
                   (lrp.amount - COALESCE((SELECT SUM(foa.amount) FROM finance_operation_allocations foa WHERE foa.linear_route_payment_id = lrp.id AND foa.cancelled_at IS NULL), 0)) AS remaining
            FROM linear_route_payments lrp
            JOIN linear_routes lr ON lr.id = lrp.linear_route_id AND lr.deleted_at IS NULL
            LEFT JOIN clients ct ON ct.id = lr.client_id
            WHERE lrp.party_role = 'customer'
              AND lrp.deleted_at IS NULL
              AND (lrp.amount - COALESCE((SELECT SUM(foa.amount) FROM finance_operation_allocations foa WHERE foa.linear_route_payment_id = lrp.id AND foa.cancelled_at IS NULL), 0)) > 0
              AND (lrp.calculated_due_date IS NULL OR lrp.calculated_due_date <= ?)
              AND NOT EXISTS (
                  SELECT 1 FROM finance_invoice_links fil
                  WHERE fil.linear_route_payment_id = lrp.id
              )
            ORDER BY lrp.id
        ");
        $rpStmt->execute([$asOfDate]);
        $rpRows = $rpStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rpRows as $r) {
            $rows[] = [
                'group' => 'Дебиторская задолженность',
                'name' => 'Платеж по рейсу #' . $r['id'],
                'counterparty' => $r['counterparty_name'] ?? '—',
                'source' => 'План платежа рейса',
                'remaining' => $r['remaining'],
                'drilldown_url' => app_url('/company/trips/linear'),
            ];
        }

        return $rows;
    }

    private static function fetchPayables(PDO $localPdo, string $asOfDate): array
    {
        $rows = [];

        $invStmt = $localPdo->prepare("
            SELECT id, number, counterparty_name,
                   amount,
                   COALESCE((SELECT SUM(foa.amount) FROM finance_operation_allocations foa WHERE foa.invoice_id = finance_invoices.id AND foa.cancelled_at IS NULL), 0) AS paid_amount,
                   (amount - COALESCE((SELECT SUM(foa.amount) FROM finance_operation_allocations foa WHERE foa.invoice_id = finance_invoices.id AND foa.cancelled_at IS NULL), 0)) AS remaining
            FROM finance_invoices
            WHERE direction = 'INCOMING'
              AND status NOT IN ('cancelled', 'paid')
              AND (amount - COALESCE((SELECT SUM(foa.amount) FROM finance_operation_allocations foa WHERE foa.invoice_id = finance_invoices.id AND foa.cancelled_at IS NULL), 0)) > 0
              AND (invoice_date IS NULL OR invoice_date <= ?)
            ORDER BY number
        ");
        $invStmt->execute([$asOfDate]);
        $invRows = $invStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($invRows as $r) {
            $rows[] = [
                'group' => 'Кредиторская задолженность',
                'name' => 'Счёт ' . ($r['number'] ?? '№' . $r['id']),
                'counterparty' => $r['counterparty_name'] ?? '—',
                'source' => 'Счёт на оплату',
                'remaining' => $r['remaining'],
                'drilldown_url' => app_url('/company/finance/invoices'),
            ];
        }

        $rpStmt = $localPdo->prepare("
            SELECT lrp.id,
                   lrp.party_role,
                   CASE lrp.party_role
                       WHEN 'carrier' THEN carrier.name
                       WHEN 'principal' THEN COALESCE(principal_client.name, principal_contractor.name, 'Принципал')
                   END AS counterparty_name,
                   lrp.amount,
                   COALESCE((SELECT SUM(foa.amount) FROM finance_operation_allocations foa WHERE foa.linear_route_payment_id = lrp.id AND foa.cancelled_at IS NULL), 0) AS paid_amount,
                   (lrp.amount - COALESCE((SELECT SUM(foa.amount) FROM finance_operation_allocations foa WHERE foa.linear_route_payment_id = lrp.id AND foa.cancelled_at IS NULL), 0)) AS remaining
            FROM linear_route_payments lrp
            JOIN linear_routes lr ON lr.id = lrp.linear_route_id AND lr.deleted_at IS NULL
            LEFT JOIN contractors carrier ON carrier.id = lr.carrier_contractor_id
            LEFT JOIN linear_route_principals lrp_principal
                ON lrp_principal.id = lrp.linear_route_principal_id
                AND lrp_principal.deleted_at IS NULL
            LEFT JOIN clients principal_client
                ON lrp_principal.principal_type = 'client'
                AND lrp_principal.principal_id = principal_client.id
            LEFT JOIN contractors principal_contractor
                ON lrp_principal.principal_type = 'contractor'
                AND lrp_principal.principal_id = principal_contractor.id
            WHERE lrp.party_role IN ('carrier', 'principal')
              AND lrp.deleted_at IS NULL
              AND (lrp.amount - COALESCE((SELECT SUM(foa.amount) FROM finance_operation_allocations foa WHERE foa.linear_route_payment_id = lrp.id AND foa.cancelled_at IS NULL), 0)) > 0
              AND (lrp.calculated_due_date IS NULL OR lrp.calculated_due_date <= ?)
              AND NOT EXISTS (
                  SELECT 1 FROM finance_invoice_links fil
                  WHERE fil.linear_route_payment_id = lrp.id
              )
            ORDER BY lrp.id
        ");
        $rpStmt->execute([$asOfDate]);
        $rpRows = $rpStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rpRows as $r) {
            $roleLabel = $r['party_role'] === 'carrier' ? 'Перевозчик' : 'Принципал';
            $rows[] = [
                'group' => 'Кредиторская задолженность',
                'name' => 'Платеж по рейсу #' . $r['id'] . ' (' . $roleLabel . ')',
                'counterparty' => $r['counterparty_name'] ?? '—',
                'source' => 'План платежа рейса',
                'remaining' => $r['remaining'],
                'drilldown_url' => app_url('/company/trips/linear'),
            ];
        }

        return $rows;
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

    private static function stringAdd(string $a, string $b): string
    {
        return self::formatCents(self::parseCents($a) + self::parseCents($b));
    }

    private static function stringSub(string $a, string $b): string
    {
        return self::formatCents(self::parseCents($a) - self::parseCents($b));
    }
}
