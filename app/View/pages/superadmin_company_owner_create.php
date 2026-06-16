<?php if ($company === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Компания не найдена. <a href="/superadmin/companies">← К реестру</a>
        </div>
    </div>
</div>

<?php elseif ($ownerExists): ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">SUPERADMIN / <?= e($company['name']) ?></span>
        <span class="page-title">Создать Руководителя</span>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies" class="btn btn-ghost">← К реестру</a>
    </div>
</div>

<div class="page-content">
<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Руководитель для этой компании уже создан: <strong><?= e($existingOwner['full_name']) ?></strong> (логин: <?= e($existingOwner['login']) ?>).
            Дублирование невозможно.
        </div>
        <div class="form-actions">
            <a href="/superadmin/companies" class="btn btn-primary">← К реестру компаний</a>
        </div>
    </div>
</div>
</div>

<?php elseif ($success): ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">SUPERADMIN / <?= e($company['name']) ?></span>
        <span class="page-title">Руководитель создан</span>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies" class="btn btn-ghost">← К реестру</a>
    </div>
</div>

<div class="page-content">
<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Главный пользователь успешно создан. Ниже — данные для передачи Руководителю.
        </div>

        <dl class="kv">
            <dt>Компания</dt>
            <dd><?= e($company['name']) ?></dd>
            <dt>ФИО</dt>
            <dd><?= e($createdOwner['full_name']) ?></dd>
            <dt>Логин</dt>
            <dd><code><?= e($createdOwner['login']) ?></code></dd>
            <dt>Временный пароль</dt>
            <dd><code class="code-hi"><?= e($tempPassword) ?></code></dd>
            <dt>Роль</dt>
            <dd>Руководитель</dd>
        </dl>

        <div class="notice warn">
            Временный пароль показан только один раз. Сохраните его или передайте Руководителю сейчас.
            Пароль не хранится в открытом виде и не может быть восстановлен.
        </div>

        <div class="form-actions">
            <a href="/superadmin/companies" class="btn btn-primary">← К реестру компаний</a>
        </div>
    </div>
</div>
</div>

<?php else: ?>

<?php $generatedPassword = $generatedPassword ?? generatePassword(); ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">SUPERADMIN / <?= e($company['name']) ?></span>
        <span class="page-title">Создать Руководителя</span>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $company['id'] ?>" class="btn btn-ghost">← К карточке</a>
    </div>
</div>

<div class="page-content">

<?php if (!empty($formError)): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/superadmin/companies/<?= $company['id'] ?>/create-owner" class="panel">
    <div class="panel-body">

        <div class="form-section">
            <p class="section-title">Основные данные</p>

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
                <label class="field-label">Должность</label>
                <input type="text" name="position" class="field-input"
                       value="<?= e($old['position'] ?? '') ?>"
                       placeholder="Например: Генеральный директор">
            </div>

            <div class="field">
                <label class="field-label">Пароль</label>
                <div class="field-inline-group">
                    <input type="text" name="password" class="field-input field-inline-grow"
                           value="<?= e($old['password'] ?? $generatedPassword ?? '') ?>"
                           placeholder="Оставьте пустым для автогенерации">
                    <button type="button" class="btn btn-toolbar btn-nowrap" onclick="generatePassword()">
                        Сгенерировать
                    </button>
                </div>
                <div class="field-hint">
                    Если не заполнено — пароль будет сгенерирован автоматически.
                </div>
                <?php if (!empty($errors['password'])): ?>
                    <div class="field-msg is-error"><?= e($errors['password']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-section">
            <p class="section-title">Контакты (опционально)</p>

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
                <?php if (!empty($errors['phone'])): ?>
                    <div class="field-msg is-error"><?= e($errors['phone']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-section">
            <p class="section-title">Дополнительно</p>

            <div class="field">
                <label class="field-label">Комментарий</label>
                <textarea name="comments" class="field-textarea" rows="3"><?= e($old['comments'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Создать Руководителя</button>
            <a href="/superadmin/companies" class="btn btn-ghost">Отмена</a>
        </div>

    </div>
</form>

<script>
function generatePassword() {
    var chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    var pwd = '';
    for (var i = 0; i < 12; i++) {
        pwd += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.querySelector('[name="password"]').value = pwd;
}
</script>

</div><!-- /.page-content -->

<?php endif; ?>
