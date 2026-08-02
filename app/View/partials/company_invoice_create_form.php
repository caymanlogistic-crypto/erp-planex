<?php

use App\Service\FinanceInvoiceService;

$invoice = $invoice ?? [];
$isEdit = $isEdit ?? false;
$formAction = $isEdit
    ? app_url('/company/finance/invoices/' . (int) ($invoice['id'] ?? 0) . '/modal-edit')
    : app_url('/company/finance/invoices/create');

$invDirection = $invoice['direction'] ?? ($_POST['direction'] ?? FinanceInvoiceService::DIRECTION_OUTGOING);
$invNumber = $invoice['number'] ?? ($_POST['number'] ?? '');
$invDate = $invoice['invoice_date'] ?? ($_POST['invoice_date'] ?? date('Y-m-d'));
$invCpartyType = $invoice['counterparty_entity_type'] ?? ($_POST['counterparty_entity_type'] ?? '');
$invCpartyId = $invoice['counterparty_entity_id'] ?? ($_POST['counterparty_entity_id'] ?? '');
$invCpartyName = $invoice['counterparty_name'] ?? ($_POST['counterparty_name'] ?? '');
$invCpartyInn = $invoice['counterparty_inn'] ?? ($_POST['counterparty_inn'] ?? '');
$invAmount = $invoice['amount'] ?? ($_POST['amount'] ?? '');
$invVatRate = $invoice['vat_rate'] ?? ($_POST['vat_rate'] ?? '');
$invBasis = $invoice['basis'] ?? ($_POST['basis'] ?? '');
$invPlanned = $invoice['planned_payment_date'] ?? ($_POST['planned_payment_date'] ?? '');
$invComment = $invoice['comment'] ?? ($_POST['comment'] ?? '');
$invStatus = $invoice['status'] ?? ($_POST['status'] ?? 'draft');

