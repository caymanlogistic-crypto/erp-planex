<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Редактировать транспорт</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/vehicle-sets" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Редактирование недоступно.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Редактировать транспорт</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/vehicle-sets" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif ($entityNotFound): ?>

<div class="page-head">
    <div>
        <h1>Транспорт не найден</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/vehicle-sets" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Транспорт с указанным ID не найден.
</div>

<?php elseif ($success): ?>

<div class="page-head">
    <div>
        <h1>Транспорт обновлён</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/vehicle-sets/<?= $vehicleSet['id'] ?>" class="btn btn-primary">← К просмотру</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Транспорт успешно обновлён.
        </div>
        <div class="form-actions mt-4">
            <a href="/company/vehicle-sets/<?= $vehicleSet['id'] ?>" class="btn btn-primary">← К просмотру</a>
            <a href="/company/vehicle-sets" class="btn btn-ghost">← К списку</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Редактировать транспорт #<?= $vehicleSet['id'] ?></h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/vehicle-sets/<?= $vehicleSet['id'] ?>" class="btn btn-ghost">← К просмотру</a>
    </div>
</div>

<?php if ($formError): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/company/vehicle-sets/<?= $vehicleSet['id'] ?>/edit" class="panel">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Транспорт</h3>

            <div class="field">
                <label class="field-label">Тип комплекта</label>
                <select name="set_type" class="field-input" id="set_type_select">
                    <option value="">— Выберите тип —</option>
                    <option value="single" <?= ($old['set_type'] ?? '') === 'single' ? 'selected' : '' ?>>Одиночка (одна единица)</option>
                    <option value="coupling" <?= ($old['set_type'] ?? '') === 'coupling' ? 'selected' : '' ?>>Сцепка (тягач + полуприцеп)</option>
                    <option value="road_train" <?= ($old['set_type'] ?? '') === 'road_train' ? 'selected' : '' ?>>Автопоезд (машина + прицеп)</option>
                </select>
                <?php if (!empty($errors['set_type'])): ?>
                    <div class="field-msg is-error"><?= e($errors['set_type']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Основная транспортная единица <span class="req">*</span></label>
                <select name="primary_vehicle_unit_id" class="field-input">
                    <option value="">— Выберите единицу —</option>
                    <?php foreach ($vehicleUnits as $vu): ?>
                    <option value="<?= $vu['id'] ?>" <?= ($old['primary_vehicle_unit_id'] ?? '') == $vu['id'] ? 'selected' : '' ?>>
                        <?= e($vu['plate_number']) ?> — <?= e($vu['brand'] ?? '') ?> <?= e($vu['model'] ?? '') ?> (<?= e($vu['unit_type'] ?? '—') ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['primary_vehicle_unit_id'])): ?>
                    <div class="field-msg is-error"><?= e($errors['primary_vehicle_unit_id']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field" id="secondary_field">
                <label class="field-label">Дополнительная транспортная единица <span class="text-muted">(обязательно для сцепки и автопоезда)</span></label>
                <select name="secondary_vehicle_unit_id" class="field-input">
                    <option value="">— Выберите единицу —</option>
                    <?php foreach ($vehicleUnits as $vu): ?>
                    <option value="<?= $vu['id'] ?>" <?= ($old['secondary_vehicle_unit_id'] ?? '') == $vu['id'] ? 'selected' : '' ?>>
                        <?= e($vu['plate_number']) ?> — <?= e($vu['brand'] ?? '') ?> <?= e($vu['model'] ?? '') ?> (<?= e($vu['unit_type'] ?? '—') ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['secondary_vehicle_unit_id'])): ?>
                    <div class="field-msg is-error"><?= e($errors['secondary_vehicle_unit_id']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Статус</h3>
            <div class="field">
                <label class="field-label">Статус</label>
                <select name="status" class="field-input">
                    <option value="active" <?= ($old['status'] ?? '') === 'active' ? 'selected' : '' ?>>Активен</option>
                    <option value="inactive" <?= ($old['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Неактивен</option>
                    <option value="archived" <?= ($old['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Архив</option>
                </select>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Дополнительно</h3>
            <div class="field">
                <label class="field-label">Комментарий</label>
                <textarea name="comments" class="field-textarea" rows="3"><?= e($old['comments'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Сохранить изменения</button>
            <a href="/company/vehicle-sets/<?= $vehicleSet['id'] ?>" class="btn btn-ghost">← К просмотру</a>
        </div>

    </div>
</form>

<script>
(function() {
    var setType = document.getElementById('set_type_select');
    var secondary = document.getElementById('secondary_field');
    if (setType && secondary) {
        function update() {
            secondary.style.display = (setType.value === 'coupling' || setType.value === 'road_train') ? '' : 'none';
        }
        setType.addEventListener('change', update);
        update();
    }
})();
</script>

<?php endif; ?>
