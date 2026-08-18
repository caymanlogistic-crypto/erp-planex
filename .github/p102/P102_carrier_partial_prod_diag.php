<?php

declare(strict_types=1);

$root = $argv[1] ?? '';
if ($root === '' || !is_dir($root)) {
    fwrite(STDERR, "invalid app root\n");
    exit(2);
}

require_once $root . '/app/Support/helpers.php';
require_once $root . '/app/Support/environment.php';
loadEnvFileNonOverwriting($root . '/.env');
$config = require $root . '/bootstrap/app.php';
require_once base_path('app/Support/entrypoint_dependencies.php');

$servicePath = $root . '/app/Service/FinanceCarrierBankAutoSettlementService.php';
$applyRulesPath = $root . '/app/Http/Controllers/Company/BankFinanceActions/applyRules.php';
if (!is_file($servicePath)) {
    throw new RuntimeException('deployed carrier auto-settlement service missing');
}
if (!is_file($applyRulesPath) || !str_contains((string)file_get_contents($applyRulesPath), 'autoAllocateOutgoingCarrierPayments')) {
    throw new RuntimeException('deployed applyRules carrier call missing');
}

$central = (new \App\Core\Database($config['database']))->connection();
$companies = $central->query("SELECT * FROM companies WHERE status='active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$out = [];

foreach ($companies as $company) {
    try {
        $pdo = (new \App\Core\Database(companyDatabaseConfig($config, $company)))->connection();
        $txStmt = $pdo->prepare(
            "SELECT * FROM bank_transactions
              WHERE operation_date='2026-08-11'
                AND counterparty_inn='2703906300'
                AND ABS(COALESCE(debit_amount,0)-145000.00)<0.01
                AND purpose LIKE '%26/3947%'
              ORDER BY id"
        );
        $txStmt->execute();
        $txs = $txStmt->fetchAll(PDO::FETCH_ASSOC);
        if ($txs === []) {
            continue;
        }

        foreach ($txs as $tx) {
            $opStmt = $pdo->prepare(
                "SELECT fo.*,
                        (fo.amount-COALESCE((SELECT SUM(a.amount)
                                               FROM finance_operation_allocations a
                                              WHERE a.operation_id=fo.id AND a.cancelled_at IS NULL),0)) AS remaining_amount
                   FROM finance_operations fo
                  WHERE fo.bank_transaction_id=? AND fo.status='POSTED'
                  ORDER BY fo.id"
            );
            $opStmt->execute([(int)$tx['id']]);
            $ops = $opStmt->fetchAll(PDO::FETCH_ASSOC);

            $invoiceStmt = $pdo->prepare(
                "SELECT i.*,
                        (i.amount-COALESCE((SELECT SUM(a.amount)
                                             FROM finance_operation_allocations a
                                            WHERE a.invoice_id=i.id AND a.cancelled_at IS NULL),0)) AS remaining_amount,
                        ct.inn AS contractor_inn
                   FROM finance_invoices i
              LEFT JOIN contractors ct
                     ON i.counterparty_entity_type='contractor'
                    AND ct.id=i.counterparty_entity_id
                    AND ct.deleted_at IS NULL
                  WHERE i.direction='INCOMING'
                    AND i.status NOT IN ('cancelled','paid')
                    AND (TRIM(COALESCE(i.counterparty_inn,''))='2703906300' OR TRIM(COALESCE(ct.inn,''))='2703906300')
                  ORDER BY i.invoice_date,i.id"
            );
            $invoiceStmt->execute();
            $invoices = $invoiceStmt->fetchAll(PDO::FETCH_ASSOC);

            $matchingInvoices = [];
            $purposeNorm = mb_strtolower(preg_replace('/\s+/u', '', (string)$tx['purpose']) ?? (string)$tx['purpose'], 'UTF-8');
            foreach ($invoices as $invoice) {
                $numberNorm = mb_strtolower(preg_replace('/\s+/u', '', trim((string)$invoice['number'])) ?? trim((string)$invoice['number']), 'UTF-8');
                $withoutSign = preg_replace('/^[№#]+/u', '', $numberNorm) ?? $numberNorm;
                if (($numberNorm !== '' && str_contains($purposeNorm, $numberNorm))
                    || ($withoutSign !== '' && str_contains($purposeNorm, $withoutSign))) {
                    $matchingInvoices[] = $invoice;
                }
            }

            $target = count($matchingInvoices) === 1 ? $matchingInvoices[0] : null;
            $links = [];
            if ($target) {
                $l = $pdo->prepare(
                    "SELECT l.id,l.amount,l.obligation_id,o.direction,o.due_date,o.paid_amount,o.status
                       FROM finance_invoice_links l
                       JOIN finance_obligations o ON o.id=l.obligation_id
                      WHERE l.invoice_id=?
                      ORDER BY CASE WHEN o.due_date IS NULL THEN 1 ELSE 0 END,o.due_date,l.id"
                );
                $l->execute([(int)$target['id']]);
                $links = $l->fetchAll(PDO::FETCH_ASSOC);
            }

            $out[] = [
                'company_id' => (int)$company['id'],
                'company_name' => (string)$company['name'],
                'bank_transaction' => [
                    'id' => (int)$tx['id'],
                    'operation_date' => (string)$tx['operation_date'],
                    'counterparty_name' => (string)$tx['counterparty_name'],
                    'counterparty_inn' => (string)$tx['counterparty_inn'],
                    'debit_amount' => (string)$tx['debit_amount'],
                    'purpose' => (string)$tx['purpose'],
                    'classification_status' => (string)($tx['classification_status'] ?? ''),
                ],
                'operations' => $ops,
                'open_invoices' => array_map(static fn(array $i): array => [
                    'id'=>(int)$i['id'],
                    'number'=>(string)$i['number'],
                    'amount'=>(string)$i['amount'],
                    'remaining_amount'=>(string)$i['remaining_amount'],
                    'status'=>(string)$i['status'],
                ], $invoices),
                'number_match_count' => count($matchingInvoices),
                'target_invoice' => $target ? [
                    'id'=>(int)$target['id'],
                    'number'=>(string)$target['number'],
                    'amount'=>(string)$target['amount'],
                    'remaining_amount'=>(string)$target['remaining_amount'],
                    'status'=>(string)$target['status'],
                ] : null,
                'links' => $links,
            ];
        }
    } catch (Throwable $e) {
        $out[] = ['company_id'=>(int)$company['id'],'company_name'=>(string)$company['name'],'error'=>$e->getMessage()];
    }
}

$okRows = array_values(array_filter($out, static function(array $row): bool {
    if (!empty($row['error']) || empty($row['target_invoice'])) return false;
    if (($row['number_match_count'] ?? 0) !== 1) return false;
    $ops = $row['operations'] ?? [];
    if (count($ops) !== 1) return false;
    $op = $ops[0];
    if (($op['operation_type'] ?? '') !== 'EXPENSE') return false;
    if (abs((float)($op['remaining_amount'] ?? 0) - 145000.00) > 0.01) return false;
    if (abs((float)($row['target_invoice']['remaining_amount'] ?? 0) - 195000.00) > 0.01) return false;
    $amounts = array_map(static fn(array $l): float => (float)$l['amount'], $row['links'] ?? []);
    sort($amounts);
    return count($amounts) === 2 && abs($amounts[0]-58500.00)<0.01 && abs($amounts[1]-136500.00)<0.01;
}));

file_put_contents('/tmp/P102_CARRIER_PARTIAL_DIAG.json', json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
if (count($okRows) !== 1) {
    fwrite(STDERR, 'expected exactly one unambiguous carrier partial settlement candidate, got ' . count($okRows) . "\n");
    exit(1);
}

echo "P102_CARRIER_PARTIAL_READONLY_OK\n";
echo 'BANK_TX_ID=' . $okRows[0]['bank_transaction']['id'] . "\n";
echo 'INVOICE=' . $okRows[0]['target_invoice']['number'] . "\n";
echo 'BANK_REMAINING=' . $okRows[0]['operations'][0]['remaining_amount'] . "\n";
echo 'INVOICE_REMAINING=' . $okRows[0]['target_invoice']['remaining_amount'] . "\n";
