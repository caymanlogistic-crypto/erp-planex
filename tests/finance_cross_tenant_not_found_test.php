<?php
declare(strict_types=1);
$files=[
 __DIR__.'/../app/Http/Controllers/Company/InvoiceActions/modal_view.php',
 __DIR__.'/../app/Http/Controllers/Company/FinanceOperationActions/modal_view.php',
];
$pass=true;foreach($files as $file){$s=file_get_contents($file);$ok=preg_match('/if \(!\$(?:invoice|operation)\)\s*\{\s*http_response_code\(404\);/s',$s)===1;echo($ok?'PASS ':'FAIL '),basename(dirname($file)).'/'.basename($file),' returns 404 for tenant-local absence',PHP_EOL;$pass=$pass&&$ok;}exit($pass?0:1);
