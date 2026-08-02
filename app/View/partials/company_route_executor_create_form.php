<?php
$routeExecutorCreateFormMode = $routeExecutorCreateFormMode ?? 'page';
$routeExecutorFormId = $routeExecutorFormId ?? 'route-executor-create-form';
$routeExecutorFormAction = $routeExecutorFormAction ?? app_url('/company/route-executors/create');
$formError = $formError ?? null;
$errors = $errors ?? [];
$old = $old ?? [];
$contractors = $contractors ?? [];
$drivers = $drivers ?? [];
$vehicleSets = $vehicleSets ?? [];
?>
<?php if ($formError): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form id="<?= $routeExecutorFormId ?>" method="post" action="<?= $routeExecutorFormAction ?>" class="<?= $routeExecutorCreateFormMode === 'modal' ? '' : 'panel' ?>">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Исполнитель рейса</h3>
            <p class="text-muted mb-section">Подрядчик + Водитель + ТС</p>

            <div class="field">
                <label class="field-label">Подрядчик <span class="req">*</span></label>
                <select name="contractor_id" class="field-input" required<?= empty($contractors) ? ' disabled' : '' ?>>
                    <option value="">— Выберите подрядчика —</option>
                    <?php foreach ($contractors as $ctr): ?>
                    <option value="<?= $ctr['id'] ?>" <?= ($old['contractor_id'] ?? '') == $ctr['id'] ? 'selected' : '' ?>>
                        <?= e($ctr['name']) ?> (ИНН: <?= e($ctr['inn']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($contractors)): ?>
                    <p class="field-hint">Нет доступных подрядчиков. <a href="<?= app_url('/company/contractors/create') ?>">Создать подрядчика</a></p>
                <?php endif; ?>
                <?php if (!empty($errors['contractor_id'])): ?>
                    <div class="field-msg is-error"><?= e($errors['contractor_id']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Водитель <span class="req">*</span></label>
                <select name="driver_id" class="field-input" required<?= empty($drivers) ? ' disabled' : '' ?>>
                    <option value="">— Выберите водителя —</option>
                    <?php foreach ($drivers as $drv): ?>
                    <option value="<?= $drv['id'] ?>" <?= ($old['driver_id'] ?? '') == $drv['id'] ? 'selected' : '' ?>>
                        <?= e($drv['full_name']) ?> — <?= e($drv['phone']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($drivers)): ?>
                    <p class="field-hint">Нет доступных водителей. <a href="<?= app_url('/company/drivers') ?>">Создать водителя</a></p>
                <?php endif; ?>
                <?php if (!empty($errors['driver_id'])): ?>
                    <div class="field-msg is-error"><?= e($errors['driver_id']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Транспорт (ТС) <span class="req">*</span></label>
                <select name="vehicle_set_id" class="field-input" required<?= empty($vehicleSets) ? ' disabled' : '' ?>>
                    <option value="">— Выберите транспортный комплект —</option>
                    <?php foreach ($vehicleSets as $vs): ?>
                    <option value="<?= $vs['id'] ?>" <?= ($old['vehicle_set_id'] ?? '') == $vs['id'] ? 'selected' : '' ?>>
                        <?= e(ui_set_type($vs['set_type'] ?? null)) ?> — <?= e($vs['primary_plate'] ?? '—') ?><?= !empty($vs['secondary_plate']) ? ' + ' . e($vs['secondary_plate']) : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($vehicleSets)): ?>
                    <p class="field-hint">Нет доступных транспортных комплектов. <a href="<?= app_url('/company/vehicle-sets') ?>">Создать ТС</a></p>
                <?php endif; ?>
                <?php if (!empty($errors['vehicle_set_id'])): ?>
                    <div class="field-msg is-error"><?= e($errors['vehicle_set_id']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Дополнительно</h3>

            <div class="field">
                <label class="field-label">Комментарий</label>
                <textarea name="comments" class="field-textarea" rows="3"><?= e($old['comments'] ?? '') ?></textarea>
            </div>
        </div>

        <?php if ($routeExecutorCreateFormMode === 'page'): ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Создать исполнителя рейса</button>
        </div>
        <?php endif; ?>

        <?php if ($routeExecutorCreateFormMode === 'modal'): ?>
        <input type="hidden" name="_is_modal" value="1">
        <?php endif; ?>

    </div>
</form>
