<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Support/helpers.php';
require_once __DIR__ . '/../app/Support/environment.php';
require_once __DIR__ . '/../app/Support/crypto_helper.php';
require_once __DIR__ . '/../app/Support/company_database.php';
require_once __DIR__ . '/../app/Core/Database.php';

loadEnvFileNonOverwriting(dirname(__DIR__) . '/.env');
$config = require dirname(__DIR__) . '/bootstrap/app.php';
$apply = in_array('--apply', $argv, true);
$sql = file_get_contents(dirname(__DIR__) . '/database/migrations-local/028_add_secondary_driver_to_crews.sql');
if ($sql === false) { throw new RuntimeException('Migration file 028 not found.'); }

$central = new App\Core\Database($config['database']);
$pdo = $central->connection();
$companies = $pdo->query("SELECT id, name, db_identifier, db_host, db_port, db_username, db_password FROM companies WHERE db_identifier IS NOT NULL AND db_identifier != '' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

$result = ['mode' => $apply ? 'apply' : 'dry-run', 'companies' => count($companies), 'applied' => [], 'skipped' => [], 'errors' => []];
foreach ($companies as $company) {
    try {
        $local = new App\Core\Database(companyDatabaseConfig($config, $company));
        $lpdo = $local->connection();
        $exists = (int)$lpdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='crews' AND COLUMN_NAME='secondary_driver_id'")->fetchColumn() > 0;
        if ($exists) { $result['skipped'][] = ['company_id'=>(int)$company['id'],'db'=>$company['db_identifier'],'reason'=>'already_present']; continue; }
        if (!$apply) { $result['applied'][] = ['company_id'=>(int)$company['id'],'db'=>$company['db_identifier'],'action'=>'would_apply']; continue; }
        $lpdo->exec($sql);
        $verify = (int)$lpdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='crews' AND COLUMN_NAME='secondary_driver_id'")->fetchColumn();
        if ($verify !== 1) throw new RuntimeException('Column verification failed after migration.');
        $result['applied'][] = ['company_id'=>(int)$company['id'],'db'=>$company['db_identifier'],'action'=>'applied'];
    } catch (Throwable $e) {
        $result['errors'][] = ['company_id'=>(int)$company['id'],'db'=>$company['db_identifier'],'error'=>$e->getMessage()];
    }
}

if ($result['errors']) { fwrite(STDERR, json_encode($result, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n"); exit(1); }
echo json_encode($result, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
