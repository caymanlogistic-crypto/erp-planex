<?php

namespace App\Service;

use PDO;

final class FinancePaymentCalendarService
{
    public static function formatAmount(mixed $value): string
    {
        return FinanceOperationService::formatAmount($value);
    }

    public static function fetchCalendarData(PDO $localPdo, array $filters = []): array
    {
        $rows = [];

        $invoiceRows = self::fetchInvoiceRows($localPdo, $filters);
        foreach ($invoiceRows as $r) {
            $rows[] = $r;
        }

        $routePaymentRows = self::fetchRoutePaymentRows($localPdo, $filters);
        foreach ($routePaymentRows as $r) {
            $rows[] = $r;
        }

        usort($rows, function ($a, $b) {
            if ($a['due_date'] === null && $b['due_date'] === null) {
                return 0;
            }
            if ($a['due_date'] === null) {
                return 1;
            }
            if ($b['due_date'] === null) {
                return -1;
            }
            return strcmp((string) $a['due_date'], (string) $b['due_date']);
        });

        return $rows;
    }

    public static function getSummary(PDO $localPdo, array $filters = []): array
    {
        $rows = self::fetchCalendarData($localPdo, $filters);

        $expectedIncome = '0.00';
        $expectedExpense = '0.00';
        $overdueIncome = '0.00';
        $overdueExpense = '0.00';
        $today = date('Y-m-d');

        foreach ($rows as $row) {
            $remaining = $row['remaining'];
            $direction = $row['direction'];
            $dueDate = $row['due_date'];
            $isOverdue = $dueDate !== null && $dueDate < $today && self::isPositiveAmount($remaining);

            if ($direction === 'INCOME') {
                $expectedIncome = self::stringAdd($expectedIncome, $remaining);
                if ($isOverdue) {
                    $overdueIncome = self::stringAdd($overdueIncome, $remaining);
                }
            } elseif ($direction === 'EXPENSE') {
                $expectedExpense = self::stringAdd($expectedExpense, $remaining);
                if ($isOverdue) {
                    $overdueExpense = self::stringAdd($overdueExpense, $remaining);
                }
            }
        }

        $netPlan = self::stringSub($expectedIncome, $expectedExpense);

        return [
            'expected_income' => $expectedIncome,
            'expected_expense' => $expectedExpense,
            'overdue_income' => $overdueIncome,
            'overdue_expense' => $overdueExpense,
            'net_plan' => $netPlan,
        ];
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

    private static function isPositiveAmount(string $amount): bool
    {
        $parts = explode('.', $amount);
        $intPart = ltrim($parts[0] ?? '0', '0') ?: '0';
        $decPart = $parts[1] ?? '00';
        $decPart = str_pad(substr($decPart . '00', 0, 2), 2, '0');
        return $intPart !== '0' || $decPart !== '00';
    }

    private static function fetchInvoiceRows(PDO $localPdo, array $filters): array
    {
        $sql = "SELECT
                    fi.id AS source_id,
                    'invoice' AS source_type,
                    CASE fi.direction
                        WHEN 'OUTGOING' THEN 'INCOME'
                        WHEN 'INCOMING' THEN 'EXPENSE'
                    END AS direction,
                    'Счёт' AS source_label,
                    fi.counterparty_name AS counterparty,
                    NULL AS route_label,
                    fi.amount AS amount,
                    COALESCE(
                        (SELECT SUM(foa.amount)
                           FROM finance_operation_allocations foa
                          WHERE foa.invoice_id = fi.id
                            AND foa.cancelled_at IS NULL),
                        0
                    ) AS paid,
                    (fi.amount - COALESCE(
                        (SELECT SUM(foa.amount)
                           FROM finance_operation_allocations foa
                          WHERE foa.invoice_id = fi.id
                            AND foa.cancelled_at IS NULL),
                        0
                    )) AS remaining,
                    COALESCE(fi.planned_payment_date, fi.invoice_date) AS due_date,
                    fi.status AS invoice_status
                FROM finance_invoices fi
                WHERE fi.status NOT IN ('cancelled')
                  AND (fi.amount - COALESCE(
                        (SELECT SUM(foa.amount)
                           FROM finance_operation_allocations foa
                          WHERE foa.invoice_id = fi.id
                            AND foa.cancelled_at IS NULL),
                        0
                  )) > 0";

        $params = [];

        $roleCode = (string) ($filters['role_code'] ?? '');
        if ($roleCode === 'logist') {
            $userId = (int) ($filters['user_id'] ?? 0);
            $sql .= " AND (fi.created_by_user_id = ?
                        OR fi.id IN (
                            SELECT fil.invoice_id
                              FROM finance_invoice_links fil
                              JOIN linear_routes lr ON lr.id = fil.linear_route_id
                             WHERE (lr.created_by_user_id = ?
                                OR lr.id IN (
                                    SELECT entity_id
                                      FROM entity_access_grants
                                     WHERE entity_type = 'linear_route'
                                       AND granted_to_user_id = ?
                                       AND access_level IN ('view','edit')
                                       AND revoked_at IS NULL
                                ))
                        ))";
            $params[] = $userId;
            $params[] = $userId;
            $params[] = $userId;
        }

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

        $result = [];
        foreach ($rows as $r) {
            $r['remaining'] = (string) $r['remaining'];
            $r['amount'] = (string) $r['amount'];
            $r['paid'] = (string) $r['paid'];
            $result[] = self::applyPostFilters($r, $filters);
        }

        return array_values(array_filter($result));
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
                    END AS direction,
                    'Рейс' AS source_label,
                    CASE lrp.party_role
                        WHEN 'customer' THEN ct.name
                        WHEN 'carrier' THEN carrier.name
                        WHEN 'principal' THEN COALESCE(principal_client.name, principal_contractor.name, 'Принципал')
                    END AS counterparty,
                    CONCAT('Рейс #', lr.id) AS route_label,
                    lrp.amount AS amount,
                    COALESCE(
                        (SELECT SUM(foa.amount)
                           FROM finance_operation_allocations foa
                          WHERE foa.linear_route_payment_id = lrp.id
                            AND foa.cancelled_at IS NULL),
                        0
                    ) AS paid,
                    (lrp.amount - COALESCE(
                        (SELECT SUM(foa.amount)
                           FROM finance_operation_allocations foa
                          WHERE foa.linear_route_payment_id = lrp.id
                            AND foa.cancelled_at IS NULL),
                        0
                    )) AS remaining,
                    lrp.calculated_due_date AS due_date
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
                  AND (lrp.amount - COALESCE(
                        (SELECT SUM(foa.amount)
                           FROM finance_operation_allocations foa
                          WHERE foa.linear_route_payment_id = lrp.id
                            AND foa.cancelled_at IS NULL),
                        0
                  )) > 0
                  AND NOT EXISTS (
                      SELECT 1
                        FROM finance_invoice_links fil
                       WHERE fil.linear_route_payment_id = lrp.id
                  )";

        $params = [];

        $roleCode = (string) ($filters['role_code'] ?? '');
        if ($roleCode === 'logist') {
            $userId = (int) ($filters['user_id'] ?? 0);
            $sql .= " AND (lr.created_by_user_id = ?
                        OR lr.id IN (
                            SELECT entity_id
                              FROM entity_access_grants
                             WHERE entity_type = 'linear_route'
                               AND granted_to_user_id = ?
                               AND access_level IN ('view','edit')
                               AND revoked_at IS NULL
                        ))";
            $params[] = $userId;
            $params[] = $userId;
        }

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

