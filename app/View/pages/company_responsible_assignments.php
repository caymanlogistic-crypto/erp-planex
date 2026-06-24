<?php

/**
 * Страница: Ответственные логисты
 * Доступ: только company_owner
 * Позволяет массово и индивидуально перепривязывать исполнителей рейса,
 * подрядчиков, водителей и ТС от одного логиста к другому.
 */

$tabs = [
    'route_executor' => ['label' => 'Исполнители рейса', 'url' => '/company/responsible-assignments?tab=route_executor'],
    'contractor'     => ['label' => 'Подрядчики',       'url' => '/company/responsible-assignments?tab=contractor'],
    'driver'         => ['label' => 'Водители',          'url' => '/company/responsible-assignments?tab=driver'],
    'vehicle_set'    => ['label' => 'ТС',                'url' => '/company/responsible-assignments?tab=vehicle_set'],
];

$tabLabels = [
    'route_executor' => 'Исполнители рейса',
    'contractor'     => 'Подрядчики',
    'driver'         => 'Водители',
    'vehicle_set'    => 'ТС',
];

$showCascade = in_array($activeTab, ['contractor', 'driver', 'vehicle_set'], true);
$tabLabel    = $tabLabels[$activeTab] ?? '';

?>
<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Ответственные логисты</h1>
        <div class="page-summary"><span>Переназначение ответственных логистов для исполнителей рейса, подрядчиков, водителей и ТС</span></div>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Работа с ответственными логистами недоступна.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Ответственные логисты</h1>
        <div class="page-summary"><span>Переназначение ответственных логистов для исполнителей рейса, подрядчиков, водителей и ТС</span></div>
    </div>
</div>

<div class="notice danger"><?= e($dbError) ?></div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Ответственные логисты</h1>
        <div class="page-summary"><span>Переназначение ответственных логистов для исполнителей рейса, подрядчиков, водителей и ТС</span></div>
    </div>
</div>

<?php if ($successMessage): ?>
<div class="notice ok" style="margin-bottom:12px;"><?= e($successMessage) ?></div>
<?php endif; ?>

<?php if ($formError): ?>
<div class="notice warn" style="margin-bottom:12px;"><?= e($formError) ?></div>
<?php endif; ?>

<div class="tab-bar">
    <?php foreach ($tabs as $key => $tab): ?>
    <a href="<?= e($tab['url']) ?>" class="tab-btn<?= $activeTab === $key ? ' is-active' : '' ?>"><?= e($tab['label']) ?></a>
    <?php endforeach; ?>
</div>

<?php if (empty($items)): ?>

<div class="table-card table-card--toolbar-only" style="margin-top:12px;">
    <div class="empty-state">
        <div class="empty-icon">📋</div>
        <p class="empty-title">Нет записей</p>
        <p class="empty-desc">В разделе «<?= e($tabLabel) ?>» нет активных записей для переназначения.</p>
    </div>
</div>

<?php else: ?>

