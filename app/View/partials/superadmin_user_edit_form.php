<?php if (!empty($formError)): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="<?= app_url('/superadmin/companies/' . (int)$company['id'] . ($isOwner ? '/owner' : '/users/logists/' . (int)$user['id']) . '/modal-edit') ?>" id="user-edit-form">
    <div class="form-section">
        <p class="section-title">Основные данные</p>

        <div class="field">
            <label class="field-label">ФИО <span class="req">*</span></label>
            <input type="text" name="full_name" class="field-input<?= !empty($errors['full_name']) ? ' is-error' : '' ?>" required
                   value="<?= e($old['full_name'] ?? $user['full_name']) ?>">
            <?php if (!empty($errors['full_name'])): ?>
                <div class="field-msg is-error"><?= e($errors['full_name']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="field-label">Логин <span class="req">*</span></label>
            <input type="text" name="login" class="field-input<?= !empty($errors['login']) ? ' is-error' : '' ?>" required
                   value="<?= e($old['login'] ?? $user['login']) ?>"
                   placeholder="Латинские буквы, цифры, подчёркивание">
            <?php if (!empty($errors['login'])): ?>
                <div class="field-msg is-error"><?= e($errors['login']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-grid-2">
            <div class="field<?= !empty($errors['email']) ? ' is-error' : '' ?>">
                <label class="field-label">Email</label>
                <input type="email" name="email" class="field-input"
                       value="<?= e($old['email'] ?? $user['email'] ?? '') ?>">
                <?php if (!empty($errors['email'])): ?>
                    <div class="field-msg is-error"><?= e($errors['email']) ?></div>
                <?php endif; ?>
            </div>
            <div class="field">
                <label class="field-label">Телефон</label>
                <input type="text" name="phone" class="field-input"
                       value="<?= e($old['phone'] ?? $user['phone'] ?? '') ?>">
            </div>
        </div>

        <?php if ($isOwner): ?>
        <div class="field">
            <label class="field-label">Должность</label>
            <input type="text" name="position" class="field-input"
                   value="<?= e($old['position'] ?? $user['position'] ?? '') ?>"
                   placeholder="Например: Генеральный директор">
        </div>
        <?php else: ?>
        <div class="field">
            <label class="field-label">Роль <span class="req">*</span></label>
            <select name="role" class="field-select<?= !empty($errors['role']) ? ' is-error' : '' ?>">
                <?php
                $currentRole = $old['role'] ?? $user['role'] ?? 'logist';
                ?>
                <option value="logist" <?= $currentRole === 'logist' ? 'selected' : '' ?>>Логист</option>
                <option value="senior_logist" <?= $currentRole === 'senior_logist' ? 'selected' : '' ?>>Логист+</option>
                <option value="company_owner" disabled>Руководитель (недоступно)</option>
            </select>
            <?php if (!empty($errors['role'])): ?>
                <div class="field-msg is-error"><?= e($errors['role']) ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="form-section">
        <p class="section-title">Статус и комментарий</p>

        <div class="field">
            <label class="field-label">Статус <span class="req">*</span></label>
            <select name="status" class="field-select<?= !empty($errors['status']) ? ' is-error' : '' ?>">
                <?php
                $statuses = ['active' => 'Активен', 'blocked' => 'Заблокирован'];
                $currentStatus = $old['status'] ?? $user['status'];
                foreach ($statuses as $val => $label):
                ?>
                <option value="<?= $val ?>" <?= $currentStatus === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['status'])): ?>
                <div class="field-msg is-error"><?= e($errors['status']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="field-label">Комментарий</label>
            <textarea name="comments" class="field-textarea" rows="3"><?= e($old['comments'] ?? $user['comments'] ?? '') ?></textarea>
        </div>
    </div>
</form>
