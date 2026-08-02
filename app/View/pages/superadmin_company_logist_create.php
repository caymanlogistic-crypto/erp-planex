<?php if ($company === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            <?= e($formError ?? 'Компания не найдена.') ?> <a href="<?= app_url('/superadmin/companies') ?>">← К реестру</a>
        </div>
    </div>
</div>

<?php elseif ($success): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Пользователь создан</h1>
        <div class="page-summary"><span>SUPERADMIN / <?= e($company['name']) ?></span></div>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/superadmin/companies/' . $company['id'] . '/users') ?>" class="btn btn-ghost">← К пользователям</a>
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
            <dd><?= e(($old['role'] ?? 'logist') === 'senior_logist' ? 'Логист+' : 'Пользователь') ?></dd>
        </dl>

        <div class="notice warn">
            Временный пароль показан только один раз. Передайте его пользователю по безопасному каналу сейчас: пароль не хранится в открытом виде и не может быть восстановлен.
        </div>

        <div class="form-actions">
            <a href="<?= app_url('/superadmin/companies/' . $company['id'] . '/users') ?>" class="btn btn-secondary">← К пользователям</a>
        </div>
    </div>
</div>
</div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Создать пользователя</h1>
        <div class="page-summary"><span>SUPERADMIN / <?= e($company['name']) ?></span></div>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/superadmin/companies/' . $company['id'] . '/users') ?>" class="btn btn-ghost">← К пользователям</a>
    </div>
</div>

<div class="page-content">

<?php if (!empty($formError)): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" class="panel">
    <div class="panel-body">
        <div class="notice info">
            Создаётся обычный пользователь компании. Он получает рабочий доступ, но не заменяет руководителя и не является главным ответственным контактом компании.
        </div>

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
                <select name="role" class="field-select<?= !empty($errors['role']) ? ' is-error' : '' ?>">
                    <option value="logist" <?= ($old['role'] ?? 'logist') === 'logist' ? 'selected' : '' ?>>Пользователь</option>
                    <option value="senior_logist" <?= ($old['role'] ?? '') === 'senior_logist' ? 'selected' : '' ?>>Логист+</option>
                    <option value="company_owner" disabled>Руководитель (недоступно)</option>
                </select>
                <?php if (!empty($errors['role'])): ?>
                    <div class="field-msg is-error"><?= e($errors['role']) ?></div>
                <?php endif; ?>
            </div>

            <!-- Пароль -->
            <div class="field">
                <label class="field-label">Пароль</label>
                <div class="input-group">
                    <input type="password" name="password" id="password_field" class="field-input code-hi" placeholder="Оставьте пустым для автогенерации" value="<?= e($old['password'] ?? '') ?>">
                    <button type="button" class="btn btn-ghost btn-sm button-offset" onclick="var p=document.getElementById('password_field'); p.type=p.type==='password'?'text':'password';">👁</button>
                    <button type="button" class="btn btn-ghost btn-sm button-offset" onclick="navigator.clipboard.writeText(document.getElementById('password_field').value)">📋 Копировать</button>
                    <button type="button" class="btn btn-secondary btn-sm button-offset" onclick="generatePasswordField()">Сгенерировать</button>
                </div>
                <div class="field-msg">Оставьте пустым — пароль будет сгенерирован автоматически. Сохраните пароль — после создания пользователя он не будет доступен повторно.</div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Создать</button>
            <a href="<?= app_url('/superadmin/companies/' . $company['id'] . '/users') ?>" class="btn btn-ghost">Отмена</a>
        </div>

    </div>
</form>

</div><!-- /.page-content -->

<?php endif; ?>

<script>
function generatePasswordField() {
    var chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    var pwd = '';
    for (var i = 0; i < 10; i++) pwd += chars.charAt(Math.floor(Math.random() * chars.length));
    document.getElementById('password_field').value = pwd;
}
</script>
