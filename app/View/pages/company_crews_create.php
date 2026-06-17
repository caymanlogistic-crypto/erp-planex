<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Создать экипаж</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/crews" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Создание экипажа недоступно.
</div>

<?php elseif (!empty($blockingNotices)): ?>

<div class="page-head">
    <div>
        <h1>Создать экипаж</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/crews" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<?php foreach ($blockingNotices as $notice): ?>
<div class="notice warn">
    <?= $notice['message'] ?>
    <a href="<?= $notice['link'] ?>" class="btn btn-ghost notice-action"><?= $notice['action'] ?></a>
</div>
<?php endforeach; ?>

<?php elseif ($success): ?>

<div class="page-head">
    <div>
        <h1>Экипаж создан</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/crews" class="btn btn-primary">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Экипаж успешно создан.
        </div>

        <div class="kv mt-4">
            <div class="kv-row">
                <span class="kv-key">Подрядчик</span>
                <span class="kv-value"><?= e($createdCrew['contractor_name']) ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Транспорт</span>
                <span class="kv-value"><code><?= e($createdCrew['plate_number']) ?></code></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Водитель</span>
                <span class="kv-value"><?= e($createdCrew['driver_name']) ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Статус</span>
                <span class="kv-value">Активен</span>
            </div>
        </div>

        <div class="form-actions mt-4">
            <a href="/company/crews" class="btn btn-primary">← К списку</a>
            <a href="/company/crews/create" class="btn btn-ghost">Создать ещё</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Создать экипаж</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/crews" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<?php if ($formError): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/company/crews/create" class="panel">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Экипаж</h3>

            <div class="field">
                <label class="field-label">Подрядчик <span class="req">*</span></label>
                <select name="contractor_id" class="field-input" required>
                    <option value="">— Выберите подрядчика —</option>
                    <?php foreach ($contractors as $ctr): ?>
                    <option value="<?= $ctr['id'] ?>" <?= ($old['contractor_id'] ?? '') == $ctr['id'] ? 'selected' : '' ?>>
                        <?= e($ctr['name']) ?> (ИНН: <?= e($ctr['inn']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['contractor_id'])): ?>
                    <div class="field-msg is-error"><?= e($errors['contractor_id']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Блок "Водитель + ТС" <span class="req">*</span></label>
                <select name="driver_vehicle_block_id" class="field-input">
                    <option value="">— Выберите блок —</option>
                    <?php foreach ($driverVehicleBlocks as $dvb): ?>
                    <option value="<?= $dvb['id'] ?>" <?= ($old['driver_vehicle_block_id'] ?? '') == $dvb['id'] ? 'selected' : '' ?>>
                        <?= e($dvb['driver_name']) ?> — <?= e(ui_set_type($dvb['set_type'] ?? null)) ?> — <?= e($dvb['primary_plate'] ?? '') ?><?= !empty($dvb['secondary_plate']) ? ' + ' . e($dvb['secondary_plate']) : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['driver_vehicle_block_id'])): ?>
                    <div class="field-msg is-error"><?= e($errors['driver_vehicle_block_id']) ?></div>
                <?php endif; ?>
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
            <button type="submit" class="btn btn-primary">Создать экипаж</button>
        </div>

    </div>
</form>

<?php endif; ?>
