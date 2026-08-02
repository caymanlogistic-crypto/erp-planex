<?php
declare(strict_types=1);
$base=dirname(__DIR__);
$delete=file_get_contents($base.'/app/Http/Controllers/Company/DocumentActions/delete.php');
$replace=file_get_contents($base.'/app/Http/Controllers/Company/DocumentActions/replace.php');
$checks=[
 'delete resolves stored path'=>str_contains((string)$delete,'DocumentService::resolveStoredPath'),
 'delete removes resolved file'=>str_contains((string)$delete,'unlink($oldFile)'),
 'replace resolves old stored path'=>str_contains((string)$replace,'$oldDoc[\'relative_path\']'),
 'replace removes old file after commit'=>strpos((string)$replace,'unlink($oldFile)')>strpos((string)$replace,'$lpdo->commit()'),
];
$failed=0;foreach($checks as$name=>$ok){echo($ok?'PASS: ':'FAIL: '),$name,"\n";if(!$ok)$failed++;}exit($failed===0?0:1);
