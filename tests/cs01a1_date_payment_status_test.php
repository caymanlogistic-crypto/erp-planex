<?php
require_once __DIR__ . '/../app/Service/DateCalculationService.php';
require_once __DIR__ . '/../app/Service/RoutePaymentStatusService.php';

use App\Service\DateCalculationService as D;
use App\Service\RoutePaymentStatusService as S;

$passed = 0; $failed = 0;
function check(string $name, mixed $actual, mixed $expected): void {
    global $passed, $failed;
    if ($actual === $expected) { $passed++; return; }
    $failed++; fwrite(STDERR, "FAIL {$name}: expected=" . var_export($expected, true) . " actual=" . var_export($actual, true) . "\n");
}
function throwsInvalid(string $name, callable $fn): void {
    global $passed, $failed;
    try { $fn(); } catch (InvalidArgumentException) { $passed++; return; } catch (Throwable $e) {
        $failed++; fwrite(STDERR, "FAIL {$name}: wrong exception " . get_class($e) . "\n"); return;
    }
    $failed++; fwrite(STDERR, "FAIL {$name}: no exception\n");
}

foreach (['2026-01-01'=>true,'2028-02-29'=>true,'2026-02-30'=>false,'2026-13-01'=>false,'2026-1-01'=>false,''=>false,' 2026-01-01'=>false] as $v=>$e) check("date {$v}", D::isValidDate($v), $e);
check('calendar zero', D::addCalendarDays('2026-01-31',0),'2026-01-31');
check('calendar boundary', D::addCalendarDays('2026-01-31',1),'2026-02-01');
check('working fri+1', D::addWorkingDays('2026-01-02',1),'2026-01-05');
check('working fri+2', D::addWorkingDays('2026-01-02',2),'2026-01-06');
check('next work sat', D::nextWorkingDay('2026-01-03'),'2026-01-05');
check('bad kind', D::addDays('2026-01-01',1,'holiday'),null);
check('negative days', D::addDays('2026-01-01',-1,'calendar'),null);
check('after start', D::calculateForecastDueDate('after_start','2026-01-02',null,1,'working'),'2026-01-05');
check('after zero invalid', D::calculateForecastDueDate('after_start','2026-01-02',null,0),null);
check('start exact', D::calculateForecastDueDate('start_day','2026-01-02',null,null),'2026-01-02');
check('specific strict', D::calculateForecastDueDate('specific_date',null,null,null,'calendar','2026-02-30'),null);
check('docs forecast waits', D::calculateForecastDueDate('after_documents',null,null,3),null);
check('docs final', D::calculateFinalDueDate('after_documents',null,null,'2026-01-02',1,'working'),'2026-01-05');
check('unknown condition', D::calculateForecastDueDate('wat',null,null,null),null);
check('validate unknown', D::validateCondition('wat',null,null) !== null, true);
check('validate after null', D::validateCondition('after_start',null,null) !== null, true);
check('validate after one', D::validateCondition('after_start',1,null),null);
check('validate start offset', D::validateCondition('start_day',1,null) !== null,true);
check('validate specific', D::validateCondition('specific_date',0,'2026-01-01'),null);
check('validate bad kind', D::validateCondition('after_start',1,null,'holiday') !== null,true);

$future = '2099-01-01'; $past='2000-01-01';
check('planned', S::computeStatus('100','0',$future), S::STATUS_PLANNED);
check('overdue', S::computeStatus('100.00',null,$past), S::STATUS_OVERDUE);
check('partial', S::computeStatus('100.00','1.00',$future), S::STATUS_PARTIALLY_PAID);
check('partial overdue', S::computeStatus('100.00','1.00',$past), S::STATUS_OVERDUE);
check('paid', S::computeStatus('100.00','100.00',$future), S::STATUS_PAID);
check('overpaid', S::computeStatus('100.00','101.00',$future), S::STATUS_PAID);
check('cancelled', S::computeStatus('100','0',$future,'2026-01-01'), S::STATUS_CANCELLED);
check('waiting docs', S::computeStatus('100','0',null,null,null,'after_documents'), S::STATUS_WAITING_EVENT);
check('docs received', S::computeStatus('100','0',$future,null,'2026-01-01','after_documents'), S::STATUS_PLANNED);
foreach ([null,'','0','0.00','-1','1,00','1e2',' 1','1.001','abc'] as $v) throwsInvalid('bad total '.var_export($v,true), fn()=>S::computeStatus($v,'0',$future));
foreach (['','-1','1,00','1e2','1.001','abc'] as $v) throwsInvalid('bad paid '.$v, fn()=>S::computeStatus('100',$v,$future));
throwsInvalid('bad due', fn()=>S::computeStatus('100','0','2026-02-30'));
throwsInvalid('bad docs', fn()=>S::computeStatus('100','0',$future,null,'2026-02-30','after_documents'));
throwsInvalid('bad condition', fn()=>S::computeStatus('100','0',$future,null,null,'unknown'));

printf("CS01A1: %d passed, %d failed\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);
