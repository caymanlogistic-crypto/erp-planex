<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Создать водителя</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/drivers" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Создание водителей недоступно.
</div>

<?php elseif ($success): ?>

<div class="page-head">
    <div>
        <h1>Водитель создан</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/drivers" class="btn btn-primary">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Водитель успешно создан.
        </div>

        <div class="kv mt-4">
            <div class="kv-row">
                <span class="kv-key">ФИО</span>
                <span class="kv-value"><?= e($createdDriver['full_name']) ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Телефон</span>
                <span class="kv-value"><code><?= e($createdDriver['phone']) ?></code></span>
            </div>
            <?php if (!empty($createdDriver['passport_number'])): ?>
            <div class="kv-row">
                <span class="kv-key">Паспорт</span>
                <span class="kv-value"><?= e($createdDriver['passport_number']) ?> / <?= e($createdDriver['passport_issued_by'] ?? '—') ?> / <?= e($createdDriver['passport_issue_date'] ?? '—') ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($createdDriver['snils'])): ?>
            <div class="kv-row">
                <span class="kv-key">СНИЛС</span>
                <span class="kv-value"><?= e($createdDriver['snils']) ?></span>
            </div>
            <?php endif; ?>
            <div class="kv-row">
                <span class="kv-key">Номер ВУ</span>
                <span class="kv-value"><?= e($createdDriver['license_number'] ?? '—') ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Категория ВУ</span>
                <span class="kv-value"><?= e($createdDriver['license_category'] ?? '—') ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Статус</span>
                <span class="kv-value">Активен</span>
            </div>
        </div>

        <div class="form-actions mt-4">
            <a href="/company/drivers" class="btn btn-primary">← К списку водителей</a>
            <a href="/company/drivers/create" class="btn btn-ghost">Создать ещё</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Создать водителя</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/drivers" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<?php if ($formError): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/company/drivers/create" class="panel">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Основные данные</h3>

            <div class="field">
                <label class="field-label">ФИО <span class="req">*</span></label>
                <input type="text" name="full_name" class="field-input" required
                       value="<?= e($old['full_name'] ?? '') ?>">
                <?php if (!empty($errors['full_name'])): ?>
                    <div class="field-msg is-error"><?= e($errors['full_name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Телефон <span class="req">*</span></label>
                <input type="text" name="phone" class="field-input"
                       value="<?= e($old['phone'] ?? '') ?>">
                <?php if (!empty($errors['phone'])): ?>
                    <div class="field-msg is-error"><?= e($errors['phone']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Паспортные данные</h3>

            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label">Серия и номер</label>
                    <input type="text" name="passport_number" class="field-input"
                           value="<?= e($old['passport_number'] ?? '') ?>">
                </div>

                <div class="field">
                    <label class="field-label">Код подразделения</label>
                    <input type="text" name="passport_department_code" class="field-input"
                           value="<?= e($old['passport_department_code'] ?? '') ?>">
                </div>
            </div>

            <div class="field">
                <label class="field-label">Кем выдан</label>
                <input type="text" name="passport_issued_by" class="field-input"
                       value="<?= e($old['passport_issued_by'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Дата выдачи</label>
                <input type="date" name="passport_issue_date" class="field-input"
                       value="<?= e($old['passport_issue_date'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">СНИЛС</label>
                <input type="text" name="snils" class="field-input"
                       value="<?= e($old['snils'] ?? '') ?>">
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Водительское удостоверение</h3>

            <div class="field">
                <label class="field-label">Номер ВУ</label>
                <input type="text" name="license_number" class="field-input"
                       value="<?= e($old['license_number'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Категория</label>
                <input type="text" name="license_category" class="field-input"
                       value="<?= e($old['license_category'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Дата выдачи</label>
                <input type="date" name="license_issue_date" class="field-input"
                       value="<?= e($old['license_issue_date'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Дата окончания</label>
                <input type="date" name="license_expire_date" class="field-input"
                       value="<?= e($old['license_expire_date'] ?? '') ?>">
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
            <button type="submit" class="btn btn-primary">Создать водителя</button>
        </div>

    </div>
</form>

<?php endif; ?>
