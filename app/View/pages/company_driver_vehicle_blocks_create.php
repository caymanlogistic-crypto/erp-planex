<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Создать связку</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/driver-vehicle-blocks" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Создание связок недоступно.
</div>

<?php elseif ($success): ?>

<div class="page-head">
    <div>
        <h1>Связка создана</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/driver-vehicle-blocks" class="btn btn-primary">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Связка успешно создана.
        </div>
        <div class="kv mt-4">
            <div class="kv-row">
                <span class="kv-key">Водитель</span>
                <span class="kv-value"><?= e($createdBlock['driver_name'] ?? '—') ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Транспорт</span>
                <span class="kv-value"><?= e(ui_set_type($createdBlock['set_type'] ?? null)) ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Статус</span>
                <span class="kv-value">Активен</span>
            </div>
        </div>
        <div class="form-actions mt-4">
            <a href="/company/driver-vehicle-blocks" class="btn btn-primary">← К списку</a>
            <a href="/company/driver-vehicle-blocks/create" class="btn btn-ghost">Создать ещё</a>
        </div>
    </div>
</div>

<?php else: ?>

<?php
// Determine initial step based on validation errors
$initialStep = 1;
if (!empty($old)) {
    if (!empty($errors['driver_id'])) {
        $initialStep = 1;
    } elseif (!empty($errors['vehicle_set_id'])) {
        $initialStep = 2;
    } else {
        $initialStep = 3;
    }
}

// Pre-compute selected driver/vehicle names for step 3 review
$selectedDriverName = '';
$selectedVehicleName = '';
if (!empty($old['driver_id'])) {
    foreach ($drivers as $d) {
        if ($d['id'] == $old['driver_id']) {
            $selectedDriverName = $d['full_name'] . ' (' . ($d['phone'] ?? '') . ')';
            break;
        }
    }
}
if (!empty($old['vehicle_set_id'])) {
    foreach ($vehicleSets as $vs) {
        if ($vs['id'] == $old['vehicle_set_id']) {
            $selectedVehicleName = ui_set_type($vs['set_type'] ?? null) . ' — ' . ($vs['primary_plate'] ?? '—') . (!empty($vs['secondary_plate']) ? ' + ' . $vs['secondary_plate'] : '');
            break;
        }
    }
}
?>

