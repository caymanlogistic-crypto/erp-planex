<?php if ($company === null): ?>
<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>
<?php elseif ($company['status'] !== 'active'): ?>
<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Работа с настройками выписок недоступна.
</div>
<?php elseif (isset($dbError)): ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Настройки выписок</h1>
        <div class="page-summary"><span>Настройки импорта выписок по IMAP</span></div>
    </div>
</div>
<div class="notice warn">
    <?= e($dbError) ?>
</div>
<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Настройки выписок</h1>
        <div class="page-summary"><span>Настройки импорта выписок по IMAP</span></div>
    </div>
</div>

<div class="panel bank-settings-panel">
    <div class="panel-section">
        <?php $currSetting = $bankSettings[0] ?? null; ?>
        <form action="<?= app_url('/company/finance/bank-accounts/settings') ?>" method="post">
            <?= csrfField() ?>
            <input type="hidden" name="imap_ssl" value="1">
            <?php if ($currSetting): ?>
            <input type="hidden" name="setting_id" value="<?= (int)$currSetting['id'] ?>">
            <?php endif; ?>

            <div class="form-grid-3 bank-settings-form-grid">
                <div class="field">
                    <label class="field-label">Банк</label>
                    <select name="bank_code" class="field-select">
                        <option value="vtb" <?= $currSetting && $currSetting['bank_code'] === 'vtb' ? 'selected' : '' ?>>ВТБ</option>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">IMAP-хост</label>
                    <input type="text" name="imap_host" class="field-input" value="<?= e($currSetting['imap_host'] ?? '') ?>" placeholder="imap.example.ru">
                </div>
                <div class="field">
                    <label class="field-label">IMAP-порт</label>
                    <input type="number" name="imap_port" class="field-input" value="<?= (int)($currSetting['imap_port'] ?? 993) ?>" min="1" max="65535">
                    <div class="field-hint">TLS обязателен; сертификат сервера проверяется.</div>
                </div>
                <div class="field">
                    <label class="field-label">Имя пользователя</label>
                    <input type="text" name="username" class="field-input" value="<?= e($currSetting['username'] ?? '') ?>" placeholder="mailbox@example.ru">
                </div>
                <div class="field">
                    <label class="field-label">Пароль</label>
                    <input type="password" name="password" class="field-input" placeholder="<?= $currSetting && $currSetting['has_password'] ? '•••••• (оставьте пустым, чтобы сохранить)' : '' ?>" autocomplete="off">
                </div>
                <div class="field">
                    <label class="field-label">Фильтр отправителя</label>
                    <input type="text" name="sender_filter" class="field-input" value="<?= e($currSetting['sender_filter'] ?? 'vtb-inform@vtb.ru') ?>">
                </div>
                <div class="field span-2">
                    <label class="field-label">Фильтр темы</label>
                    <input type="text" name="subject_filter" class="field-input" value="<?= e($currSetting['subject_filter'] ?? 'Регулярная выписка') ?>">
                </div>
            </div>

            <div class="bank-settings-actions">
                <div class="form-toggle">
                    <input type="hidden" name="is_active" value="0">
                    <label class="checkbox-item">
                        <input type="checkbox" name="is_active" value="1" <?= !$currSetting || $currSetting['is_active'] ? 'checked' : '' ?>>
                        <span class="checkbox-mark"></span>
                        <span class="checkbox-label">Активно</span>
                    </label>
                </div>
                <button type="submit" class="btn btn-primary">Сохранить настройки</button>
            </div>
        </form>
    </div>
</div>

<?php endif; ?>
