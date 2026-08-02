<?php if (!empty($formError)): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<div class="modal-body">
<form method="post" action="<?= app_url('/company/logists/modal-create') ?>" id="company-user-create-form">
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

        <div class="form-grid-2">
            <div class="field<?= !empty($errors['email']) ? ' is-error' : '' ?>">
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

        <div class="field">
            <label class="field-label">Роль <span class="req">*</span></label>
            <select name="role_code" class="field-select<?= !empty($errors['role_code']) ? ' is-error' : '' ?>">
                <option value="logist" <?= ($old['role_code'] ?? 'logist') === 'logist' ? 'selected' : '' ?>>Логист</option>
                <option value="senior_logist" <?= ($old['role_code'] ?? '') === 'senior_logist' ? 'selected' : '' ?>>Логист+</option>
            </select>
            <?php if (!empty($errors['role_code'])): ?>
                <div class="field-msg is-error"><?= e($errors['role_code']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="field-label">Пароль</label>
            <div class="field-inline-group">
                <input type="password" name="password" class="field-input field-inline-grow code-hi"
                       data-company-user-password-field
                       value="<?= e($old['password'] ?? $generatedPassword ?? '') ?>"
                       placeholder="Оставьте пустым для автогенерации">
                <button type="button" class="btn btn-ghost btn-sm" data-company-user-password-toggle title="Показать/скрыть">Показать</button>
                <button type="button" class="btn btn-ghost btn-sm" data-company-user-password-copy title="Копировать">Копировать</button>
                <button type="button" class="btn btn-toolbar btn-nowrap" data-company-user-password-generate>Сгенерировать</button>
            </div>
            <div class="field-msg">Оставьте пустым — пароль будет сгенерирован автоматически.</div>
        </div>
    </div>

    <div class="form-section">
        <p class="section-title">Доступ</p>

        <div class="field">
            <label class="field-label">Статус <span class="req">*</span></label>
            <select name="status" class="field-select">
                <option value="active" <?= ($old['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Активен</option>
                <option value="blocked" <?= ($old['status'] ?? '') === 'blocked' ? 'selected' : '' ?>>Заблокирован</option>
            </select>
        </div>
    </div>
</form>
</div>
<div class="modal-foot is-spaced">
    <div class="modal-required-note"><span class="req">*</span> — обязательные поля</div>
    <div class="modal-foot-actions">
        <button type="button" class="btn btn-ghost" data-company-user-create-close>Отмена</button>
        <button type="submit" form="company-user-create-form" class="btn btn-primary">Создать пользователя</button>
    </div>
</div>
