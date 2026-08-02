<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    $root . '/app/Http/Controllers/Superadmin/CompanyActions/view.php',
    $root . '/app/Http/Controllers/Superadmin/ManagementActions/directories.php',
    $root . '/app/Http/Controllers/Superadmin/ManagementActions/documents.php',
    $root . '/app/Http/Controllers/Superadmin/ManagementActions/access_grants.php',
    $root . '/app/Http/Controllers/Superadmin/ManagementActions/entity_list.php',
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

$directories = file_get_contents($files[1]);
$directoriesView = file_get_contents($root . '/app/View/pages/superadmin_company_directories.php');
if (!is_string($directories) || !str_contains($directories, '$dirs[$table]')) {
    fwrite(STDERR, "FAIL: directories action does not provide the view data contract\n");
    $failed = true;
} else {
    echo "PASS: directories action provides the view data contract\n";
}
if (!is_string($directoriesView) || preg_match('~href="/superadmin/~', $directoriesView)) {
    fwrite(STDERR, "FAIL: directories view bypasses APP_BASE_PATH\n");
    $failed = true;
} else {
    echo "PASS: directories links honor APP_BASE_PATH\n";
}

exit($failed ? 1 : 0);
