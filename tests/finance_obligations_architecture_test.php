<?php

declare(strict_types=1);

function guard(bool $ok, string $message): void { if (!$ok) throw new RuntimeException('FAIL: '.$message); }
$root=dirname(__DIR__);
$service=file_get_contents($root.'/app/Service/FinanceObligationService.php');
$migration=file_get_contents($root.'/database/migrations-local/071_finance_obligations.sql');
$manifest=file_get_contents($root.'/app/Support/entrypoint_dependencies.php');
$invoiceForm=file_get_contents($root.'/app/View/partials/company_invoice_create_form.php');
$bankImport=file_get_contents($root.'/app/Http/Controllers/Company/BankFinanceActions/import.php');
$tripController=file_get_contents($root.'/app/Http/Controllers/Company/LinearTripController.php');
$routes=file_get_contents($root.'/app/Http/Routes/company_finance_invoices.php');

guard(str_contains($manifest, "app/Service/FinanceObligationService.php"), 'runtime manifest registers obligation service');
guard(str_contains($migration, 'uk_finance_obligation_source_key'), 'durable source key unique index exists');
guard(substr_count($migration, 'obligation_id') >= 4, 'invoice and allocation obligation links exist');
guard(str_contains($service, 'ProductionCalendarService::addWorkingDays'), 'working days use production calendar');
guard(!str_contains($service, 'DateCalculationService::addWorkingDays'), 'no weekday-only fallback for obligations');
guard(str_contains($service, 'linearSourceKey'), 'durable route source identity exists');
guard(str_contains($service, 'replaceInvoiceLinks'), 'invoice N:M obligation allocation exists');
guard(str_contains($service, 'autoAllocateIncomingCustomerReceipts'), 'incoming customer auto-allocation exists');
guard(str_contains($service, "count($numberMatches) === 1"), 'invoice-number auto-match is uniqueness guarded');
guard(str_contains($service, "count($exact) === 1"), 'amount auto-match is uniqueness guarded');
guard(str_contains($invoiceForm, 'obligation_id[]') && str_contains($invoiceForm, 'obligation_amount[]'), 'invoice form supports multiple obligations');
guard(str_contains($bankImport, 'autoAllocateIncomingCustomerReceipts'), 'bank import invokes obligation auto-allocation');
guard(str_contains($tripController, 'syncFinanceObligations'), 'trip reads synchronize due/status');
guard(str_contains($routes, '/company/finance/receivables') && str_contains($routes, '/company/finance/invoices/obligations'), 'receivables and obligation endpoints routed');
echo "FINANCE_OBLIGATIONS_ARCHITECTURE_OK\n";
