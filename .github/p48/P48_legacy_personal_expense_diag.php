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

$central = (new \App\Core\Database($config['database']))->connection();
$companies = $central->query("SELECT * FROM companies WHERE status='active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$out = [];
foreach ($companies as $company) {
    try {
        $pdo = (new \App\Core\Database(companyDatabaseConfig($config, $company)))->connection();
        $has = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_employee_personal_expenses'")->fetchColumn();
        if ((int)$has !== 1) continue;

        $stmt = $pdo->prepare("SELECT * FROM finance_employee_personal_expenses
            WHERE status='POSTED'
              AND amount = 1100.00
              AND operation_date BETWEEN '2026-08-01' AND '2026-08-03'
              AND (employee_name_snapshot LIKE '%Гладких%' OR counterparty_name LIKE '%ATI%' OR purpose LIKE '%ATI%')
            ORDER BY id");
        $stmt->execute();
        $facts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$facts) continue;

        $opsStmt = $pdo->prepare("SELECT fo.id,fo.operation_date,fo.operation_type,fo.source,fo.status,fo.amount,fo.currency,
               fo.money_account_id,ma.name AS money_account_name,ma.type AS money_account_type,
               fo.transfer_direction,fo.transfer_account_id,ta.name AS transfer_account_name,
               fo.counterparty_entity_type,fo.counterparty_entity_id,fo.counterparty_name,
               fo.cash_flow_center_id,fo.dds_category_id,fo.linear_route_id,fo.purpose,fo.comment,
               fo.created_at,fo.updated_at
          FROM finance_operations fo
          LEFT JOIN finance_money_accounts ma ON ma.id=fo.money_account_id
          LEFT JOIN finance_money_accounts ta ON ta.id=fo.transfer_account_id
         WHERE fo.status='POSTED' AND fo.amount=1100.00
           AND fo.operation_date BETWEEN '2026-08-01' AND '2026-08-03'
         ORDER BY fo.id");
        $opsStmt->execute();
        $ops = $opsStmt->fetchAll(PDO::FETCH_ASSOC);

        $movements = [];
        $hasMov = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_employee_movements'")->fetchColumn();
        if ((int)$hasMov === 1) {
            $m = $pdo->prepare("SELECT fem.*,fo.operation_date,fo.operation_type,fo.source,fo.amount,fo.money_account_id,fo.transfer_direction,fo.purpose AS operation_purpose,fo.comment AS operation_comment
              FROM finance_employee_movements fem
              LEFT JOIN finance_operations fo ON fo.id=fem.finance_operation_id
             WHERE fem.employee_name_snapshot LIKE '%Гладких%'
               AND fo.amount=1100.00
               AND fo.operation_date BETWEEN '2026-08-01' AND '2026-08-03'
             ORDER BY fem.id");
            $m->execute();
            $movements = $m->fetchAll(PDO::FETCH_ASSOC);
        }

        $resolutions = [];
        $hasRes = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_cash_resolutions'")->fetchColumn();
        if ((int)$hasRes === 1 && $ops) {
            $ids = array_map(static fn(array $r): int => (int)$r['id'], $ops);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $r = $pdo->prepare("SELECT * FROM finance_cash_resolutions WHERE source_finance_operation_id IN ($placeholders) OR outflow_finance_operation_id IN ($placeholders) ORDER BY id");
            $r->execute(array_merge($ids, $ids));
            $resolutions = $r->fetchAll(PDO::FETCH_ASSOC);
        }

        $out[] = [
            'company_id' => (int)$company['id'],
            'company_name' => (string)$company['name'],
            'facts' => $facts,
            'operations' => $ops,
            'employee_movements' => $movements,
            'cash_resolutions' => $resolutions,
        ];
    } catch (Throwable $e) {
        $out[] = ['company_id'=>(int)$company['id'],'company_name'=>(string)$company['name'],'error'=>$e->getMessage()];
    }
}

file_put_contents('P48_LEGACY_DIAG.json', json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
printf("P48_LEGACY_DIAG_OK matches=%d\n", count(array_filter($out, static fn(array $r): bool => !empty($r['facts']))));
