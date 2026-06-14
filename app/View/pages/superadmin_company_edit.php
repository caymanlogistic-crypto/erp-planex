<?php

require_once __DIR__ . '/../components/status_badge.php';

?>

<?php if ($company === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Компания не найдена. <a href="/superadmin/companies">← К реестру</a>
        </div>
    </div>
</div>

<?php elseif (isset($dbError)): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice danger">
            <?= e($dbError) ?>
        </div>
        <div class="form-actions" style="margin-top:16px">
            <a href="/superadmin/companies" class="btn btn-ghost">← К реестру</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Редактировать компанию</h1>
        <p class="text-muted"><?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $company['id'] ?>" class="btn btn-ghost">← К карточке</a>
    </div>
</div>

<?php if (!empty($formError)): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/superadmin/companies/<?= $company['id'] ?>/edit" class="panel">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Основные данные</h3>

            <div class="field">
                <label class="field-label">Название <span class="req">*</span></label>
                <input type="text" name="name" class="field-input<?= !empty($errors['name']) ? ' is-error' : '' ?>" required
                       value="<?= e($old['name'] ?? $company['name']) ?>">
                <?php if (!empty($errors['name'])): ?>
                    <div class="field-msg is-error"><?= e($errors['name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">ИНН <span class="req">*</span></label>
                <input type="text" name="inn" class="field-input<?= !empty($errors['inn']) ? ' is-error' : '' ?>" required
                       value="<?= e($old['inn'] ?? $company['inn']) ?>">
                <?php if (!empty($errors['inn'])): ?>
                    <div class="field-msg is-error"><?= e($errors['inn']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">КПП</label>
                <input type="text" name="kpp" class="field-input"
                       value="<?= e($old['kpp'] ?? $company['kpp'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">ОГРН</label>
                <input type="text" name="ogrn" class="field-input"
                       value="<?= e($old['ogrn'] ?? $company['ogrn'] ?? '') ?>">
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Адреса</h3>

            <div class="field">
                <label class="field-label">Юридический адрес</label>
                <textarea name="legal_address" class="field-textarea" rows="2"><?= e($old['legal_address'] ?? $company['legal_address'] ?? '') ?></textarea>
            </div>

            <div class="field">
                <label class="field-label">Фактический адрес</label>
                <textarea name="physical_address" class="field-textarea" rows="2"><?= e($old['physical_address'] ?? $company['physical_address'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Контакты</h3>

            <div class="field">
                <label class="field-label">Контактное лицо</label>
                <input type="text" name="contact_person" class="field-input"
                       value="<?= e($old['contact_person'] ?? $company['contact_person'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Телефон</label>
                <input type="text" name="contact_phone" class="field-input"
                       value="<?= e($old['contact_phone'] ?? $company['contact_phone'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Email</label>
                <input type="email" name="contact_email" class="field-input"
                       value="<?= e($old['contact_email'] ?? $company['contact_email'] ?? '') ?>">
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Статус и комментарий</h3>

            <div class="field">
                <label class="field-label">Статус <span class="req">*</span></label>
                <select name="status" class="field-select<?= !empty($errors['status']) ? ' is-error' : '' ?>">
                    <?php
                    $statuses = ['active' => 'Активен', 'inactive' => 'Неактивен', 'blocked' => 'Заблокирован', 'archived' => 'Архив'];
                    $currentStatus = $old['status'] ?? $company['status'];
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
                <textarea name="comments" class="field-textarea" rows="3"><?= e($old['comments'] ?? $company['comments'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Сохранить</button>
            <a href="/superadmin/companies/<?= $company['id'] ?>" class="btn btn-ghost">Отмена</a>
        </div>

    </div>
</form>

<?php endif; ?>
