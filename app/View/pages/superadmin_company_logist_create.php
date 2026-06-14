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
    <div>
        <h1>Создать логиста</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $company['id'] ?>/users" class="btn btn-ghost">← К пользователям</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Логист успешно создан.
        </div>

        <dl class="kv" style="margin-top:16px">
            <dt>ФИО</dt>
            <dd><?= e($old['full_name'] ?? '') ?></dd>
            <dt>Логин</dt>
            <dd><code><?= e($old['login'] ?? '') ?></code></dd>
            <dt>Временный пароль</dt>
            <dd><code style="background:var(--warning-bg);padding:2px 6px;border-radius:2px"><?= e($newPassword) ?></code></dd>
            <dt>Роль</dt>
            <dd>Логист</dd>
        </dl>

        <div class="notice warn" style="margin-top:16px">
            Временный пароль показан только один раз. Сохраните его сейчас. Пароль не хранится в открытом виде и не может быть восстановлен.
        </div>

        <div class="form-actions" style="margin-top:16px">
            <a href="/superadmin/companies/<?= $company['id'] ?>/users" class="btn btn-secondary">← К пользователям</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Создать логиста</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $company['id'] ?>/users" class="btn btn-ghost">← К пользователям</a>
    </div>
</div>

<?php if (!empty($formError)): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" class="panel">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Основные данные</h3>

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
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Создать</button>
            <a href="/superadmin/companies/<?= $company['id'] ?>/users" class="btn btn-ghost">Отмена</a>
        </div>

    </div>
</form>

<?php endif; ?>
