<?php

declare(strict_types=1);

$root = $argv[1] ?? '';
if ($root === '' || !is_dir($root)) { fwrite(STDERR,"invalid app root\n"); exit(2); }
require_once $root.'/app/Support/helpers.php';
require_once $root.'/app/Support/environment.php';
loadEnvFileNonOverwriting($root.'/.env');
$config=require $root.'/bootstrap/app.php';
require_once base_path('app/Support/entrypoint_dependencies.php');

$servicePath=$root.'/app/Service/FinanceCarrierBankAutoSettlementService.php';
$applyRulesPath=$root.'/app/Http/Controllers/Company/BankFinanceActions/applyRules.php';
if(!is_file($servicePath)) throw new RuntimeException('deployed carrier auto-settlement service missing');
if(!is_file($applyRulesPath)||!str_contains((string)file_get_contents($applyRulesPath),'autoAllocateOutgoingCarrierPayments')) throw new RuntimeException('deployed applyRules carrier call missing');

$central=(new \App\Core\Database($config['database']))->connection();
$companies=$central->query("SELECT * FROM companies WHERE status='active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$out=[];
foreach($companies as $company){
 try{
  $pdo=(new \App\Core\Database(companyDatabaseConfig($config,$company)))->connection();
  $txs=$pdo->query("SELECT * FROM bank_transactions WHERE purpose LIKE '%26/3947%' OR (operation_date BETWEEN '2026-08-10' AND '2026-08-12' AND counterparty_name LIKE '%МОТОР%') ORDER BY operation_date,id")->fetchAll(PDO::FETCH_ASSOC);
  $invoices=$pdo->query("SELECT i.*,ct.name contractor_name,ct.inn contractor_inn,ct.status contractor_status,ct.deleted_at contractor_deleted_at,(i.amount-COALESCE((SELECT SUM(a.amount) FROM finance_operation_allocations a WHERE a.invoice_id=i.id AND a.cancelled_at IS NULL),0)) remaining_amount FROM finance_invoices i LEFT JOIN contractors ct ON i.counterparty_entity_type='contractor' AND ct.id=i.counterparty_entity_id WHERE i.number LIKE '%26/3947%' OR (i.direction='INCOMING' AND i.counterparty_name LIKE '%МОТОР%') ORDER BY i.id")->fetchAll(PDO::FETCH_ASSOC);
  $txDetails=[];
  foreach($txs as $tx){
   $s=$pdo->prepare("SELECT fo.*,(fo.amount-COALESCE((SELECT SUM(a.amount) FROM finance_operation_allocations a WHERE a.operation_id=fo.id AND a.cancelled_at IS NULL),0)) remaining_amount FROM finance_operations fo WHERE fo.bank_transaction_id=? ORDER BY fo.id");
   $s->execute([(int)$tx['id']]);
   $ops=$s->fetchAll(PDO::FETCH_ASSOC);
   $allocs=[];
   foreach($ops as $op){
    $a=$pdo->prepare("SELECT a.*,i.number invoice_number,o.direction obligation_direction,o.due_date obligation_due_date FROM finance_operation_allocations a LEFT JOIN finance_invoices i ON i.id=a.invoice_id LEFT JOIN finance_obligations o ON o.id=a.obligation_id WHERE a.operation_id=? ORDER BY a.id");
    $a->execute([(int)$op['id']]);
    $allocs[(int)$op['id']]=$a->fetchAll(PDO::FETCH_ASSOC);
   }
   $txDetails[]=['transaction'=>$tx,'operations'=>$ops,'allocations'=>$allocs];
  }
  $invoiceDetails=[];
  foreach($invoices as $invoice){
   $l=$pdo->prepare("SELECT l.*,o.direction,o.due_date,o.amount obligation_amount,o.paid_amount obligation_paid,o.status obligation_status,o.counterparty_name obligation_counterparty,o.counterparty_inn obligation_inn FROM finance_invoice_links l LEFT JOIN finance_obligations o ON o.id=l.obligation_id WHERE l.invoice_id=? ORDER BY CASE WHEN o.due_date IS NULL THEN 1 ELSE 0 END,o.due_date,l.id");
   $l->execute([(int)$invoice['id']]);
   $invoiceDetails[]=['invoice'=>$invoice,'links'=>$l->fetchAll(PDO::FETCH_ASSOC)];
  }
  if($txDetails!==[]||$invoiceDetails!==[]) $out[]=['company_id'=>(int)$company['id'],'company_name'=>(string)$company['name'],'transactions'=>$txDetails,'invoices'=>$invoiceDetails];
 }catch(Throwable $e){$out[]=['company_id'=>(int)$company['id'],'company_name'=>(string)$company['name'],'error'=>$e->getMessage()];}
}
file_put_contents('/tmp/P102_CARRIER_PARTIAL_DIAG.json',json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
echo "P102_DIAG_CAPTURED\n";
echo 'COMPANY_MATCHES='.count($out)."\n";
