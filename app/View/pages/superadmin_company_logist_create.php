<?php if ($company === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            <?= e($formError ?? 'Компания не найдена.') ?> <a href="/superadmin/companies">← К реестру</a>
        </div>
    </div>
</div>

<?php elseif ($success): ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">SUPERADMIN / <?= e($company['name']) ?></span>
        <span class="page-title">Пользователь создан</span>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $company['id'] ?>/users" class="btn btn-ghost">← К пользователям</a>
    </div>
</div>

<div class="page-content">
<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Пользователь успешно создан.
        </div>

        <dl class="kv">
            <dt>ФИО</dt>
            <dd><?= e($old['full_name'] ?? '') ?></dd>
            <dt>Логин</dt>
            <dd><code><?= e($old['login'] ?? '') ?></code></dd>
            <dt>Временный пароль</dt>
            <dd><code class="code-hi"><?= e($newPassword) ?></code></dd>
            <dt>Роль</dt>
            <dd><?= e($old['role_label'] ?? 'Логист') ?></dd>
        </dl>

        <div class="notice warn">
            Временный пароль показан только один раз. Сохраните его сейчас. Пароль не хранится в открытом виде и не может быть восстановлен.
        </div>

        <div class="form-actions">
            <a href="/superadmin/companies/<?= $company['id'] ?>/users" class="btn btn-secondary">← К пользователям</a>
        </div>
    </div>
</div>
</div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">SUPERADMIN / <?= e($company['name']) ?></span>
        <span class="page-title">Создать пользователя</span>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $company['id'] ?>/users" class="btn btn-ghost">← К пользователям</a>
    </div>
</div>

<div class="page-content">

<?php if (!empty($formError)): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" class="panel">
    <div class="panel-body">

        <div class="form-section">
            <p class="section-title">Основные данные</p>

            <div class="field">
                <label class="field-label">ФИО <span class="req">*</span></label>
                <input type="text" name="full_name" class="field-input<?= !empty($errors['full_name']) ? ' is-error' : '' ?>" required
                       value="<?= e($old['full_name'] ?? '') ?>">
                <?php if (!empty($errors['full_name'])): ?>
                    <div class="field-msg is-error"><?= e($errors['full_name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Логин <span class="req">*</span></label>
                <input type="text" name="login" class="field-input<?= !empty($errors['login']) ? ' is-error' : '' ?>" required
                       value="<?= e($old['login'] ?? '') ?>"
                       placeholder="Латинские буквы, цифры, подчёркивание">
                <?php if (!empty($errors['login'])): ?>
                    <div class="field-msg is-error"><?= e($errors['login']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Email</label>
                <input type="email" name="email" class="field-input"
                       value="<?= e($old['email'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Телефон</label>
                <input type="text" name="phone" class="field-input"
                       value="<?= e($old['phone'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Роль <span class="req">*</span></label>
                <select name="role_code" class="field-select<?= !empty($errors['role_code']) ? ' is-error' : '' ?>">
                    <option value="logist" <?= ($old['role_code'] ?? 'logist') === 'logist' ? 'selected' : '' ?>>Логист</option>
                    <!-- DESIGN_TODO: company_owner/Руководитель — ждёт решения по multi-owner -->
                    <option value="company_owner" disabled>Руководитель (недоступно)</option>
                </select>
                <?php if (!empty($errors['role_code'])): ?>
                    <div class="field-msg is-error"><?= e($errors['role_code']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Создать</button>
            <a href="/superadmin/companies/<?= $companyId ?>/users" class="btn btn-ghost">Отмена</a>
        </div>

    </div>
</form>

</div><!-- /.page-content -->

<?php endif; ?>
