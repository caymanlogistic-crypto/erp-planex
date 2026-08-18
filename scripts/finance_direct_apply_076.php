<?php
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'cgi-fcgi') { fwrite(STDERR, "CLI/CGI only.\n"); exit(1); }

function fd76_env_file($path) {
    if (!is_file($path)) throw new RuntimeException('Missing .env file.');
    $rows = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $out = array();
    foreach ($rows as $row) {
        $row = trim($row); if ($row === '' || $row[0] === '#') continue;
        $pos = strpos($row, '='); if ($pos === false) continue;
        $key = trim(substr($row,0,$pos)); $value = trim(substr($row,$pos+1));
        if (strlen($value) >= 2) { $a=$value[0]; $b=$value[strlen($value)-1]; if (($a==='"'&&$b==='"')||($a==="'"&&$b==="'")) $value=substr($value,1,-1); }
        $out[$key]=$value;
    }
    return $out;
}

$envRoot=getenv('PLANEX_ROOT'); $argRoot=isset($argv[1])?$argv[1]:''; $root=rtrim((string)(($envRoot!==false&&$envRoot!=='')?$envRoot:$argRoot),'/');
if ($root==='' || !is_file($root.'/.env')) throw new RuntimeException('PLANEX_ROOT is invalid.');
$name='076_finance_employee_money_accounts.sql'; $path=$root.'/database/migrations-local/'.$name; $sql=file_get_contents($path);
if ($sql===false || trim($sql)==='') throw new RuntimeException('Migration 076 missing.');
$checksum=hash('sha256',$sql); $env=fd76_env_file($root.'/.env');
$host=isset($env['DB_HOST'])?$env['DB_HOST']:'127.0.0.1'; $port=isset($env['DB_PORT'])?$env['DB_PORT']:'3306'; $user=isset($env['DB_USERNAME'])?$env['DB_USERNAME']:''; $pass=isset($env['DB_PASSWORD'])?$env['DB_PASSWORD']:'';
if ($user==='') throw new RuntimeException('DB_USERNAME missing.');
$dbName='spugovxsim_1';
$pdo=new PDO('mysql:host='.$host.';port='.$port.';dbname='.$dbName.';charset=utf8mb4',$user,$pass,array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC));
if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn()!==$dbName) throw new RuntimeException('Unexpected tenant database.');
$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (id INT UNSIGNED NOT NULL AUTO_INCREMENT,migration VARCHAR(255) NOT NULL,checksum VARCHAR(64) NOT NULL,executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uk_local_migration(migration)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$lockName='planex_finance_direct_076_'.substr(hash('sha256',$dbName),0,24); $lock=$pdo->prepare('SELECT GET_LOCK(?,10)'); $lock->execute(array($lockName));
if ((int)$lock->fetchColumn()!==1) throw new RuntimeException('Cannot acquire migration lock.');
$state='';
try {
    $stmt=$pdo->prepare('SELECT checksum FROM schema_migrations WHERE migration=? LIMIT 1'); $stmt->execute(array($name)); $stored=$stmt->fetchColumn();
    if ($stored!==false) {
        if ((string)$stored!==$checksum) throw new RuntimeException('Migration 076 checksum mismatch.');
        $state='existing';
    } else {
        $newer=$pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE migration REGEXP '^[0-9]{3}_' AND CAST(LEFT(migration,3) AS UNSIGNED)>76");
        if ((int)$newer->fetchColumn()!==0) throw new RuntimeException('Newer migration exists while 076 absent.');
        $pdo->exec($sql); $ins=$pdo->prepare('INSERT INTO schema_migrations(migration,checksum) VALUES(?,?)'); $ins->execute(array($name,$checksum)); $state='applied';
    }
    $table=$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_employee_money_accounts'");
    if ((int)$table->fetchColumn()!==1) throw new RuntimeException('Employee account table missing.');
    $cols=$pdo->query("SELECT COUNT(DISTINCT COLUMN_NAME) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_employee_money_accounts' AND COLUMN_NAME IN ('money_account_id','employee_identity_type','employee_identity_id','employee_name_snapshot','is_active')");
    if ((int)$cols->fetchColumn()!==5) throw new RuntimeException('Employee account table shape invalid.');
    printf("FINANCE_DIRECT_076_COMPANY_OK id=25 db=%s state=%s\n",$dbName,$state);
} catch (Exception $e) {
    $release=$pdo->prepare('SELECT RELEASE_LOCK(?)'); $release->execute(array($lockName)); throw $e;
}
$release=$pdo->prepare('SELECT RELEASE_LOCK(?)'); $release->execute(array($lockName));
printf("FINANCE_DIRECT_076_OK applied=%d existing=%d companies=1 checksum=%s\n",$state==='applied'?1:0,$state==='existing'?1:0,$checksum);
