<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Создать исполнителя рейса</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/company/route-executors') ?>" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Создание исполнителя рейса недоступно.
</div>

<?php elseif (!empty($blockingNotices)): ?>

<div class="page-head">
    <div>
        <h1>Создать исполнителя рейса</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/company/route-executors') ?>" class="btn btn-ghost">← К списку</a>
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
        <h1>Исполнитель рейса создан</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/company/route-executors') ?>" class="btn btn-primary">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Исполнитель рейса успешно создан.
        </div>

        <div class="kv mt-4">
            <div class="kv-row">
                <span class="kv-key">Подрядчик</span>
                <span class="kv-value"><?= e($createdExecutor['contractor_name'] ?? '') ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Водитель</span>
                <span class="kv-value"><?= e($createdExecutor['driver_name'] ?? '') ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Транспорт</span>
                <span class="kv-value"><code><?= e($createdExecutor['plate_number'] ?? '—') ?></code></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Статус</span>
                <span class="kv-value">Активен</span>
            </div>
        </div>

        <div class="form-actions mt-4">
            <a href="/company/route-executors/<?= $createdExecutor['id'] ?>" class="btn btn-primary">Просмотреть</a>
            <a href="<?= app_url('/company/route-executors') ?>" class="btn btn-ghost">← К списку</a>
            <a href="<?= app_url('/company/route-executors/create') ?>" class="btn btn-ghost">Создать ещё</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Создать исполнителя рейса</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/company/route-executors') ?>" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<?php if ($formError): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="<?= app_url('/company/route-executors/create') ?>" class="panel">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Исполнитель рейса</h3>
            <p class="text-muted" style="margin-bottom:16px;">Подрядчик + Водитель + ТС</p>

            <div class="field">
                <label class="field-label">Подрядчик <span class="req">*</span></label>
                <select name="contractor_id" class="field-input" required<?= empty($contractors) ? ' disabled' : '' ?>>
                    <option value="">— Выберите подрядчика —</option>
                    <?php foreach ($contractors as $ctr): ?>
                    <option value="<?= $ctr['id'] ?>" <?= ($old['contractor_id'] ?? '') == $ctr['id'] ? 'selected' : '' ?>>
                        <?= e($ctr['name']) ?> (ИНН: <?= e($ctr['inn']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($contractors)): ?>
                    <p class="field-hint">Нет доступных подрядчиков. <a href="<?= app_url('/company/contractors/create') ?>">Создать подрядчика</a></p>
                <?php endif; ?>
                <?php if (!empty($errors['contractor_id'])): ?>
                    <div class="field-msg is-error"><?= e($errors['contractor_id']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Водитель <span class="req">*</span></label>
                <select name="driver_id" class="field-input" required<?= empty($drivers) ? ' disabled' : '' ?>>
                    <option value="">— Выберите водителя —</option>
                    <?php foreach ($drivers as $drv): ?>
                    <option value="<?= $drv['id'] ?>" <?= ($old['driver_id'] ?? '') == $drv['id'] ? 'selected' : '' ?>>
                        <?= e($drv['full_name']) ?> — <?= e($drv['phone']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($drivers)): ?>
                    <p class="field-hint">Нет доступных водителей. <a href="<?= app_url('/company/drivers') ?>">Создать водителя</a></p>
                <?php endif; ?>
                <?php if (!empty($errors['driver_id'])): ?>
                    <div class="field-msg is-error"><?= e($errors['driver_id']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Транспорт (ТС) <span class="req">*</span></label>
                <select name="vehicle_set_id" class="field-input" required<?= empty($vehicleSets) ? ' disabled' : '' ?>>
                    <option value="">— Выберите транспортный комплект —</option>
                    <?php foreach ($vehicleSets as $vs): ?>
                    <option value="<?= $vs['id'] ?>" <?= ($old['vehicle_set_id'] ?? '') == $vs['id'] ? 'selected' : '' ?>>
                        <?= e(ui_set_type($vs['set_type'] ?? null)) ?> — <?= e($vs['primary_plate'] ?? '—') ?><?= !empty($vs['secondary_plate']) ? ' + ' . e($vs['secondary_plate']) : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($vehicleSets)): ?>
                    <p class="field-hint">Нет доступных транспортных комплектов. <a href="<?= app_url('/company/vehicle-sets') ?>">Создать ТС</a></p>
                <?php endif; ?>
                <?php if (!empty($errors['vehicle_set_id'])): ?>
                    <div class="field-msg is-error"><?= e($errors['vehicle_set_id']) ?></div>
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
            <button type="submit" class="btn btn-primary">Создать исполнителя рейса</button>
        </div>

    </div>
</form>

<?php endif; ?>
