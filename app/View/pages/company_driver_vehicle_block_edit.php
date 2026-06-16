<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Редактировать блок</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/driver-vehicle-blocks" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Редактирование недоступно.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Редактировать блок</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/driver-vehicle-blocks" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif ($entityNotFound): ?>

<div class="page-head">
    <div>
        <h1>Блок не найден</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/driver-vehicle-blocks" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Блок "Водитель + ТС" с указанным ID не найден.
</div>

<?php elseif ($success): ?>

<div class="page-head">
    <div>
        <h1>Блок обновлён</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/driver-vehicle-blocks/<?= $block['id'] ?>" class="btn btn-primary">← К просмотру</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Блок "Водитель + ТС" успешно обновлён.
        </div>
        <div class="form-actions mt-4">
            <a href="/company/driver-vehicle-blocks/<?= $block['id'] ?>" class="btn btn-primary">← К просмотру</a>
            <a href="/company/driver-vehicle-blocks" class="btn btn-ghost">← К списку</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Редактировать блок #<?= $block['id'] ?></h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/driver-vehicle-blocks/<?= $block['id'] ?>" class="btn btn-ghost">← К просмотру</a>
    </div>
</div>

<?php if ($formError): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/company/driver-vehicle-blocks/<?= $block['id'] ?>/edit" class="panel">
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
                    <option value="active" <?= ($old['status'] ?? '') === 'active' ? 'selected' : '' ?>>Активен</option>
                    <option value="inactive" <?= ($old['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Неактивен</option>
                    <option value="archived" <?= ($old['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Архив</option>
                </select>
            </div>

            <div class="field">
                <label class="field-label">Комментарий</label>
                <textarea name="comments" class="field-textarea" rows="3"><?= e($old['comments'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Сохранить изменения</button>
            <a href="/company/driver-vehicle-blocks/<?= $block['id'] ?>" class="btn btn-ghost">← К просмотру</a>
        </div>

    </div>
</form>

<?php endif; ?>