        $result = [];
        foreach ($rows as $r) {
            $r['remaining'] = (string) $r['remaining'];
            $r['amount'] = (string) $r['amount'];
            $r['paid'] = (string) $r['paid'];
            $r['invoice_status'] = null;
            $result[] = self::applyPostFilters($r, $filters);
        }

        return array_values(array_filter($result));
    }

    private static function applyPostFilters(array $row, array $filters): ?array
    {
        $today = date('Y-m-d');
        $dueDate = $row['due_date'];
        $remaining = $row['remaining'];

        if ($dueDate !== null && $dueDate < $today && self::isPositiveAmount($remaining)) {
            $row['calendar_status'] = 'overdue';
        } elseif (self::isPositiveAmount($row['paid']) && self::isPositiveAmount($remaining)) {
            $row['calendar_status'] = 'partial';
        } elseif ($dueDate === null) {
            $row['calendar_status'] = 'waiting_event';
        } else {
            $row['calendar_status'] = 'planned';
        }

        $directionFilter = $filters['direction'] ?? '';
        if ($directionFilter !== '' && $directionFilter !== 'all') {
            if ($row['direction'] !== $directionFilter) {
                return null;
            }
        }

        $statusFilter = $filters['status'] ?? '';
        if ($statusFilter !== '' && $statusFilter !== 'all') {
            if ($row['calendar_status'] !== $statusFilter) {
                return null;
            }
        }

        $searchQuery = $filters['search'] ?? '';
        if ($searchQuery !== '') {
            $searchQuery = mb_strtolower($searchQuery, 'UTF-8');
            $found = false;
            foreach (['counterparty', 'source_label', 'route_label'] as $field) {
                $val = mb_strtolower((string) ($row[$field] ?? ''), 'UTF-8');
                if (str_contains($val, $searchQuery)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return null;
            }
        }

        return $row;
    }
}
