<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">КОМПАНИЯ / <?= e($company['name']) ?></span>
        <span class="page-title">Добавить экипаж перевозчику</span>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors/<?= (int)($contractorId ?? 0) ?>" class="btn btn-ghost">← К перевозчику</a>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Создание недоступно.
</div>

<?php elseif (isset($dbError)): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice danger">
            <?= e($dbError) ?>
        </div>
        <div class="form-actions mt-4">
            <a href="/company/contractors/<?= (int)($contractorId ?? 0) ?>" class="btn btn-ghost">← К перевозчику</a>
        </div>
    </div>
</div>

<?php elseif ($contractor === null): ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">КОМПАНИЯ / <?= e($company['name']) ?></span>
        <span class="page-title">Перевозчик не найден</span>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/company/contractors') ?>" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Перевозчик с указанным ID не найден.
        </div>
    </div>
</div>

<?php elseif ($success): ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">КОМПАНИЯ / <?= e($company['name']) ?></span>
        <span class="page-title">Экипаж создан</span>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors/<?= (int)($contractorId ?? 0) ?>" class="btn btn-primary">← К перевозчику</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Водитель, транспорт и экипаж успешно созданы для перевозчика «<?= e($contractor['name']) ?>».
        </div>

        <div class="tbl-wrap mt-4">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Сущность</th>
                        <th>Данные</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Перевозчик</td>
                        <td class="cell-double">
                            <span class="cell-main"><?= e($contractor['name']) ?></span>
                            <span class="cell-sub">ИНН <?= e($contractor['inn'] ?? '—') ?></span>
                        </td>
                        <td class="col-actions">
                            <a href="/company/contractors/<?= (int)($contractorId ?? 0) ?>" class="btn btn-toolbar">Просмотр</a>
                        </td>
                    </tr>
                    <tr>
                        <td>Водитель</td>
                        <td>
                            <?= e($createdDriver['full_name']) ?>
                        </td>
                        <td class="col-actions">
                            <a href="/company/drivers/<?= $createdDriver['id'] ?>" class="btn btn-toolbar">Просмотр</a>
                        </td>
                    </tr>
                    <tr>
                        <td>Транспорт</td>
                        <td class="cell-double">
                            <span class="cell-main"><?= e($createdVehicleSet['primary_plate']) ?></span>
                            <?php if (!empty($createdVehicleSet['secondary_plate'])): ?>
                            <span class="cell-sub">+ <?= e($createdVehicleSet['secondary_plate']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="col-actions">
                            <a href="/company/vehicle-sets/<?= $createdVehicleSet['id'] ?>" class="btn btn-toolbar">Просмотр</a>
                        </td>
                    </tr>
                    <tr>
                        <td>Связка Водители+ТС</td>
                        <td>
                            <?= e($createdDriver['full_name']) ?> + <?= e($createdVehicleSet['primary_plate']) ?>
                        </td>
                        <td class="col-actions">
                            <a href="/company/driver-vehicle-blocks/<?= $createdBlock['id'] ?>" class="btn btn-toolbar">Просмотр</a>
                        </td>
                    </tr>
                    <?php if (!empty($createdCrew['id'])): ?>
                    <tr>
                        <td>Экипаж</td>
                        <td>
                            <?= e($contractor['name']) ?> — <?= e($createdDriver['full_name']) ?> + <?= e($createdVehicleSet['primary_plate']) ?>
                        </td>
                        <td class="col-actions">
                            <a href="/company/crews/<?= $createdCrew['id'] ?>" class="btn btn-toolbar">Просмотр</a>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="form-actions mt-4">
            <a href="/company/contractors/<?= (int)($contractorId ?? 0) ?>" class="btn btn-primary">← К перевозчику</a>
            <a href="/company/contractors/<?= (int)($contractorId ?? 0) ?>/add-crew" class="btn btn-ghost">Добавить ещё экипаж</a>
        </div>
    </div>
</div>

<?php else: ?>

<?php
// Determine block_mode from old data
$blockMode = $old['block_mode'] ?? 'existing';

// Determine initial step
$initialStep = 1;
if (!empty($old)) {
    if ($blockMode === 'existing') {
        if (!empty($errors['block_id'])) {
            $initialStep = 1;
        } else {
            $initialStep = 4; // skip to review
        }
    } else {
        if (!empty($errors['block_mode']) || empty($old['block_mode']) || $old['block_mode'] === 'existing') {
            $initialStep = 1;
        } elseif (!empty($errors['driver_id']) || !empty($errors['driver_full_name'])) {
            $initialStep = 2;
        } elseif (!empty($errors['vehicle_set_id']) || !empty($errors['plate_number']) || !empty($errors['secondary_plate_number'])) {
            $initialStep = 3;
        } else {
            $initialStep = 4;
        }
    }
}

// Step names and visibility
$showDriverStep = ($blockMode === 'new');
$showVehicleStep = ($blockMode === 'new');

// Pre-compute review display data
$reviewBlock = '';
if ($blockMode === 'existing' && !empty($old['block_id'])) {
    foreach ($driverVehicleBlocks as $dvb) {
        if ($dvb['id'] == $old['block_id']) {
            $reviewBlock = $dvb['driver_name'] . ' — ' . ($dvb['primary_plate'] ?? '—') . (!empty($dvb['secondary_plate']) ? ' + ' . $dvb['secondary_plate'] : '');
            break;
        }
    }
}

$reviewDriver = '';
if ($blockMode === 'new') {
    if (!empty($old['driver_mode']) && $old['driver_mode'] === 'existing' && !empty($old['driver_id'])) {
        foreach ($drivers as $d) {
            if ($d['id'] == $old['driver_id']) {
                $reviewDriver = $d['full_name'] . ' (' . ($d['phone'] ?? '—') . ')';
                break;
            }
        }
    } elseif (!empty($old['driver_full_name'])) {
        $reviewDriver = $old['driver_full_name'] . (!empty($old['driver_phone']) ? ' (' . $old['driver_phone'] . ')' : '');
    }
}

$reviewVehicle = '';
if ($blockMode === 'new') {
    if (!empty($old['vehicle_mode']) && $old['vehicle_mode'] === 'existing' && !empty($old['vehicle_set_id'])) {
        foreach ($vehicleSets as $vs) {
            if ($vs['id'] == $old['vehicle_set_id']) {
                $reviewVehicle = ui_set_type($vs['set_type'] ?? null) . ' — ' . ($vs['primary_plate'] ?? '—') . (!empty($vs['secondary_plate']) ? ' + ' . $vs['secondary_plate'] : '');
                break;
            }
        }
    } elseif (!empty($old['plate_number'])) {
        $reviewVehicle = ($old['plate_number'] ?? '—') . (!empty($old['brand']) ? ' ' . $old['brand'] : '') . (!empty($old['model']) ? ' ' . $old['model'] : '') . ' (' . ui_set_type($old['set_type'] ?? 'single') . ')' . (!empty($old['secondary_plate_number']) ? ' + ' . $old['secondary_plate_number'] : '');
    }
}
?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">КОМПАНИЯ / <?= e($company['name']) ?></span>
        <span class="page-title">Добавить экипаж перевозчику «<?= e($contractor['name']) ?>»</span>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors/<?= (int)($contractorId ?? 0) ?>" class="btn btn-ghost">← К перевозчику</a>
    </div>
</div>

<?php if ($formError): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/company/contractors/<?= (int)($contractorId ?? 0) ?>/add-crew">

    <!-- Hidden block_mode field to track approach -->
    <input type="hidden" name="block_mode" id="block-mode-input" value="<?= e($blockMode) ?>">

    <!-- STEPPER INDICATORS: Step 1 always first, step 2/3 conditional -->
    <div class="stepper" id="stepper">
        <div class="step<?= $initialStep >= 1 ? ' is-active' : '' ?>" data-step="1">
            <div class="step-circle">1</div>
            <div class="step-label">Связка</div>
            <div class="step-sublabel">Выберите подход</div>
        </div>
        <div class="step<?= $showDriverStep ? ($initialStep == 2 ? ' is-active' : ($initialStep > 2 ? ' is-done' : '')) : ' is-hidden' ?>" data-step="2" id="step-indicator-2">
            <div class="step-circle">2</div>
            <div class="step-label">Водитель</div>
            <div class="step-sublabel">Выберите или создайте</div>
        </div>
        <div class="step<?= $showVehicleStep ? ($initialStep == 3 ? ' is-active' : ($initialStep > 3 ? ' is-done' : '')) : ' is-hidden' ?>" data-step="3" id="step-indicator-3">
            <div class="step-circle">3</div>
            <div class="step-label">Машина / Транспорт</div>
            <div class="step-sublabel">Выберите или создайте</div>
        </div>
        <div class="step<?= $initialStep >= 4 ? ' is-active' : '' ?>" data-step="4">
            <div class="step-circle"><?= $showDriverStep ? '4' : '2' ?></div>
            <div class="step-label">Проверка</div>
            <div class="step-sublabel">Подтверждение</div>
        </div>
    </div>

    <!-- ================================================================
    STEP 1: ВЫБОР ПОДХОДА
    ================================================================ -->
    <div class="step-body<?= $initialStep !== 1 ? ' is-hidden' : '' ?>" id="step-body-1">
        <div class="step-body-head">
            <span class="step-body-title">Шаг 1: Выберите подход</span>
        </div>
        <div class="step-body-content">

            <div class="field">
                <label class="field-label">Как вы хотите добавить экипаж?</label>
                <div class="form-grid-2">
                    <label class="btn btn-ghost mode-option<?= $blockMode === 'existing' ? ' is-active' : '' ?>" id="lbl-block-existing">
                        <input type="radio" name="block_mode_radio" value="existing" class="is-hidden" <?= $blockMode === 'existing' ? 'checked' : '' ?>>
                        Выбрать готовую связку
                    </label>
                    <label class="btn btn-ghost mode-option<?= $blockMode === 'new' ? ' is-active' : '' ?>" id="lbl-block-new">
                        <input type="radio" name="block_mode_radio" value="new" class="is-hidden" <?= $blockMode === 'new' ? 'checked' : '' ?>>
                        Собрать новую связку
                    </label>
                </div>
            </div>

            <!-- Existing block select -->
            <div id="block-existing-block"<?= $blockMode !== 'existing' ? ' class="is-hidden"' : '' ?>>
                <div class="field">
                    <label class="field-label">Связка Водитель+ТС <span class="req">*</span></label>
                    <select name="block_id" class="field-select<?= !empty($errors['block_id']) ? ' is-error' : '' ?>"<?= empty($driverVehicleBlocks) ? ' disabled' : '' ?>>
                        <option value="">— Выберите связку —</option>
                        <?php foreach ($driverVehicleBlocks as $dvb): ?>
                        <option value="<?= $dvb['id'] ?>" <?= ($old['block_id'] ?? '') == $dvb['id'] ? 'selected' : '' ?>>
                            <?= e($dvb['driver_name']) ?> — <?= e(ui_set_type($dvb['set_type'] ?? null)) ?> — <?= e($dvb['primary_plate'] ?? '—') ?><?= !empty($dvb['secondary_plate']) ? ' + ' . e($dvb['secondary_plate']) : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($driverVehicleBlocks)): ?>
                        <p class="field-hint">Нет доступных связок. Выберите «Собрать новую связку».</p>
                    <?php endif; ?>
                    <?php if (!empty($errors['block_id'])): ?>
                        <div class="field-msg is-error"><?= e($errors['block_id']) ?></div>
                    <?php endif; ?>
                    <div class="field-msg is-error is-hidden" id="err-js-block-select">Пожалуйста, выберите связку.</div>
                </div>
            </div>

            <!-- New build info -->
            <div id="block-new-block"<?= $blockMode !== 'new' ? ' class="is-hidden"' : '' ?>>
                <p class="text-muted">Вы сможете выбрать или создать водителя и транспорт на следующих шагах.</p>
            </div>

        </div>
        <div class="step-nav">
            <button type="button" class="btn btn-ghost step-back" disabled>← Назад</button>
            <button type="button" class="btn btn-primary step-next" data-next="<?= $blockMode === 'existing' ? '4' : '2' ?>">Далее →</button>
        </div>
    </div>

    <?php if ($showDriverStep): ?>
    <!-- ================================================================
    STEP 2: ВОДИТЕЛЬ
    ================================================================ -->
    <div class="step-body<?= $initialStep !== 2 ? ' is-hidden' : '' ?>" id="step-body-2">
        <div class="step-body-head">
            <span class="step-body-title">Шаг 2: Водитель</span>
        </div>
        <div class="step-body-content">

            <div class="field">
                <label class="field-label">Режим</label>
                <div class="form-grid-2">
                    <label class="btn btn-ghost mode-option<?= ($old['driver_mode'] ?? 'existing') === 'existing' ? ' is-active' : '' ?>" id="lbl-driver-existing">
                        <input type="radio" name="driver_mode" value="existing" class="is-hidden" <?= ($old['driver_mode'] ?? 'existing') === 'existing' ? 'checked' : '' ?>>
                        Выбрать существующего
                    </label>
                    <label class="btn btn-ghost mode-option<?= ($old['driver_mode'] ?? '') === 'new' ? ' is-active' : '' ?>" id="lbl-driver-new">
                        <input type="radio" name="driver_mode" value="new" class="is-hidden" <?= ($old['driver_mode'] ?? '') === 'new' ? 'checked' : '' ?>>
                        Создать нового
                    </label>
                </div>
            </div>

            <div id="driver-existing-block"<?= ($old['driver_mode'] ?? 'existing') !== 'existing' ? ' class="is-hidden"' : '' ?>>
                <div class="field">
                    <label class="field-label">Водитель <span class="req">*</span></label>
                    <select name="driver_id" class="field-select<?= !empty($errors['driver_id']) ? ' is-error' : '' ?>"<?= empty($drivers) ? ' disabled' : '' ?>>
                        <option value="">— Выберите водителя —</option>
                        <?php foreach ($drivers as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= ($old['driver_id'] ?? '') == $d['id'] ? 'selected' : '' ?>>
                            <?= e($d['full_name']) ?> (<?= e($d['phone'] ?? '—') ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($drivers)): ?>
                        <p class="field-hint">Нет доступных водителей. <a href="<?= app_url('/company/drivers/create') ?>">Создать водителя</a></p>
                    <?php endif; ?>
                    <?php if (!empty($errors['driver_id'])): ?>
                        <div class="field-msg is-error"><?= e($errors['driver_id']) ?></div>
                    <?php endif; ?>
                    <div class="field-msg is-error is-hidden" id="err-js-driver-select">Пожалуйста, выберите водителя.</div>
                </div>
            </div>

            <div id="driver-new-block"<?= ($old['driver_mode'] ?? 'existing') === 'existing' ? ' class="is-hidden"' : '' ?>>
                <div class="field">
                    <label class="field-label">ФИО <span class="req">*</span></label>
                    <input type="text" name="driver_full_name" class="field-input<?= !empty($errors['driver_full_name']) ? ' is-error' : '' ?>"
                           value="<?= e($old['driver_full_name'] ?? '') ?>">
                    <?php if (!empty($errors['driver_full_name'])): ?>
                        <div class="field-msg is-error"><?= e($errors['driver_full_name']) ?></div>
                    <?php endif; ?>
                    <div class="field-msg is-error is-hidden" id="err-js-driver-name">Пожалуйста, укажите ФИО водителя.</div>
                </div>
                <div class="field">
                    <label class="field-label">Телефон</label>
                    <input type="text" name="driver_phone" class="field-input"
                           value="<?= e($old['driver_phone'] ?? '') ?>">
                </div>
            </div>

        </div>
        <div class="step-nav">
            <button type="button" class="btn btn-ghost step-back" data-prev="1">← Назад</button>
            <button type="button" class="btn btn-primary step-next" data-next="3">Далее →</button>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($showVehicleStep): ?>
    <!-- ================================================================
    STEP 3: МАШИНА / ТРАНСПОРТ
    ================================================================ -->
    <div class="step-body<?= $initialStep !== 3 ? ' is-hidden' : '' ?>" id="step-body-3">
        <div class="step-body-head">
            <span class="step-body-title">Шаг 3: Машина / Транспорт</span>
        </div>
        <div class="step-body-content">

            <div class="field">
                <label class="field-label">Режим</label>
                <div class="form-grid-2">
                    <label class="btn btn-ghost mode-option<?= ($old['vehicle_mode'] ?? 'existing') === 'existing' ? ' is-active' : '' ?>" id="lbl-vehicle-existing">
                        <input type="radio" name="vehicle_mode" value="existing" class="is-hidden" <?= ($old['vehicle_mode'] ?? 'existing') === 'existing' ? 'checked' : '' ?>>
                        Выбрать существующий
                    </label>
                    <label class="btn btn-ghost mode-option<?= ($old['vehicle_mode'] ?? '') === 'new' ? ' is-active' : '' ?>" id="lbl-vehicle-new">
                        <input type="radio" name="vehicle_mode" value="new" class="is-hidden" <?= ($old['vehicle_mode'] ?? '') === 'new' ? 'checked' : '' ?>>
                        Создать новый
                    </label>
                </div>
            </div>

            <div id="vehicle-existing-block"<?= ($old['vehicle_mode'] ?? 'existing') !== 'existing' ? ' class="is-hidden"' : '' ?>>
                <div class="field">
                    <label class="field-label">Транспорт <span class="req">*</span></label>
                    <select name="vehicle_set_id" class="field-select<?= !empty($errors['vehicle_set_id']) ? ' is-error' : '' ?>"<?= empty($vehicleSets) ? ' disabled' : '' ?>>
                        <option value="">— Выберите транспорт —</option>
                        <?php foreach ($vehicleSets as $vs): ?>
                        <option value="<?= $vs['id'] ?>" <?= ($old['vehicle_set_id'] ?? '') == $vs['id'] ? 'selected' : '' ?>>
                            <?= e(ui_set_type($vs['set_type'] ?? null)) ?> — <?= e($vs['primary_plate'] ?? '—') ?><?= !empty($vs['secondary_plate']) ? ' + ' . e($vs['secondary_plate']) : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($vehicleSets)): ?>
                        <p class="field-hint">Нет доступного транспорта. <a href="<?= app_url('/company/vehicle-sets/create') ?>">Создать транспорт</a></p>
                    <?php endif; ?>
                    <?php if (!empty($errors['vehicle_set_id'])): ?>
                        <div class="field-msg is-error"><?= e($errors['vehicle_set_id']) ?></div>
                    <?php endif; ?>
                    <div class="field-msg is-error is-hidden" id="err-js-vehicle-select">Пожалуйста, выберите транспорт.</div>
                </div>
            </div>

            <div id="vehicle-new-block"<?= ($old['vehicle_mode'] ?? 'existing') === 'existing' ? ' class="is-hidden"' : '' ?>>
                <div class="field">
                    <label class="field-label">Госномер <span class="req">*</span></label>
                    <input type="text" name="plate_number" class="field-input<?= !empty($errors['plate_number']) ? ' is-error' : '' ?>"
                           value="<?= e($old['plate_number'] ?? '') ?>"
                           placeholder="А999АА99">
                    <?php if (!empty($errors['plate_number'])): ?>
                        <div class="field-msg is-error"><?= e($errors['plate_number']) ?></div>
                    <?php endif; ?>
                    <div class="field-msg is-error is-hidden" id="err-js-vehicle-plate">Пожалуйста, укажите госномер.</div>
                </div>
                <div class="form-grid-2">
                    <div class="field">
                        <label class="field-label">Марка</label>
                        <input type="text" name="brand" class="field-input"
                               value="<?= e($old['brand'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label class="field-label">Модель</label>
                        <input type="text" name="model" class="field-input"
                               value="<?= e($old['model'] ?? '') ?>">
                    </div>
                </div>
                <div class="field">
                    <label class="field-label">Тип транспорта</label>
                    <select name="set_type" class="field-input" id="set_type_select">
                        <option value="single" <?= ($old['set_type'] ?? 'single') === 'single' ? 'selected' : '' ?>>Одиночка</option>
                        <option value="coupling" <?= ($old['set_type'] ?? '') === 'coupling' ? 'selected' : '' ?>>Сцепка</option>
                        <option value="road_train" <?= ($old['set_type'] ?? '') === 'road_train' ? 'selected' : '' ?>>Автопоезд</option>
                    </select>
                </div>
                <div class="field is-hidden" id="secondary_field">
                    <label class="field-label">Доп. госномер <span class="req">*</span></label>
                    <input type="text" name="secondary_plate_number" class="field-input<?= !empty($errors['secondary_plate_number']) ? ' is-error' : '' ?>"
                           value="<?= e($old['secondary_plate_number'] ?? '') ?>"
                           placeholder="А999АА99">
                    <?php if (!empty($errors['secondary_plate_number'])): ?>
                        <div class="field-msg is-error"><?= e($errors['secondary_plate_number']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
        <div class="step-nav">
            <button type="button" class="btn btn-ghost step-back" data-prev="2">← Назад</button>
            <button type="button" class="btn btn-primary step-next" data-next="4">Далее →</button>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================
    STEP 4: ПРОВЕРКА
    ================================================================ -->
    <div class="step-body<?= $initialStep !== 4 ? ' is-hidden' : '' ?>" id="step-body-4">
        <div class="step-body-head">
            <span class="step-body-title">Шаг <?= $showDriverStep ? '4' : '2' ?>: Проверка и создание</span>
        </div>
        <div class="step-body-content">

            <div class="kv">
                <div class="kv-row">
                    <span class="kv-key">Перевозчик</span>
                    <span class="kv-value" id="review-contractor"><?= e($contractor['name']) ?> (ИНН <?= e($contractor['inn'] ?? '—') ?>)</span>
                </div>
                <?php if ($blockMode === 'existing'): ?>
                <div class="kv-row">
                    <span class="kv-key">Связка Водитель+ТС</span>
                    <span class="kv-value" id="review-block"><?= e($reviewBlock ?: '—') ?></span>
                </div>
                <?php else: ?>
                <div class="kv-row">
                    <span class="kv-key">Водитель</span>
                    <span class="kv-value" id="review-driver"><?= e($reviewDriver ?: '—') ?></span>
                </div>
                <div class="kv-row">
                    <span class="kv-key">Транспорт</span>
                    <span class="kv-value" id="review-vehicle"><?= e($reviewVehicle ?: '—') ?></span>
                </div>
                <?php endif; ?>
            </div>

        </div>
        <div class="step-nav">
            <button type="button" class="btn btn-ghost step-back" data-prev="<?= $showDriverStep ? '3' : '1' ?>">← Назад</button>
            <button type="submit" class="btn btn-primary">Создать экипаж</button>
        </div>
    </div>

</form>

<script>
(function() {
    var stepper = document.getElementById('stepper');
    var blockModeInput = document.getElementById('block-mode-input');
    var currentStep = <?= $initialStep ?>;
    var showDriverStep = <?= $showDriverStep ? 'true' : 'false' ?>;
    var showVehicleStep = <?= $showVehicleStep ? 'true' : 'false' ?>;

    // Step bodies: [step1, step2, step3, step4] (step2/3 may be null)
    var allBodies = [
        document.getElementById('step-body-1'),
        document.getElementById('step-body-2'),
        document.getElementById('step-body-3'),
        document.getElementById('step-body-4')
    ];

    // Map logical step to body index
    function bodyIndex(logicalStep) {
        if (logicalStep === 1) return 0;
        if (logicalStep === 2) return showDriverStep ? 1 : -1;
        if (logicalStep === 3) return showVehicleStep ? 2 : -1;
        if (logicalStep === 4) return 3;
        return -1;
    }

    function updateStepper(stepNum) {
        currentStep = stepNum;
        var steps = stepper.querySelectorAll('.step');
        for (var i = 0; i < steps.length; i++) {
            var s = steps[i];
            var sNum = parseInt(s.getAttribute('data-step'));
            s.classList.remove('is-active', 'is-done');
            if (sNum < stepNum) {
                s.classList.add('is-done');
            } else if (sNum === stepNum) {
                s.classList.add('is-active');
            }
        }
        // Show/hide step bodies
        for (var j = 0; j < allBodies.length; j++) {
            if (!allBodies[j]) continue;
            var bStep = j + 1;
            // Determine if this body's step is the current one
            var effectiveStep = bStep;
            if (!showDriverStep && bStep >= 2) effectiveStep++;
            if (!showVehicleStep && bStep >= 3) effectiveStep++;
            // Simple approach: show the body whose logical step matches
            var bodyLogicalStep = (j === 0) ? 1 : (j === 1 && showDriverStep) ? 2 : (j === 2 && showVehicleStep) ? 3 : (j === 3) ? 4 : -1;
            if (bodyLogicalStep === stepNum) {
                allBodies[j].classList.remove('is-hidden');
            } else if (bodyLogicalStep !== -1) {
                allBodies[j].classList.add('is-hidden');
            }
        }
        if (stepNum === 4) {
            refreshReview();
        }
    }

    function updateApproach(isExisting) {
        showDriverStep = !isExisting;
        showVehicleStep = !isExisting;
        blockModeInput.value = isExisting ? 'existing' : 'new';

        // Show/hide step indicators 2 and 3
        var stepInd2 = document.getElementById('step-indicator-2');
        var stepInd3 = document.getElementById('step-indicator-3');
        var stepInd4 = stepper.querySelector('[data-step="4"]');
        if (stepInd2) {
            if (isExisting) stepInd2.classList.add('is-hidden');
            else stepInd2.classList.remove('is-hidden');
        }
        if (stepInd3) {
            if (isExisting) stepInd3.classList.add('is-hidden');
            else stepInd3.classList.remove('is-hidden');
        }
        // Update step 4 circle number
        if (stepInd4) {
            var circle = stepInd4.querySelector('.step-circle');
            if (circle) circle.textContent = isExisting ? '2' : '4';
        }

        // Update "Next" button on step 1
        var nextBtn = document.querySelector('#step-body-1 .step-next');
        if (nextBtn) {
            nextBtn.setAttribute('data-next', isExisting ? '4' : '2');
        }

        // Update back button on step 4
        var backBtn = document.querySelector('#step-body-4 .step-back');
        if (backBtn) {
            backBtn.setAttribute('data-prev', isExisting ? '1' : '3');
        }
    }

    function refreshReview() {
        var isExisting = blockModeInput.value === 'existing';

        if (isExisting) {
            // Refresh from block select
            var reviewBlock = document.getElementById('review-block');
            var blockSelect = document.querySelector('select[name="block_id"]');
            if (reviewBlock && blockSelect && blockSelect.selectedIndex > 0) {
                reviewBlock.textContent = blockSelect.options[blockSelect.selectedIndex].textContent;
            } else if (reviewBlock) {
                reviewBlock.textContent = '—';
            }
        } else {
            var reviewDriver = document.getElementById('review-driver');
            var reviewVehicle = document.getElementById('review-vehicle');

            // --- Driver ---
            if (reviewDriver) {
                var driverMode = document.querySelector('input[name="driver_mode"]:checked');
                if (driverMode && driverMode.value === 'existing') {
                    var driverSelect = document.querySelector('select[name="driver_id"]');
                    if (driverSelect && driverSelect.selectedIndex > 0) {
                        reviewDriver.textContent = driverSelect.options[driverSelect.selectedIndex].textContent;
                    } else {
                        reviewDriver.textContent = '—';
                    }
                } else {
                    var fullNameInput = document.querySelector('input[name="driver_full_name"]');
                    var phoneInput = document.querySelector('input[name="driver_phone"]');
                    if (fullNameInput && fullNameInput.value.trim() !== '') {
                        var drvText = fullNameInput.value.trim();
                        if (phoneInput && phoneInput.value.trim() !== '') {
                            drvText += ' (' + phoneInput.value.trim() + ')';
                        }
                        reviewDriver.textContent = drvText;
                    } else {
                        reviewDriver.textContent = '—';
                    }
                }
            }

            // --- Vehicle ---
            if (reviewVehicle) {
                var vehicleMode = document.querySelector('input[name="vehicle_mode"]:checked');
                if (vehicleMode && vehicleMode.value === 'existing') {
                    var vehicleSelect = document.querySelector('select[name="vehicle_set_id"]');
                    if (vehicleSelect && vehicleSelect.selectedIndex > 0) {
                        reviewVehicle.textContent = vehicleSelect.options[vehicleSelect.selectedIndex].textContent;
                    } else {
                        reviewVehicle.textContent = '—';
                    }
                } else {
                    var plateInput = document.querySelector('input[name="plate_number"]');
                    var brandInput = document.querySelector('input[name="brand"]');
                    var modelInput = document.querySelector('input[name="model"]');
                    var setTypeSelect = document.querySelector('select[name="set_type"]');
                    var secPlateInput = document.querySelector('input[name="secondary_plate_number"]');
                    if (plateInput && plateInput.value.trim() !== '') {
                        var vehText = plateInput.value.trim();
                        if (brandInput && brandInput.value.trim() !== '') vehText += ' ' + brandInput.value.trim();
                        if (modelInput && modelInput.value.trim() !== '') vehText += ' ' + modelInput.value.trim();
                        var typeLabel = setTypeSelect ? setTypeSelect.options[setTypeSelect.selectedIndex].textContent : '';
                        vehText += ' (' + typeLabel + ')';
                        if (secPlateInput && secPlateInput.value.trim() !== '') {
                            vehText += ' + ' + secPlateInput.value.trim();
                        }
                        reviewVehicle.textContent = vehText;
                    } else {
                        reviewVehicle.textContent = '—';
                    }
                }
            }
        }
    }

    // --- Approach mode toggle ---
    var blockModeRadios = document.querySelectorAll('input[name="block_mode_radio"]');
    var blockExistingBlock = document.getElementById('block-existing-block');
    var blockNewBlock = document.getElementById('block-new-block');
    var lblBlockExisting = document.getElementById('lbl-block-existing');
    var lblBlockNew = document.getElementById('lbl-block-new');

    for (var r = 0; r < blockModeRadios.length; r++) {
        blockModeRadios[r].addEventListener('change', function() {
            var isExisting = this.value === 'existing';
            updateApproach(isExisting);
            if (blockExistingBlock) {
                if (isExisting) blockExistingBlock.classList.remove('is-hidden');
                else blockExistingBlock.classList.add('is-hidden');
            }
            if (blockNewBlock) {
                if (isExisting) blockNewBlock.classList.add('is-hidden');
                else blockNewBlock.classList.remove('is-hidden');
            }
            if (lblBlockExisting) {
                if (isExisting) lblBlockExisting.classList.add('is-active');
                else lblBlockExisting.classList.remove('is-active');
            }
            if (lblBlockNew) {
                if (isExisting) lblBlockNew.classList.remove('is-active');
                else lblBlockNew.classList.add('is-active');
            }
        });
    }

    // --- Mode toggle handlers for driver/vehicle ---
    function setupModeToggle(radioName, existingBlockId, newBlockId, existingLabelId, newLabelId) {
        var radios = document.querySelectorAll('input[name="' + radioName + '"]');
        if (radios.length === 0) return;
        var existingBlock = document.getElementById(existingBlockId);
        var newBlock = document.getElementById(newBlockId);
        var existingLbl = document.getElementById(existingLabelId);
        var newLbl = document.getElementById(newLabelId);
        for (var r2 = 0; r2 < radios.length; r2++) {
            radios[r2].addEventListener('change', function() {
                var isExisting = this.value === 'existing';
                if (existingBlock) {
                    if (isExisting) existingBlock.classList.remove('is-hidden');
                    else existingBlock.classList.add('is-hidden');
                }
                if (newBlock) {
                    if (isExisting) newBlock.classList.add('is-hidden');
                    else newBlock.classList.remove('is-hidden');
                }
                if (existingLbl) {
                    if (isExisting) existingLbl.classList.add('is-active');
                    else existingLbl.classList.remove('is-active');
                }
                if (newLbl) {
                    if (isExisting) newLbl.classList.remove('is-active');
                    else newLbl.classList.add('is-active');
                }
            });
        }
    }

    setupModeToggle('driver_mode', 'driver-existing-block', 'driver-new-block', 'lbl-driver-existing', 'lbl-driver-new');
    setupModeToggle('vehicle_mode', 'vehicle-existing-block', 'vehicle-new-block', 'lbl-vehicle-existing', 'lbl-vehicle-new');

    // --- Set type change ---
    var setType = document.getElementById('set_type_select');
    var secondary = document.getElementById('secondary_field');
    if (setType && secondary) {
        function updateSecondary() {
            var v = setType.value;
            var show = (v === 'coupling' || v === 'road_train');
            if (show) secondary.classList.remove('is-hidden');
            else secondary.classList.add('is-hidden');
            var input = secondary.querySelector('input');
            if (input) input.required = show;
        }
        setType.addEventListener('change', updateSecondary);
        updateSecondary();
    }

    // --- Next button handlers ---
    var nextButtons = document.querySelectorAll('.step-next');
    for (var n = 0; n < nextButtons.length; n++) {
        nextButtons[n].addEventListener('click', function(e) {
            var nextStepAttr = this.getAttribute('data-next');
            if (!nextStepAttr) return;
            var nextStep = parseInt(nextStepAttr);

            // Validate current step
            if (currentStep === 1) {
                var isExisting = blockModeInput.value === 'existing';
                if (isExisting) {
                    var sel = document.querySelector('select[name="block_id"]');
                    var errEl = document.getElementById('err-js-block-select');
                    if (!sel || sel.value === '') {
                        if (errEl) errEl.classList.remove('is-hidden');
                        if (sel) sel.classList.add('is-error');
                        return;
                    }
                    if (errEl) errEl.classList.add('is-hidden');
                    if (sel) sel.classList.remove('is-error');
                }
                // If new mode, just proceed to step 2 (no validation needed here)
            }

            if (currentStep === 2) {
                var driverMode = document.querySelector('input[name="driver_mode"]:checked');
                if (driverMode && driverMode.value === 'existing') {
                    var sel = document.querySelector('select[name="driver_id"]');
                    var errEl = document.getElementById('err-js-driver-select');
                    if (!sel || sel.value === '') {
                        if (errEl) errEl.classList.remove('is-hidden');
                        if (sel) sel.classList.add('is-error');
                        return;
                    }
                    if (errEl) errEl.classList.add('is-hidden');
                    if (sel) sel.classList.remove('is-error');
                } else {
                    var fnInput = document.querySelector('input[name="driver_full_name"]');
                    var errEl = document.getElementById('err-js-driver-name');
                    if (!fnInput || fnInput.value.trim() === '') {
                        if (errEl) errEl.classList.remove('is-hidden');
                        if (fnInput) fnInput.classList.add('is-error');
                        return;
                    }
                    if (errEl) errEl.classList.add('is-hidden');
                    if (fnInput) fnInput.classList.remove('is-error');
                }
            }

            if (currentStep === 3) {
                var vehicleMode = document.querySelector('input[name="vehicle_mode"]:checked');
                if (vehicleMode && vehicleMode.value === 'existing') {
                    var sel = document.querySelector('select[name="vehicle_set_id"]');
                    var errEl = document.getElementById('err-js-vehicle-select');
                    if (!sel || sel.value === '') {
                        if (errEl) errEl.classList.remove('is-hidden');
                        if (sel) sel.classList.add('is-error');
                        return;
                    }
                    if (errEl) errEl.classList.add('is-hidden');
                    if (sel) sel.classList.remove('is-error');
                } else {
                    var plateInput = document.querySelector('input[name="plate_number"]');
                    var errEl = document.getElementById('err-js-vehicle-plate');
                    if (!plateInput || plateInput.value.trim() === '') {
                        if (errEl) errEl.classList.remove('is-hidden');
                        if (plateInput) plateInput.classList.add('is-error');
                        return;
                    }
                    if (errEl) errEl.classList.add('is-hidden');
                    if (plateInput) plateInput.classList.remove('is-error');
                }
            }

            updateStepper(nextStep);
        });
    }

    // --- Back button handlers ---
    var backButtons = document.querySelectorAll('.step-back');
    for (var b = 0; b < backButtons.length; b++) {
        backButtons[b].addEventListener('click', function(e) {
            var prevStepAttr = this.getAttribute('data-prev');
            if (!prevStepAttr) return;
            var prevStep = parseInt(prevStepAttr);
            updateStepper(prevStep);
        });
    }

    // Initial review
    if (currentStep === 4) {
        refreshReview();
    }

    // Clear js errors
    var jsErrFields = [
        {trigger: 'select[name="block_id"]', err: 'err-js-block-select'},
        {trigger: 'select[name="driver_id"]', err: 'err-js-driver-select'},
        {trigger: 'input[name="driver_full_name"]', err: 'err-js-driver-name'},
        {trigger: 'select[name="vehicle_set_id"]', err: 'err-js-vehicle-select'},
        {trigger: 'input[name="plate_number"]', err: 'err-js-vehicle-plate'},
    ];
    for (var f = 0; f < jsErrFields.length; f++) {
        var el = document.querySelector(jsErrFields[f].trigger);
        if (el) {
            el.addEventListener('change', (function(errId, field) {
                return function() {
                    var err = document.getElementById(errId);
                    if (err) err.classList.add('is-hidden');
                    field.classList.remove('is-error');
                };
            })(jsErrFields[f].err, el));
            el.addEventListener('input', (function(errId, field) {
                return function() {
                    var err = document.getElementById(errId);
                    if (err) err.classList.add('is-hidden');
                    field.classList.remove('is-error');
                };
            })(jsErrFields[f].err, el));
        }
    }

    var modeRadios = document.querySelectorAll('input[name="block_mode_radio"], input[name="driver_mode"], input[name="vehicle_mode"]');
    for (var m = 0; m < modeRadios.length; m++) {
        modeRadios[m].addEventListener('change', function() {
            var allErrs = document.querySelectorAll('.field-msg.is-error[id^="err-js-"]');
            for (var e = 0; e < allErrs.length; e++) {
                allErrs[e].classList.add('is-hidden');
            }
            var allInputs = document.querySelectorAll('.field-input.is-error, .field-select.is-error');
            for (var i = 0; i < allInputs.length; i++) {
                allInputs[i].classList.remove('is-error');
            }
        });
    }
})();
</script>

<?php endif; ?>
