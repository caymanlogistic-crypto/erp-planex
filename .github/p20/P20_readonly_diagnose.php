<?php
$root = getenv('P20_ROOT') ?: '/home/s/spugovxsim/planexp/public_html/erpv2';
chdir($root);
$config = require $root . '/bootstrap/app.php';
require_once $root . '/app/Core/Database.php';
require_once $root . '/app/Support/crypto_helper.php';
require_once $root . '/app/Support/company_database.php';
require_once $root . '/app/Service/FinanceOperationActionService.php';
use App\Core\Database;
use App\Service\FinanceOperationActionService;
$out = ['status'=>'FAIL','company_id'=>null,'operation_id'=>null,'tables'=>[],'service_call'=>null,'exception'=>null];
try {
    $central = (new Database($config['database']))->connection();
    $companies = $central->query("SELECT id,name,status,db_identifier,db_host,db_port,db_username,db_password FROM companies WHERE status='active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($companies as $company) {
        try {
            $pdo = (new Database(companyDatabaseConfig($config, $company)))->connection();
            $count = (int)$pdo->query('SELECT COUNT(*) FROM finance_operations')->fetchColumn();
            if ($count < 1) continue;
            $op = $pdo->query('SELECT id,operation_type,status,source FROM finance_operations ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
            if (!$op) continue;
            $out['company_id'] = (int)$company['id'];
            $out['operation_id'] = (int)$op['id'];
            $out['operation'] = $op;
            foreach (['finance_operations','finance_operation_allocations','finance_audit_log'] as $table) {
                $exists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table))->fetchColumn();
                $row = ['exists'=>(bool)$exists,'columns'=>[]];
                if ($exists) {
                    foreach ($pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC) as $c) {
                        $row['columns'][] = ['Field'=>$c['Field'],'Type'=>$c['Type'],'Null'=>$c['Null'],'Key'=>$c['Key']];
                    }
                }
                $out['tables'][$table] = $row;
            }
            try {
                $logs = FinanceOperationActionService::fetchOperationHistory($pdo, (int)$op['id']);
                $out['service_call'] = ['status'=>'PASS','count'=>count($logs)];
                $out['status'] = 'PASS';
            } catch (Throwable $e) {
                $out['service_call'] = ['status'=>'FAIL'];
                $out['exception'] = [
                    'class'=>get_class($e),
                    'message'=>$e->getMessage(),
                    'code'=>$e->getCode(),
                    'file'=>$e->getFile(),
                    'line'=>$e->getLine(),
                    'trace'=>array_slice($e->getTrace(),0,12),
                ];
            }
            break;
        } catch (Throwable $inner) {
            continue;
        }
    }
    if ($out['company_id'] === null) throw new RuntimeException('No active company with finance operations found.');
} catch (Throwable $e) {
    if ($out['exception'] === null) $out['exception']=['class'=>get_class($e),'message'=>$e->getMessage(),'code'=>$e->getCode(),'file'=>$e->getFile(),'line'=>$e->getLine(),'trace'=>array_slice($e->getTrace(),0,12)];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT), PHP_EOL;
