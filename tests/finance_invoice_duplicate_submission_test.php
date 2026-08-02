<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../app/Http/Controllers/Company/InvoiceActions/create_submit.php');
$checks = [
    'duplicate lookup is parameterized' => str_contains($source, 'WHERE direction = :direction')
        && str_contains($source, "':number' => \$number")
        && str_contains($source, "':amount' => \$normalizedAmount"),
    'duplicate key includes tenant-local business identity' => str_contains($source, 'counterparty_entity_type')
        && str_contains($source, 'counterparty_entity_id')
        && str_contains($source, 'counterparty_name')
        && str_contains($source, 'invoice_date = :invoice_date'),
    'cancelled invoices do not block replacement' => str_contains($source, 'cancelled_at IS NULL'),
    'duplicate is rejected before insert' => strpos($source, 'Идентичный счёт уже существует.') < strpos($source, 'FinanceInvoiceService::createInvoice('),
    'validation render variables are initialized' => str_contains($source, '$dbError = null;')
        && str_contains($source, '$invPage = 1;')
        && str_contains($source, '$invPerPage = 100;'),
];

$failed = array_keys(array_filter($checks, static fn(bool $pass): bool => !$pass));
foreach ($checks as $name => $pass) echo ($pass ? 'PASS ' : 'FAIL '), $name, PHP_EOL;
exit($failed === [] ? 0 : 1);
