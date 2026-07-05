<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Редактировать связку</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/company/driver-vehicle-blocks') ?>" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Редактирование недоступно.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Редактировать связку</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/company/driver-vehicle-blocks') ?>" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif ($entityNotFound): ?>

<div class="page-head">
    <div>
        <h1>Связка не найдена</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/company/driver-vehicle-blocks') ?>" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Связка с указанным ID не найдена.
</div>

<?php elseif ($success): ?>

<div class="page-head">
    <div>
        <h1>Связка обновлена</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/driver-vehicle-blocks/<?= $block['id'] ?>" class="btn btn-primary">← К просмотру</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Связка успешно обновлена.
        </div>
        <div class="form-actions mt-4">
            <a href="/company/driver-vehicle-blocks/<?= $block['id'] ?>" class="btn btn-primary">← К просмотру</a>
            <a href="<?= app_url('/company/driver-vehicle-blocks') ?>" class="btn btn-ghost">← К списку</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Редактировать связку #<?= $block['id'] ?></h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
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
            <h3 class="panel-head-title">Водители+ТС</h3>

            <div class="panel-section">
                <div>
                    <strong>Водитель:</strong>
                    <?= e($blockDriver['full_name'] ?? '—') ?>
                    <?php if (!empty($blockDriver['phone'])): ?>
                    (<?= e($blockDriver['phone']) ?>)
                    <?php endif; ?>
                </div>
                <a href="/company/drivers/<?= $block['driver_id'] ?>" class="btn btn-ghost">Открыть карточку водителя</a>
            </div>

            <div class="panel-section">
                <div>
                    <strong>Транспорт:</strong>
                    <?= e($blockPlate ?? '—') ?>
                </div>
                <a href="/company/vehicle-sets/<?= $block['vehicle_set_id'] ?>" class="btn btn-ghost">Открыть карточку транспорта</a>
            </div>

            <p class="text-muted">Состав связки нельзя изменить. Если нужен другой водитель или транспорт — создайте новую связку.</p>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Дополнительно</h3>

            <div class="field">
                <label class="field-label">Статус</label>
                <select name="status" class="field-input">
                    <option value="active" <?= ($old['status'] ?? '') === 'active' ? 'selected' : '' ?>>Активен</option>
                    <option value="inactive" <?= ($old['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Неактивен</option>

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
