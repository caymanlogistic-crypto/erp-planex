<?php
$_SERVER['REQUEST_METHOD']='POST';
require_once __DIR__.'/../app/Support/helpers.php';
require_once __DIR__.'/../app/Support/environment.php';
loadEnvFileNonOverwriting(dirname(__DIR__).'/.env');
$config=require __DIR__.'/../bootstrap/app.php';
require_once base_path('app/Support/entrypoint_dependencies.php');
$db=new \App\Core\Database($config['database']);
$pdo=$db->connection();
$companies=$pdo->query("SELECT * FROM companies WHERE status='active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$files=glob(base_path('database/migrations-local/*.sql'))?:[]; sort($files,SORT_STRING);
foreach($companies as $company){
  $local=(new \App\Core\Database(companyDatabaseConfig($config,$company)))->connection();
  $dbName=(string)$local->query('SELECT DATABASE()')->fetchColumn();
  echo "COMPANY={$company['id']} DB={$dbName}\n";
  $has=(int)$local->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations'")->fetchColumn();
  if(!$has){echo "NO_SCHEMA_MIGRATIONS\n";continue;}
  $applied=$local->query('SELECT migration,checksum FROM schema_migrations')->fetchAll(PDO::FETCH_KEY_PAIR);
  foreach($files as $file){$name=basename($file); if(!isset($applied[$name])) continue; $cur=hash('sha256',(string)file_get_contents($file)); if(!hash_equals((string)$applied[$name],$cur)){echo "MISMATCH {$name} stored={$applied[$name]} current={$cur}\n";}}
  $idx=$local->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='document_types' AND INDEX_NAME='uk_name_entity_type'")->fetchColumn();
  $types=$local->query("SELECT code,entity_type,category FROM document_types WHERE entity_type='vehicle_unit' AND code IN ('sts','diagnostic_card','photo') ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
  echo 'M027_COMPAT index='.(int)$idx.' rows='.json_encode($types,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
}