$linkRouteId = $linkRouteId ?? ($_POST['link_route_id'] ?? '');
$linkPaymentId = $linkPaymentId ?? ($_POST['link_payment_id'] ?? '');
$linkSide = $linkSide ?? ($_POST['link_side'] ?? '');
?>
<form action="<?= $formAction ?>" method="post" class="invoice-form">
    <?= csrfField() ?>
    <div class="modal-body">
        <?php if (!empty($formError)): ?>
        <div class="form-alert alert-error"><?= e($formError) ?></div>
        <?php endif; ?>

        <div class="form-grid-3">
            <div class="field">
                <label class="field-label">Направление</label>
                <select name="direction" class="field-select" required>
                    <option value="<?= FinanceInvoiceService::DIRECTION_OUTGOING ?>"<?= $invDirection === FinanceInvoiceService::DIRECTION_OUTGOING ? ' selected' : '' ?>>Выставленный (клиенту)</option>
                    <option value="<?= FinanceInvoiceService::DIRECTION_INCOMING ?>"<?= $invDirection === FinanceInvoiceService::DIRECTION_INCOMING ? ' selected' : '' ?>>Полученный (от перевозчика)</option>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Номер счёта</label>
                <input type="text" name="number" class="field-input" value="<?= e($invNumber) ?>" required>
            </div>
            <div class="field">
                <label class="field-label">Дата счёта</label>
                <input type="date" name="invoice_date" class="field-input" value="<?= e($invDate) ?>" required>
            </div>
        </div>

        <div class="section-title mt-section">Контрагент</div>
        <div class="form-grid-3">
            <div class="field">
                <label class="field-label">Тип контрагента</label>
                <select name="counterparty_entity_type" class="field-select" id="inv-cparty-type">
                    <option value="">— Не выбран —</option>
                    <option value="client"<?= $invCpartyType === 'client' ? ' selected' : '' ?>>Клиент</option>
                    <option value="contractor"<?= $invCpartyType === 'contractor' ? ' selected' : '' ?>>Перевозчик</option>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Контрагент</label>
                <select name="counterparty_entity_id" class="field-select" id="inv-cparty-select">
                    <option value="">— Выберите тип контрагента —</option>
                    <?php if ($invCpartyType === 'client'): ?>
                        <?php foreach ($clients as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" data-name="<?= e($c['name']) ?>" data-inn="<?= e($c['inn'] ?? '') ?>"<?= (int) $invCpartyId === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    <?php elseif ($invCpartyType === 'contractor'): ?>
                        <?php foreach ($contractors as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" data-name="<?= e($c['name']) ?>" data-inn="<?= e($c['inn'] ?? '') ?>"<?= (int) $invCpartyId === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label">ИНН</label>
                <input type="text" name="counterparty_inn" class="field-input" id="inv-cparty-inn" value="<?= e($invCpartyInn) ?>" readonly>
            </div>
        </div>
        <div class="form-grid-3">
            <div class="field">
                <label class="field-label">Наименование (вручную)</label>
                <input type="text" name="counterparty_name" class="field-input" id="inv-cparty-name" value="<?= e($invCpartyName) ?>" placeholder="Если контрагент не выбран из списка">
            </div>
        </div>

        <div class="section-title mt-section">Финансовые данные</div>
        <div class="form-grid-3">
            <div class="field">
                <label class="field-label">Сумма</label>
                <input type="text" name="amount" class="field-input" value="<?= e($invAmount) ?>" required placeholder="0.00">
            </div>
            <div class="field">
                <label class="field-label">Ставка НДС</label>
                <select name="vat_rate" class="field-select">
                    <option value=""<?= $invVatRate === '' || $invVatRate === null ? ' selected' : '' ?>>Без НДС</option>
                    <option value="0"<?= $invVatRate === '0' || $invVatRate === 0 ? ' selected' : '' ?>>0%</option>
                    <option value="5"<?= $invVatRate === '5' || $invVatRate === 5 ? ' selected' : '' ?>>5%</option>
                    <option value="7"<?= $invVatRate === '7' || $invVatRate === 7 ? ' selected' : '' ?>>7%</option>
                    <option value="20"<?= $invVatRate === '20' || $invVatRate === 20 ? ' selected' : '' ?>>20%</option>
                    <option value="22"<?= $invVatRate === '22' || $invVatRate === 22 ? ' selected' : '' ?>>22%</option>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Плановая дата оплаты</label>
                <input type="date" name="planned_payment_date" class="field-input" value="<?= e($invPlanned) ?>">
            </div>
        </div>
        <div class="field">
            <label class="field-label">Основание</label>
            <textarea name="basis" class="field-textarea" rows="2"><?= e($invBasis) ?></textarea>
        </div>

        <div class="section-title mt-section">Привязка к рейсу</div>
        <div class="form-grid-3">
            <div class="field">
                <label class="field-label">Рейс</label>
                <select name="link_route_id" class="field-select" id="inv-link-route">
                    <option value="">— Не привязывать —</option>
                    <?php foreach ($routes as $r): ?>
                    <option value="<?= (int) $r['id'] ?>"<?= (int) $linkRouteId === (int) $r['id'] ? ' selected' : '' ?>>
                        #<?= (int) $r['id'] ?> (<?= e($r['route_type'] ?? '') ?>, <?= e($r['planned_loading_date'] ?? '') ?>) — <?= e($r['client_name'] ?? '') ?> / <?= e($r['carrier_name'] ?? '') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Сторона</label>
                <select name="link_side" class="field-select">
                    <option value=""<?= $linkSide === '' ? ' selected' : '' ?>>—</option>
                    <option value="customer"<?= $linkSide === 'customer' ? ' selected' : '' ?>>Заказчик</option>
                    <option value="carrier"<?= $linkSide === 'carrier' ? ' selected' : '' ?>>Перевозчик</option>
                    <option value="principal"<?= $linkSide === 'principal' ? ' selected' : '' ?>>Принципал</option>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Платёжная строка</label>
                <select name="link_payment_id" class="field-select" id="inv-link-payment">
                    <option value="">— Все строки —</option>
                    <?php if (!empty($routePayments)): ?>
                        <?php foreach ($routePayments as $p): ?>
                        <option value="<?= (int) $p['id'] ?>"<?= (int) $linkPaymentId === (int) $p['id'] ? ' selected' : '' ?>>
                            <?= e(FinanceInvoiceService::formatAmount($p['amount'])) ?> · <?= e($p['party_role'] ?? '') ?>
                        </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div class="form-grid-3">
            <div class="field">
                <label class="field-label">Статус</label>
                <select name="status" class="field-select">
                    <?php foreach (FinanceInvoiceService::STATUSES as $key => $label): ?>
                    <option value="<?= e($key) ?>"<?= $invStatus === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Комментарий</label>
                <textarea name="comment" class="field-textarea" rows="2"><?= e($invComment) ?></textarea>
            </div>
        </div>
    </div>
    <div class="modal-foot is-spaced">
        <div class="modal-foot-actions">
            <button type="button" class="btn btn-ghost" data-close-modal="invoice-create-modal">Отмена</button>
        </div>
        <div class="modal-foot-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Сохранить' : 'Создать счёт' ?></button>
        </div>
    </div>
</form>

<script>
(function() {
    var form = document.querySelector('.invoice-form');
    if (!form) return;

    var cpartyType = form.querySelector('#inv-cparty-type');
    var cpartySelect = form.querySelector('#inv-cparty-select');
    var cpartyName = form.querySelector('#inv-cparty-name');
    var cpartyInn = form.querySelector('#inv-cparty-inn');
    var linkRoute = form.querySelector('#inv-link-route');
    var linkPayment = form.querySelector('#inv-link-payment');

    function updateCpartySelect() {
        var type = cpartyType.value;
        var url = window.getErpBasePath() + '/company/finance/invoices/counterparty-list?type=' + encodeURIComponent(type);
        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                cpartySelect.innerHTML = '<option value="">— Выберите контрагента —</option>';
                data.forEach(function(item) {
                    var opt = document.createElement('option');
                    opt.value = item.id;
                    opt.textContent = item.name;
                    opt.dataset.name = item.name;
                    opt.dataset.inn = item.inn || '';
                    cpartySelect.appendChild(opt);
                });
            })
            .catch(function() {
                cpartySelect.innerHTML = '<option value="">— Ошибка загрузки —</option>';
            });
    }

    cpartyType.addEventListener('change', updateCpartySelect);

    cpartySelect.addEventListener('change', function() {
        var opt = this.options[this.selectedIndex];
        if (opt && opt.dataset.name) {
            cpartyName.value = opt.dataset.name;
            cpartyInn.value = opt.dataset.inn || '';
        }
    });

    linkRoute.addEventListener('change', function() {
        var routeId = this.value;
        if (!routeId) {
            linkPayment.innerHTML = '<option value="">— Все строки —</option>';
            return;
        }
        var url = window.getErpBasePath() + '/company/finance/invoices/route-payments?route_id=' + encodeURIComponent(routeId);
        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                linkPayment.innerHTML = '<option value="">— Все строки —</option>';
                data.forEach(function(item) {
                    var opt = document.createElement('option');
                    opt.value = item.id;
                    opt.textContent = item.label;
                    linkPayment.appendChild(opt);
                });
            })
            .catch(function() {
                linkPayment.innerHTML = '<option value="">— Ошибка загрузки —</option>';
            });
    });
})();
</script>
