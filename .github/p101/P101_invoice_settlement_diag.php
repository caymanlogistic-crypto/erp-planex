<?php

declare(strict_types=1);

$root = $argv[1] ?? '';
if ($root === '' || !is_dir($root)) { fwrite(STDERR, "invalid app root\n"); exit(2); }
require_once $root . '/app/Support/helpers.php';
require_once $root . '/app/Support/environment.php';
loadEnvFileNonOverwriting($root . '/.env');
$config = require $root . '/bootstrap/app.php';
require_once base_path('app/Support/entrypoint_dependencies.php');
require_once base_path('app/Service/FinanceInvoiceSettlementHistoryService.php');

$central = (new \App\Core\Database($config['database']))->connection();
$companies = $central->query("SELECT * FROM companies WHERE status='active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$out = [];
foreach ($companies as $company) {
    try {
        $pdo = (new \App\Core\Database(companyDatabaseConfig($config, $company)))->connection();
        $invoiceStmt = $pdo->prepare("SELECT * FROM finance_invoices WHERE direction='INCOMING' AND amount=14600.00 AND counterparty_name LIKE '%Неизвестное%' ORDER BY id DESC LIMIT 1");
        $invoiceStmt->execute();
        $invoice = $invoiceStmt->fetch(PDO::FETCH_ASSOC);
        if (!$invoice) continue;
        $invoiceId = (int)$invoice['id'];

        $alloc = $pdo->prepare("SELECT a.*,fo.operation_date,fo.operation_type,fo.source,fo.status AS operation_status,fo.bank_transaction_id,fo.money_account_id,ma.name AS money_account_name,ma.type AS money_account_type
          FROM finance_operation_allocations a
          JOIN finance_operations fo ON fo.id=a.operation_id
          LEFT JOIN finance_money_accounts ma ON ma.id=fo.money_account_id
         WHERE a.invoice_id=? ORDER BY a.id");
        $alloc->execute([$invoiceId]);
        $allocations = $alloc->fetchAll(PDO::FETCH_ASSOC);

        $events = [];
        try {
            $e = $pdo->prepare("SELECT * FROM finance_employee_invoice_payments WHERE invoice_id=? ORDER BY id");
            $e->execute([$invoiceId]);
            $events = $e->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $events = [['error'=>$e->getMessage()]];
        }

        $serviceRows = \App\Service\FinanceInvoiceSettlementHistoryService::forInvoice($pdo, $invoiceId);

        $rawQueryRows = [];
        $rawQueryError = null;
        try {
            $stmt = $pdo->prepare(
                "SELECT fo.id AS operation_id,
                        COALESCE(bt.operation_date,fo.operation_date,MAX(a.allocation_date)) AS actual_date,
                        SUM(a.amount) AS amount,
                        fo.bank_transaction_id,
                        fo.source,
                        fma.type AS money_account_type,
                        fma.name AS money_account_name,
                        MAX(eip.employee_name_snapshot) AS employee_name
                   FROM finance_operation_allocations a
                   JOIN finance_operations fo ON fo.id=a.operation_id
              LEFT JOIN bank_transactions bt ON bt.id=fo.bank_transaction_id
              LEFT JOIN finance_money_accounts fma ON fma.id=fo.money_account_id
              LEFT JOIN finance_employee_invoice_payments eip
                     ON eip.expense_finance_operation_id=fo.id AND eip.status='POSTED'
                  WHERE a.invoice_id=?
                    AND a.cancelled_at IS NULL
                    AND fo.status='POSTED'
                  GROUP BY fo.id,bt.operation_date,fo.operation_date,fo.bank_transaction_id,fo.source,fma.type,fma.name
                  ORDER BY COALESCE(bt.operation_date,fo.operation_date,MAX(a.allocation_date)),fo.id"
            );
            $stmt->execute([$invoiceId]);
            $rawQueryRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $rawQueryError = $e->getMessage();
        }

        $fallbackRows = [];
        $fallbackError = null;
        try {
            $stmt = $pdo->prepare(
                "SELECT fo.id AS operation_id,
                        COALESCE(bt.operation_date,fo.operation_date,MAX(a.allocation_date)) AS actual_date,
                        SUM(a.amount) AS amount,
                        fo.bank_transaction_id,
                        fo.source,
                        fma.type AS money_account_type,
                        fma.name AS money_account_name,
                        NULL AS employee_name
                   FROM finance_operation_allocations a
                   JOIN finance_operations fo ON fo.id=a.operation_id
              LEFT JOIN bank_transactions bt ON bt.id=fo.bank_transaction_id
              LEFT JOIN finance_money_accounts fma ON fma.id=fo.money_account_id
                  WHERE a.invoice_id=?
                    AND a.cancelled_at IS NULL
                    AND fo.status='POSTED'
                  GROUP BY fo.id,bt.operation_date,fo.operation_date,fo.bank_transaction_id,fo.source,fma.type,fma.name
                  ORDER BY COALESCE(bt.operation_date,fo.operation_date,MAX(a.allocation_date)),fo.id"
            );
            $stmt->execute([$invoiceId]);
            $fallbackRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $fallbackError = $e->getMessage();
        }

        $out[] = [
            'company_id'=>(int)$company['id'],
            'company_name'=>(string)$company['name'],
            'invoice'=>$invoice,
            'allocations'=>$allocations,
            'employee_events'=>$events,
            'service_rows'=>$serviceRows,
            'raw_query_rows'=>$rawQueryRows,
            'raw_query_error'=>$rawQueryError,
            'fallback_rows'=>$fallbackRows,
            'fallback_error'=>$fallbackError,
        ];
    } catch (Throwable $e) {
        $out[] = ['company_id'=>(int)$company['id'],'company_name'=>(string)$company['name'],'error'=>$e->getMessage()];
    }
}

file_put_contents('/tmp/P101_SETTLEMENT_DIAG.json', json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
printf("P101_SETTLEMENT_DIAG_OK matches=%d\n", count(array_filter($out, static fn(array $r): bool => isset($r['invoice']))));
