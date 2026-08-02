<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Создать перевозчика + Водителя + Транспорт</h1>
        <div class="page-summary"><span>Компания: <?= e($company['name']) ?></span></div>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/company/contractors') ?>" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Создание недоступно.
</div>

<?php elseif ($success): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Созданы перевозчик, водитель и транспорт</h1>
        <div class="page-summary"><span>Компания: <?= e($company['name']) ?></span></div>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/company/contractors') ?>" class="btn btn-primary">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Перевозчик, водитель и транспорт успешно созданы.
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
                            <span class="cell-main"><?= e($createdContractor['name']) ?></span>
                            <span class="cell-sub">ИНН <?= e($createdContractor['inn']) ?></span>
                        </td>
                        <td class="col-actions">
                            <a href="/company/contractors/<?= $createdContractor['id'] ?>" class="btn btn-toolbar">Просмотр</a>
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
                            <?= e($createdContractor['name']) ?> — <?= e($createdDriver['full_name']) ?> + <?= e($createdVehicleSet['primary_plate']) ?>
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
            <a href="<?= app_url('/company/contractors') ?>" class="btn btn-primary">← К списку перевозчиков</a>
            <a href="<?= app_url('/company/contractors/create-full') ?>" class="btn btn-ghost">Создать ещё</a>
        </div>
    </div>
</div>

<?php else: ?>

<?php
// Determine initial step based on validation errors
$initialStep = 1;
if (!empty($old)) {
    if (!empty($errors['contractor_id']) || !empty($errors['name']) || !empty($errors['inn']) || !empty($errors['kpp'])) {
        $initialStep = 1;
    } elseif (!empty($errors['driver_id']) || !empty($errors['driver_full_name'])) {
        $initialStep = 2;
    } elseif (!empty($errors['vehicle_set_id']) || !empty($errors['plate_number']) || !empty($errors['secondary_plate_number'])) {
        $initialStep = 3;
    } else {
        $initialStep = 4;
    }
}

// Pre-compute review display data
$reviewContractor = '';
if (!empty($old['contractor_mode']) && $old['contractor_mode'] === 'existing' && !empty($old['contractor_id'])) {
    foreach ($contractors as $c) {
        if ($c['id'] == $old['contractor_id']) {
            $reviewContractor = $c['name'] . ' (ИНН ' . ($c['inn'] ?? '—') . ')';
            break;
        }
    }
} elseif (!empty($old['name'])) {
    $reviewContractor = $old['name'] . ' (ИНН ' . ($old['inn'] ?? '—') . ')';
}

$reviewDriver = '';
if (!empty($old['driver_mode']) && $old['driver_mode'] === 'existing' && !empty($old['driver_id'])) {
    foreach ($drivers as $d) {
        if ($d['id'] == $old['driver_id']) {
            $reviewDriver = $d['full_name'] . ' (' . ($d['phone'] ?? '') . ')';
            break;
        }
    }
} elseif (!empty($old['driver_full_name'])) {
    $reviewDriver = $old['driver_full_name'] . (!empty($old['driver_phone']) ? ' (' . $old['driver_phone'] . ')' : '');
}

