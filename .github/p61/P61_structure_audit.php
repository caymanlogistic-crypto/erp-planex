<?php
if (PHP_SAPI !== 'cli') { exit(2); }
$root = getenv('ERPV2_ROOT') ?: '/home/s/spugovxsim/planexp/public_html/erpv2';
require_once $root . '/app/Support/helpers.php';
require_once $root . '/app/Support/environment.php';
loadEnvFileNonOverwriting($root . '/.env');
$config = require $root . '/bootstrap/app.php';
require_once $root . '/app/Support/entrypoint_dependencies.php';
$db = new \App\Core\Database($config['database']);
$central = $db->connection();
$companies = $central->query("SELECT * FROM companies WHERE status='active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($companies as $company) {
    $name = (string)($company['name'] ?? '');
    $local = (new \App\Core\Database(companyDatabaseConfig($config, $company)))->connection();
    $dbName = (string)$local->query('SELECT DATABASE()')->fetchColumn();
    $isPlanex = mb_stripos($name, 'ПЛАНЭКС') !== false || mb_stripos($name, 'PLANEX') !== false;
    $tables = [];
    $stmt = $local->query("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME IN ('dds_category_id','cash_flow_center_id','target_dds_category_id','target_cash_flow_center_id') ORDER BY TABLE_NAME,COLUMN_NAME");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        $table = $col['TABLE_NAME']; $column = $col['COLUMN_NAME'];
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) continue;
        $count = (int)$local->query("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` IS NOT NULL")->fetchColumn();
        $tables[] = ['table'=>$table,'column'=>$column,'nonnull'=>$count];
    }
    $payload = [
        'company_id'=>(int)$company['id'],
        'company_name'=>$name,
        'database'=>$dbName,
        'is_planex'=>$isPlanex,
        'cfu_count'=>(int)$local->query('SELECT COUNT(*) FROM finance_cash_flow_centers')->fetchColumn(),
        'dds_count'=>(int)$local->query('SELECT COUNT(*) FROM finance_dds_categories')->fetchColumn(),
        'dds_system_count'=>(int)$local->query('SELECT COUNT(*) FROM finance_dds_categories WHERE is_system=1')->fetchColumn(),
        'link_count'=>(int)$local->query('SELECT COUNT(*) FROM finance_cash_flow_center_dds_categories')->fetchColumn(),
        'active_matching_rules'=>(int)$local->query('SELECT COUNT(*) FROM finance_matching_rules WHERE deleted_at IS NULL')->fetchColumn(),
        'references'=>$tables,
    ];
    echo 'P61_AUDIT=' . json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . PHP_EOL;
    if ($isPlanex) {
        $cfu = $local->query('SELECT id,name,is_active,sort_order FROM finance_cash_flow_centers ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $dds = $local->query('SELECT id,name,direction,is_system,is_active,sort_order FROM finance_dds_categories ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        echo 'P61_PLANEX_CFU=' . json_encode($cfu, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . PHP_EOL;
        echo 'P61_PLANEX_DDS=' . json_encode($dds, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
}
