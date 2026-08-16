<?php

declare(strict_types=1);

function mustContain(string $path,string $needle,string $message):void{
    $text=file_get_contents(__DIR__.'/../'.$path);
    if($text===false||!str_contains($text,$needle))throw new RuntimeException('FAIL: '.$message);
}

mustContain('app/Support/entrypoint_dependencies.php','FinanceBankInvoiceSettlementService.php','runtime service dependency');
mustContain('app/Http/Routes/company_bank_finance.php','/bank-transactions/{id}/settlement','settlement routes');
mustContain('app/Http/Controllers/Company/BankFinanceController.php','settlementForm','settlement form controller action');
mustContain('app/Http/Controllers/Company/BankFinanceController.php','settlementSubmit','settlement submit controller action');
mustContain('app/Http/Controllers/Company/BankFinanceActions/applyRules.php','autoAllocateIncomingCustomerReceipts','apply rules also settles unambiguous client receipts');
mustContain('app/Http/Controllers/Company/BankFinanceActions/indexAutoFilters.php','bank-invoice-settlement-modal','dedicated settlement modal');
mustContain('app/Http/Controllers/Company/BankFinanceActions/indexAutoFilters.php','settlementGenericPass','generic double-click fallback preserved');
mustContain('app/Http/Controllers/Company/BankFinanceActions/indexAutoFilters.php','/settlement','specialized settlement endpoint used');
mustContain('app/View/partials/company_bank_invoice_settlement_form.php','Открытые счета','specialized invoice list');
mustContain('app/View/partials/company_bank_invoice_settlement_form.php','invoice_id[]','multi-invoice manual allocation');
mustContain('app/Service/FinanceBankInvoiceSettlementService.php','FinanceSettlementCascadeService::cascadeAfterAllocationCreate','manual allocations use canonical cascade');
mustContain('app/Service/FinanceBankInvoiceSettlementService.php',"'manual'",'manual settlement method persisted');
mustContain('app/Service/FinanceBankInvoiceSettlementService.php','obligation_id','manual settlement closes universal obligations');
mustContain('app/Http/Routes/company_bank_finance.php','/bank-transactions/{id}/classify','generic classification route remains');

echo "FINANCE_BANK_INVOICE_SETTLEMENT_ARCHITECTURE_OK\n";