<!-- Mass reassignment form (global bar above table) -->
<div class="table-card table-card--standard" style="margin-top:12px;">
    <div class="table-toolbar">
        <form id="massReassignToolbarForm" method="post" action="/company/responsible-assignments/reassign" class="inline-form" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;" onsubmit="return massReassignConfirm(this)">
            <input type="hidden" name="entity_type" value="<?= e($activeTab) ?>">
            <?php if ($showCascade): ?>
            <label style="display:flex;align-items:center;gap:4px;font-size:12px;font-weight:500;white-space:nowrap;">
                <input type="checkbox" name="cascade" value="1">
                Перенести вместе с исполнителями рейса
            </label>
            <?php endif; ?>
            <span class="found-label" style="white-space:nowrap;">Новый логист для выбранных:</span>
            <select name="new_logist_id" class="field-select" style="min-width:180px;">
                <option value="">— Выберите —</option>
                <?php foreach ($logists as $l): ?>
                <option value="<?= $l['id'] ?>"><?= e($l['full_name']) ?> (<?= e($l['login']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Переназначить выбранные</button>
        </form>
    </div>
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:40px;">
                        <input type="checkbox" onclick="toggleAllCheckboxes(this)" title="Выбрать все">
                    </th>
                    <?php if ($activeTab === 'route_executor'): ?>
                    <th>Подрядчик</th>
                    <th>Водитель</th>
                    <th>ТС</th>
                    <?php elseif ($activeTab === 'contractor'): ?>
                    <th>Подрядчик</th>
                    <th>ИНН</th>
                    <?php elseif ($activeTab === 'driver'): ?>
                    <th>Водитель</th>
                    <th>Телефон</th>
                    <?php elseif ($activeTab === 'vehicle_set'): ?>
                    <th>ТС</th>
                    <th>Госномер</th>
                    <?php endif; ?>
                    <th>Текущий логист</th>
                    <th>Новый логист</th>
                    <th style="width:130px;">Действие</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <input type="checkbox" class="js-mass-entity-checkbox" name="entity_ids[]" value="<?= $item[($activeTab === 'route_executor') ? 'crew_id' : 'id'] ?>" form="massReassignToolbarForm">
                    </td>
                    <?php if ($activeTab === 'route_executor'): ?>
                    <td><?= e($item['contractor_name'] ?? '—') ?></td>
                    <td><?= e($item['driver_name'] ?? '—') ?></td>
                    <td class="col-mono"><?= e($item['plates'] ?? '—') ?></td>
                    <?php elseif ($activeTab === 'contractor'): ?>
                    <td>
                        <a href="/company/contractors/<?= $item['id'] ?>" class="cell-link"><?= e($item['name'] ?? '—') ?></a>
                    </td>
                    <td class="col-mono"><?= e($item['inn'] ?? '') ?: '—' ?></td>
                    <?php elseif ($activeTab === 'driver'): ?>
                    <td><?= e($item['full_name'] ?? '—') ?></td>
                    <td class="col-mono"><?= e($item['phone'] ?? '—') ?></td>
                    <?php elseif ($activeTab === 'vehicle_set'): ?>
                    <td><?= e(ui_set_type($item['set_type'] ?? null)) ?></td>
                    <td class="col-mono"><?= e($item['plate_number'] ?? '—') ?></td>
                    <?php endif; ?>
                    <td>
                        <?php if (!empty($item['logist_name'])): ?>
                        <span class="cell-main"><?= e($item['logist_name']) ?></span>
                        <?php else: ?>
                        <span class="text-muted">Не назначен</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" action="/company/responsible-assignments/reassign" class="inline-form" style="display:flex;align-items:center;gap:6px;">
                            <input type="hidden" name="entity_type" value="<?= e($activeTab) ?>">
                            <input type="hidden" name="entity_ids[]" value="<?= $item[($activeTab === 'route_executor') ? 'crew_id' : 'id'] ?>">
                            <?php if ($showCascade): ?>
                            <input type="hidden" name="cascade" value="0">
                            <?php endif; ?>
                            <select name="new_logist_id" class="field-select" style="min-width:160px;">
                                <option value="">— Выберите —</option>
                                <?php foreach ($logists as $l):
                                    $sel = ((int)$l['id'] === (int)($item['logist_id'] ?? 0)) ? ' selected' : '';
                                ?>
                                <option value="<?= $l['id'] ?>"<?= $sel ?>><?= e($l['full_name']) ?> (<?= e($l['login']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Переназначить ответственность? Владение записью перейдёт к новому логисту.')">Переназначить</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span class="footer-label">Показано <b><?= count($items) ?></b> записей</span>
    </div>
</div>

<?php endif; ?>

<?php endif; ?>

<!-- Form-level error (non-blocking, shown before mass submit) -->
<div id="reassignFormError" class="form-alert alert-error" style="display:none;margin-bottom:8px;"></div>

<script>
// Show form error inline (no alert)
function showFormError(msg) {
    var el = document.getElementById('reassignFormError');
    if (el) {
        el.textContent = msg;
        el.style.display = 'block';
        setTimeout(function () { el.style.display = 'none'; }, 5000);
    }
}

// Toggle all checkboxes
function toggleAllCheckboxes(el) {
    var checkboxes = document.querySelectorAll('.js-mass-entity-checkbox');
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = el.checked;
    }
}

// Mass reassign confirm
function massReassignConfirm(form) {
    var checked = document.querySelectorAll('.js-mass-entity-checkbox:checked');
    if (checked.length === 0) {
        showFormError('Выберите хотя бы одну запись для переназначения.');
        return false;
    }
    var newLogist = form.querySelector('select[name="new_logist_id"]');
    if (!newLogist || !newLogist.value) {
        showFormError('Выберите нового логиста из выпадающего списка.');
        return false;
    }

    return true;
}
</script>
