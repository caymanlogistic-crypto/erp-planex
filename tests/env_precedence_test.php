<?php
require_once __DIR__ . '/../app/Support/environment.php';

$pass=0;$fail=0;
function check(string $name,bool $ok):void{global $pass,$fail;if($ok){$pass++;echo "[PASS] $name\n";}else{$fail++;echo "[FAIL] $name\n";}}
$tmp=tempnam(sys_get_temp_dir(),'planex_env_');
if($tmp===false){fwrite(STDERR,"Cannot create env fixture\n");exit(1);}
file_put_contents($tmp,"APP_ENV=local\nAPP_NAME='fixture name'\nEMPTY_ALLOWED=\nINVALID-NAME=x\n# COMMENTED=y\n");
$names=['APP_ENV','APP_NAME','EMPTY_ALLOWED','INVALID-NAME','COMMENTED'];
$before=[];foreach($names as $n){$before[$n]=getenv($n);putenv($n);unset($_ENV[$n]);}
try{
  putenv('APP_ENV=production');$_ENV['APP_ENV']='production';
  $loaded=loadEnvFileNonOverwriting($tmp);
  check('process APP_ENV wins',getenv('APP_ENV')==='production');
  check('APP_ENV not reported loaded',!in_array('APP_ENV',$loaded,true));
  check('quoted value loaded',getenv('APP_NAME')==='fixture name');
  check('empty value loaded',getenv('EMPTY_ALLOWED')==='');
  check('invalid name ignored',getenv('INVALID-NAME')===false);
  check('comment ignored',getenv('COMMENTED')===false);
  putenv('APP_ENV');unset($_ENV['APP_ENV']);
  $loaded2=loadEnvFileNonOverwriting($tmp);
  check('missing APP_ENV loaded',getenv('APP_ENV')==='local');
  check('already loaded APP_NAME preserved',getenv('APP_NAME')==='fixture name');
  check('missing file no-op',loadEnvFileNonOverwriting($tmp.'.missing')===[]);
} finally {
  @unlink($tmp);
  foreach($before as $n=>$v){if($v===false){putenv($n);unset($_ENV[$n]);}else{putenv($n.'='.$v);$_ENV[$n]=$v;}}
}
printf("ENV: %d passed, %d failed\n",$pass,$fail);exit($fail?1:0);
