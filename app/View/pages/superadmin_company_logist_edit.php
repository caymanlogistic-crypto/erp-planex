<?php

require_once __DIR__ . '/../components/status_badge.php';

?>
<?php if ($company === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            <?= e($formError ?? 'Компания не найдена.') ?> <a href="/superadmin/companies">← К реестру</a>
        </div>
    </div>
</div>

<?php elseif (!empty($formError) && $logist === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            <?= e($formError) ?>
        </div>
        <div class="form-actions mt-4">
            <a href="/superadmin/companies/<?= $companyId ?>/users" class="btn btn-ghost">← К пользователям</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">SUPERADMIN / <?= e($company['name']) ?></span>
        <span class="page-title">Редактировать пользователя</span>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $companyId ?>/users/logists/<?= $logistId ?>" class="btn btn-ghost">← К карточке</a>
    </div>
</div>

<div class="page-content">

<form method="post" action="/superadmin/companies/<?= $companyId ?>/users/logists/<?= $logistId ?>/edit" class="panel">
    <div class="panel-body">

        <div class="form-section">
            <p class="section-title">Основные данные</p>

            <div class="field">
                <label class="field-label">ФИО <span class="req">*</span></label>
                <input type="text" name="full_name" class="field-input" required
                       value="<?= e($old['full_name'] ?? $logist['full_name'] ?? '') ?>">
                <?php if (!empty($errors['full_name'])): ?>
                    <div class="field-msg is-error"><?= e($errors['full_name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Логин <span class="req">*</span></label>
                <input type="text" name="login" class="field-input" required
                       value="<?= e($old['login'] ?? $logist['login'] ?? '') ?>"
                       placeholder="Латинские буквы, цифры, подчёркивание">
                <?php if (!empty($errors['login'])): ?>
                    <div class="field-msg is-error"><?= e($errors['login']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-grid-2">
                <div class="field<?= !empty($errors['email']) ? ' is-error' : '' ?>">
                    <label class="field-label">Email</label>
                    <input type="email" name="email" class="field-input"
                           value="<?= e($old['email'] ?? $logist['email'] ?? '') ?>">
                    <?php if (!empty($errors['email'])): ?>
                        <div class="field-msg is-error"><?= e($errors['email']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="field">
                    <label class="field-label">Телефон</label>
                    <input type="text" name="phone" class="field-input"
                           value="<?= e($old['phone'] ?? $logist['phone'] ?? '') ?>">
                </div>
            </div>

            <div class="field">
                <label class="field-label">Роль <span class="req">*</span></label>
                <select name="role_code" class="field-select<?= !empty($errors['role_code']) ? ' is-error' : '' ?>">
                    <?php
                    $currentRole = $old['role_code'] ?? $logist['role_code'] ?? 'logist';
                    ?>
                    <option value="logist" <?= $currentRole === 'logist' ? 'selected' : '' ?>>Логист</option>
                    <!-- DESIGN_TODO: company_owner/Руководитель — ждёт решения по multi-owner -->
                    <option value="company_owner" disabled>Руководитель (недоступно)</option>
                </select>
                <?php if (!empty($errors['role_code'])): ?>
                    <div class="field-msg is-error"><?= e($errors['role_code']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-section">
            <p class="section-title">Статус и комментарий</p>

            <div class="field">
                <label class="field-label">Статус <span class="req">*</span></label>
                <select name="status" class="field-select">
                    <option value="active" <?= ($old['status'] ?? $logist['status'] ?? '') === 'active' ? 'selected' : '' ?>>Активен</option>
                    <option value="blocked" <?= ($old['status'] ?? $logist['status'] ?? '') === 'blocked' ? 'selected' : '' ?>>Заблокирован</option>
                    <option value="archived" <?= ($old['status'] ?? $logist['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Архивирован</option>
                </select>
            </div>

            <div class="field">
                <label class="field-label">Комментарий</label>
                <textarea name="comments" class="field-textarea" rows="3"><?= e($old['comments'] ?? $logist['comments'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Сохранить</button>
            <a href="/superadmin/companies/<?= $companyId ?>/users/logists/<?= $logistId ?>" class="btn btn-ghost">Отмена</a>
        </div>

    </div>
</form>

</div><!-- /.page-content -->

<?php endif; ?>
