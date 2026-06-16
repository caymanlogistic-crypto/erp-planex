<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Редактировать пользователя</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?> (ID: <?= $company['id'] ?>)</p>
    </div>
    <div class="page-head-actions">
        <a href="/company/logists" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>».
</div>

<?php elseif ($logist === null): ?>

<div class="page-head">
    <div>
        <h1>Редактировать пользователя</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/logists" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Пользователь не найден.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Редактировать пользователя</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/logists/<?= $logist['id'] ?? '' ?>" class="btn btn-ghost">← К карточке пользователя</a>
    </div>
</div>

<div class="notice danger">
    <?= e($dbError) ?>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Редактировать пользователя</h1>
        <p class="text-muted"><?= e($logist['full_name']) ?> — Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/logists/<?= $logist['id'] ?>" class="btn btn-ghost">← К карточке пользователя</a>
    </div>
</div>

<?php if (!empty($formError)): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/company/logists/<?= $logist['id'] ?>/edit" class="panel">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Основные данные</h3>

            <div class="field">
                <label class="field-label">ФИО <span class="req">*</span></label>
                <input type="text" name="full_name" class="field-input<?= !empty($errors['full_name']) ? ' is-error' : '' ?>" required
                       value="<?= e($old['full_name'] ?? $logist['full_name']) ?>">
                <?php if (!empty($errors['full_name'])): ?>
                    <div class="field-msg is-error"><?= e($errors['full_name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Логин <span class="req">*</span></label>
                <input type="text" name="login" class="field-input<?= !empty($errors['login']) ? ' is-error' : '' ?>" required
                       value="<?= e($old['login'] ?? $logist['login']) ?>"
                       placeholder="Латинские буквы, цифры, подчёркивание">
                <?php if (!empty($errors['login'])): ?>
                    <div class="field-msg is-error"><?= e($errors['login']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Email</label>
                <input type="email" name="email" class="field-input<?= !empty($errors['email']) ? ' is-error' : '' ?>"
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

            <div class="field">
                <label class="field-label">Роль <span class="req">*</span></label>
                <select name="role_code" class="field-select<?= !empty($errors['role_code']) ? ' is-error' : '' ?>">
                    <?php
                    $currentRole = $old['role_code'] ?? $logist['role_code'] ?? 'logist';
                    ?>
                    <option value="logist" <?= $currentRole === 'logist' ? 'selected' : '' ?>>Логист</option>
                </select>
                <?php if (!empty($errors['role_code'])): ?>
                    <div class="field-msg is-error"><?= e($errors['role_code']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Статус <span class="req">*</span></label>
                <select name="status" class="field-select<?= !empty($errors['status']) ? ' is-error' : '' ?>">
                    <?php
                    $statuses = ['active' => 'Активен', 'blocked' => 'Заблокирован'];
                    $currentStatus = $old['status'] ?? $logist['status'];
                    foreach ($statuses as $val => $label):
                    ?>
                    <option value="<?= $val ?>" <?= $currentStatus === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['status'])): ?>
                    <div class="field-msg is-error"><?= e($errors['status']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Сохранить</button>
            <a href="/company/logists/<?= $logist['id'] ?>" class="btn btn-ghost">Отмена</a>
        </div>

    </div>
</form>

<?php endif; ?>
