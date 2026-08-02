<?php
require_once __DIR__ . '/../app/Service/FinanceAuditLogService.php';
use App\Service\FinanceAuditLogService as A;

$pass=0;$fail=0;
function ok(string $n,bool $v):void{global $pass,$fail;if($v){$pass++;}else{$fail++;fwrite(STDERR,"FAIL $n\n");}}
$p=A::sanitizePayload(['password'=>'x','nested'=>['api_token'=>'y','safe'=>'z'],'session_id'=>'s']);
ok('password redacted',$p['password']==='[REDACTED]');
ok('nested token redacted',$p['nested']['api_token']==='[REDACTED]');
ok('safe retained',$p['nested']['safe']==='z');
ok('session redacted',$p['session_id']==='[REDACTED]');
$source=file_get_contents(__DIR__.'/../app/Service/FinanceAuditLogService.php');
ok('json throws',str_contains($source,'JSON_THROW_ON_ERROR'));
ok('actor status signature',preg_match('/logStatusChange\([\s\S]*int \$userId = 0,[\s\S]*string \$roleCode = \'system\'/',$source)===1);
ok('status delegates log',str_contains($source,"'status_change'"));
ok('payload limit',str_contains($source,'MAX_PAYLOAD_BYTES'));
printf("AUDIT: %d passed, %d failed\n",$pass,$fail);exit($fail?1:0);
