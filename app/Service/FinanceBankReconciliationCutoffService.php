<?php

namespace App\Service;

use PDO;

/**
 * Keeps legacy bank history intact while making the operational reconciliation
 * strict only from the agreed control date forward.
 */
final class FinanceBankReconciliationCutoffService
{
    public static function reconcileAll(PDO $localPdo, string $controlFromDate = '2026-07-24', ?string $asOfDate = null): array
    {
        $report = FinanceBankReconciliationService::reconcileAll($localPdo, $asOfDate);
        $accounts = [];

        foreach (($report['accounts'] ?? []) as $account) {
            $account['statement_arithmetic'] = self::filterByDate($account['statement_arithmetic'] ?? [], 'statement_date', $controlFromDate);

            $continuityItems = self::filterByDate(($account['continuity']['items'] ?? []), 'statement_date', $controlFromDate);
            if ($continuityItems) {
                $continuityItems[0]['status'] = 'FIRST';
                $continuityItems[0]['previous_closing_balance'] = null;
                $continuityItems[0]['previous_date'] = null;
                $continuityItems[0]['difference'] = '0.00';
            }
            $hasGap = false;
            foreach ($continuityItems as $item) {
                if (($item['status'] ?? '') === 'GAP') {
                    $hasGap = true;
                    break;
                }
            }
            $account['continuity'] = $continuityItems ? [
                'status' => $hasGap ? 'GAP' : 'OK',
                'items' => $continuityItems,
            ] : null;

            $account['duplicate_check'] = self::filterByDate($account['duplicate_check'] ?? [], 'operation_date', $controlFromDate);

            $aggregate = $account['transaction_aggregates'] ?? ['items' => [], 'mismatches' => []];
            $aggregate['items'] = self::filterByDate($aggregate['items'] ?? [], 'statement_date', $controlFromDate);
            $aggregate['mismatches'] = self::filterByDate($aggregate['mismatches'] ?? [], 'statement_date', $controlFromDate);
            $account['transaction_aggregates'] = $aggregate;

            $account['status'] = self::accountStatus($account);
            $account['control_from_date'] = $controlFromDate;
            $accounts[] = $account;
        }

        $report['accounts'] = $accounts;
        $report['summary'] = self::summarize($accounts);
        $report['control_from_date'] = $controlFromDate;
        $report['historical_before_control_ignored'] = true;

        return $report;
    }

    private static function filterByDate(array $items, string $field, string $fromDate): array
    {
        return array_values(array_filter($items, static function (array $item) use ($field, $fromDate): bool {
            $value = (string)($item[$field] ?? '');
            return $value !== '' && $value >= $fromDate;
        }));
    }

    private static function accountStatus(array $account): string
    {
        foreach (($account['statement_arithmetic'] ?? []) as $row) {
            if (($row['status'] ?? '') !== 'OK') {
                return 'INVALID_ARITHMETIC';
            }
        }
        if (($account['continuity']['status'] ?? 'OK') !== 'OK') {
            return 'GAP';
        }
        if (!empty($account['duplicate_check'])) {
            return 'DUPLICATE';
        }
        if (!empty($account['transaction_aggregates']['mismatches'])) {
            return 'MISMATCH';
        }
        if (empty($account['statement_arithmetic'])) {
            return 'NO_STATEMENT';
        }
        return 'OK';
    }

    private static function summarize(array $accounts): array
    {
        $summary = [
            'total_accounts' => count($accounts),
            'ok' => 0,
            'mismatch' => 0,
            'gap' => 0,
            'duplicate' => 0,
            'invalid_arithmetic' => 0,
            'no_statement' => 0,
            'not_found' => 0,
            'all_ok' => true,
        ];

        foreach ($accounts as $account) {
            $status = (string)($account['status'] ?? 'NOT_FOUND');
            switch ($status) {
                case 'OK': $summary['ok']++; break;
                case 'MISMATCH': $summary['mismatch']++; break;
                case 'GAP': $summary['gap']++; break;
                case 'DUPLICATE': $summary['duplicate']++; break;
                case 'INVALID_ARITHMETIC': $summary['invalid_arithmetic']++; break;
                case 'NO_STATEMENT': $summary['no_statement']++; break;
                default: $summary['not_found']++; break;
            }
            if ($status !== 'OK') {
                $summary['all_ok'] = false;
            }
        }

        return $summary;
    }
}
