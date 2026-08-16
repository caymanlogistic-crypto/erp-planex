<?php

use App\Service\FinanceInvoiceService;

$invoice = $invoice ?? [];
$isEdit = $isEdit ?? false;
$formAction = $isEdit
    ? app_url('/company/finance/invoices/' . (int)($invoice['id'] ?? 0) . '/modal-edit')
    : app_url('/company/finance/invoices/create');

$invDirection = (string)($invoice['direction'] ?? ($_POST['direction'] ?? FinanceInvoiceService::DIRECTION_OUTGOING));
if (!in_array($invDirection, [FinanceInvoiceService::DIRECTION_OUTGOING, FinanceInvoiceService::DIRECTION_INCOMING], true)) {
    $invDirection = FinanceInvoiceService::DIRECTION_OUTGOING;
}
$invNumber = (string)($invoice['number'] ?? ($_POST['number'] ?? ''));
$invDate = (string)($invoice['invoice_date'] ?? ($_POST['invoice_date'] ?? date('Y-m-d')));
$invCpartyId = (string)($invoice['counterparty_entity_id'] ?? ($_POST['counterparty_entity_id'] ?? ''));
$invCpartyInn = (string)($invoice['counterparty_inn'] ?? '');
$invAmount = (string)($invoice['amount'] ?? ($_POST['amount'] ?? ''));
$invVatRate = $invoice['vat_rate'] ?? ($_POST['vat_rate'] ?? '');
$invBasis = (string)($invoice['basis'] ?? ($_POST['basis'] ?? ''));
$invComment = (string)($invoice['comment'] ?? ($_POST['comment'] ?? ''));
$expectedCounterpartyType = $invDirection === FinanceInvoiceService::DIRECTION_OUTGOING ? 'client' : 'contractor';
$counterpartySource = $expectedCounterpartyType === 'client' ? ($clients ?? []) : ($contractors ?? []);
$catalogsJson = json_encode([
    'client' => array_values(array_map(static fn(array $row): array => [
        'id' => (int)($row['id'] ?? 0),
        'name' => (string)($row['name'] ?? ''),
        'inn' => (string)($row['inn'] ?? ''),
    ], $clients ?? [])),
    'contractor' => array_values(array_map(static fn(array $row): array => [
        'id' => (int)($row['id'] ?? 0),
        'name' => (string)($row['name'] ?? ''),
        'inn' => (string)($row['inn'] ?? ''),
    ], $contractors ?? [])),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<form action="<?= e($formAction) ?>" method="post" class="invoice-form" data-invoice-id="<?= (int)($invoice['id'] ?? 0) ?>">
<?= csrfField() ?>
<div class="modal-body">
<?php if (!empty($formError)): ?><div class="form-alert alert-error"><?= e($formError) ?></div><?php endif; ?>

<div class="form-grid-3">
    <div class="field">
        <label class="field-label">Направление</label>
        <select name="direction" class="field-select js-inv-direction" required>
            <option value="OUTGOING"<?= $invDirection === 'OUTGOING' ? ' selected' : '' ?>>Выставленный (клиенту)</option>
            <option value="INCOMING"<?= $invDirection === 'INCOMING' ? ' selected' : '' ?>>Полученный (от перевозчика)</option>
        </select>
    </div>
    <div class="field"><label class="field-label">Номер счёта</label><input type="text" name="number" class="field-input" value="<?= e($invNumber) ?>" required></div>
    <div class="field"><label class="field-label">Дата счёта</label><input type="date" name="invoice_date" class="field-input" value="<?= e($invDate) ?>" required></div>
</div>

<div class="section-title mt-section">Контрагент</div>
<input type="hidden" name="counterparty_entity_type" class="js-inv-cparty-type" value="<?= e($expectedCounterpartyType) ?>">
<div class="form-grid-2">
    <div class="field">
        <label class="field-label">Контрагент</label>
        <select name="counterparty_entity_id" class="field-select js-inv-cparty-select" required>
            <option value="">— Выберите контрагента —</option>
            <?php foreach ($counterpartySource as $c): ?>
            <option value="<?= (int)$c['id'] ?>" data-inn="<?= e((string)($c['inn'] ?? '')) ?>"<?= (int)$invCpartyId === (int)$c['id'] ? ' selected' : '' ?>><?= e((string)$c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field"><label class="field-label">ИНН</label><input type="text" class="field-input js-inv-cparty-inn" value="<?= e($invCpartyInn) ?>" readonly></div>
</div>

<div class="section-title mt-section">Финансовые данные</div>
<div class="form-grid-2">
    <div class="field"><label class="field-label">Сумма</label><input type="text" name="amount" class="field-input" value="<?= e($invAmount) ?>" required placeholder="0.00"></div>
    <div class="field">
        <label class="field-label">Ставка НДС</label>
        <select name="vat_rate" class="field-select">
            <option value=""<?= $invVatRate === '' || $invVatRate === null ? ' selected' : '' ?>>Без НДС</option>
            <?php foreach ([0, 5, 7, 20, 22] as $rate): ?><option value="<?= $rate ?>"<?= (string)$invVatRate === (string)$rate ? ' selected' : '' ?>><?= $rate ?>%</option><?php endforeach; ?>
        </select>
    </div>
</div>
<div class="field"><label class="field-label">Основание</label><textarea name="basis" class="field-textarea" rows="2"><?= e($invBasis) ?></textarea></div>

<div class="section-title mt-section">Платёжные обязательства</div>
<div class="form-hint" style="margin-bottom:8px">Срок оплаты берётся из условий рейса. В один счёт можно включить несколько событий или выставить несколько счетов на одно событие. Сумма выбранных связей должна равняться сумме счёта.</div>
<div class="js-obligations-box" style="border:1px solid var(--border,#d3cec3);max-height:250px;overflow:auto;background:var(--surface,#fff)"><div style="padding:12px;color:var(--muted,#746f66)">Выберите контрагента — система покажет его открытые обязательства.</div></div>

<div class="field mt-section"><label class="field-label">Комментарий</label><textarea name="comment" class="field-textarea" rows="2"><?= e($invComment) ?></textarea></div>
</div>
<div class="modal-foot is-spaced">
    <div class="modal-foot-actions"><button type="button" class="btn btn-ghost" data-close-modal>Отмена</button></div>
    <div class="modal-foot-actions"><button type="submit" class="btn btn-primary"><?= $isEdit ? 'Сохранить' : 'Создать счёт' ?></button></div>
</div>
</form>
<script>
(function(){
    var script = document.currentScript;
    var form = script.previousElementSibling;
    if (!form || !form.classList.contains('invoice-form')) return;

    var catalogs = <?= $catalogsJson ?: '{"client":[],"contractor":[]}' ?>;
    var type = form.querySelector('.js-inv-cparty-type');
    var cp = form.querySelector('.js-inv-cparty-select');
    var inn = form.querySelector('.js-inv-cparty-inn');
    var dir = form.querySelector('.js-inv-direction');
    var box = form.querySelector('.js-obligations-box');
    var base = window.getErpBasePath ? window.getErpBasePath() : '';
    var initialId = <?= json_encode((string)$invCpartyId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function esc(s){return String(s == null ? '' : s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
    function expectedType(){return dir.value === 'INCOMING' ? 'contractor' : 'client';}
    function emptyObligations(){box.innerHTML='<div style="padding:12px;color:var(--muted,#746f66)">Выберите контрагента — система покажет его открытые обязательства.</div>';}

    function populateCounterparties(selectedId){
        var t = expectedType();
        type.value = t;
        var rows = Array.isArray(catalogs[t]) ? catalogs[t] : [];
        cp.innerHTML = '<option value="">— Выберите контрагента —</option>';
        rows.forEach(function(x){
            var o = document.createElement('option');
            o.value = String(x.id || '');
            o.textContent = x.name || '';
            o.dataset.inn = x.inn || '';
            if (selectedId && String(x.id) === String(selectedId)) o.selected = true;
            cp.appendChild(o);
        });
        var selected = cp.options[cp.selectedIndex];
        inn.value = selected && selected.value ? (selected.dataset.inn || '') : '';
    }

    function loadOb(){
        var t = expectedType(), id = cp.value;
        type.value = t;
        if (!id){ emptyObligations(); return; }
        var invoiceId = form.dataset.invoiceId || '';
        var url = base + '/company/finance/invoices/obligations?direction=' + encodeURIComponent(dir.value) + '&counterparty=' + encodeURIComponent(t + ':' + id) + (invoiceId ? '&invoice_id=' + encodeURIComponent(invoiceId) : '');
        box.innerHTML = '<div style="padding:12px">Загрузка…</div>';
        fetch(url, {headers:{'Accept':'application/json'}})
            .then(function(r){return r.json().then(function(data){if(!r.ok) throw new Error(data && data.error ? data.error : 'HTTP '+r.status); return data;});})
            .then(function(data){
                if (!Array.isArray(data) || !data.length){
                    box.innerHTML='<div style="padding:12px;color:var(--muted,#746f66)">Открытых обязательств для этого контрагента нет.</div>';
                    return;
                }
                var h='<table style="width:100%;border-collapse:collapse"><thead><tr><th style="padding:7px;text-align:left">Выбрать</th><th style="padding:7px;text-align:left">Рейс / событие</th><th style="padding:7px;text-align:right">Свободно</th><th style="padding:7px;text-align:right">В счёт</th></tr></thead><tbody>';
                data.forEach(function(x){
                    var cur=parseFloat(x.current_amount||0), checked=cur>0, val=checked?x.current_amount:x.available;
                    var due=x.due_date?' · срок '+esc(x.due_date):' · срок ожидает события';
                    h+='<tr style="border-top:1px solid var(--border,#ddd)"><td style="padding:7px"><input type="checkbox" class="js-ob-check" '+(checked?'checked':'')+'></td><td style="padding:7px"><strong>Рейс #'+esc(x.route_id)+'</strong><div style="font-size:11px;color:var(--muted,#746f66)">'+esc(x.condition)+due+'</div></td><td style="padding:7px;text-align:right;white-space:nowrap">'+esc(x.available)+' ₽</td><td style="padding:7px;text-align:right"><input type="hidden" name="obligation_id[]" value="'+esc(x.id)+'" '+(checked?'':'disabled')+'><input class="field-input js-ob-amount" style="width:120px;text-align:right" name="obligation_amount[]" value="'+esc(val)+'" '+(checked?'':'disabled')+'></td></tr>';
                });
                box.innerHTML=h+'</tbody></table>';
                box.querySelectorAll('.js-ob-check').forEach(function(ch){
                    ch.addEventListener('change',function(){
                        var tr=ch.closest('tr');
                        tr.querySelectorAll('input[name]').forEach(function(i){i.disabled=!ch.checked;});
                    });
                });
            })
            .catch(function(){box.innerHTML='<div class="form-alert alert-error" style="margin:8px">Не удалось загрузить платёжные обязательства.</div>';});
    }

    dir.addEventListener('change', function(){populateCounterparties(''); emptyObligations();});
    cp.addEventListener('change', function(){
        var o=cp.options[cp.selectedIndex];
        inn.value=o && o.value ? (o.dataset.inn||'') : '';
        loadOb();
    });

    populateCounterparties(initialId);
    if (cp.value) loadOb(); else emptyObligations();
})();
</script>