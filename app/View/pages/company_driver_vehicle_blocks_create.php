<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Создать блок "Водитель + ТС"</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/driver-vehicle-blocks" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Создание блоков недоступно.
</div>

<?php elseif ($success): ?>

<div class="page-head">
    <div>
        <h1>Блок создан</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/driver-vehicle-blocks" class="btn btn-primary">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Блок "Водитель + ТС" успешно создан.
        </div>
        <div class="kv mt-4">
            <div class="kv-row">
                <span class="kv-key">Водитель</span>
                <span class="kv-value"><?= e($createdBlock['driver_name'] ?? '—') ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Комплект</span>
                <span class="kv-value"><?= e($createdBlock['set_type'] ?? '—') ?></span>
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

<div class="page-head">
    <div>
        <h1>Создать блок "Водитель + ТС"</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/driver-vehicle-blocks" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<?php if ($formError): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/company/driver-vehicle-blocks/create" class="panel">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Блок "Водитель + ТС"</h3>

            <div class="field">
                <label class="field-label">Водитель <span class="req">*</span></label>
                <select name="driver_id" class="field-input">
                    <option value="">— Выберите водителя —</option>
                    <?php foreach ($drivers as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= ($old['driver_id'] ?? '') == $d['id'] ? 'selected' : '' ?>>
                        <?= e($d['full_name']) ?> (<?= e($d['phone'] ?? '') ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['driver_id'])): ?>
                    <div class="field-msg is-error"><?= e($errors['driver_id']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Транспортный комплект <span class="req">*</span></label>
                <select name="vehicle_set_id" class="field-input">
                    <option value="">— Выберите комплект —</option>
                    <?php foreach ($vehicleSets as $vs): ?>
                    <option value="<?= $vs['id'] ?>" <?= ($old['vehicle_set_id'] ?? '') == $vs['id'] ? 'selected' : '' ?>>
                        #<?= $vs['id'] ?> — <?= e($vs['set_type'] ?? '—') ?> — <?= e($vs['primary_plate'] ?? '—') ?><?= !empty($vs['secondary_plate']) ? ' + ' . e($vs['secondary_plate']) : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['vehicle_set_id'])): ?>
                    <div class="field-msg is-error"><?= e($errors['vehicle_set_id']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Дополнительно</h3>

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

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Создать блок</button>
            <a href="/company/driver-vehicle-blocks" class="btn btn-ghost">← К списку</a>
        </div>

    </div>
</form>

<?php endif; ?>
