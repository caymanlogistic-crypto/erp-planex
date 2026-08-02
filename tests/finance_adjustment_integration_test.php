<?php
require_once __DIR__ . '/../app/Service/FinanceOperationService.php';
require_once __DIR__ . '/../app/Service/FinanceAuditLogService.php';
require_once __DIR__ . '/../app/Service/FinanceAdjustmentService.php';

use App\Service\FinanceAdjustmentService;

final class FakeStmt extends PDOStatement
{
    public array $executions=[];
    public function __construct(private FakePdo $pdo, private string $sql) {}
    public function execute(?array $params = null): bool
    {
        $this->executions[]=$params ?? [];
        $this->pdo->executed[]=['sql'=>$this->sql,'params'=>$params ?? []];
        if ($this->pdo->throwOnAudit && str_contains($this->sql,'finance_audit_log')) {
            throw new RuntimeException('audit failure');
        }
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        if (str_contains($this->sql,'finance_money_accounts')) {
            return $this->pdo->accountExists ? ['id'=>1,'type'=>'CASH'] : false;
        }
        return false;
    }
}
final class FakePdo extends PDO
{
    public bool $tx=false,$accountExists=true,$throwOnAudit=false,$committed=false,$rolledBack=false; public int $beginCount=0;
    public array $prepared=[],$executed=[];
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { $this->prepared[]=$query; return new FakeStmt($this,$query); }
    public function beginTransaction(): bool {$this->beginCount++;$this->tx=true;return true;}
    public function inTransaction(): bool {return $this->tx;}
    public function commit(): bool {$this->committed=true;$this->tx=false;return true;}
    public function rollBack(): bool {$this->rolledBack=true;$this->tx=false;return true;}
    public function lastInsertId(?string $name = null): string|false {return '77';}
}
$pass=0;$fail=0;
function ok(string $n,bool $v):void{global $pass,$fail;if($v){$pass++;}else{$fail++;fwrite(STDERR,"FAIL $n\n");}}
function expectThrow(string $n, callable $fn):void{global $pass,$fail;try{$fn();$fail++;fwrite(STDERR,"FAIL $n no exception\n");}catch(Throwable){$pass++;}}
$p=new FakePdo();$id=FinanceAdjustmentService::createAdjustment($p,1,'100,50','Инвентаризация',5,'company_owner');
ok('id',$id===77);ok('commit',$p->committed);ok('no rollback',!$p->rolledBack);ok('account locked',array_filter($p->prepared,fn($s)=>str_contains($s,'FOR UPDATE'))!==[]);
$op=array_values(array_filter($p->executed,fn($e)=>str_contains($e['sql'],'INSERT INTO finance_operations')))[0]??null;
ok('normalized amount',($op['params'][':amount']??null)==='100.50');ok('actor role',($op['params'][':role']??null)==='company_owner');
$audit=array_values(array_filter($p->executed,fn($e)=>str_contains($e['sql'],'finance_audit_log')))[0]??null;
ok('audit actor',($audit['params'][5]??null)===5 && ($audit['params'][6]??null)==='company_owner');
expectThrow('empty reason',fn()=>FinanceAdjustmentService::createAdjustment(new FakePdo(),1,'1.00','   ',1,'company_owner'));
$missing=new FakePdo();$missing->accountExists=false;expectThrow('missing account',fn()=>FinanceAdjustmentService::createAdjustment($missing,1,'1.00','x',1,'company_owner'));ok('missing rollback',$missing->rolledBack);
$af=new FakePdo();$af->throwOnAudit=true;expectThrow('audit failure',fn()=>FinanceAdjustmentService::createAdjustment($af,1,'1.00','x',1,'company_owner'));ok('audit rollback',$af->rolledBack && !$af->committed);

$nested=new FakePdo();$nested->tx=true;$nestedId=FinanceAdjustmentService::createAdjustment($nested,1,'2.00','Внешняя транзакция',6,'company_owner');ok('nested id',$nestedId===77);ok('nested transaction not committed',$nested->tx && !$nested->committed && $nested->beginCount===0);
$nestedFail=new FakePdo();$nestedFail->tx=true;$nestedFail->throwOnAudit=true;expectThrow('nested audit failure',fn()=>FinanceAdjustmentService::createAdjustment($nestedFail,1,'2.00','x',6,'company_owner'));ok('nested failure leaves owner transaction',$nestedFail->tx && !$nestedFail->rolledBack);
printf("ADJUSTMENT: %d passed, %d failed\n",$pass,$fail);exit($fail?1:0);
