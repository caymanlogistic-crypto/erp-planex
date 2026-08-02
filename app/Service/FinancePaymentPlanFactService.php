<?php

namespace App\Service;

use PDO;

final class FinancePaymentPlanFactService
{
    public static function formatAmount(mixed $value): string
    {
        return FinanceOperationService::formatAmount($value);
    }

    public static function fetchPlanFactData(PDO $localPdo, array $filters = []): array
    {
        $rows = [];

        $invoiceRows = self::fetchInvoiceRows($localPdo, $filters);
        foreach ($invoiceRows as $r) {
            $rows[] = self::computeRow($r);
        }

        $routePaymentRows = self::fetchRoutePaymentRows($localPdo, $filters);
        foreach ($routePaymentRows as $r) {
            $rows[] = self::computeRow($r);
        }

        usort($rows, function ($a, $b) {
            if ($a['planned_date'] === null && $b['planned_date'] === null) {
                return 0;
            }
            if ($a['planned_date'] === null) {
                return 1;
            }
            if ($b['planned_date'] === null) {
                return -1;
            }
            return strcmp((string) $a['planned_date'], (string) $b['planned_date']);
        });

        return $rows;
    }

    public static function getTotals(PDO $localPdo, array $filters = []): array
    {
        $rows = self::fetchPlanFactData($localPdo, $filters);

        $expectedIncome = '0.00';
        $receivedIncome = '0.00';
        $remainingIncome = '0.00';
        $expectedExpense = '0.00';
        $paidExpense = '0.00';
        $remainingExpense = '0.00';
        $overdueIncome = '0.00';
        $overdueExpense = '0.00';

        foreach ($rows as $row) {
            $side = $row['side'];
            $planned = $row['planned_amount'];
            $paid = $row['paid_amount'];
            $remaining = $row['remaining'];
            $overdueDays = $row['overdue_days'];

            if ($side === 'INCOME') {
                $expectedIncome = self::stringAdd($expectedIncome, $planned);
                $receivedIncome = self::stringAdd($receivedIncome, $paid);
                $remainingIncome = self::stringAdd($remainingIncome, $remaining);
                if ($overdueDays > 0) {
                    $overdueIncome = self::stringAdd($overdueIncome, $remaining);
                }
            } else {
                $expectedExpense = self::stringAdd($expectedExpense, $planned);
                $paidExpense = self::stringAdd($paidExpense, $paid);
                $remainingExpense = self::stringAdd($remainingExpense, $remaining);
                if ($overdueDays > 0) {
                    $overdueExpense = self::stringAdd($overdueExpense, $remaining);
                }
            }
        }

        $netPlan = self::stringSub($expectedIncome, $expectedExpense);
        $netFact = self::stringSub($receivedIncome, $paidExpense);
        $netRemaining = self::stringSub($remainingIncome, $remainingExpense);

        return [
            'expected_income' => $expectedIncome,
            'received_income' => $receivedIncome,
            'remaining_income' => $remainingIncome,
            'expected_expense' => $expectedExpense,
            'paid_expense' => $paidExpense,
            'remaining_expense' => $remainingExpense,
            'overdue_income' => $overdueIncome,
            'overdue_expense' => $overdueExpense,
            'net_plan' => $netPlan,
            'net_fact' => $netFact,
            'net_remaining' => $netRemaining,
        ];
    }

    private static function computeRow(array $source): array
    {
        $today = date('Y-m-d');
        $plannedAmount = (string) $source['planned_amount'];
        $paidAmount = (string) ($source['paid_amount'] ?? '0.00');
        $remaining = self::stringSubPositive($plannedAmount, $paidAmount);
        $plannedDate = $source['planned_date'];

        $status = 'planned';
        if (!self::isPositiveAmount($remaining)) {
            $remaining = '0.00';
            $status = 'paid';
        } elseif ($plannedDate !== null && $plannedDate < $today) {
            $status = 'overdue';
        } elseif (self::isPositiveAmount($paidAmount)) {
            $status = 'partial';
        }

        $overdueDays = 0;
        if ($remaining !== '0.00' && self::isPositiveAmount($remaining) && $plannedDate !== null && $plannedDate < $today) {
            $overdueDays = (int) ((strtotime($today) - strtotime($plannedDate)) / 86400);
        }

        $actualClosedDate = null;
        if ($remaining === '0.00') {
            $actualClosedDate = $source['max_allocation_date'] ?? null;
            if ($actualClosedDate === null) {
                $actualClosedDate = $source['paid_at'] ?? null;
            }
        }

        return [
            'source_id' => $source['source_id'],
            'source_type' => $source['source_type'],
            'source_label' => $source['source_label'],
            'side' => $source['side'],
            'counterparty' => $source['counterparty'] ?? '—',
            'route_label' => $source['route_label'] ?? null,
            'planned_amount' => $plannedAmount,
            'planned_date' => $plannedDate,
            'paid_amount' => $paidAmount,
            'remaining' => $remaining,
            'actual_closed_date' => $actualClosedDate,
            'overdue_days' => $overdueDays,
            'status' => $status,
        ];
    }

