<?php
require_once __DIR__ . '/../app/Service/DateCalculationService.php';
require_once __DIR__ . '/../app/Service/RoutePaymentStatusService.php';
require_once __DIR__ . '/../app/Service/FinanceAuditLogService.php';
require_once __DIR__ . '/../app/Service/FinanceSettlementCascadeService.php';
use App\Service\FinanceSettlementCascadeService as C;

final class SStmt extends PDOStatement {
  private mixed $row=false; private mixed $column=false; private array $rows=[];
  public function __construct(private SPdo $pdo, private string $sql) {}
  public function execute(?array $params=null): bool {
    $this->pdo->executed[]=['sql'=>$this->sql,'params'=>$params??[]];
    if(str_contains($this->sql,'FROM finance_invoices')&&str_contains($this->sql,'FOR UPDATE')){$this->row=$this->pdo->invoice;}
    elseif(str_contains($this->sql,'SUM(foa.amount)')&&str_contains($this->sql,'foa.invoice_id')){$this->column=$this->pdo->invoicePaid;}
    elseif(str_contains($this->sql,'MIN(foa.allocation_date)')){$this->column='2026-07-01';}
    elseif(str_contains($this->sql,'FROM linear_route_payments lrp')){$this->row=$this->pdo->payment;}
    elseif(str_contains($this->sql,'SUM(foa.amount)')&&str_contains($this->sql,'linear_route_payment_id')){$this->column=$this->pdo->paymentPaid;}
    elseif(str_contains($this->sql,'FROM finance_operation_allocations')&&str_contains($this->sql,'FOR UPDATE')){$this->row=$this->pdo->allocation;}
    elseif(str_contains($this->sql,'SELECT id FROM linear_route_payments')){$this->rows=$this->pdo->routePayments;}
    return true;
  }
  public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0):mixed{return $this->row;}
  public function fetchColumn(int $column=0):mixed{return $this->column;}
  public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return $this->rows;}
}
final class SPdo extends PDO {
 public bool $tx=false,$committed=false,$rolled=false; public array $executed=[];
 public array|false $invoice=false,$payment=false,$allocation=false,$routePayments=[]; public string $invoicePaid='0.00',$paymentPaid='0.00';
 public function __construct(){}
 public function prepare(string $query,array $options=[]):PDOStatement|false{return new SStmt($this,$query);}
 public function beginTransaction():bool{$this->tx=true;return true;} public function inTransaction():bool{return $this->tx;}
 public function commit():bool{$this->committed=true;$this->tx=false;return true;} public function rollBack():bool{$this->rolled=true;$this->tx=false;return true;}
}
$pass=0;$fail=0;function ok(string $n,bool $v):void{global$pass,$fail;if($v)$pass++;else{$fail++;fwrite(STDERR,"FAIL $n\n");}}
$p=new SPdo();$p->invoice=['id'=>1,'amount'=>'100.00','paid_amount'=>'0.00','status'=>'issued','planned_payment_date'=>'2099-01-01','first_paid_at'=>null,'fully_paid_at'=>null,'cancelled_at'=>null];$p->invoicePaid='50.00';
C::recalculateInvoice($p,1,9,'company_owner');
ok('invoice committed',$p->committed);ok('invoice lock',count(array_filter($p->executed,fn($e)=>str_contains($e['sql'],'finance_invoices')&&str_contains($e['sql'],'FOR UPDATE')))===1);
$upd=array_values(array_filter($p->executed,fn($e)=>str_contains($e['sql'],'UPDATE finance_invoices')))[0]??null;ok('invoice partial',($upd['params'][':status']??null)==='partially_paid');ok('invoice amount',($upd['params'][':paid_amount']??null)==='50.00');
$audit=array_values(array_filter($p->executed,fn($e)=>str_contains($e['sql'],'finance_audit_log')))[0]??null;ok('invoice audit actor',($audit['params'][5]??null)===9);
$r=new SPdo();$r->payment=['id'=>2,'amount'=>'100.00','paid_amount'=>'0.00','payment_status'=>'planned','calculated_due_date'=>null,'cancelled_at'=>null,'paid_at'=>null,'condition_type'=>'after_documents','closing_documents_received_date'=>null];$r->paymentPaid='0.00';
C::recalculateRoutePayment($r,2,10,'company_owner');$rupd=array_values(array_filter($r->executed,fn($e)=>str_contains($e['sql'],'UPDATE linear_route_payments')))[0]??null;ok('route waiting docs',($rupd['params'][':payment_status']??null)==='waiting_event');ok('route join docs',count(array_filter($r->executed,fn($e)=>str_contains($e['sql'],'closing_documents_received_date')))===1);ok('route audit',count(array_filter($r->executed,fn($e)=>str_contains($e['sql'],'finance_audit_log')))===1);
$r2=new SPdo();$r2->payment=['id'=>3,'amount'=>'100.00','paid_amount'=>'0.00','payment_status'=>'waiting_event','calculated_due_date'=>'2099-01-01','cancelled_at'=>null,'paid_at'=>null,'condition_type'=>'after_documents','closing_documents_received_date'=>'2026-01-01'];$r2->paymentPaid='100.00';C::recalculateRoutePayment($r2,3,11,'company_owner');$u2=array_values(array_filter($r2->executed,fn($e)=>str_contains($e['sql'],'UPDATE linear_route_payments')))[0]??null;ok('route paid',($u2['params'][':payment_status']??null)==='paid');ok('paid date set',($u2['params'][':paid_at']??null)===date('Y-m-d'));

$reset=new SPdo();$reset->invoice=['id'=>4,'direction'=>'OUTGOING','amount'=>'100.00','paid_amount'=>'100.00','status'=>'paid','planned_payment_date'=>'2099-01-01','first_paid_at'=>'2026-07-01','fully_paid_at'=>'2026-07-02','cancelled_at'=>null];$reset->invoicePaid='0.00';
C::recalculateInvoice($reset,4,12,'company_owner');$resetUpd=array_values(array_filter($reset->executed,fn($e)=>str_contains($e['sql'],'UPDATE finance_invoices')))[0]??null;ok('cancelled allocation resets outgoing invoice',($resetUpd['params'][':status']??null)==='issued');ok('fully paid date cleared',isset($resetUpd['params']) && array_key_exists(':fully_paid_at',$resetUpd['params']) && $resetUpd['params'][':fully_paid_at']===null);
$resetIn=new SPdo();$resetIn->invoice=['id'=>5,'direction'=>'INCOMING','amount'=>'100.00','paid_amount'=>'50.00','status'=>'partially_paid','planned_payment_date'=>'2099-01-01','first_paid_at'=>'2026-07-01','fully_paid_at'=>null,'cancelled_at'=>null];$resetIn->invoicePaid='0.00';
C::recalculateInvoice($resetIn,5,13,'company_owner');$resetInUpd=array_values(array_filter($resetIn->executed,fn($e)=>str_contains($e['sql'],'UPDATE finance_invoices')))[0]??null;ok('cancelled allocation resets incoming invoice',($resetInUpd['params'][':status']??null)==='received');
printf("SETTLEMENT_DB_PATH: %d passed, %d failed\n",$pass,$fail);exit($fail?1:0);
