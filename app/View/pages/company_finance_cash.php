<?php

use App\Service\FinanceCashLedgerService;
use App\Service\FinanceCashResolutionService;
use App\Service\FinanceCashService;

$fmtDate = function ($d) { return ($d && $d !== '—') ? date('d.m.Y', is_numeric(strtotime($d)) ? strtotime($d) : time()) : '—'; };
$unresolvedCount = max(0, (int)($unresolvedCash['count'] ?? 0));
$unresolvedAmount = (string)($unresolvedCash['amount'] ?? '0.00');
?>
<?php if ($company === null): ?>
<div class="notice warn">Компания не найдена. Укажите корректный company_id.</div>
<?php elseif (($company['status'] ?? '') !== 'active'): ?>
<div class="page-head"><div class="page-head-left"><h1 class="page-title">Касса</h1><div class="page-summary"><span>Компания находится в неактивном статусе.</span></div></div></div>
<div class="notice warn">Работа с кассой недоступна.</div>
<?php elseif ($dbError !== null): ?>
<div class="page-head"><div class="page-head-left"><h1 class="page-title">Касса</h1><div class="page-summary"><span>Наличные кассы, кассовые операции и внутренние переводы.</span></div></div></div>
<div class="notice warn"><?= e($dbError) ?></div>
<?php else: ?>
<style>
.cash-clearing-alert{display:flex;align-items:center;gap:8px;margin:8px 0 0;padding:7px 9px;border:1px solid #cdbca5;background:#f3ede3;font-size:11px;color:#514536}.cash-clearing-alert strong{color:#7c3f12}.cash-batch-bar{display:flex;align-items:center;gap:8px;min-height:34px;padding:6px 8px;border:1px solid #c9c2b8;border-bottom:0;background:#ece8e0}.cash-batch-summary{font-size:11px;color:#514c45;margin-right:auto}.cash-batch-summary strong{color:#25221e}.cash-select-cell{width:28px;text-align:center}.cash-select-cell input[type="checkbox"]{width:14px;height:14px;margin:0;vertical-align:middle;accent-color:var(--accent)}.cash-row-unresolved>td{background:var(--color-danger-bg)!important}.cash-row-resolved .cash-resolved-note{font-size:10px;font-weight:700;color:var(--accent)}.cash-dispatch-total{padding:7px 9px;background:#f3f0ea;border:1px solid #d3cdc3;font-size:11px}.cash-dispatch-total strong{font-size:13px}.cash-action-disabled{opacity:.5;cursor:not-allowed}.cash-technical-status{display:inline-flex;align-items:center;gap:4px;padding:2px 6px;border-radius:2px;font-size:9px;font-weight:700}.cash-technical-status.is-alert{background:#f3dfd1;color:#843b12;border:1px solid #dab69d}.cash-technical-status.is-ok{background:#dcebe7;color:#1f6358;border:1px solid #b8d3cc}.cash-pagination{display:flex;align-items:center;justify-content:flex-end;gap:8px;margin-top:8px;font-size:11px}.cash-pagination__meta{color:#6b655d}.cash-pagination .btn[aria-disabled="true"]{opacity:.45;pointer-events:none}.cash-col-recipient{width:110px;min-width:0}.cash-col-purpose{width:auto;min-width:0}.cash-handoff-date{color:var(--text-faint);white-space:nowrap}.cash-lifecycle-arrow{color:var(--accent)}
.cash-ledger-card .table-scroll{overflow-x:hidden}.cash-ledger-card #cash-ledger-table{width:100%;min-width:0;table-layout:fixed}.cash-ledger-card #cash-ledger-table th:nth-child(1){width:28px}.cash-ledger-card #cash-ledger-table th:nth-child(2){width:82px}.cash-ledger-card #cash-ledger-table th:nth-child(3){width:130px}.cash-ledger-card #cash-ledger-table th:nth-child(4){width:145px}.cash-ledger-card #cash-ledger-table th:nth-child(5){width:190px}.cash-ledger-card #cash-ledger-table th:nth-child(7){width:95px}.cash-ledger-card #cash-ledger-table th:nth-child(8){width:110px}.cash-ledger-card #cash-ledger-table th:nth-child(7),.cash-ledger-card #cash-ledger-table td:nth-child(7){text-align:right}
.cash-lifecycle-toggle{display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border:0;background:transparent;color:var(--accent);font-size:15px;line-height:1;cursor:pointer;padding:0}.cash-lifecycle-toggle:hover{background:rgba(124,63,18,.08)}.cash-lifecycle-toggle .cash-lifecycle-chevron{display:inline-block;transition:transform .14s ease;transform-origin:center}.cash-lifecycle-toggle[aria-expanded="true"] .cash-lifecycle-chevron{transform:rotate(90deg)}.cash-row-expandable>td{cursor:default}.cash-lifecycle-detail-row>td{padding:0!important;background:#f7f4ee!important;border-top:0!important}.cash-lifecycle-detail{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:8px 14px;padding:8px 12px 10px 40px;border-top:1px solid #ddd5ca;border-bottom:1px solid #d7cfc3}.cash-lifecycle-leg{display:grid;grid-template-columns:80px minmax(0,1fr) auto;align-items:center;gap:8px;min-height:28px;font-size:10px}.cash-lifecycle-leg-label{font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.02em}.cash-lifecycle-leg-flow{min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#2d2924}.cash-lifecycle-leg-amount{font-family:var(--font-mono,monospace);font-weight:700;white-space:nowrap}.cash-lifecycle-leg-amount.is-in{color:#256257}.cash-lifecycle-leg-amount.is-out{color:#8b3e20}.cash-lifecycle-meta{grid-column:1/-1;display:flex;flex-wrap:wrap;gap:5px 14px;padding-top:2px;font-size:10px;color:var(--text-muted)}.cash-lifecycle-meta strong{color:#514c45}.cash-lifecycle-tech{color:var(--text-faint)}
</style>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Касса</h1>
        <div class="page-summary"><span>Основная касса хранит реальные наличные. Связанные оплаты сотрудников показываются одной строкой и раскрываются до двух реальных проводок.</span></div>
    </div>
    <div class="page-head-right">
        <button type="button" class="btn btn-primary btn--toolbar" id="cash-account-create-btn">Создать кассу</button>
        <button type="button" class="btn btn-primary btn--toolbar" id="cash-operation-create-btn">Новая операция</button>
        <button type="button" class="btn btn-primary btn--toolbar" id="cash-transfer-create-btn">Перевод</button>
    </div>
</div>

<?php if (!empty($successFlash)): ?><div class="notice success"><?= e($successFlash) ?></div><?php endif; ?>
<?php if (!empty($errorFlash)): ?><div class="notice warn"><?= e($errorFlash) ?></div><?php endif; ?>

<?php if ($unresolvedCount > 0): ?>
<div class="cash-clearing-alert" id="cash-clearing-alert"><strong>Требует разнесения: <?= $unresolvedCount ?></strong><span>·</span><span><?= FinanceCashService::formatAmount($unresolvedAmount) ?> ₽ находятся в Основной кассе без назначения.</span></div>
<?php endif; ?>

<div class="section-title">Кассы</div>
<?php if (empty($cashAccounts)): ?>
<div class="panel"><div class="panel-body"><div class="empty-state"><p class="empty-title">Кассы не созданы.</p><p class="empty-desc">Создайте кассу для учёта наличных денежных средств.</p></div></div></div>
<?php else: ?>
<div class="table-card table-card--standard"><div class="table-scroll"><table class="table"><thead><tr><th>Название</th><th>Валюта</th><th>Начальный остаток</th><th>Текущий остаток</th><th>Статус</th></tr></thead><tbody>
<?php foreach ($cashAccounts as $acc): $isMain = trim((string)($acc['name'] ?? '')) === FinanceCashResolutionService::MAIN_CASH_NAME; ?>
<tr>
<td><?= e($acc['name'] ?? '—') ?><?= $isMain ? ' <span class="text-muted">· основная</span>' : '' ?></td>
<td><?= e($acc['currency'] ?? 'RUR') ?></td>
<td class="col-mono"><?= FinanceCashService::formatAmount($acc['opening_balance'] ?? null) ?></td>
<td class="col-mono"><strong><?= FinanceCashService::formatAmount($acc['computed_balance'] ?? null) ?></strong></td>
<td><?php if ($isMain): ?><span class="cash-technical-status <?= $unresolvedCount > 0 ? 'is-alert' : 'is-ok' ?>"><?= $unresolvedCount > 0 ? 'Требует разнесения · '.$unresolvedCount : 'В норме' ?></span><?php else: ?><span class="<?= ($acc['is_active'] ?? 0) ? 'badge badge-ok' : 'badge badge-neutral' ?>"><span class="dot"></span><?= ($acc['is_active'] ?? 0) ? 'Активна' : 'Неактивна' ?></span><?php endif; ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>

<div class="section-title mt-section">Движения по кассе<?php if (isset($cashTotal)): ?><span class="section-title-meta">— <?= count($recentOperations) ?> из <?= $cashTotal ?> (стр. <?= $cashPage ?>/<?= max(1, $cashPages) ?>)</span><?php endif; ?></div>
<?php if (empty($recentOperations)): ?>
<div class="panel"><div class="panel-body"><div class="empty-state"><p class="empty-title">Движений нет.</p><p class="empty-desc">Проведите приход, расход или внутренний перевод.</p></div></div></div>
<?php else: ?>
<div class="cash-batch-bar" id="cash-batch-bar">
    <div class="cash-batch-summary">Выбрано: <strong id="cash-selected-count">0</strong> · <strong id="cash-selected-total">0,00 ₽</strong></div>
    <button type="button" class="btn btn-primary btn--toolbar" id="cash-dispatch-employee-btn" disabled>Передать сотруднику</button>
    <button type="button" class="btn btn-secondary btn--toolbar cash-action-disabled" id="cash-dispatch-carrier-btn" disabled title="Следующий этап реализации">Перевозчику</button>
</div>
<div class="table-card table-card--standard cash-ledger-card"><div class="table-scroll"><table class="table" id="cash-ledger-table"><thead><tr><th class="cash-select-cell"><input type="checkbox" id="cash-select-all" aria-label="Выбрать все неразнесённые позиции на странице"></th><th>Дата</th><th>Касса</th><th>Движение</th><th>Получено от</th><th class="cash-col-purpose">Назначение</th><th>Сумма</th><th class="cash-col-recipient">Передано</th></tr></thead><tbody>
<?php foreach ($recentOperations as $op):
    $movementLabel = FinanceCashLedgerService::movementLabel($op);
    $selectable = !empty($op['is_unresolved_cash_source']);
    $expandable = !empty($op['is_expandable_employee_lifecycle']);
    $rowClass = $selectable ? 'cash-row-unresolved' : (!empty($op['is_resolved_cash_lifecycle']) ? 'cash-row-resolved' : '');
    if ($expandable) $rowClass .= ' cash-row-expandable';
    $detailId = 'cash-lifecycle-detail-' . (int)($op['id'] ?? 0);
?>
<tr data-cash-ledger-row data-cash-direction="<?= e($op['cash_direction'] ?? '') ?>" data-cash-selectable="<?= $selectable ? '1' : '0' ?>" data-cash-expandable="<?= $expandable ? '1' : '0' ?>" data-cash-lifecycle-kind="<?= e($op['employee_lifecycle_kind'] ?? '') ?>" class="<?= e(trim($rowClass)) ?>">
<td class="cash-select-cell"><?php if ($selectable): ?><input type="checkbox" class="cash-source-select" name="source_operation_ids[]" value="<?= (int)$op['id'] ?>" data-amount="<?= e((string)($op['amount'] ?? '0.00')) ?>" aria-label="Выбрать позицию #<?= (int)$op['id'] ?>"><?php elseif ($expandable): ?><button type="button" class="cash-lifecycle-toggle" data-cash-lifecycle-toggle aria-expanded="false" aria-controls="<?= e($detailId) ?>" title="Показать проводки"><span class="cash-lifecycle-chevron">▸</span></button><?php elseif (!empty($op['is_resolved_cash_lifecycle'])): ?><span class="cash-resolved-note" title="Жизненный цикл разнесён">✓</span><?php endif; ?></td>
<td class="col-mono"><?= $fmtDate($op['journal_date'] ?? ($op['operation_date'] ?? '')) ?></td>
<td><?= e($op['account_name'] ?? '—') ?></td>
<td><strong><?= e($movementLabel) ?></strong></td>
<td><?= e($op['source_label'] ?? '—') ?></td>
<td><?= e($op['display_purpose'] ?? '—') ?></td>
<td class="col-mono"><?= FinanceCashService::formatAmount($op['amount'] ?? null) ?></td>
<td><?= e($op['handoff_recipient_label'] ?? '—') ?><?php if (!empty($op['handoff_date'])): ?><span class="cash-handoff-date"> · <?= $fmtDate($op['handoff_date']) ?></span><?php endif; ?></td>
</tr>
<?php if ($expandable): ?>
<tr class="cash-lifecycle-detail-row" id="<?= e($detailId) ?>" data-cash-lifecycle-detail hidden>
<td colspan="8">
    <div class="cash-lifecycle-detail">
        <div class="cash-lifecycle-leg">
            <span class="cash-lifecycle-leg-label">Поступление</span>
            <span class="cash-lifecycle-leg-flow"><strong><?= e($op['source_label'] ?? 'Сотрудник') ?></strong> → Основная касса</span>
            <span class="cash-lifecycle-leg-amount is-in">+<?= FinanceCashService::formatAmount($op['amount'] ?? null) ?> ₽</span>
        </div>
        <div class="cash-lifecycle-leg">
            <span class="cash-lifecycle-leg-label">Списание</span>
            <span class="cash-lifecycle-leg-flow">Основная касса → <strong><?= e($op['handoff_recipient_label'] ?? 'Получатель') ?></strong></span>
            <span class="cash-lifecycle-leg-amount is-out">−<?= FinanceCashService::formatAmount($op['amount'] ?? null) ?> ₽</span>
        </div>
        <div class="cash-lifecycle-meta">
            <?php if (($op['employee_lifecycle_kind'] ?? '') === 'EMPLOYEE_INVOICE'): ?>
                <span><strong>Основание:</strong> входящий счёт<?= !empty($op['employee_lifecycle_invoice_number']) ? ' №'.e($op['employee_lifecycle_invoice_number']) : '' ?></span>
            <?php else: ?>
                <?php if (!empty($op['employee_lifecycle_cfu'])): ?><span><strong>ЦФУ:</strong> <?= e($op['employee_lifecycle_cfu']) ?></span><?php endif; ?>
                <?php if (!empty($op['employee_lifecycle_dds'])): ?><span><strong>Статья ДДС:</strong> <?= e($op['employee_lifecycle_dds']) ?></span><?php endif; ?>
                <?php if (!empty($op['employee_lifecycle_route_id'])): ?><span><strong>Рейс:</strong> #<?= (int)$op['employee_lifecycle_route_id'] ?></span><?php endif; ?>
            <?php endif; ?>
            <span class="cash-lifecycle-tech">Проводки: поступление #<?= (int)$op['id'] ?> · списание #<?= (int)($op['employee_lifecycle_expense_operation_id'] ?? 0) ?></span>
        </div>
    </div>
</td>
</tr>
<?php endif; ?>
<?php endforeach; ?>
</tbody></table></div></div>
<?php if (($cashPages ?? 0) > 1):
    $cashBaseUrl = app_url('/company/finance/cash');
    $prevPage = max(1, $cashPage - 1);
    $nextPage = min($cashPages, $cashPage + 1);
?>
<div class="cash-pagination" aria-label="Навигация по движениям кассы">
    <a class="btn btn-secondary btn--toolbar" href="<?= e($cashBaseUrl.'?page='.$prevPage.'&per_page='.$cashPerPage) ?>" <?= $cashPage <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>← Назад</a>
    <span class="cash-pagination__meta">Страница <?= (int)$cashPage ?> из <?= (int)$cashPages ?> · всего <?= (int)$cashTotal ?> движений</span>
    <a class="btn btn-secondary btn--toolbar" href="<?= e($cashBaseUrl.'?page='.$nextPage.'&per_page='.$cashPerPage) ?>" <?= $cashPage >= $cashPages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Вперёд →</a>
</div>
<?php endif; ?>
<?php endif; ?>

<div id="cash-dispatch-employee-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="0" data-close-on-escape="0"><div class="modal modal-md"><div class="modal-head"><span class="modal-title">Передать сотруднику</span><button type="button" class="modal-close" data-close-modal="cash-dispatch-employee-modal">&times;</button></div><form method="post" action="<?= app_url('/company/finance/cash/dispatch-employee') ?>" id="cash-dispatch-employee-form"><?= csrfField() ?><div class="modal-body"><div class="cash-dispatch-total">Будет разнесено: <strong id="cash-dispatch-modal-count">0 поз.</strong> на <strong id="cash-dispatch-modal-total">0,00 ₽</strong></div><div class="field mt-12"><label class="field-label" for="cash-employee-ref">Сотрудник</label><select class="field-select" id="cash-employee-ref" name="employee_ref" required><option value="">Выберите сотрудника</option><?php foreach ($cashEmployees as $employee): ?><option value="<?= e($employee['ref']) ?>"><?= e($employee['full_name']) ?><?= !empty($employee['role_code']) ? ' · '.e($employee['role_code']) : '' ?></option><?php endforeach; ?></select><div class="field-hint">Каждая выбранная позиция будет списана из технической кассы и отражена во взаиморасчётах выбранного сотрудника.</div></div><div id="cash-dispatch-source-inputs"></div></div><div class="modal-foot"><button type="button" class="btn btn-secondary" data-close-modal="cash-dispatch-employee-modal">Отмена</button><button type="submit" class="btn btn-primary" id="cash-dispatch-confirm">Передать</button></div></form></div></div>

<div id="cash-account-create-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="0" data-close-on-escape="0"><div class="modal modal-md"><div class="modal-head"><span class="modal-title">Создание кассы</span><button type="button" class="modal-close" data-close-modal="cash-account-create-modal">&times;</button></div><div class="modal-body" id="cash-account-create-modal-body"></div></div></div>
<div id="cash-operation-create-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="0" data-close-on-escape="0"><div class="modal modal-md"><div class="modal-head"><span class="modal-title">Новая финансовая операция</span><button type="button" class="modal-close" data-close-modal="cash-operation-create-modal">&times;</button></div><div class="modal-body" id="cash-operation-create-modal-body"></div></div></div>
<div id="cash-transfer-create-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="0" data-close-on-escape="0"><div class="modal modal-md"><div class="modal-head"><span class="modal-title">Внутренний перевод</span><button type="button" class="modal-close" data-close-modal="cash-transfer-create-modal">&times;</button></div><div class="modal-body" id="cash-transfer-create-modal-body"></div></div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var unresolvedCount = <?= $unresolvedCount ?>;
    if (unresolvedCount > 0) {
        var cashNav = Array.from(document.querySelectorAll('.nav-item')).find(function(a){ return (a.getAttribute('href') || '').indexOf('/company/finance/cash') !== -1; });
        if (cashNav && !cashNav.querySelector('.nav-count')) { var badge=document.createElement('span'); badge.className='nav-count is-alert'; badge.title='Неразнесённые позиции технической кассы'; badge.textContent=unresolvedCount>999?'999+':String(unresolvedCount); cashNav.appendChild(badge); }
    }

    Array.from(document.querySelectorAll('[data-cash-lifecycle-toggle]')).forEach(function(toggle) {
        toggle.addEventListener('click', function() {
            var detailId = toggle.getAttribute('aria-controls');
            var detail = detailId ? document.getElementById(detailId) : null;
            if (!detail) return;
            var open = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
            toggle.setAttribute('title', open ? 'Показать проводки' : 'Скрыть проводки');
            detail.hidden = open;
        });
    });

    function initManualFinanceForm(root) {
        var form = root.querySelector('#finance-cash-manual-form');
        if (!form || form.dataset.initialized === '1') return;
        form.dataset.initialized = '1';
        var scenario = form.querySelector('#cash-scenario');
        var blocks = Array.from(form.querySelectorAll('[data-scenario-block]'));
        var hint = form.querySelector('#scenario-hint');
        var purpose = form.querySelector('[name="purpose"]');
        var purposeMark = form.querySelector('#purpose-required-mark');
        var normalType = form.querySelector('#cash-operation-type');
        var normalDds = form.querySelector('#cash-dds-category');
        var personalCfu = form.querySelector('#personal-expense-cfu');
        var personalDds = form.querySelector('#personal-expense-dds');
        var allowedMap = {};
        try { allowedMap = JSON.parse(form.getAttribute('data-allowed-expense-dds-map') || '{}'); } catch (_) { allowedMap = {}; }

        function setRequired(block, activeScenario) {
            var names = activeScenario === 'CLIENT_CASH_RECEIPT' ? ['linear_route_payment_id'] : activeScenario === 'EMPLOYEE_PERSONAL_EXPENSE' ? ['employee_ref','cash_flow_center_id','personal_dds_category_id'] : activeScenario === 'CASH_OPERATION' ? ['operation_type','money_account_id'] : [];
            Array.from(block.querySelectorAll('select,input,textarea')).forEach(function(el){ el.required = names.indexOf(el.name) !== -1; });
        }
        function syncNormalDds() {
            if (!normalDds || !normalType) return;
            Array.from(normalDds.options).forEach(function(opt,index){ if(index===0)return; var dir=opt.dataset.direction||'BOTH'; var visible=dir===normalType.value||dir==='BOTH'; opt.hidden=!visible; opt.disabled=!visible; if(!visible&&opt.selected)normalDds.value=''; });
        }
        function syncPersonalDds() {
            if (!personalCfu || !personalDds) return;
            var allowed = (allowedMap[personalCfu.value] || []).map(Number);
            Array.from(personalDds.options).forEach(function(opt,index){ if(index===0)return; var visible=personalCfu.value!==''&&allowed.indexOf(Number(opt.value))!==-1; opt.hidden=!visible; opt.disabled=!visible; if(!visible&&opt.selected)personalDds.value=''; });
            if (personalDds.options[0]) personalDds.options[0].textContent = personalCfu.value === '' ? '— Сначала выберите ЦФУ —' : '— Выберите статью —';
        }
        function syncScenario() {
            var value = scenario ? scenario.value : 'CASH_OPERATION';
            blocks.forEach(function(block){ var active=block.dataset.scenarioBlock===value; block.hidden=!active; setRequired(block,active?value:''); Array.from(block.querySelectorAll('select,input,textarea')).forEach(function(el){el.disabled=!active;}); });
            if (purpose) { purpose.required=value==='EMPLOYEE_PERSONAL_EXPENSE'; if(purposeMark)purposeMark.hidden=!purpose.required; }
            if (hint) hint.textContent = value==='CLIENT_CASH_RECEIPT' ? 'Реальный приход в Основную кассу с ручной привязкой к выбранному рейсу.' : value==='EMPLOYEE_PERSONAL_EXPENSE' ? 'Сотрудник вносит личные средства в Основную кассу, после чего касса списывает ту же сумму на выбранный расход. Чистый эффект кассы — 0 ₽.' : 'Обычная операция изменяет остаток выбранной кассы.';
            syncNormalDds(); syncPersonalDds();
        }
        if (scenario) scenario.addEventListener('change',syncScenario);
        if (normalType) normalType.addEventListener('change',syncNormalDds);
        if (personalCfu) personalCfu.addEventListener('change',syncPersonalDds);
        syncScenario();
    }

    function loadModal(btnId, modalId, bodyId, url) {
        var btn = document.getElementById(btnId); if (!btn) return;
        btn.addEventListener('click', function() { var body=document.getElementById(bodyId); if(!body)return; body.innerHTML='<div class="empty-state compact"><p>Загрузка...</p></div>'; window.openModal(modalId); fetch(window.getErpBasePath()+url).then(function(r){return r.text();}).then(function(html){body.innerHTML=html;initManualFinanceForm(body);}).catch(function(){body.innerHTML='<div class="form-alert alert-error">Не удалось загрузить форму.</div>';}); });
    }
    loadModal('cash-account-create-btn','cash-account-create-modal','cash-account-create-modal-body','/company/finance/cash/account-create');
    loadModal('cash-operation-create-btn','cash-operation-create-modal','cash-operation-create-modal-body','/company/finance/cash/operation-create');
    loadModal('cash-transfer-create-btn','cash-transfer-create-modal','cash-transfer-create-modal-body','/company/finance/cash/transfer-create');

    var boxes=Array.from(document.querySelectorAll('.cash-source-select')), selectAll=document.getElementById('cash-select-all'), action=document.getElementById('cash-dispatch-employee-btn');
    function cents(value){ var n=String(value||'0').replace(',','.').split('.'); return (parseInt(n[0]||'0',10)*100)+parseInt(String(n[1]||'').padEnd(2,'0').slice(0,2),10); }
    function money(c){ return (c/100).toLocaleString('ru-RU',{minimumFractionDigits:2,maximumFractionDigits:2})+' ₽'; }
    function selected(){ return boxes.filter(function(b){return b.checked;}); }
    function sync(){ var s=selected(), total=s.reduce(function(sum,b){return sum+cents(b.dataset.amount);},0); var countEl=document.getElementById('cash-selected-count'), totalEl=document.getElementById('cash-selected-total'); if(countEl)countEl.textContent=String(s.length); if(totalEl)totalEl.textContent=money(total); if(action)action.disabled=s.length===0; if(selectAll){selectAll.checked=boxes.length>0&&s.length===boxes.length;selectAll.indeterminate=s.length>0&&s.length<boxes.length;} return {items:s,total:total}; }
    boxes.forEach(function(b){b.addEventListener('change',sync);});
    if(selectAll)selectAll.addEventListener('change',function(){boxes.forEach(function(b){b.checked=selectAll.checked;});sync();});
    if(action)action.addEventListener('click',function(){ var state=sync(); if(!state.items.length)return; var inputs=document.getElementById('cash-dispatch-source-inputs'); inputs.innerHTML=''; state.items.forEach(function(b){var i=document.createElement('input');i.type='hidden';i.name='source_operation_ids[]';i.value=b.value;inputs.appendChild(i);}); document.getElementById('cash-dispatch-modal-count').textContent=state.items.length+' поз.'; document.getElementById('cash-dispatch-modal-total').textContent=money(state.total); window.openModal('cash-dispatch-employee-modal'); });
    sync();
});
</script>
<?php endif; ?>