<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    $root . '/app/Http/Controllers/Superadmin/CompanyActions/view.php',
    $root . '/app/Http/Controllers/Superadmin/ManagementActions/directories.php',
];

$failed = false;
foreach ($files as $file) {
    $source = file_get_contents($file);
    if ($source === false) {
        fwrite(STDERR, "FAIL: cannot read {$file}\n");
        $failed = true;
        continue;
    }

    if (str_contains($source, 'applyLocalMigrations(')) {
        fwrite(STDERR, "FAIL: read-only superadmin action invokes migrations: {$file}\n");
        $failed = true;
    } else {
        echo "PASS: no migrations in {$file}\n";
    }
}

exit($failed ? 1 : 0);
