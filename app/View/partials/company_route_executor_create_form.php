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
$selectedDriverIds = $old['driver_ids'] ?? (($old['driver_id'] ?? '') !== '' ? [$old['driver_id']] : ['']);
if (!is_array($selectedDriverIds) || !$selectedDriverIds) $selectedDriverIds = [''];
?>
<?php if ($formError): ?><div class="notice warn"><?= e($formError) ?></div><?php endif; ?>

<form id="<?= e($routeExecutorFormId) ?>" method="post" action="<?= e($routeExecutorFormAction) ?>" class="<?= $routeExecutorCreateFormMode === 'modal' ? '' : 'panel' ?>" data-crew-driver-form>
    <div class="panel-body">
        <div class="form-section">
            <h3 class="panel-head-title">Исполнитель рейса</h3>
            <p class="text-muted mb-section">Подрядчик + экипаж водителей + ТС</p>

            <div class="field">
                <label class="field-label">Подрядчик <span class="req">*</span></label>
                <select name="contractor_id" class="field-input" required<?= empty($contractors) ? ' disabled' : '' ?>>
                    <option value="">— Выберите подрядчика —</option>
                    <?php foreach ($contractors as $ctr): ?>
                    <option value="<?= (int)$ctr['id'] ?>" <?= ($old['contractor_id'] ?? '') == $ctr['id'] ? 'selected' : '' ?>><?= e($ctr['name']) ?> (ИНН: <?= e($ctr['inn']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($contractors)): ?><p class="field-hint">Нет доступных подрядчиков. <a href="<?= app_url('/company/contractors/create') ?>">Создать подрядчика</a></p><?php endif; ?>
                <?php if (!empty($errors['contractor_id'])): ?><div class="field-msg is-error"><?= e($errors['contractor_id']) ?></div><?php endif; ?>
            </div>

            <div class="field" data-crew-drivers-field>
                <label class="field-label">Экипаж <span class="req">*</span></label>
                <div data-crew-driver-list>
                    <?php foreach (array_values($selectedDriverIds) as $i => $selectedDriverId): ?>
                    <div class="crew-driver-row" data-crew-driver-row>
                        <div class="crew-driver-main">
                            <span class="crew-driver-number">Водитель <?= $i + 1 ?></span>
                            <select name="driver_ids[]" class="field-input" required<?= empty($drivers) ? ' disabled' : '' ?>>
                                <option value="">— Выберите водителя —</option>
                                <?php foreach ($drivers as $drv): ?>
                                <option value="<?= (int)$drv['id'] ?>" <?= (string)$selectedDriverId === (string)$drv['id'] ? 'selected' : '' ?>><?= e($drv['full_name']) ?><?= !empty($drv['phone']) ? ' — ' . e($drv['phone']) : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($i > 0): ?><button type="button" class="btn btn-ghost crew-driver-remove" data-remove-crew-driver aria-label="Удалить водителя">Удалить</button><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-ghost mt-2" data-add-crew-driver<?= empty($drivers) ? ' disabled' : '' ?>>+ Добавить водителя</button>
                <p class="field-hint">Минимум один водитель. При необходимости можно добавить второго, третьего и следующих водителей на эту же машину.</p>
                <?php if (empty($drivers)): ?><p class="field-hint">Нет доступных водителей. <a href="<?= app_url('/company/drivers') ?>">Создать водителя</a></p><?php endif; ?>
                <?php if (!empty($errors['driver_ids'])): ?><div class="field-msg is-error"><?= e($errors['driver_ids']) ?></div><?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Транспорт (ТС) <span class="req">*</span></label>
                <select name="vehicle_set_id" class="field-input" required<?= empty($vehicleSets) ? ' disabled' : '' ?>>
                    <option value="">— Выберите транспортный комплект —</option>
                    <?php foreach ($vehicleSets as $vs): ?>
                    <option value="<?= (int)$vs['id'] ?>" <?= ($old['vehicle_set_id'] ?? '') == $vs['id'] ? 'selected' : '' ?>><?= e(ui_set_type($vs['set_type'] ?? null)) ?> — <?= e($vs['primary_plate'] ?? '—') ?><?= !empty($vs['secondary_plate']) ? ' + ' . e($vs['secondary_plate']) : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($vehicleSets)): ?><p class="field-hint">Нет доступных транспортных комплектов. <a href="<?= app_url('/company/vehicle-sets') ?>">Создать ТС</a></p><?php endif; ?>
                <?php if (!empty($errors['vehicle_set_id'])): ?><div class="field-msg is-error"><?= e($errors['vehicle_set_id']) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Дополнительно</h3>
            <div class="field"><label class="field-label">Комментарий</label><textarea name="comments" class="field-textarea" rows="3"><?= e($old['comments'] ?? '') ?></textarea></div>
        </div>

        <?php if ($routeExecutorCreateFormMode === 'page'): ?><div class="form-actions"><button type="submit" class="btn btn-primary">Создать исполнителя рейса</button></div><?php endif; ?>
        <?php if ($routeExecutorCreateFormMode === 'modal'): ?><input type="hidden" name="_is_modal" value="1"><?php endif; ?>
    </div>
</form>