$reviewVehicle = '';
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
?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Создать перевозчика + Водителя + Транспорт</h1>
        <div class="page-summary"><span>Компания: <?= e($company['name']) ?></span></div>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/company/contractors') ?>" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<?php if ($formError): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="<?= app_url('/company/contractors/create-full') ?>">

    <!-- STEPPER INDICATORS -->
    <div class="stepper" id="stepper">
        <div class="step<?= $initialStep >= 1 ? ' is-active' : '' ?>" data-step="1">
            <div class="step-circle">1</div>
            <div class="step-label">Перевозчик</div>
            <div class="step-sublabel">Выберите или создайте</div>
        </div>
        <div class="step<?= $initialStep == 2 ? ' is-active' : ($initialStep > 2 ? ' is-done' : '') ?>" data-step="2">
            <div class="step-circle">2</div>
            <div class="step-label">Водитель</div>
            <div class="step-sublabel">Выберите или создайте</div>
        </div>
        <div class="step<?= $initialStep == 3 ? ' is-active' : ($initialStep > 3 ? ' is-done' : '') ?>" data-step="3">
            <div class="step-circle">3</div>
            <div class="step-label">Машина / Транспорт</div>
            <div class="step-sublabel">Выберите или создайте</div>
        </div>
        <div class="step<?= $initialStep >= 4 ? ' is-active' : '' ?>" data-step="4">
            <div class="step-circle">4</div>
            <div class="step-label">Проверка</div>
            <div class="step-sublabel">Подтверждение</div>
        </div>
    </div>

    <!-- ================================================================
    STEP 1: ПЕРЕВОЗЧИК
    ================================================================ -->
    <div class="step-body<?= $initialStep !== 1 ? ' is-hidden' : '' ?>" id="step-body-1">
        <div class="step-body-head">
            <span class="step-body-title">Шаг 1: Перевозчик</span>
        </div>
        <div class="step-body-content">

            <div class="field">
                <label class="field-label">Режим</label>
                <div class="form-grid-2">
                    <label class="btn btn-ghost mode-option<?= ($old['contractor_mode'] ?? 'existing') === 'existing' ? ' is-active' : '' ?>" id="lbl-contractor-existing">
                        <input type="radio" name="contractor_mode" value="existing" class="is-hidden" <?= ($old['contractor_mode'] ?? 'existing') === 'existing' ? 'checked' : '' ?>>
                        Выбрать существующего
                    </label>
                    <label class="btn btn-ghost mode-option<?= ($old['contractor_mode'] ?? '') === 'new' ? ' is-active' : '' ?>" id="lbl-contractor-new">
                        <input type="radio" name="contractor_mode" value="new" class="is-hidden" <?= ($old['contractor_mode'] ?? '') === 'new' ? 'checked' : '' ?>>
                        Создать нового
                    </label>
                </div>
            </div>

            <!-- Existing contractor select -->
            <div id="contractor-existing-block"<?= ($old['contractor_mode'] ?? 'existing') !== 'existing' ? ' class="is-hidden"' : '' ?>>
                <div class="field">
                    <label class="field-label">Перевозчик <span class="req">*</span></label>
                    <select name="contractor_id" class="field-select<?= !empty($errors['contractor_id']) ? ' is-error' : '' ?>"<?= empty($contractors) ? ' disabled' : '' ?>>
                        <option value="">— Выберите перевозчика —</option>
                        <?php foreach ($contractors as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($old['contractor_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?> (ИНН <?= e($c['inn'] ?? '—') ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($contractors)): ?>
                        <p class="field-hint">Нет доступных перевозчиков</p>
                    <?php endif; ?>
                    <?php if (!empty($errors['contractor_id'])): ?>
                        <div class="field-msg is-error"><?= e($errors['contractor_id']) ?></div>
                    <?php endif; ?>
                    <div class="field-msg is-error is-hidden" id="err-js-contractor-select">Пожалуйста, выберите перевозчика.</div>
                </div>
            </div>

            <!-- New contractor form -->
            <div id="contractor-new-block"<?= ($old['contractor_mode'] ?? 'existing') === 'existing' ? ' class="is-hidden"' : '' ?>>
                <div class="field">
                    <label class="field-label">Наименование <span class="req">*</span></label>
                    <input type="text" name="name" class="field-input<?= !empty($errors['name']) ? ' is-error' : '' ?>"
                           value="<?= e($old['name'] ?? '') ?>">
                    <?php if (!empty($errors['name'])): ?>
                        <div class="field-msg is-error"><?= e($errors['name']) ?></div>
                    <?php endif; ?>
                    <div class="field-msg is-error is-hidden" id="err-js-contractor-name">Пожалуйста, укажите наименование перевозчика.</div>
                </div>

                <div class="form-grid-2">
                    <div class="field">
                        <label class="field-label">ИНН <span class="req">*</span></label>
                        <input type="text" name="inn" class="field-input<?= !empty($errors['inn']) ? ' is-error' : '' ?>"
                               value="<?= e($old['inn'] ?? '') ?>">
                        <?php if (!empty($errors['inn'])): ?>
                            <div class="field-msg is-error"><?= e($errors['inn']) ?></div>
                        <?php endif; ?>
                        <div class="field-msg is-error is-hidden" id="err-js-contractor-inn">Пожалуйста, укажите ИНН перевозчика.</div>
                    </div>

                    <div class="field">
                        <label class="field-label">КПП</label>
                        <input type="text" name="kpp" class="field-input"
                               value="<?= e($old['kpp'] ?? '') ?>">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label">Телефон</label>
                    <input type="text" name="phone" class="field-input"
                           value="<?= e($old['phone'] ?? '') ?>">
                </div>
            </div>

        </div>
        <div class="step-nav">
            <button type="button" class="btn btn-ghost step-back" disabled>← Назад</button>
            <button type="button" class="btn btn-primary step-next" data-next="2">Далее →</button>
        </div>
    </div>

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

            <!-- Existing driver select -->
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

            <!-- New driver form -->
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

            <!-- Existing vehicle set select -->
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

            <!-- New vehicle form -->
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

    <!-- ================================================================
    STEP 4: ПРОВЕРКА
    ================================================================ -->
    <div class="step-body<?= $initialStep !== 4 ? ' is-hidden' : '' ?>" id="step-body-4">
        <div class="step-body-head">
            <span class="step-body-title">Шаг 4: Проверка и создание</span>
        </div>
        <div class="step-body-content">

            <div class="kv">
                <div class="kv-row">
                    <span class="kv-key">Перевозчик</span>
                    <span class="kv-value" id="review-contractor"><?= e($reviewContractor ?: '—') ?></span>
                </div>
                <div class="kv-row">
                    <span class="kv-key">Водитель</span>
                    <span class="kv-value" id="review-driver"><?= e($reviewDriver ?: '—') ?></span>
                </div>
                <div class="kv-row">
                    <span class="kv-key">Транспорт</span>
                    <span class="kv-value" id="review-vehicle"><?= e($reviewVehicle ?: '—') ?></span>
                </div>
            </div>

        </div>
        <div class="step-nav">
            <button type="button" class="btn btn-ghost step-back" data-prev="3">← Назад</button>
            <button type="submit" class="btn btn-primary">Создать связку</button>
        </div>
    </div>

</form>

<script>
(function() {
    var stepper = document.getElementById('stepper');
    var steps = stepper.querySelectorAll('.step');
    var bodies = [
        document.getElementById('step-body-1'),
        document.getElementById('step-body-2'),
        document.getElementById('step-body-3'),
        document.getElementById('step-body-4')
    ];
    var currentStep = <?= $initialStep ?>;

    function updateStepper(stepNum) {
        currentStep = stepNum;
        // Update step indicators
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
        for (var j = 0; j < bodies.length; j++) {
            if (j + 1 === stepNum) {
                bodies[j].classList.remove('is-hidden');
            } else {
                bodies[j].classList.add('is-hidden');
            }
        }
        // If navigating to step 4, refresh review data
        if (stepNum === 4) {
            refreshReview();
        }
    }

    function refreshReview() {
        var reviewContractor = document.getElementById('review-contractor');
        var reviewDriver = document.getElementById('review-driver');
        var reviewVehicle = document.getElementById('review-vehicle');

        // --- Contractor ---
        var contractorMode = document.querySelector('input[name="contractor_mode"]:checked');
        if (contractorMode && contractorMode.value === 'existing') {
            var contractorSelect = document.querySelector('select[name="contractor_id"]');
            if (contractorSelect && contractorSelect.selectedIndex > 0) {
                reviewContractor.textContent = contractorSelect.options[contractorSelect.selectedIndex].textContent;
            } else {
                reviewContractor.textContent = '—';
            }
        } else {
            var nameInput = document.querySelector('input[name="name"]');
            var innInput = document.querySelector('input[name="inn"]');
            if (nameInput && nameInput.value.trim() !== '') {
                reviewContractor.textContent = nameInput.value.trim() + ' (ИНН ' + (innInput ? innInput.value.trim() || '—' : '—') + ')';
            } else {
                reviewContractor.textContent = '—';
            }
        }

        // --- Driver ---
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

        // --- Vehicle ---
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

    // --- Mode toggle handlers ---
    function setupModeToggle(radioName, existingBlockId, newBlockId, existingLabelId, newLabelId) {
        var radios = document.querySelectorAll('input[name="' + radioName + '"]');
        var existingBlock = document.getElementById(existingBlockId);
        var newBlock = document.getElementById(newBlockId);
        var existingLbl = document.getElementById(existingLabelId);
        var newLbl = document.getElementById(newLabelId);

        for (var r = 0; r < radios.length; r++) {
            radios[r].addEventListener('change', function() {
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

    setupModeToggle('contractor_mode', 'contractor-existing-block', 'contractor-new-block', 'lbl-contractor-existing', 'lbl-contractor-new');
    setupModeToggle('driver_mode', 'driver-existing-block', 'driver-new-block', 'lbl-driver-existing', 'lbl-driver-new');
    setupModeToggle('vehicle_mode', 'vehicle-existing-block', 'vehicle-new-block', 'lbl-vehicle-existing', 'lbl-vehicle-new');

    // --- Set type change: show/hide secondary plate ---
    var setType = document.getElementById('set_type_select');
    var secondary = document.getElementById('secondary_field');
    if (setType && secondary) {
        function updateSecondary() {
            var v = setType.value;
            var show = (v === 'coupling' || v === 'road_train');
            if (show) secondary.classList.remove('is-hidden');
            else secondary.classList.add('is-hidden');
            var reqSpan = secondary.querySelector('.req');
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
            var nextStep = parseInt(this.getAttribute('data-next'));

            // Validate current step before moving forward
            if (currentStep === 1) {
                var contractorMode = document.querySelector('input[name="contractor_mode"]:checked');
                if (contractorMode && contractorMode.value === 'existing') {
                    var sel = document.querySelector('select[name="contractor_id"]');
                    var errEl = document.getElementById('err-js-contractor-select');
                    if (!sel || sel.value === '') {
                        if (errEl) errEl.classList.remove('is-hidden');
                        if (sel) sel.classList.add('is-error');
                        return;
                    }
                    if (errEl) errEl.classList.add('is-hidden');
                    if (sel) sel.classList.remove('is-error');
                } else {
                    var nameInput = document.querySelector('input[name="name"]');
                    var innInput = document.querySelector('input[name="inn"]');
                    var errName = document.getElementById('err-js-contractor-name');
                    var errInn = document.getElementById('err-js-contractor-inn');
                    var hasError = false;
                    if (!nameInput || nameInput.value.trim() === '') {
                        if (errName) errName.classList.remove('is-hidden');
                        if (nameInput) nameInput.classList.add('is-error');
                        hasError = true;
                    } else {
                        if (errName) errName.classList.add('is-hidden');
                        if (nameInput) nameInput.classList.remove('is-error');
                    }
                    if (!innInput || innInput.value.trim() === '') {
                        if (errInn) errInn.classList.remove('is-hidden');
                        if (innInput) innInput.classList.add('is-error');
                        hasError = true;
                    } else {
                        if (errInn) errInn.classList.add('is-hidden');
                        if (innInput) innInput.classList.remove('is-error');
                    }
                    if (hasError) return;
                }
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
            var prevStep = parseInt(this.getAttribute('data-prev'));
            updateStepper(prevStep);
        });
    }

    // Initial review population if starting on step 4
    if (currentStep === 4) {
        refreshReview();
    }

    // Clear js-validation errors on input/selection
    var jsErrFields = [
        {trigger: 'select[name="contractor_id"]', err: 'err-js-contractor-select'},
        {trigger: 'input[name="name"]', err: 'err-js-contractor-name'},
        {trigger: 'input[name="inn"]', err: 'err-js-contractor-inn'},
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

    // Also clear error when mode switches
    var modeRadios = document.querySelectorAll('input[name="contractor_mode"], input[name="driver_mode"], input[name="vehicle_mode"]');
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
