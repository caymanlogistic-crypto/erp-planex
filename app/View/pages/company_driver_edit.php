<?php

require_once __DIR__ . '/../components/status_badge.php';

?>

<?php if ($company === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Компания не найдена. <a href="/company/drivers">← К списку</a>
        </div>
    </div>
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Редактировать водителя</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>».
</div>

<?php elseif (isset($dbError)): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice danger">
            <?= e($dbError) ?>
        </div>
        <div class="form-actions mt-4">
            <a href="/company/drivers" class="btn btn-ghost">← К списку</a>
        </div>
    </div>
</div>

<?php elseif ($driver === null): ?>

<div class="page-head">
    <div>
        <h1>Водитель не найден</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/drivers" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Водитель с указанным ID не найден.
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Редактировать водителя</h1>
        <p class="text-muted"><?= e($driver['full_name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/drivers/<?= $driver['id'] ?>" class="btn btn-ghost">← К карточке</a>
    </div>
</div>

<?php if (!empty($formError)): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/company/drivers/<?= $driver['id'] ?>/edit" class="panel">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Основные данные</h3>

            <div class="field">
                <label class="field-label">ФИО <span class="req">*</span></label>
                <input type="text" name="full_name" class="field-input<?= !empty($errors['full_name']) ? ' is-error' : '' ?>" required
                       value="<?= e($old['full_name'] ?? $driver['full_name']) ?>">
                <?php if (!empty($errors['full_name'])): ?>
                    <div class="field-msg is-error"><?= e($errors['full_name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Телефон <span class="req">*</span></label>
                <input type="text" name="phone" class="field-input<?= !empty($errors['phone']) ? ' is-error' : '' ?>" required
                       value="<?= e($old['phone'] ?? $driver['phone']) ?>">
                <?php if (!empty($errors['phone'])): ?>
                    <div class="field-msg is-error"><?= e($errors['phone']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Водительское удостоверение</h3>

            <div class="field">
                <label class="field-label">Номер</label>
                <input type="text" name="license_number" class="field-input"
                       value="<?= e($old['license_number'] ?? $driver['license_number'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Категория</label>
                <input type="text" name="license_category" class="field-input"
                       value="<?= e($old['license_category'] ?? $driver['license_category'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Дата выдачи</label>
                <input type="date" name="license_issue_date" class="field-input"
                       value="<?= e($old['license_issue_date'] ?? $driver['license_issue_date'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Дата окончания</label>
                <input type="date" name="license_expire_date" class="field-input"
                       value="<?= e($old['license_expire_date'] ?? $driver['license_expire_date'] ?? '') ?>">
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Паспортные данные</h3>

            <?php
            // Determine passport series and number for pre-fill
            if (!empty($old)) {
                // Form was submitted — use form values
                $passportSeries = $old['passport_series'] ?? '';
                $passportNumber = $old['passport_number'] ?? '';
            } else {
                // First load — split DB value if combined
                $storedPassport = $driver['passport_number'] ?? '';
                if (!empty($storedPassport) && strpos($storedPassport, ' ') !== false) {
                    [$passportSeries, $passportNumber] = explode(' ', $storedPassport, 2);
                } else {
                    $passportSeries = '';
                    $passportNumber = $storedPassport;
                }
            }
            ?>

            <div class="frm-row">
                <div class="field">
                    <label class="field-label">Серия паспорта</label>
                    <input type="text" name="passport_series" class="field-input" maxlength="4" placeholder="4 цифры"
                           value="<?= e($passportSeries) ?>">
                </div>
                <div class="field">
                    <label class="field-label">Номер паспорта</label>
                    <input type="text" name="passport_number" class="field-input" maxlength="6" placeholder="6 цифр"
                           value="<?= e($passportNumber) ?>">
                </div>
            </div>

            <div class="frm-row">
                <div class="field">
                    <label class="field-label">Дата выдачи</label>
                    <input type="date" name="passport_issue_date" class="field-input"
                           value="<?= e($old['passport_issue_date'] ?? $driver['passport_issue_date'] ?? '') ?>">
                </div>
                <div class="field">
                    <label class="field-label">Код подразделения</label>
                    <input type="text" name="passport_department_code" class="field-input" maxlength="10"
                           value="<?= e($old['passport_department_code'] ?? $driver['passport_department_code'] ?? '') ?>">
                </div>
            </div>

            <div class="field">
                <label class="field-label">Кем выдан</label>
                <input type="text" name="passport_issued_by" class="field-input"
                       value="<?= e($old['passport_issued_by'] ?? $driver['passport_issued_by'] ?? '') ?>">
            </div>

            <p class="field-hint">Все поля паспортных данных необязательные.</p>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Статус и комментарий</h3>

            <div class="field">
                <label class="field-label">Статус <span class="req">*</span></label>
                <select name="status" class="field-select<?= !empty($errors['status']) ? ' is-error' : '' ?>">
                    <?php
                    $statuses = ['active' => 'Активен', 'inactive' => 'Неактивен', 'archived' => 'Архив'];
                    $currentStatus = $old['status'] ?? $driver['status'];
                    foreach ($statuses as $val => $label):
                    ?>
                    <option value="<?= $val ?>" <?= $currentStatus === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['status'])): ?>
                    <div class="field-msg is-error"><?= e($errors['status']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Комментарий</label>
                <textarea name="comments" class="field-textarea" rows="3"><?= e($old['comments'] ?? $driver['comments'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Сохранить</button>
            <a href="/company/drivers/<?= $driver['id'] ?>" class="btn btn-ghost">Отмена</a>
        </div>

    </div>
</form>

<?php endif; ?>
