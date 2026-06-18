<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Создать транспорт</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/vehicle-sets" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Создание транспорта недоступно.
</div>

<?php elseif ($success): ?>

<div class="page-head">
    <div>
        <h1>Транспорт создан</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/vehicle-sets" class="btn btn-primary">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Транспорт успешно создан.
            <?php if (!empty($uploadedDocs)): ?>
                <br>Загружено документов: <?= count($uploadedDocs) ?>.
            <?php endif; ?>
        </div>
        <?php if (!empty($docErrors)): ?>
            <div class="notice warn mt-4">
                <strong>Некоторые документы не были загружены:</strong>
                <ul style="margin:0.5rem 0 0 1.2rem;">
                    <?php foreach ($docErrors as $de): ?>
                        <li><?= e($de) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <div class="kv mt-4">
            <div class="kv-row">
                <span class="kv-key">Тип комплекта</span>
                <span class="kv-value"><?= e(ui_set_type($createdVehicleSet['set_type'] ?? null)) ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Основная единица</span>
                <span class="kv-value"><?= e($createdVehicleSet['primary_plate'] ?? '—') ?></span>
            </div>
            <?php if (!empty($createdVehicleSet['secondary_plate'])): ?>
            <div class="kv-row">
                <span class="kv-key">Доп. единица</span>
                <span class="kv-value"><?= e($createdVehicleSet['secondary_plate']) ?></span>
            </div>
            <?php endif; ?>
            <div class="kv-row">
                <span class="kv-key">Статус</span>
                <span class="kv-value">Активен</span>
            </div>
        </div>
        <div class="form-actions mt-4">
            <a href="/company/vehicle-sets" class="btn btn-primary">← К списку</a>
            <a href="/company/vehicle-sets/create" class="btn btn-ghost">Создать ещё</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Создать транспорт</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/vehicle-sets" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<?php if ($formError): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/company/vehicle-sets/create" class="panel" enctype="multipart/form-data">
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
                <select name="primary_vehicle_unit_id" class="field-input"<?= empty($vehicleUnits) ? ' disabled' : '' ?>>
                    <option value="">— Выберите единицу —</option>
                    <?php foreach ($vehicleUnits as $vu): ?>
                    <option value="<?= $vu['id'] ?>" <?= ($old['primary_vehicle_unit_id'] ?? '') == $vu['id'] ? 'selected' : '' ?>>
                        <?= e($vu['plate_number']) ?> — <?= e($vu['brand'] ?? '') ?> <?= e($vu['model'] ?? '') ?> (<?= e($vu['unit_type'] ?? '—') ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($vehicleUnits)): ?>
                    <p class="field-hint">Нет доступных транспортных единиц. <a href="/company/vehicles/create">Создать транспортную единицу</a></p>
                <?php endif; ?>
                <?php if (!empty($errors['primary_vehicle_unit_id'])): ?>
                    <div class="field-msg is-error"><?= e($errors['primary_vehicle_unit_id']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field" id="secondary_field">
                <label class="field-label">Дополнительная транспортная единица <span class="text-muted">(обязательно для сцепки и автопоезда)</span></label>
                <select name="secondary_vehicle_unit_id" class="field-input"<?= empty($vehicleUnits) ? ' disabled' : '' ?>>
                    <option value="">— Выберите единицу —</option>
                    <?php foreach ($vehicleUnits as $vu): ?>
                    <option value="<?= $vu['id'] ?>" <?= ($old['secondary_vehicle_unit_id'] ?? '') == $vu['id'] ? 'selected' : '' ?>>
                        <?= e($vu['plate_number']) ?> — <?= e($vu['brand'] ?? '') ?> <?= e($vu['model'] ?? '') ?> (<?= e($vu['unit_type'] ?? '—') ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($vehicleUnits)): ?>
                    <p class="field-hint">Нет доступных транспортных единиц. <a href="/company/vehicles/create">Создать транспортную единицу</a></p>
                <?php endif; ?>
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
                    <option value="active" <?= ($old['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Активен</option>
                    <option value="inactive" <?= ($old['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Неактивен</option>
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

        <?php
        // Predefined documents for vehicle set
        $predefDocs = [
            ['name' => 'СТС', 'code' => 'sts'],
            ['name' => 'ПТС', 'code' => 'pts'],
            ['name' => 'Страховка ОСАГО', 'code' => 'osago'],
        ];
        if (!empty($predefDocs)):
        ?>
        <div class="form-section">
            <h3 class="panel-head-title">Предопределённые документы</h3>
            <p class="field-hint">Загрузите ожидаемые документы. Можно загрузить сейчас или позже в карточке транспорта.</p>

            <?php foreach ($predefDocs as $pdoc): ?>
            <div class="doc-upload-row" style="display:flex; gap:0.75rem; align-items:center; margin-bottom:0.75rem;">
                <span style="min-width:200px; font-size:13px;"><?= e($pdoc['name']) ?></span>
                <input type="file" name="predef_doc[<?= $pdoc['code'] ?>]" accept=".pdf,.jpg,.jpeg,.png" class="field-input" style="flex:1;">
                <input type="hidden" name="predef_doc_type[<?= $pdoc['code'] ?>]" value="<?= e($pdoc['name']) ?>">
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Custom documents block -->
        <div class="form-section">
            <h3 class="panel-head-title">Произвольные документы</h3>
            <p class="field-hint">Добавьте дополнительные документы с указанием типа. <a href="/company/document-types/create" target="_blank">Создать новый тип</a></p>

            <div id="custom-docs-container"></div>

            <button type="button" class="btn btn-ghost btn-sm" id="add-custom-doc-btn">+ Добавить документ</button>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Создать транспорт</button>
        </div>

    </div>
</form>

<!-- DOC_JS: custom document rows -->
<script>
(function() {
    var container = document.getElementById('custom-docs-container');
    var addBtn = document.getElementById('add-custom-doc-btn');
    if (!container || !addBtn) return;

    var docTypes = <?= json_encode($docTypes ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;

    function buildSelect(name) {
        var s = '<select name="' + name + '" class="field-input" style="flex:1;"><option value="">— Выберите тип —</option>';
        for (var i = 0; i < docTypes.length; i++) {
            s += '<option value="' + docTypes[i].name.replace(/"/g, '&quot;') + '" data-id="' + docTypes[i].id + '">' + docTypes[i].name.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</option>';
        }
        s += '</select>';
        return s;
    }

    function buildRow() {
        var div = document.createElement('div');
        div.className = 'custom-doc-row';
        div.style.cssText = 'display:flex; gap:0.75rem; align-items:flex-start; margin-bottom:0.75rem;';
        div.innerHTML =
            buildSelect('custom_doc_type[]') +
            '<input type="text" name="custom_doc_type_new[]" class="field-input" style="flex:1;" placeholder="Или новый тип...">' +
            '<input type="file" name="custom_doc_file[]" class="field-input" style="flex:2;" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">' +
            '<button type="button" class="btn btn-ghost btn-sm remove-custom-doc" title="Удалить строку">&times;</button>';
        container.appendChild(div);

        div.querySelector('.remove-custom-doc').addEventListener('click', function() {
            div.parentNode.removeChild(div);
        });
    }

    addBtn.addEventListener('click', function(e) {
        e.preventDefault();
        buildRow();
    });

    // Preserve existing set_type toggle
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
