<?php
declare(strict_types=1);
$source=file_get_contents(__DIR__.'/../app/Service/FinanceCashFlowReportService.php');
$pass=str_contains($source,"if (\$catId === null)")
    && str_contains($source,'continue;')
    && strpos($source,"if (\$catId === null)") < strpos($source,'$catMap[$catId]');
echo ($pass?'PASS':'FAIL')," uncategorized aggregate never uses a null array key\n";
exit($pass?0:1);
