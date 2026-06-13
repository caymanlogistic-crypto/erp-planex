<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Создать логиста</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/logists?company_id=<?= $companyId ?>" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Создание логистов недоступно.
</div>

<?php elseif ($success): ?>

<div class="page-head">
    <div>
        <h1>Логист создан</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/logists?company_id=<?= $companyId ?>" class="btn btn-primary">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Логист успешно создан. Ниже — данные для передачи.
        </div>

        <div class="kv" style="margin-top:16px">
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
                    <code style="background:var(--warn-bg);padding:2px 6px;border-radius:3px"><?= e($tempPassword) ?></code>
                </span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Роль</span>
                <span class="kv-value">Логист</span>
            </div>
        </div>

        <div class="notice warn" style="margin-top:16px">
            Временный пароль показан только один раз. Сохраните его или передайте логисту сейчас.
            Пароль не хранится в открытом виде и не может быть восстановлен.
        </div>

        <div class="form-actions" style="margin-top:16px">
            <a href="/company/logists?company_id=<?= $companyId ?>" class="btn btn-primary">← К списку логистов</a>
            <a href="/company/logists/create?company_id=<?= $companyId ?>" class="btn btn-ghost">Создать ещё</a>
        </div>
    </div>
</div>

<?php else: ?>

<?php $generatedPassword = $generatedPassword ?? generatePassword(); ?>

<div class="page-head">
    <div>
        <h1>Создать логиста</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/logists?company_id=<?= $companyId ?>" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<?php if ($formError): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/company/logists/create?company_id=<?= $companyId ?>" class="panel">
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
                <div style="display:flex;gap:8px;align-items:flex-start">
                    <input type="text" name="password" class="field-input"
                           value="<?= e($old['password'] ?? $generatedPassword ?? '') ?>"
                           placeholder="Оставьте пустым для автогенерации"
                           style="flex:1">
                    <button type="button" class="btn btn-toolbar" onclick="generatePassword()"
                            style="white-space:nowrap;margin-top:0">
                        Сгенерировать
                    </button>
                </div>
                <div class="field-msg" style="margin-top:4px">
                    Если не заполнено — пароль будет сгенерирован автоматически.
                </div>
                <?php if (!empty($errors['password'])): ?>
                    <div class="field-msg is-error"><?= e($errors['password']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Контакты (опционально)</h3>

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

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Создать логиста</button>
            <a href="/company/logists?company_id=<?= $companyId ?>" class="btn btn-ghost">Отмена</a>
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