    private static function fetchInvoiceRows(PDO $localPdo, array $filters): array
    {
        $sql = "SELECT
                    fi.id AS source_id,
                    'invoice' AS source_type,
                    CASE fi.direction
                        WHEN 'OUTGOING' THEN 'INCOME'
                        WHEN 'INCOMING' THEN 'EXPENSE'
                    END AS side,
                    'Счёт' AS source_label,
                    fi.counterparty_name AS counterparty,
                    NULL AS route_label,
                    fi.amount AS planned_amount,
                    COALESCE(fi.planned_payment_date, fi.invoice_date) AS planned_date,
                    CASE
                        WHEN (SELECT COUNT(*) FROM finance_operation_allocations foa2 WHERE foa2.invoice_id = fi.id AND foa2.cancelled_at IS NULL) > 0
                        THEN (SELECT SUM(foa3.amount) FROM finance_operation_allocations foa3 WHERE foa3.invoice_id = fi.id AND foa3.cancelled_at IS NULL)
                        ELSE COALESCE(fi.paid_amount, 0)
                    END AS paid_amount,
                    (SELECT MAX(foa4.allocation_date) FROM finance_operation_allocations foa4 WHERE foa4.invoice_id = fi.id AND foa4.cancelled_at IS NULL) AS max_allocation_date,
                    NULL AS paid_at
                FROM finance_invoices fi
                WHERE fi.status NOT IN ('cancelled')";

        $params = [];

        $dateFrom = $filters['date_from'] ?? '';
        $dateTo = $filters['date_to'] ?? '';
        if ($dateFrom !== '') {
            $sql .= " AND COALESCE(fi.planned_payment_date, fi.invoice_date) >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo !== '') {
            $sql .= " AND COALESCE(fi.planned_payment_date, fi.invoice_date) <= ?";
            $params[] = $dateTo;
        }

        $stmt = $localPdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return self::filterRows($rows, $filters);
    }

    private static function fetchRoutePaymentRows(PDO $localPdo, array $filters): array
    {
        $sql = "SELECT
                    lrp.id AS source_id,
                    'route_payment' AS source_type,
                    CASE lrp.party_role
                        WHEN 'customer' THEN 'INCOME'
                        WHEN 'carrier' THEN 'EXPENSE'
                        WHEN 'principal' THEN 'EXPENSE'
                    END AS side,
                    CONCAT('Рейс #', lr.id) AS source_label,
                    CASE lrp.party_role
                        WHEN 'customer' THEN ct.name
                        WHEN 'carrier' THEN carrier.name
                        WHEN 'principal' THEN COALESCE(principal_client.name, principal_contractor.name, 'Принципал')
                    END AS counterparty,
                    CONCAT('Рейс #', lr.id) AS route_label,
                    lrp.amount AS planned_amount,
                    lrp.calculated_due_date AS planned_date,
                    CASE
                        WHEN (SELECT COUNT(*) FROM finance_operation_allocations foa2 WHERE foa2.linear_route_payment_id = lrp.id AND foa2.cancelled_at IS NULL) > 0
                        THEN (SELECT SUM(foa3.amount) FROM finance_operation_allocations foa3 WHERE foa3.linear_route_payment_id = lrp.id AND foa3.cancelled_at IS NULL)
                        ELSE COALESCE(lrp.paid_amount, 0)
                    END AS paid_amount,
                    (SELECT MAX(foa4.allocation_date) FROM finance_operation_allocations foa4 WHERE foa4.linear_route_payment_id = lrp.id AND foa4.cancelled_at IS NULL) AS max_allocation_date,
                    lrp.paid_at
                FROM linear_route_payments lrp
                JOIN linear_routes lr ON lr.id = lrp.linear_route_id AND lr.deleted_at IS NULL
                LEFT JOIN clients ct ON ct.id = lr.client_id
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
                WHERE lrp.deleted_at IS NULL
                  AND NOT EXISTS (
                      SELECT 1
                        FROM finance_invoice_links fil
                       WHERE fil.linear_route_payment_id = lrp.id
                  )";

        $params = [];

        $dateFrom = $filters['date_from'] ?? '';
        $dateTo = $filters['date_to'] ?? '';
        if ($dateFrom !== '') {
            $sql .= " AND lrp.calculated_due_date >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo !== '') {
            $sql .= " AND lrp.calculated_due_date <= ?";
            $params[] = $dateTo;
        }

        $stmt = $localPdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return self::filterRows($rows, $filters);
    }

