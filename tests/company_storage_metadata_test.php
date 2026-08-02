<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('STORAGE_PATH', BASE_PATH . '/storage');
require BASE_PATH . '/app/Support/helpers.php';

$expected = storage_path('companies/25');
$checks = [
    company_storage_path('storage/companies/25/', 25) === $expected,
    company_storage_path(null, 25) === $expected,
    company_storage_path('custom/company-25', 25) === base_path('custom/company-25'),
];

$controller = file_get_contents(BASE_PATH . '/app/Http/Controllers/Superadmin/CompanyActions/view.php');
$checks[] = is_string($controller) && str_contains($controller, 'company_storage_path(');

foreach ($checks as $index => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ': storage metadata check ' . ($index + 1) . PHP_EOL;
}

exit(in_array(false, $checks, true) ? 1 : 0);
