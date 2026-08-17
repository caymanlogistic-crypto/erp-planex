<?php
$isEdit = !empty($event['id']);
$eventId = $isEdit ? (int)$event['id'] : 0;
$employeeRef = (string)($employee['ref'] ?? '');
$employeeName = (string)($employee['full_name'] ?? '');
$selectedCfu = (int)($event['cash_flow_center_id'] ?? 0);
$selectedDds = (int)($event['dds_category_id'] ?? 0);
$selectedRoute = (int)($event['linear_route_id'] ?? 0);
$allowedMapJson = json_encode($allowedExpenseDdsMap ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
$action = $isEdit
    ? app_url('/company/finance/employee-payments/personal-expense/' . $eventId . '/update')
    : app_url('/company/finance/employee-payments/personal-expense/create');
?>
<div class="modal-overlay is-open employee-personal-expense-modal" role="dialog" aria-modal="true" aria-labelledby="employee-personal-expense-title">
    <div class="modal modal-lg">
        <div class="modal-head">
            <span class="modal-title" id="employee-personal-expense-title"><?= $isEdit ? 'Изменить расход из личных средств' : 'Сотрудник оплатил расход компании' ?></span>
            <button type="button" class="modal-close" data-personal-expense-close>&times;</button>
        </div>
        <form method="post" action="<?= e($action) ?>" data-personal-expense-form data-allowed-expense-dds-map="<?= e($allowedMapJson) ?>">
            <?= csrfField() ?>
            <div class="modal-body">
                <div class="form-alert alert-info" style="margin-bottom:12px;">
                    После сохранения система создаст единое связанное событие: <strong>расход сотрудника</strong>,
                    <strong>поступление в Основную кассу от сотрудника</strong> и <strong>списание из Основной кассы на выбранные ЦФУ / статью ДДС</strong>.
                    Остаток Основной кассы по событию не изменится.
                </div>

                <?php if ($isEdit || $employee): ?>
                    <input type="hidden" name="employee_ref" value="<?= e($employeeRef) ?>">
                    <div class="field">
                        <label class="field-label">Сотрудник</label>
                        <input class="field-input" value="<?= e($employeeName) ?>" readonly>
                    </div>
                <?php else: ?>
                    <div class="field">
                        <label class="field-label">Сотрудник <span class="field-required">*</span></label>
                        <select class="field-select" name="employee_ref" required>
                            <option value="">— Выберите сотрудника —</option>
                            <?php foreach (($employees ?? []) as $row): ?>
                                <option value="<?= e($row['ref']) ?>"><?= e($row['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="form-grid-3">
                    <div class="field">
                        <label class="field-label">Дата <span class="field-required">*</span></label>
                        <input class="field-input" type="date" name="operation_date" value="<?= e((string)($event['operation_date'] ?? date('Y-m-d'))) ?>" required>
                    </div>
                    <div class="field">
                        <label class="field-label">Сумма <span class="field-required">*</span></label>
                        <input class="field-input" name="amount" inputmode="decimal" autocomplete="off" value="<?= e((string)($event['amount'] ?? '')) ?>" placeholder="0,00" required>
                    </div>
                    <div class="field">
                        <label class="field-label">Получатель / контрагент</label>
                        <input class="field-input" name="counterparty_name" value="<?= e((string)($event['counterparty_name'] ?? '')) ?>" placeholder="Например: ATI.SU">
                    </div>
                </div>

                <div class="form-grid-3">
                    <div class="field">
                        <label class="field-label">ЦФУ <span class="field-required">*</span></label>
                        <select class="field-select" name="cash_flow_center_id" data-personal-expense-cfu required>
                            <option value="">— Выберите ЦФУ —</option>
                            <?php foreach (($cashFlowCenters ?? []) as $center): ?>
                                <option value="<?= (int)$center['id'] ?>" <?= $selectedCfu === (int)$center['id'] ? 'selected' : '' ?>><?= e($center['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label">Статья ДДС <span class="field-required">*</span></label>
                        <select class="field-select" name="dds_category_id" data-personal-expense-dds data-selected-dds="<?= $selectedDds ?>" required>
                            <option value="">— Выберите статью —</option>
                            <?php foreach (($expenseDdsCategories ?? []) as $dds): ?>
                                <option value="<?= (int)$dds['id'] ?>" <?= $selectedDds === (int)$dds['id'] ? 'selected' : '' ?>><?= e($dds['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label">Рейс</label>
                        <select class="field-select" name="linear_route_id">
                            <option value="">— Не относится к рейсу —</option>
                            <?php foreach (($routes ?? []) as $route): ?>
                                <option value="<?= (int)$route['id'] ?>" <?= $selectedRoute === (int)$route['id'] ? 'selected' : '' ?>>Рейс #<?= (int)$route['id'] ?><?= !empty($route['client_name']) ? ' · ' . e($route['client_name']) : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label">Назначение <span class="field-required">*</span></label>
                    <input class="field-input" name="purpose" value="<?= e((string)($event['purpose'] ?? '')) ?>" placeholder="Например: доступ ATI за август" required>
                </div>
                <div class="field">
                    <label class="field-label">Комментарий</label>
                    <textarea class="field-input" name="comment" rows="3" placeholder="Необязательно"><?= e((string)($event['comment'] ?? '')) ?></textarea>
                </div>
            </div>
            <div class="modal-foot is-spaced">
                <div class="modal-foot-actions">
                    <?php if ($isEdit && ($event['status'] ?? '') === 'POSTED'): ?>
                        <button type="button" class="btn btn-ghost fs-delete-action" data-personal-expense-cancel-event="<?= $eventId ?>" data-employee-ref="<?= e($employeeRef) ?>">Отменить операцию</button>
                    <?php endif; ?>
                </div>
                <div class="modal-foot-actions">
                    <button type="button" class="btn btn-secondary" data-personal-expense-close>Закрыть</button>
                    <?php if (!$isEdit || ($event['status'] ?? '') === 'POSTED'): ?>
                        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Сохранить изменения' : 'Провести операцию' ?></button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>