    private static function filterRows(array $rows, array $filters): array
    {
        $directionFilter = $filters['direction'] ?? '';
        $statusFilter = $filters['status'] ?? '';
        $searchQuery = $filters['search'] ?? '';

        $result = [];
        foreach ($rows as $r) {
            $today = date('Y-m-d');
            $plannedAmount = (string) $r['planned_amount'];
            $paidAmount = (string) ($r['paid_amount'] ?? '0.00');
            $remaining = self::stringSubPositive($plannedAmount, $paidAmount);
            $plannedDate = $r['planned_date'];

            $status = 'planned';
            if (!self::isPositiveAmount($remaining)) {
                $remaining = '0.00';
                $status = 'paid';
            } elseif ($plannedDate !== null && $plannedDate < $today) {
                $status = 'overdue';
            } elseif (self::isPositiveAmount($paidAmount)) {
                $status = 'partial';
            }

            $r['_status'] = $status;
            $r['_remaining'] = $remaining;

            if ($directionFilter !== '' && $directionFilter !== 'all') {
                if ($r['side'] !== $directionFilter) {
                    continue;
                }
            }

            if ($statusFilter !== '' && $statusFilter !== 'all') {
                if ($status !== $statusFilter) {
                    continue;
                }
            }

            if ($searchQuery !== '') {
                $searchQuery = mb_strtolower($searchQuery, 'UTF-8');
                $found = false;
                foreach (['counterparty', 'source_label', 'route_label'] as $field) {
                    $val = mb_strtolower((string) ($r[$field] ?? ''), 'UTF-8');
                    if (str_contains($val, $searchQuery)) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    continue;
                }
            }

            $result[] = $r;
        }

        return $result;
    }

    private static function stringAdd(string $a, string $b): string
    {
        $partsA = explode('.', $a);
        $partsB = explode('.', $b);
        $intA = ltrim($partsA[0] ?? '0', '0') ?: '0';
        $intB = ltrim($partsB[0] ?? '0', '0') ?: '0';
        $decA = str_pad(substr(($partsA[1] ?? '') . '00', 0, 2), 2, '0');
        $decB = str_pad(substr(($partsB[1] ?? '') . '00', 0, 2), 2, '0');

        $decSum = (int) $decA + (int) $decB;
        $carry = 0;
        if ($decSum >= 100) {
            $decSum -= 100;
            $carry = 1;
        }
        $decStr = str_pad((string) $decSum, 2, '0', STR_PAD_LEFT);

        $intSum = (int) $intA + (int) $intB + $carry;

        return $intSum . '.' . $decStr;
    }

    private static function stringSub(string $a, string $b): string
    {
        $partsA = explode('.', $a);
        $partsB = explode('.', $b);
        $intA = (int) (ltrim($partsA[0] ?? '0', '0') ?: '0');
        $intB = (int) (ltrim($partsB[0] ?? '0', '0') ?: '0');
        $decA = (int) str_pad(substr(($partsA[1] ?? '') . '00', 0, 2), 2, '0');
        $decB = (int) str_pad(substr(($partsB[1] ?? '') . '00', 0, 2), 2, '0');

        $totalCentsA = $intA * 100 + $decA;
        $totalCentsB = $intB * 100 + $decB;
        $resultCents = $totalCentsA - $totalCentsB;

        $sign = '';
        if ($resultCents < 0) {
            $sign = '-';
            $resultCents = abs($resultCents);
        }

        $intResult = intdiv($resultCents, 100);
        $decResult = $resultCents % 100;

        return $sign . $intResult . '.' . str_pad((string) $decResult, 2, '0', STR_PAD_LEFT);
    }

    private static function stringSubPositive(string $a, string $b): string
    {
        $result = self::stringSub($a, $b);
        if (str_starts_with($result, '-')) {
            return '0.00';
        }
        return $result;
    }

    private static function isPositiveAmount(string $amount): bool
    {
        $parts = explode('.', $amount);
        $intPart = ltrim($parts[0] ?? '0', '0') ?: '0';
        $decPart = $parts[1] ?? '00';
        $decPart = str_pad(substr($decPart . '00', 0, 2), 2, '0');
        return $intPart !== '0' || $decPart !== '00';
    }
}
