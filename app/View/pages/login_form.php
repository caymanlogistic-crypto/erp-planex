<?php

/**
 * Login form — all 5 states per login.md §5.
 *
 * Variables expected:
 *   $loginValue         — string, pre-filled login from previous attempt
 *   $errors             — array, validation errors keyed by field ('login', 'password')
 *   $authError          — string|null, «Неверный логин или пароль.»
 *   $multiLogistError   — string|null, «Логин найден в нескольких компаниях...»
 *   $devSeedPassword    — string|null, temporary SUPERADMIN password (shown once)
 */
?>
<div class="login-card">
    <div class="login-card-brand">
        <div class="login-brand">ERP PLANEX</div>
        <div class="login-brand-sub">Система управления транспортной логистикой</div>
    </div>
    <div class="login-card-head">
        <h1>Вход в систему</h1>
    </div>
    <div class="login-card-body">

        <?php if ($devSeedPassword !== null): ?>
        <div class="form-alert alert-success auth-alert">
            <div class="alert-mark">OK</div>
            <div class="alert-body">
                <div class="alert-body-title">Создан системный аккаунт</div>
                <div class="alert-body-sub">
                    Логин: <code>admin@planex.local</code><br>
                    Пароль: <code><?= e($devSeedPassword) ?></code><br>
                    Сохраните пароль. Он будет показан только один раз.
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
        <div class="form-alert alert-error auth-alert">
            <div class="alert-mark"><svg width="11" height="11" viewBox="0 0 18 18" fill="none"><path d="M9 2L16.5 15H1.5L9 2Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 8V11M9 13V13.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></div>
            <div class="alert-body">
                <div class="alert-body-title">Заполните все поля</div>
                <div class="alert-body-sub">Логин и пароль обязательны для входа.</div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($authError !== null): ?>
        <div class="form-alert alert-error auth-alert">
            <div class="alert-mark"><svg width="11" height="11" viewBox="0 0 18 18" fill="none"><path d="M9 2L16.5 15H1.5L9 2Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 8V11M9 13V13.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></div>
            <div class="alert-body">
                <div class="alert-body-title">Вход не выполнен</div>
                <div class="alert-body-sub"><?= e($authError) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($multiLogistError !== null): ?>
        <div class="form-alert alert-error auth-alert">
            <div class="alert-mark"><svg width="11" height="11" viewBox="0 0 18 18" fill="none"><path d="M9 2L16.5 15H1.5L9 2Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 8V11M9 13V13.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></div>
            <div class="alert-body">
                <div class="alert-body-title">Требуется помощь администратора</div>
                <div class="alert-body-sub"><?= e($multiLogistError) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <form method="post" action="<?= app_url('/login') ?>" class="login-form" novalidate>
            <div class="field<?= isset($errors['login']) ? ' is-error' : '' ?>">
                <label class="field-label" for="login">Логин</label>
                <input type="text" id="login" name="login"
                       class="field-input"
                       value="<?= e($loginValue ?? '') ?>"
                       autocomplete="username"
                       required>
                <?php if (isset($errors['login'])): ?>
                <div class="field-msg"><?= e($errors['login']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field<?= isset($errors['password']) ? ' is-error' : '' ?>">
                <label class="field-label" for="password">Пароль</label>
                <input type="password" id="password" name="password"
                       class="field-input"
                       autocomplete="current-password"
                       required>
                <?php if (isset($errors['password'])): ?>
                <div class="field-msg"><?= e($errors['password']) ?></div>
                <?php endif; ?>
            </div>

            <div class="login-actions">
                <button type="submit" class="btn btn-primary btn-full">Войти</button>
            </div>
        </form>

        <div class="auth-footer">© 2026 PLANEX</div>
    </div>
</div>
