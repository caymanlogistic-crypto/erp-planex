<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Service/DocumentService.php';
use App\Service\DocumentService;
$dir=sys_get_temp_dir().'/erp-synthetic-doc-'.bin2hex(random_bytes(4));mkdir($dir);
$fake=$dir.'/fake.pdf';file_put_contents($fake,"MZ\x90\x00SYNTHETIC TEST executable");
$txt=$dir.'/ТЕСТ.txt';file_put_contents($txt,'SYNTHETIC TEST document');
$cases=[
 ['fake executable renamed PDF',DocumentService::validateUploadedFile(['error'=>0,'tmp_name'=>$fake,'size'=>filesize($fake),'name'=>'SYNTHETIC.pdf'],false),'invalid_mime'],
 ['valid synthetic text',DocumentService::validateUploadedFile(['error'=>0,'tmp_name'=>$txt,'size'=>filesize($txt),'name'=>'ТЕСТ.txt'],false),null],
 ['traversal filename',DocumentService::validateUploadedFile(['error'=>0,'tmp_name'=>$txt,'size'=>filesize($txt),'name'=>'../../ТЕСТ.txt'],false),'invalid_filename'],
 ['zero size',DocumentService::validateUploadedFile(['error'=>0,'tmp_name'=>$txt,'size'=>0,'name'=>'zero.txt'],false),'file_too_large'],
];
$failed=0;foreach($cases as[$name,$actual,$expected]){if($actual!==$expected){$failed++;fwrite(STDERR,"FAIL $name\n");}else echo "PASS: $name\n";}
@unlink($fake);@unlink($txt);@rmdir($dir);exit($failed===0?0:1);
