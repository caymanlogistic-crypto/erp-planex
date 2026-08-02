<?php

namespace App\Service;

use PDO;

final class FinanceCashFlowReportService
{
    public static function formatAmount(mixed $value): string
    {
        return FinanceOperationService::formatAmount($value);
    }

    public static function fetchReportData(PDO $localPdo, array $filters = []): array
    {
        $dateFrom = $filters['date_from'] ?? date('Y-m-01');
        $dateTo = $filters['date_to'] ?? date('Y-m-t');
        $moneyAccountId = $filters['money_account_id'] ?? '';
        $directionFilter = $filters['direction'] ?? 'all';
        $ddsCategoryId = $filters['dds_category_id'] ?? '';
        $searchQuery = $filters['search'] ?? '';

        $rows = [];
        $totalIncome = '0.00';
        $totalExpense = '0.00';

        $catWhere = '1=1';
        if ($directionFilter !== 'all' && $directionFilter !== '') {
            if ($directionFilter === 'INCOME') {
                $catWhere = "(d.direction IN ('INCOME','BOTH'))";
            } elseif ($directionFilter === 'EXPENSE') {
                $catWhere = "(d.direction IN ('EXPENSE','BOTH'))";
            }
        }

        $allCategories = [];
        $fetchUncategorized = false;

        if ($ddsCategoryId !== '') {
            if ($ddsCategoryId === 'null') {
                $fetchUncategorized = true;
            } else {
                $catSql = "SELECT d.id, d.code, d.name, d.direction, d.sort_order
                           FROM finance_dds_categories d
                           WHERE d.id = ? AND d.is_active = 1 AND {$catWhere}";
                $catStmt = $localPdo->prepare($catSql);
                $catStmt->execute([$ddsCategoryId]);
                $allCategories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            $catSql = "SELECT d.id, d.code, d.name, d.direction, d.sort_order
                        FROM finance_dds_categories d
                        WHERE d.is_active = 1 AND {$catWhere}
                        ORDER BY d.sort_order, d.code";
            $catStmt = $localPdo->prepare($catSql);
            $catStmt->execute();
            $allCategories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
            $fetchUncategorized = true;
        }

        $opWhere = "fo.status = 'POSTED' AND fo.operation_date >= ? AND fo.operation_date <= ?
                    AND fo.operation_type IN ('INCOME','EXPENSE')";
        $opParams = [$dateFrom, $dateTo];

        if ($moneyAccountId !== '') {
            $opWhere .= ' AND fo.money_account_id = ?';
            $opParams[] = $moneyAccountId;
        }

        if ($directionFilter !== 'all' && $directionFilter !== '') {
            $opWhere .= ' AND fo.operation_type = ?';
            $opParams[] = $directionFilter;
        }

        if ($ddsCategoryId !== '' && $ddsCategoryId !== 'null') {
            $opWhere .= ' AND fo.dds_category_id = ?';
            $opParams[] = $ddsCategoryId;
        }
        if ($ddsCategoryId === 'null') {
            $opWhere .= ' AND fo.dds_category_id IS NULL';
        }

        $opSql = "SELECT fo.dds_category_id,
                         SUM(CASE WHEN fo.operation_type = 'INCOME' THEN fo.amount ELSE 0 END) AS fact_income,
                         SUM(CASE WHEN fo.operation_type = 'EXPENSE' THEN fo.amount ELSE 0 END) AS fact_expense,
                         COUNT(fo.id) AS operation_count
                  FROM finance_operations fo
                  WHERE {$opWhere}
                  GROUP BY fo.dds_category_id";
        $opStmt = $localPdo->prepare($opSql);
        $opStmt->execute($opParams);
        $opRows = $opStmt->fetchAll(PDO::FETCH_ASSOC);

        $catMap = [];
        foreach ($opRows as $op) {
            $catId = $op['dds_category_id'];
            if ($catId === null) {
                // Uncategorized operations are calculated by the dedicated
                // query below; do not use null as an array key on PHP 8.5+.
                continue;
            }
            $catId = (string) $catId;
            $catMap[$catId] = [
                'fact_income' => $op['fact_income'],
                'fact_expense' => $op['fact_expense'],
                'operation_count' => (int) $op['operation_count'],
            ];
        }

        foreach ($allCategories as $cat) {
            $cid = (string) $cat['id'];
            $fi = $catMap[$cid]['fact_income'] ?? '0.00';
            $fe = $catMap[$cid]['fact_expense'] ?? '0.00';
            $cnt = $catMap[$cid]['operation_count'] ?? 0;
            $flow = self::stringSub($fi, $fe);
            $rows[] = [
                'category_id' => $cat['id'],
                'code' => $cat['code'],
                'name' => $cat['name'],
                'direction' => $cat['direction'],
                'fact_income' => $fi,
                'fact_expense' => $fe,
                'net_flow' => $flow,
                'operation_count' => $cnt,
                'is_uncategorized' => false,
            ];
            $totalIncome = self::stringAdd($totalIncome, $fi);
            $totalExpense = self::stringAdd($totalExpense, $fe);
        }

        if ($fetchUncategorized) {
            $uncatOpWhere = "fo.status = 'POSTED' AND fo.operation_date >= ? AND fo.operation_date <= ?
                              AND fo.operation_type IN ('INCOME','EXPENSE')
                              AND fo.dds_category_id IS NULL";
            $uncatParams = [$dateFrom, $dateTo];

            if ($moneyAccountId !== '') {
                $uncatOpWhere .= ' AND fo.money_account_id = ?';
                $uncatParams[] = $moneyAccountId;
            }

            if ($directionFilter !== 'all' && $directionFilter !== '') {
                $uncatOpWhere .= ' AND fo.operation_type = ?';
                $uncatParams[] = $directionFilter;
            }

            $uncatSql = "SELECT fo.operation_type,
                                SUM(fo.amount) AS total_amount,
                                COUNT(fo.id) AS operation_count
                         FROM finance_operations fo
                         WHERE {$uncatOpWhere}
                         GROUP BY fo.operation_type";
            $uncatStmt = $localPdo->prepare($uncatSql);
            $uncatStmt->execute($uncatParams);
            $uncatRows = $uncatStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($uncatRows as $u) {
                $amt = $u['total_amount'];
                $cnt = (int) $u['operation_count'];
                if ($u['operation_type'] === 'INCOME') {
                    $flow = self::stringSub($amt, '0.00');
                    $rows[] = [
                        'category_id' => null,
                        'code' => '',
                        'name' => 'Без статьи: поступления',
                        'direction' => 'INCOME',
                        'fact_income' => $amt,
                        'fact_expense' => '0.00',
                        'net_flow' => $flow,
                        'operation_count' => $cnt,
                        'is_uncategorized' => true,
                    ];
                    $totalIncome = self::stringAdd($totalIncome, $amt);
                } elseif ($u['operation_type'] === 'EXPENSE') {
                    $flow = self::stringSub('0.00', $amt);
                    $rows[] = [
                        'category_id' => null,
                        'code' => '',
                        'name' => 'Без статьи: расходы',
                        'direction' => 'EXPENSE',
                        'fact_income' => '0.00',
                        'fact_expense' => $amt,
                        'net_flow' => $flow,
                        'operation_count' => $cnt,
                        'is_uncategorized' => true,
                    ];
                    $totalExpense = self::stringAdd($totalExpense, $amt);
                }
            }
        }

        if ($searchQuery !== '') {
            $sq = mb_strtolower(trim($searchQuery), 'UTF-8');
            $filteredRows = array_filter($rows, function ($r) use ($sq) {
                $name = mb_strtolower($r['name'] ?? '', 'UTF-8');
                $code = mb_strtolower($r['code'] ?? '', 'UTF-8');
                return str_contains($name, $sq) || str_contains($code, $sq);
            });
            $rows = array_values($filteredRows);
            $totalIncome = '0.00';
            $totalExpense = '0.00';
            foreach ($rows as $r) {
                $totalIncome = self::stringAdd($totalIncome, $r['fact_income']);
                $totalExpense = self::stringAdd($totalExpense, $r['fact_expense']);
            }
        }

        $transferData = self::fetchTransferTotals($localPdo, $dateFrom, $dateTo, $moneyAccountId);
        $adjustmentData = self::fetchAdjustmentTotals($localPdo, $dateFrom, $dateTo, $moneyAccountId);

        return [
            'rows' => $rows,
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'transfer_in' => $transferData['transfer_in'],
            'transfer_out' => $transferData['transfer_out'],
            'transfer_net' => $transferData['transfer_net'],
            'adjustment_total' => $adjustmentData,
        ];
    }

    public static function getSummary(PDO $localPdo, array $filters = []): array
    {
        $dateFrom = $filters['date_from'] ?? date('Y-m-01');
        $moneyAccountId = $filters['money_account_id'] ?? '';

        $openingBalance = self::calcOpeningBalance($localPdo, $dateFrom, $moneyAccountId);

        $reportData = self::fetchReportData($localPdo, $filters);
        $totalIncome = $reportData['total_income'];
        $totalExpense = $reportData['total_expense'];
        $netCashFlow = self::stringSub($totalIncome, $totalExpense);

        $transferIn = $reportData['transfer_in'];
        $transferOut = $reportData['transfer_out'];
        $transferNet = $reportData['transfer_net'];
        $adjustmentTotal = $reportData['adjustment_total'];

        $closingBalance = $openingBalance;
        $closingBalance = self::stringAdd($closingBalance, $totalIncome);
        $closingBalance = self::stringSub($closingBalance, $totalExpense);
        $closingBalance = self::stringAdd($closingBalance, $transferNet);
        $closingBalance = self::stringAdd($closingBalance, $adjustmentTotal);

        return [
            'opening_balance' => $openingBalance,
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_cash_flow' => $netCashFlow,
            'transfer_in' => $transferIn,
            'transfer_out' => $transferOut,
            'transfer_net' => $transferNet,
            'adjustment_total' => $adjustmentTotal,
            'closing_balance' => $closingBalance,
        ];
    }

    private static function calcOpeningBalance(PDO $localPdo, string $dateFrom, string $moneyAccountId = ''): string
    {
        $dayBefore = date('Y-m-d', strtotime($dateFrom . ' -1 day'));

        if ($moneyAccountId !== '') {
            $stmt = $localPdo->prepare('SELECT type FROM finance_money_accounts WHERE id = ?');
            $stmt->execute([$moneyAccountId]);
            $type = $stmt->fetchColumn();
            if ($type === 'BANK') {
                return FinanceBalanceService::balanceForMoneyAccount($localPdo, (int) $moneyAccountId, $dayBefore);
            }
            return FinanceBalanceService::cashOnlyBalance($localPdo, (int) $moneyAccountId, $dayBefore);
        }

        $allBalances = FinanceBalanceService::allBalances($localPdo, $dayBefore);
        $balance = '0.00';
        foreach ($allBalances as $a) {
            $balance = self::stringAdd($balance, $a['balance']);
        }
        return $balance;
    }

    private static function fetchTransferTotals(PDO $localPdo, string $dateFrom, string $dateTo, string $moneyAccountId = ''): array
    {
        $where = "fo.status = 'POSTED' AND fo.operation_type = 'TRANSFER'
                  AND fo.operation_date >= ? AND fo.operation_date <= ?";
        $params = [$dateFrom, $dateTo];
        if ($moneyAccountId !== '') {
            $where .= ' AND fo.money_account_id = ?';
            $params[] = $moneyAccountId;
        }

        $sql = "SELECT fo.transfer_direction, SUM(fo.amount) AS total
                FROM finance_operations fo
                WHERE {$where}
                GROUP BY fo.transfer_direction";
        $stmt = $localPdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $transferIn = '0.00';
        $transferOut = '0.00';
        foreach ($rows as $r) {
            $dir = $r['transfer_direction'] ?? '';
            if ($dir === 'in') {
                $transferIn = self::stringAdd($transferIn, $r['total']);
            } elseif ($dir === 'out') {
                $transferOut = self::stringAdd($transferOut, $r['total']);
            }
        }
        $transferNet = self::stringSub($transferIn, $transferOut);

        return [
            'transfer_in' => $transferIn,
            'transfer_out' => $transferOut,
            'transfer_net' => $transferNet,
        ];
    }

    private static function fetchAdjustmentTotals(PDO $localPdo, string $dateFrom, string $dateTo, string $moneyAccountId = ''): string
    {
        $where = "fo.status = 'POSTED' AND fo.operation_type = 'ADJUSTMENT'
                  AND fo.operation_date >= ? AND fo.operation_date <= ?";
        $params = [$dateFrom, $dateTo];
        if ($moneyAccountId !== '') {
            $where .= ' AND fo.money_account_id = ?';
            $params[] = $moneyAccountId;
        }

        $sql = "SELECT SUM(fo.amount) AS total FROM finance_operations fo WHERE {$where}";
        $stmt = $localPdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row['total'] ?? '0.00';
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
