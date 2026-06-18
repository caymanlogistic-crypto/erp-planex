<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Создать перевозчика</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Создание перевозчиков недоступно.
</div>

<?php elseif ($success): ?>

<div class="page-head">
    <div>
        <h1>Перевозчик создан</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors" class="btn btn-primary">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Перевозчик успешно создан.
        </div>

        <div class="kv mt-4">
            <div class="kv-row">
                <span class="kv-key">Наименование</span>
                <span class="kv-value"><?= e($createdContractor['name']) ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">ИНН</span>
                <span class="kv-value"><code><?= e($createdContractor['inn']) ?></code></span>
            </div>
            <?php if (!empty($createdContractor['contractor_type'])): ?>
            <div class="kv-row">
                <span class="kv-key">Тип</span>
                <span class="kv-value"><?= e(ui_contractor_type($createdContractor['contractor_type'])) ?></span>
            </div>
            <?php endif; ?>
            <div class="kv-row">
                <span class="kv-key">Статус</span>
                <span class="kv-value">Активен</span>
            </div>
        </div>

        <div class="form-actions mt-4">
            <a href="/company/contractors" class="btn btn-primary">← К списку перевозчиков</a>
            <a href="/company/contractors/create" class="btn btn-ghost">Создать ещё</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Создать перевозчика</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<?php if ($formError): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/company/contractors/create" class="panel">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Основные данные</h3>

            <div class="field">
                <label class="field-label">Наименование <span class="req">*</span></label>
                <input type="text" name="name" class="field-input" required
                       value="<?= e($old['name'] ?? '') ?>">
                <?php if (!empty($errors['name'])): ?>
                    <div class="field-msg is-error"><?= e($errors['name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label">ИНН <span class="req">*</span></label>
                    <input type="text" name="inn" class="field-input"
                           value="<?= e($old['inn'] ?? '') ?>">
                    <?php if (!empty($errors['inn'])): ?>
                        <div class="field-msg is-error"><?= e($errors['inn']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label class="field-label">КПП</label>
                    <input type="text" name="kpp" class="field-input"
                           value="<?= e($old['kpp'] ?? '') ?>">
                </div>
            </div>

            <div class="field">
                <label class="field-label">ОГРН</label>
                <input type="text" name="ogrn" class="field-input"
                       value="<?= e($old['ogrn'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Тип перевозчика</label>
                <select name="contractor_type" class="field-input">
                    <option value="">— Не указан —</option>
                    <option value="legal_entity" <?= ($old['contractor_type'] ?? '') === 'legal_entity' ? 'selected' : '' ?>>Юридическое лицо</option>
                    <option value="individual" <?= ($old['contractor_type'] ?? '') === 'individual' ? 'selected' : '' ?>>Индивидуальный предприниматель</option>
                    <option value="self_employed" <?= ($old['contractor_type'] ?? '') === 'self_employed' ? 'selected' : '' ?>>Самозанятый</option>
                    <option value="private_person" <?= ($old['contractor_type'] ?? '') === 'private_person' ? 'selected' : '' ?>>Физическое лицо</option>
                </select>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Адреса</h3>

            <div class="field">
                <label class="field-label">Юридический адрес</label>
                <textarea name="legal_address" class="field-textarea" rows="2"><?= e($old['legal_address'] ?? '') ?></textarea>
            </div>

            <div class="field">
                <label class="field-label">Фактический адрес</label>
                <textarea name="physical_address" class="field-textarea" rows="2"><?= e($old['physical_address'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Контакты</h3>

            <div class="field">
                <label class="field-label">Контактное лицо</label>
                <input type="text" name="contact_person" class="field-input"
                       value="<?= e($old['contact_person'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Телефон</label>
                <input type="text" name="contact_phone" class="field-input"
                       value="<?= e($old['contact_phone'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Email</label>
                <input type="email" name="contact_email" class="field-input"
                       value="<?= e($old['contact_email'] ?? '') ?>">
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Банковские реквизиты</h3>

            <div class="field">
                <label class="field-label">Расчётный счёт</label>
                <input type="text" name="bank_account" class="field-input"
                       value="<?= e($old['bank_account'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Банк</label>
                <input type="text" name="bank_name" class="field-input"
                       value="<?= e($old['bank_name'] ?? '') ?>">
            </div>

            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label">БИК</label>
                    <input type="text" name="bank_bik" class="field-input"
                           value="<?= e($old['bank_bik'] ?? '') ?>">
                </div>

                <div class="field">
                    <label class="field-label">Корр. счёт</label>
                    <input type="text" name="bank_corr_account" class="field-input"
                           value="<?= e($old['bank_corr_account'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Дополнительно</h3>

            <div class="field">
                <label class="field-label">Комментарий</label>
                <textarea name="comments" class="field-textarea" rows="3"><?= e($old['comments'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Создать перевозчика</button>
            <a href="/company/contractors" class="btn btn-ghost">Отмена</a>
        </div>

    </div>
</form>

<?php endif; ?>
