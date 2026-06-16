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
    <div class="login-card-head">
        <h1>Вход в систему</h1>
        <p>Введите логин и пароль для доступа к рабочей среде.</p>
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
            <div class="alert-mark">!</div>
            <div class="alert-body">
                <div class="alert-body-title">Заполните все поля</div>
                <div class="alert-body-sub">Логин и пароль обязательны для входа.</div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($authError !== null): ?>
        <div class="form-alert alert-error auth-alert">
            <div class="alert-mark">!</div>
            <div class="alert-body">
                <div class="alert-body-title">Вход не выполнен</div>
                <div class="alert-body-sub"><?= e($authError) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($multiLogistError !== null): ?>
        <div class="form-alert alert-error auth-alert">
            <div class="alert-mark">!</div>
            <div class="alert-body">
                <div class="alert-body-title">Требуется помощь администратора</div>
                <div class="alert-body-sub"><?= e($multiLogistError) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <form method="post" action="/login" class="login-form" novalidate>
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

        <div class="auth-footer">Защищённый доступ к рабочей среде компании.</div>
    </div>
</div>