<div class="page-head">
    <div>
        <h1>Создать связку</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/driver-vehicle-blocks" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<?php if ($formError): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/company/driver-vehicle-blocks/create">

    <!-- STEPPER INDICATORS -->
    <div class="stepper" id="stepper">
        <div class="step<?= $initialStep >= 1 ? ' is-active' : '' ?>" data-step="1">
            <div class="step-circle">1</div>
            <div class="step-label">Водитель</div>
            <div class="step-sublabel">Выберите водителя</div>
        </div>
        <div class="step<?= $initialStep >= 2 ? ($initialStep > 2 ? ' is-done' : ' is-active') : '' ?>" data-step="2">
            <div class="step-circle">2</div>
            <div class="step-label">Машина / Транспорт</div>
            <div class="step-sublabel">Выберите транспорт</div>
        </div>
        <div class="step<?= $initialStep >= 3 ? ' is-active' : '' ?>" data-step="3">
            <div class="step-circle">3</div>
            <div class="step-label">Проверка</div>
            <div class="step-sublabel">Подтверждение</div>
        </div>
    </div>

    <!-- STEP 1: ВОДИТЕЛЬ -->
    <div class="step-body<?= $initialStep !== 1 ? ' is-hidden' : '' ?>" id="step-body-1">
        <div class="step-body-head">
            <span class="step-body-title">Шаг 1: Выберите водителя</span>
        </div>
        <div class="step-body-content">
            <div class="field">
                <label class="field-label">Водитель <span class="req">*</span></label>
                <select name="driver_id" id="select-driver" class="field-input<?= !empty($errors['driver_id']) ? ' is-error' : '' ?>"<?= empty($drivers) ? ' disabled' : '' ?>>
                    <option value="">— Выберите водителя —</option>
                    <?php foreach ($drivers as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= ($old['driver_id'] ?? '') == $d['id'] ? 'selected' : '' ?>>
                        <?= e($d['full_name']) ?> (<?= e($d['phone'] ?? '') ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($drivers)): ?>
                    <p class="field-hint">Нет доступных водителей. <a href="/company/drivers/create">Создать водителя</a></p>
                <?php endif; ?>
                <?php if (!empty($errors['driver_id'])): ?>
                    <div class="field-msg is-error"><?= e($errors['driver_id']) ?></div>
                <?php endif; ?>
                <div class="field-msg is-error is-hidden" id="err-js-driver">Пожалуйста, выберите водителя.</div>
            </div>
        </div>
        <div class="step-nav">
            <button type="button" class="btn btn-ghost step-back" disabled>← Назад</button>
            <button type="button" class="btn btn-primary step-next" data-next="2">Далее →</button>
        </div>
    </div>

    <!-- STEP 2: ТРАНСПОРТ -->
    <div class="step-body<?= $initialStep !== 2 ? ' is-hidden' : '' ?>" id="step-body-2">
        <div class="step-body-head">
            <span class="step-body-title">Шаг 2: Выберите транспорт</span>
        </div>
        <div class="step-body-content">
            <div class="field">
                <label class="field-label">Транспорт <span class="req">*</span></label>
                <select name="vehicle_set_id" id="select-vehicle" class="field-input<?= !empty($errors['vehicle_set_id']) ? ' is-error' : '' ?>"<?= empty($vehicleSets) ? ' disabled' : '' ?>>
                    <option value="">— Выберите транспорт —</option>
                    <?php foreach ($vehicleSets as $vs): ?>
                    <option value="<?= $vs['id'] ?>" <?= ($old['vehicle_set_id'] ?? '') == $vs['id'] ? 'selected' : '' ?>>
                        <?= e(ui_set_type($vs['set_type'] ?? null)) ?> — <?= e($vs['primary_plate'] ?? '—') ?><?= !empty($vs['secondary_plate']) ? ' + ' . e($vs['secondary_plate']) : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($vehicleSets)): ?>
                    <p class="field-hint">Нет доступного транспорта. <a href="/company/vehicle-sets/create">Создать транспорт</a></p>
                <?php endif; ?>
                <?php if (!empty($errors['vehicle_set_id'])): ?>
                    <div class="field-msg is-error"><?= e($errors['vehicle_set_id']) ?></div>
                <?php endif; ?>
                <div class="field-msg is-error is-hidden" id="err-js-vehicle">Пожалуйста, выберите транспорт.</div>
            </div>
        </div>
        <div class="step-nav">
            <button type="button" class="btn btn-ghost step-back" data-prev="1">← Назад</button>
            <button type="button" class="btn btn-primary step-next" data-next="3">Далее →</button>
        </div>
    </div>

    <!-- STEP 3: ПРОВЕРКА -->
    <div class="step-body<?= $initialStep !== 3 ? ' is-hidden' : '' ?>" id="step-body-3">
        <div class="step-body-head">
            <span class="step-body-title">Шаг 3: Проверка и создание</span>
        </div>
        <div class="step-body-content">
            <div class="field">
                <label class="field-label">Выбранный водитель</label>
                <input type="text" class="field-input" id="review-driver" value="<?= e($selectedDriverName ?: '—') ?>" readonly>
            </div>

            <div class="field">
                <label class="field-label">Выбранный транспорт</label>
                <input type="text" class="field-input" id="review-vehicle" value="<?= e($selectedVehicleName ?: '—') ?>" readonly>
            </div>

            <div class="field">
                <label class="field-label">Статус</label>
                <select name="status" class="field-input">
                    <option value="active" <?= ($old['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Активен</option>
                    <option value="inactive" <?= ($old['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Неактивен</option>
                </select>
            </div>

            <div class="field">
                <label class="field-label">Комментарий</label>
                <textarea name="comments" class="field-textarea" rows="3"><?= e($old['comments'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="step-nav">
            <button type="button" class="btn btn-ghost step-back" data-prev="2">← Назад</button>
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
        document.getElementById('step-body-3')
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
        // If navigating to step 3, refresh review data
        if (stepNum === 3) {
            refreshReview();
        }
    }

    function refreshReview() {
        var driverSelect = document.getElementById('select-driver');
        var vehicleSelect = document.getElementById('select-vehicle');
        var reviewDriver = document.getElementById('review-driver');
        var reviewVehicle = document.getElementById('review-vehicle');

        if (driverSelect && driverSelect.selectedIndex > 0) {
            reviewDriver.value = driverSelect.options[driverSelect.selectedIndex].textContent;
        } else {
            reviewDriver.value = '—';
        }

        if (vehicleSelect && vehicleSelect.selectedIndex > 0) {
            reviewVehicle.value = vehicleSelect.options[vehicleSelect.selectedIndex].textContent;
        } else {
            reviewVehicle.value = '—';
        }
    }

    // Next button handlers
    var nextButtons = document.querySelectorAll('.step-next');
    for (var n = 0; n < nextButtons.length; n++) {
        nextButtons[n].addEventListener('click', function(e) {
            var nextStep = parseInt(this.getAttribute('data-next'));
            // Validate current step before moving forward
            if (currentStep === 1) {
                var driverSelect = document.getElementById('select-driver');
                var errDrv = document.getElementById('err-js-driver');
                if (!driverSelect || driverSelect.value === '') {
                    if (errDrv) errDrv.classList.remove('is-hidden');
                    if (driverSelect) driverSelect.classList.add('is-error');
                    return;
                }
                if (errDrv) errDrv.classList.add('is-hidden');
                if (driverSelect) driverSelect.classList.remove('is-error');
            }
            if (currentStep === 2) {
                var vehicleSelect = document.getElementById('select-vehicle');
                var errVeh = document.getElementById('err-js-vehicle');
                if (!vehicleSelect || vehicleSelect.value === '') {
                    if (errVeh) errVeh.classList.remove('is-hidden');
                    if (vehicleSelect) vehicleSelect.classList.add('is-error');
                    return;
                }
                if (errVeh) errVeh.classList.add('is-hidden');
                if (vehicleSelect) vehicleSelect.classList.remove('is-error');
            }
            updateStepper(nextStep);
        });
    }

    // Back button handlers
    var backButtons = document.querySelectorAll('.step-back');
    for (var b = 0; b < backButtons.length; b++) {
        backButtons[b].addEventListener('click', function(e) {
            var prevStep = parseInt(this.getAttribute('data-prev'));
            updateStepper(prevStep);
        });
    }

    // Initial review population if starting on step 3
    if (currentStep === 3) {
        refreshReview();
    }

    // Clear js-validation errors on selection
    var driverSelect = document.getElementById('select-driver');
    var vehicleSelect = document.getElementById('select-vehicle');
    if (driverSelect) {
        driverSelect.addEventListener('change', function() {
            var err = document.getElementById('err-js-driver');
            if (err) err.classList.add('is-hidden');
            driverSelect.classList.remove('is-error');
        });
    }
    if (vehicleSelect) {
        vehicleSelect.addEventListener('change', function() {
            var err = document.getElementById('err-js-vehicle');
            if (err) err.classList.add('is-hidden');
            vehicleSelect.classList.remove('is-error');
        });
    }
})();
</script>

<?php endif; ?>
