<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Создать пользователя</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/logists" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Создание пользователей недоступно.
</div>

<?php elseif ($success): ?>

<div class="page-head">
    <div>
        <h1>Пользователь создан</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/logists" class="btn btn-primary">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Пользователь успешно создан. Ниже — данные для передачи.
        </div>

        <div class="kv mt-4">
            <div class="kv-row">
                <span class="kv-key">Компания</span>
                <span class="kv-value"><?= e($company['name']) ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">ФИО</span>
                <span class="kv-value"><?= e($createdLogist['full_name']) ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Логин</span>
                <span class="kv-value"><code><?= e($createdLogist['login']) ?></code></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Временный пароль</span>
                <span class="kv-value">
                    <code class="code-hi"><?= e($tempPassword) ?></code>
                </span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Роль</span>
                <span class="kv-value"><?php $rc = $createdLogist['role_code'] ?? 'logist'; echo e($rc === 'logist' ? 'Логист' : ($rc === 'senior_logist' ? 'Логист+' : $rc)) ?></span>
            </div>
        </div>

        <div class="notice warn mt-4">
            Временный пароль показан только один раз. Сохраните его или передайте пользователю сейчас.
            Пароль не хранится в открытом виде и не может быть восстановлен.
        </div>

        <div class="form-actions mt-4">
            <a href="/company/logists" class="btn btn-primary">← К списку пользователей</a>
            <a href="/company/logists/create" class="btn btn-ghost">Создать ещё</a>
        </div>
    </div>
</div>

<?php else: ?>

<?php $generatedPassword = $generatedPassword ?? generatePassword(); ?>

<div class="page-head">
    <div>
        <h1>Создать пользователя</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/logists" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<?php if ($formError): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/company/logists/create" class="panel">
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
                <label class="field-label">Логин <span class="req">*</span></label>
                <input type="text" name="login" class="field-input" required
                       value="<?= e($old['login'] ?? '') ?>"
                       placeholder="Латинские буквы, цифры, подчёркивание">
                <?php if (!empty($errors['login'])): ?>
                    <div class="field-msg is-error"><?= e($errors['login']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Пароль</label>
                <div class="field-inline-group">
                    <input type="password" name="password" id="password_field" class="field-input field-inline-grow code-hi"
                           value="<?= e($old['password'] ?? $generatedPassword ?? '') ?>"
                           placeholder="Оставьте пустым для автогенерации">
                    <button type="button" class="btn btn-ghost btn-sm" onclick="var p=document.getElementById('password_field'); p.type=p.type==='password'?'text':'password';" style="margin-left:4px;">👁</button>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('password_field').value)" style="margin-left:4px;">📋 Копировать</button>
                    <button type="button" class="btn btn-ghost btn-sm btn-nowrap btn-align-top" onclick="generatePassword()">
                        Сгенерировать
                    </button>
                </div>
                <div class="field-msg field-msg-tight">
                    Если не заполнено — пароль будет сгенерирован автоматически. Сохраните пароль — после создания пользователя он не будет доступен повторно.
                </div>
                <?php if (!empty($errors['password'])): ?>
                    <div class="field-msg is-error"><?= e($errors['password']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Контакты</h3>

            <div class="field">
                <label class="field-label">Email</label>
                <input type="email" name="email" class="field-input"
                       value="<?= e($old['email'] ?? '') ?>">
                <?php if (!empty($errors['email'])): ?>
                    <div class="field-msg is-error"><?= e($errors['email']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Телефон</label>
                <input type="text" name="phone" class="field-input"
                       value="<?= e($old['phone'] ?? '') ?>">
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Роль</h3>

            <div class="field">
                <label class="field-label">Роль пользователя <span class="req">*</span></label>
                <select name="role_code" class="field-select<?= !empty($errors['role_code']) ? ' is-error' : '' ?>">
                    <option value="logist" <?= ($old['role_code'] ?? 'logist') === 'logist' ? 'selected' : '' ?>>Логист</option>
                    <option value="senior_logist" <?= ($old['role_code'] ?? '') === 'senior_logist' ? 'selected' : '' ?>>Логист+</option>
                </select>
                <?php if (!empty($errors['role_code'])): ?>
                    <div class="field-msg is-error"><?= e($errors['role_code']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Создать пользователя</button>
            <a href="/company/logists" class="btn btn-ghost">Отмена</a>
        </div>

    </div>
</form>

<script>
function generatePassword() {
    const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let pwd = '';
    for (let i = 0; i < 10; i++) {
        pwd += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.querySelector('input[name="password"]').value = pwd;
}
</script>

<?php endif; ?>
