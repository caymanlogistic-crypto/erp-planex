<?php
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(2); }

$root = getenv('ERPV2_ROOT') ?: '/home/s/spugovxsim/planexp/public_html/erpv2';
require_once $root . '/app/Support/helpers.php';
require_once $root . '/app/Support/environment.php';
loadEnvFileNonOverwriting($root . '/.env');
$config = require $root . '/bootstrap/app.php';
require_once $root . '/app/Support/entrypoint_dependencies.php';

$db = new \App\Core\Database($config['database']);
$central = $db->connection();
$stmt = $central->query("SELECT * FROM companies WHERE status='active' ORDER BY id");
$planex = null;
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $company) {
    $name = (string)($company['name'] ?? '');
    if (mb_stripos($name, 'ПЛАНЭКС') !== false || mb_stripos($name, 'PLANEX') !== false) {
        if ($planex !== null) { throw new RuntimeException('Fail closed: multiple PLANEX-like active companies.'); }
        $planex = $company;
    }
}
if (!$planex) { throw new RuntimeException('Fail closed: PLANEX active company not found.'); }
if ((int)$planex['id'] !== 25) { throw new RuntimeException('Fail closed: unexpected PLANEX company id.'); }

$pdo = (new \App\Core\Database(companyDatabaseConfig($config, $planex)))->connection();
$dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();

$refs = [];
$columns = $pdo->query("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME IN ('dds_category_id','cash_flow_center_id','target_dds_category_id','target_cash_flow_center_id') ORDER BY TABLE_NAME,COLUMN_NAME")->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    $table = (string)$col['TABLE_NAME']; $column = (string)$col['COLUMN_NAME'];
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) continue;
    if ($table === 'finance_cash_flow_center_dds_categories') continue;
    $count = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` IS NOT NULL")->fetchColumn();
    $refs[] = ['table'=>$table,'column'=>$column,'count'=>$count];
    if ($count !== 0) {
        throw new RuntimeException("Fail closed: {$table}.{$column} has {$count} references.");
    }
}

$before = [
    'company_id'=>(int)$planex['id'],
    'company_name'=>(string)$planex['name'],
    'database'=>$dbName,
    'cfu'=>$pdo->query('SELECT id,name,is_active,sort_order FROM finance_cash_flow_centers ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
    'dds'=>$pdo->query('SELECT id,name,direction,is_system,is_active,sort_order,parent_id FROM finance_dds_categories ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
    'links'=>$pdo->query('SELECT * FROM finance_cash_flow_center_dds_categories ORDER BY cash_flow_center_id,dds_category_id')->fetchAll(PDO::FETCH_ASSOC),
    'references'=>$refs,
];
echo 'P62_BACKUP=' . json_encode($before, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . PHP_EOL;

if (count($before['cfu']) !== 1 || count($before['dds']) !== 17 || count($before['links']) !== 17) {
    throw new RuntimeException('Fail closed: expected audited state 1 CFU / 17 DDS / 17 links has changed.');
}

$pdo->beginTransaction();
try {
    $pdo->exec('DELETE FROM finance_cash_flow_center_dds_categories');
    $pdo->exec('UPDATE finance_dds_categories SET parent_id=NULL WHERE parent_id IS NOT NULL');
    $pdo->exec('DELETE FROM finance_dds_categories');
    $pdo->exec('DELETE FROM finance_cash_flow_centers');
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}

// Clean-slate IDs. These tables are now empty and are user-maintained directories.
$pdo->exec('ALTER TABLE finance_dds_categories AUTO_INCREMENT=1');
$pdo->exec('ALTER TABLE finance_cash_flow_centers AUTO_INCREMENT=1');

$after = [
    'cfu_count'=>(int)$pdo->query('SELECT COUNT(*) FROM finance_cash_flow_centers')->fetchColumn(),
    'dds_count'=>(int)$pdo->query('SELECT COUNT(*) FROM finance_dds_categories')->fetchColumn(),
    'link_count'=>(int)$pdo->query('SELECT COUNT(*) FROM finance_cash_flow_center_dds_categories')->fetchColumn(),
    'bank_cfu_refs'=>(int)$pdo->query('SELECT COUNT(*) FROM bank_transactions WHERE cash_flow_center_id IS NOT NULL')->fetchColumn(),
    'bank_dds_refs'=>(int)$pdo->query('SELECT COUNT(*) FROM bank_transactions WHERE dds_category_id IS NOT NULL')->fetchColumn(),
    'operation_cfu_refs'=>(int)$pdo->query('SELECT COUNT(*) FROM finance_operations WHERE cash_flow_center_id IS NOT NULL')->fetchColumn(),
    'operation_dds_refs'=>(int)$pdo->query('SELECT COUNT(*) FROM finance_operations WHERE dds_category_id IS NOT NULL')->fetchColumn(),
];
if (array_sum($after) !== 0) { throw new RuntimeException('Reset verification failed: non-zero state remains.'); }
echo 'P62_AFTER=' . json_encode($after, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo "P62_PLANEX_FINANCIAL_STRUCTURE_RESET_OK\n";
