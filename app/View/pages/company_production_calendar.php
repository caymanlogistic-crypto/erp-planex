<?php
use App\Service\ProductionCalendarService;

$monthNames=[1=>'Январь',2=>'Февраль',3=>'Март',4=>'Апрель',5=>'Май',6=>'Июнь',7=>'Июль',8=>'Август',9=>'Сентябрь',10=>'Октябрь',11=>'Ноябрь',12=>'Декабрь'];
$weekDays=['Пн','Вт','Ср','Чт','Пт','Сб','Вс'];
$daysByDate=[];
foreach($days as $day){$daysByDate[$day['calendar_date']]=$day;}
$yearOptions=[];
foreach($years as $item){$yearOptions[(int)$item['calendar_year']]=true;}
$yearOptions[$year]=true;
$yearOptions[$year-1]=true;
$yearOptions[$year+1]=true;
ksort($yearOptions);
$statusReady=$calendar&&($calendar['status']??'')===ProductionCalendarService::STATUS_READY;
?>
<style>
.production-calendar{max-width:1500px;margin:0 auto}
.pc-toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.pc-toolbar .field-select{width:130px}
.pc-summary{display:flex;gap:18px;flex-wrap:wrap;align-items:center;padding:10px 12px;border-bottom:1px solid var(--line-soft);background:var(--surface-strong);font-size:11px}
.pc-summary strong{font-size:12px}.pc-status{font-weight:700}.pc-status.ready{color:var(--success)}.pc-status.draft{color:var(--danger)}
.pc-warning{margin-bottom:12px;padding:11px 13px;border:1px solid var(--danger);background:#fff2f0;color:var(--danger);font-weight:700}
.pc-empty{padding:26px}.pc-empty-actions{margin-top:14px}
.pc-months{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;padding:10px}
.pc-month{border:1px solid var(--line-soft);background:var(--surface-strong);min-width:0}
.pc-month-title{padding:8px 9px;font-weight:700;border-bottom:1px solid var(--line-soft);background:var(--surface-form)}
.pc-week,.pc-days{display:grid;grid-template-columns:repeat(7,minmax(0,1fr))}
.pc-week span{padding:5px 2px;text-align:center;font-size:9px;color:var(--text-faint);font-weight:700;border-bottom:1px solid var(--line-soft)}
.pc-day,.pc-blank{min-height:43px;border-right:1px solid var(--line-soft);border-bottom:1px solid var(--line-soft)}
.pc-day:nth-child(7n),.pc-blank:nth-child(7n){border-right:0}
.pc-day{padding:4px 5px;cursor:pointer;position:relative;background:var(--surface-strong)}
.pc-day:hover{outline:1px solid var(--accent);outline-offset:-1px;z-index:1}
.pc-day-number{font-size:11px;font-weight:700}.pc-day-name{font-size:8px;line-height:1.15;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--text-faint)}
.pc-day.weekend{background:#f2f0eb;color:var(--text-faint)}
.pc-day.holiday{background:#fff0ee;color:var(--danger)}
.pc-day.transferred-off{background:#fff4df;color:#8a4b05}
.pc-day.transferred-work{background:#eef7f1;color:var(--success)}
.pc-legend{display:flex;gap:14px;align-items:center;flex-wrap:wrap;padding:0 10px 10px;font-size:10px;color:var(--text-faint)}
.pc-legend-item{display:flex;align-items:center;gap:5px}.pc-swatch{width:12px;height:12px;border:1px solid var(--line-soft)}
.pc-swatch.workday{background:var(--surface-strong)}.pc-swatch.weekend{background:#f2f0eb}.pc-swatch.holiday{background:#fff0ee}.pc-swatch.transferred-off{background:#fff4df}.pc-swatch.transferred-work{background:#eef7f1}
.pc-confirm{padding:12px;border-top:1px solid var(--line-soft);background:var(--surface-form)}
.pc-confirm-grid{display:grid;grid-template-columns:1.4fr 1.4fr;gap:10px}.pc-confirm .field:last-child{grid-column:1/-1}
.pc-confirm-actions{display:flex;justify-content:flex-end;margin-top:10px}
@media(max-width:1200px){.pc-months{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:760px){.pc-months{grid-template-columns:1fr}.pc-confirm-grid{grid-template-columns:1fr}}
</style>

<div class="production-calendar">
    <div class="page-head">
        <div class="page-head-left">
            <h1 class="page-title">Производственный календарь</h1>
            <div class="page-summary"><span>Единый календарь рабочих, выходных и праздничных дней РФ для расчёта сроков в рабочих днях.</span></div>
        </div>
        <div class="page-head-right">
            <form method="get" class="pc-toolbar">
                <label class="field-label" for="pc-year">Год</label>
                <select class="field-select" id="pc-year" name="year" onchange="this.form.submit()">
                    <?php foreach(array_keys($yearOptions) as $optionYear): ?>
                        <option value="<?= (int)$optionYear ?>" <?= $optionYear===$year?'selected':'' ?>><?= (int)$optionYear ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <?php if(!empty($successFlash)): ?><div class="notice success"><?= e($successFlash) ?></div><?php endif; ?>
    <?php if(!empty($errorFlash)): ?><div class="notice warn"><?= e($errorFlash) ?></div><?php endif; ?>
    <?php if($warningYear): ?>
        <div class="pc-warning">Не загружен или не подтверждён производственный календарь на <?= (int)$warningYear ?> год. Подготовьте и подтвердите его до начала расчётов следующего года.</div>
    <?php endif; ?>

    <div class="table-card table-card--standard">
        <div class="table-toolbar">
            <div class="table-toolbar-left"><strong><?= (int)$year ?> год</strong></div>
            <div class="table-toolbar-right">
                <?php if($calendar): ?>
                    <span class="pc-status <?= $statusReady?'ready':'draft' ?>"><?= $statusReady?'ГОТОВ':'ЧЕРНОВИК' ?></span>
                <?php endif; ?>
            </div>
        </div>

        <?php if(!$calendar): ?>
            <div class="pc-empty">
                <div class="empty-state">
                    <p class="empty-title">Календарь на <?= (int)$year ?> год отсутствует.</p>
                    <p class="empty-desc">Создайте год. ERP заполнит базовую пятидневную неделю, после чего нужно проверить официальные праздники и переносы и подтвердить календарь.</p>
                    <div class="pc-empty-actions">
                        <form method="post" action="<?= app_url('/company/misc/production-calendar/year/create') ?>">
                            <?= csrfField() ?>
                            <input type="hidden" name="year" value="<?= (int)$year ?>">
                            <button class="btn btn-primary" type="submit">Создать календарь на <?= (int)$year ?> год</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="pc-summary">
                <div>Дней: <strong><?= (int)($calendar['days_count']??0) ?></strong></div>
                <div>Рабочих: <strong><?= (int)($calendar['working_days']??0) ?></strong></div>
                <div>Нерабочих: <strong><?= (int)($calendar['non_working_days']??0) ?></strong></div>
                <div>Статус: <strong class="pc-status <?= $statusReady?'ready':'draft' ?>"><?= $statusReady?'Готов к использованию':'Требует подтверждения' ?></strong></div>
                <div style="margin-left:auto;color:var(--text-faint)">Двойной щелчок по дню — редактировать</div>
            </div>

            <div class="pc-months">
                <?php for($month=1;$month<=12;$month++):
                    $first=new DateTimeImmutable(sprintf('%04d-%02d-01',$year,$month));
                    $daysInMonth=(int)$first->format('t');
                    $offset=(int)$first->format('N')-1;
                ?>
                    <section class="pc-month">
                        <div class="pc-month-title"><?= e($monthNames[$month]) ?></div>
                        <div class="pc-week"><?php foreach($weekDays as $wd): ?><span><?= e($wd) ?></span><?php endforeach; ?></div>
                        <div class="pc-days">
                            <?php for($blank=0;$blank<$offset;$blank++): ?><div class="pc-blank"></div><?php endfor; ?>
                            <?php for($dayNum=1;$dayNum<=$daysInMonth;$dayNum++):
                                $date=sprintf('%04d-%02d-%02d',$year,$month,$dayNum);
                                $item=$daysByDate[$date]??null;
                                $type=(string)($item['day_type']??'');
                                $class=match($type){
                                    ProductionCalendarService::TYPE_WEEKEND=>'weekend',
                                    ProductionCalendarService::TYPE_HOLIDAY=>'holiday',
                                    ProductionCalendarService::TYPE_TRANSFERRED_DAY_OFF=>'transferred-off',
                                    ProductionCalendarService::TYPE_TRANSFERRED_WORKDAY=>'transferred-work',
                                    default=>'workday',
                                };
                                $payload=$item?json_encode($item,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):'{}';
                            ?>
                                <div class="pc-day <?= e($class) ?>" data-pc-day="<?= e((string)$payload) ?>" title="<?= e((string)($item['name']??ProductionCalendarService::TYPE_LABELS[$type]??'')) ?>">
                                    <div class="pc-day-number"><?= $dayNum ?></div>
                                    <?php if(!empty($item['name'])): ?><div class="pc-day-name"><?= e($item['name']) ?></div><?php endif; ?>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </section>
                <?php endfor; ?>
            </div>
            <div class="pc-legend">
                <span class="pc-legend-item"><span class="pc-swatch workday"></span>Рабочий</span>
                <span class="pc-legend-item"><span class="pc-swatch weekend"></span>Выходной</span>
                <span class="pc-legend-item"><span class="pc-swatch holiday"></span>Праздник</span>
                <span class="pc-legend-item"><span class="pc-swatch transferred-off"></span>Перенесённый выходной</span>
                <span class="pc-legend-item"><span class="pc-swatch transferred-work"></span>Перенесённый рабочий</span>
            </div>

            <form class="pc-confirm" method="post" action="<?= app_url('/company/misc/production-calendar/year/confirm') ?>">
                <?= csrfField() ?>
                <input type="hidden" name="year" value="<?= (int)$year ?>">
                <div class="section-title">Источник и подтверждение календаря</div>
                <div class="pc-confirm-grid">
                    <div class="field">
                        <label class="field-label">Источник</label>
                        <input class="field-input" name="source_title" value="<?= e((string)($calendar['source_title']??'')) ?>" placeholder="Например: Постановление Правительства РФ">
                    </div>
                    <div class="field">
                        <label class="field-label">Ссылка на источник</label>
                        <input class="field-input" name="source_url" value="<?= e((string)($calendar['source_url']??'')) ?>" placeholder="https://...">
                    </div>
                    <div class="field">
                        <label class="field-label">Комментарий</label>
                        <input class="field-input" name="note" value="<?= e((string)($calendar['note']??'')) ?>" placeholder="Примечание к календарю">
                    </div>
                </div>
                <div class="pc-confirm-actions"><button class="btn btn-primary" type="submit">Подтвердить календарь</button></div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if($calendar): ?>
<div id="pc-day-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="pc-day-title">
    <div class="modal modal-md">
        <div class="modal-head">
            <span class="modal-title" id="pc-day-title">Редактирование дня</span>
            <button type="button" class="modal-close" data-pc-close>&times;</button>
        </div>
        <form method="post" action="<?= app_url('/company/misc/production-calendar/day/save') ?>" id="pc-day-form">
            <?= csrfField() ?>
            <div class="modal-body">
                <div class="section-title" id="pc-day-section">День производственного календаря</div>
                <div class="form-grid two-cols">
                    <div class="field">
                        <label class="field-label">Дата</label>
                        <input class="field-input" id="pc-day-date-display" readonly>
                        <input type="hidden" name="calendar_date" id="pc-day-date">
                    </div>
                    <div class="field">
                        <label class="field-label">Тип дня <span class="field-required">*</span></label>
                        <select class="field-select" name="day_type" id="pc-day-type" required>
                            <?php foreach(ProductionCalendarService::TYPE_LABELS as $value=>$label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="field">
                    <label class="field-label">Название</label>
                    <input class="field-input" name="name" id="pc-day-name" placeholder="Например: День России">
                </div>
                <div class="field">
                    <label class="field-label">Комментарий</label>
                    <textarea class="field-input" name="note" id="pc-day-note" rows="3" placeholder="Причина переноса, нормативный акт и т. п."></textarea>
                </div>
                <div class="field-msg">После изменения любого дня календарь снова получает статус «Черновик». Для использования его нужно повторно подтвердить.</div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-pc-close>Отмена</button>
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </div>
</div>
<script>
(function(){
    const modal=document.getElementById('pc-day-modal');
    const dateInput=document.getElementById('pc-day-date');
    const dateDisplay=document.getElementById('pc-day-date-display');
    const typeInput=document.getElementById('pc-day-type');
    const nameInput=document.getElementById('pc-day-name');
    const noteInput=document.getElementById('pc-day-note');
    if(!modal||!dateInput||!dateDisplay||!typeInput||!nameInput||!noteInput)return;
    const close=()=>modal.classList.remove('is-open');
    document.querySelectorAll('[data-pc-day]').forEach((cell)=>cell.addEventListener('dblclick',()=>{
        let data={};try{data=JSON.parse(cell.dataset.pcDay||'{}');}catch(e){}
        if(!data.calendar_date)return;
        dateInput.value=data.calendar_date||'';
        const parts=(data.calendar_date||'').split('-');
        dateDisplay.value=parts.length===3?parts[2]+'.'+parts[1]+'.'+parts[0]:data.calendar_date;
        typeInput.value=data.day_type||'WORKDAY';
        nameInput.value=data.name||'';
        noteInput.value=data.note||'';
        modal.classList.add('is-open');
        setTimeout(()=>typeInput.focus(),0);
    }));
    modal.querySelectorAll('[data-pc-close]').forEach((button)=>button.addEventListener('click',close));
    modal.addEventListener('click',(event)=>{if(event.target===modal)close();});
    document.addEventListener('keydown',(event)=>{if(event.key==='Escape'&&modal.classList.contains('is-open'))close();});
})();
</script>
<?php endif; ?>
