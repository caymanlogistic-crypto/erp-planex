<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">КОМПАНИЯ / <?= e($company['name']) ?></span>
        <span class="page-title">Создать перевозчика + Водителя + Транспорт</span>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Создание недоступно.
</div>

<?php elseif ($success): ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">КОМПАНИЯ / <?= e($company['name']) ?></span>
        <span class="page-title">Созданы перевозчик, водитель и транспорт</span>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors" class="btn btn-primary">← К списку</a>
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
                </tbody>
            </table>
        </div>

        <div class="form-actions mt-4">
            <a href="/company/contractors" class="btn btn-primary">← К списку перевозчиков</a>
            <a href="/company/contractors/create-full" class="btn btn-ghost">Создать ещё</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">КОМПАНИЯ / <?= e($company['name']) ?></span>
        <span class="page-title">Создать перевозчика + Водителя + Транспорт</span>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<?php if ($formError): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/company/contractors/create-full" class="panel">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Перевозчик</h3>

            <div class="field">
                <label class="field-label">Наименование <span class="req">*</span></label>
                <input type="text" name="name" class="field-input" required
                       value="<?= e($old['name'] ?? '') ?>">
                <?php if (!empty($errors['name'])): ?>
                    <div class="field-msg is-error"><?= e($errors['name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label">ИНН <span class="req">*</span></label>
                    <input type="text" name="inn" class="field-input" required
                           value="<?= e($old['inn'] ?? '') ?>">
                    <?php if (!empty($errors['inn'])): ?>
                        <div class="field-msg is-error"><?= e($errors['inn']) ?></div>
                    <?php endif; ?>
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

        <div class="form-section">
            <h3 class="panel-head-title">Водитель</h3>

            <div class="field">
                <label class="field-label">ФИО <span class="req">*</span></label>
                <input type="text" name="driver_full_name" class="field-input" required
                       value="<?= e($old['driver_full_name'] ?? '') ?>">
                <?php if (!empty($errors['driver_full_name'])): ?>
                    <div class="field-msg is-error"><?= e($errors['driver_full_name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Телефон</label>
                <input type="text" name="driver_phone" class="field-input"
                       value="<?= e($old['driver_phone'] ?? '') ?>">
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Транспорт</h3>

            <div class="field">
                <label class="field-label">Госномер <span class="req">*</span></label>
                <input type="text" name="plate_number" class="field-input" required
                       value="<?= e($old['plate_number'] ?? '') ?>"
                       placeholder="А999АА99">
                <?php if (!empty($errors['plate_number'])): ?>
                    <div class="field-msg is-error"><?= e($errors['plate_number']) ?></div>
                <?php endif; ?>
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

            <div class="field" id="secondary_field" style="display:none">
                <label class="field-label">Доп. госномер <span class="req">*</span></label>
                <input type="text" name="secondary_plate_number" class="field-input"
                       value="<?= e($old['secondary_plate_number'] ?? '') ?>"
                       placeholder="А999АА99">
                <?php if (!empty($errors['secondary_plate_number'])): ?>
                    <div class="field-msg is-error"><?= e($errors['secondary_plate_number']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Создать перевозчика, водителя и транспорт</button>
            <a href="/company/contractors" class="btn btn-ghost">Отмена</a>
        </div>

    </div>
</form>

<script>
(function() {
    var setType = document.getElementById('set_type_select');
    var secondary = document.getElementById('secondary_field');
    if (setType && secondary) {
        function update() {
            var v = setType.value;
            secondary.style.display = (v === 'coupling' || v === 'road_train') ? '' : 'none';
            var reqSpan = secondary.querySelector('.req');
            var input = secondary.querySelector('input');
            if (input) input.required = (v === 'coupling' || v === 'road_train');
        }
        setType.addEventListener('change', update);
        update();
    }
})();
</script>

<?php endif; ?>
