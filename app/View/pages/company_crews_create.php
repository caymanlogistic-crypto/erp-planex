<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Создать экипаж</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/crews?company_id=<?= $companyId ?>" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Создание экипажа недоступно.
</div>

<?php elseif (!empty($blockingNotices)): ?>

<div class="page-head">
    <div>
        <h1>Создать экипаж</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/crews?company_id=<?= $companyId ?>" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<?php foreach ($blockingNotices as $notice): ?>
<div class="notice warn">
    <?= $notice['message'] ?>
    <a href="<?= $notice['link'] ?>" class="btn btn-ghost" style="margin-left:12px"><?= $notice['action'] ?></a>
</div>
<?php endforeach; ?>

<?php elseif ($success): ?>

<div class="page-head">
    <div>
        <h1>Экипаж создан</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/crews?company_id=<?= $companyId ?>" class="btn btn-primary">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Экипаж успешно создан.
        </div>

        <div class="kv" style="margin-top:16px">
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

        <div class="form-actions" style="margin-top:16px">
            <a href="/company/crews?company_id=<?= $companyId ?>" class="btn btn-primary">← К списку</a>
            <a href="/company/crews/create?company_id=<?= $companyId ?>" class="btn btn-ghost">Создать ещё</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Создать экипаж</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/crews?company_id=<?= $companyId ?>" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<?php if ($formError): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/company/crews/create?company_id=<?= $companyId ?>" class="panel">
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
                <label class="field-label">Транспорт <span class="req">*</span></label>
                <select name="vehicle_id" class="field-input" required>
                    <option value="">— Выберите транспорт —</option>
                    <?php foreach ($vehicles as $v): ?>
                    <option value="<?= $v['id'] ?>" <?= ($old['vehicle_id'] ?? '') == $v['id'] ? 'selected' : '' ?>>
                        <?= e($v['plate_number']) ?> — <?= e($v['brand'] ?? '') ?> <?= e($v['model'] ?? '') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['vehicle_id'])): ?>
                    <div class="field-msg is-error"><?= e($errors['vehicle_id']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Водитель <span class="req">*</span></label>
                <select name="driver_id" class="field-input" required>
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
            <a href="/company/crews?company_id=<?= $companyId ?>" class="btn btn-ghost">← К списку</a>
        </div>

    </div>
</form>

<?php endif; ?>
