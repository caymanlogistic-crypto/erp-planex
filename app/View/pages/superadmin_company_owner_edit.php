<?php

require_once __DIR__ . '/../components/status_badge.php';

?>

<?php if ($company === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Компания не найдена. <a href="<?= app_url('/superadmin/companies') ?>">← К реестру</a>
        </div>
    </div>
</div>

<?php elseif ($owner === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Руководитель не создан. <a href="/superadmin/companies/<?= $company['id'] ?>/create-owner">Создать Руководителя</a>
        </div>
    </div>
</div>

<?php elseif (isset($dbError)): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice danger">
            <?= e($dbError) ?>
        </div>
        <div class="form-actions mt-4">
            <a href="/superadmin/companies/<?= $company['id'] ?>/owner" class="btn btn-ghost">← К карточке Руководителя</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">SUPERADMIN / <?= e($company['name']) ?></span>
        <span class="page-title">Редактировать Руководителя</span>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $company['id'] ?>/owner" class="btn btn-ghost">← К карточке</a>
    </div>
</div>

<div class="page-content">

<?php if (!empty($formError)): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/superadmin/companies/<?= $company['id'] ?>/owner/edit" class="panel">
    <div class="panel-body">

        <div class="form-section">
            <p class="section-title">Основные данные</p>

            <div class="field">
                <label class="field-label">ФИО <span class="req">*</span></label>
                <input type="text" name="full_name" class="field-input<?= !empty($errors['full_name']) ? ' is-error' : '' ?>" required
                       value="<?= e($old['full_name'] ?? $owner['full_name']) ?>">
                <?php if (!empty($errors['full_name'])): ?>
                    <div class="field-msg is-error"><?= e($errors['full_name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Логин <span class="req">*</span></label>
                <input type="text" name="login" class="field-input<?= !empty($errors['login']) ? ' is-error' : '' ?>" required
                       value="<?= e($old['login'] ?? $owner['login']) ?>"
                       placeholder="Латинские буквы, цифры, подчёркивание">
                <?php if (!empty($errors['login'])): ?>
                    <div class="field-msg is-error"><?= e($errors['login']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-grid-2">
                <div class="field<?= !empty($errors['email']) ? ' is-error' : '' ?>">
                    <label class="field-label">Email</label>
                    <input type="email" name="email" class="field-input"
                           value="<?= e($old['email'] ?? $owner['email'] ?? '') ?>">
                    <?php if (!empty($errors['email'])): ?>
                        <div class="field-msg is-error"><?= e($errors['email']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="field">
                    <label class="field-label">Телефон</label>
                    <input type="text" name="phone" class="field-input"
                           value="<?= e($old['phone'] ?? $owner['phone'] ?? '') ?>">
                </div>
            </div>

            <div class="field">
                <label class="field-label">Должность</label>
                <input type="text" name="position" class="field-input"
                       value="<?= e($old['position'] ?? $owner['position'] ?? '') ?>"
                       placeholder="Например: Генеральный директор">
            </div>

            <div class="field">
                <label class="field-label">Статус <span class="req">*</span></label>
                <select name="status" class="field-select<?= !empty($errors['status']) ? ' is-error' : '' ?>">
                    <?php
                    $statuses = ['active' => 'Активен', 'blocked' => 'Заблокирован'];
                    $currentStatus = $old['status'] ?? $owner['status'];
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
                <textarea name="comments" class="field-textarea" rows="3"><?= e($old['comments'] ?? $owner['comments'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Сохранить</button>
            <a href="/superadmin/companies/<?= $company['id'] ?>/owner" class="btn btn-ghost">Отмена</a>
        </div>

    </div>
</form>

</div><!-- /.page-content -->

<?php endif; ?>
