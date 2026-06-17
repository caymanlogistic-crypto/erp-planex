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
        </div>
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
        <?php if (!empty($uploadedDocs)): ?>
        <div class="form-section mt-4">
            <h3 class="panel-head-title">Загруженные документы</h3>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Файл</th>
                            <th>Тип</th>
                            <th>Размер</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($uploadedDocs as $doc): ?>
                        <tr>
                            <td><?= e($doc['original_name']) ?></td>
                            <td><?= e($doc['mime_type']) ?></td>
                            <td><?= e(formatFileSize($doc['file_size'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($docErrors)): ?>
        <div class="notice warn mt-4">
            <p>Некоторые файлы не удалось загрузить:</p>
            <ul>
                <?php foreach ($docErrors as $de): ?>
                <li><?= e($de) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="form-actions mt-4">
            <a href="/company/vehicle-sets/<?= $createdVehicleSet['id'] ?>" class="btn btn-primary">← К карточке транспорта</a>
            <a href="/company/documents?entity_type=vehicle_set&entity_id=<?= $createdVehicleSet['id'] ?>" class="btn btn-ghost">Документы</a>
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

<form method="post" action="/company/vehicle-sets/create" enctype="multipart/form-data" class="panel">
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

        <div class="form-section">
            <h3 class="panel-head-title">Документы транспорта</h3>

            <div class="field">
                <label class="field-label">Тип документа</label>
                <input type="text" name="documents_type" class="field-input"
                       value="<?= e($old['documents_type'] ?? '') ?>"
                       placeholder="Например: СТС, ПТС, Страховка">
            </div>

            <div class="field">
                <label class="field-label">Файлы</label>
                <input type="file" name="documents_file[]" class="field-input" multiple
                       accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx">
                <div class="field-msg">
                    Допустимые форматы: PDF, JPG, PNG, WEBP, DOC, DOCX, XLS, XLSX. Максимальный размер: 20 МБ.
                </div>
                <?php if (!empty($errors['documents_file'])): ?>
                    <div class="field-msg is-error"><?= e($errors['documents_file']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Создать транспорт</button>
        </div>

    </div>
</form>

<!-- DESIGN_TODO: JS-переключение видимости secondary_field -->
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
