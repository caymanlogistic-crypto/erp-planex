<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$file = $root . '/app/Service/ContractorService.php';
$src = file_get_contents($file);
if ($src === false) {
    fwrite(STDERR, "Cannot read ContractorService.php\n");
    exit(1);
}
exec('php -l ' . escapeshellarg($file), $out, $code);
if ($code !== 0) {
    fwrite(STDERR, "PHP lint failed\n");
    exit(1);
}
if (!preg_match('/function\s+getLocalPdo\s*\([^)]*\).*?\{(.*?)\n\s*\}/s', $src, $m)) {
    fwrite(STDERR, "getLocalPdo not found\n");
    exit(1);
}
if (str_contains($m[1], 'applyLocalMigrations')) {
    fwrite(STDERR, "Runtime full migration call still present in ContractorService::getLocalPdo\n");
    exit(1);
}
if (!str_contains($m[1], 'ensureContractorTables')) {
    fwrite(STDERR, "Narrow contractor table guard missing\n");
    exit(1);
}
echo "P24 CONTRACTOR RUNTIME MIGRATION REGRESSION: PASS\n";
