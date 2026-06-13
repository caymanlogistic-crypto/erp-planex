<div class="page-head">
    <div>
        <h1>Создать экспедитора</h1>
        <p class="text-muted">Новая локальная ERP-система</p>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies" class="btn btn-ghost">&larr; К реестру</a>
    </div>
</div>

<?php if (isset($formError) && $formError !== null): ?>
    <div class="notice warn">
        <?= e($formError) ?>
    </div>
<?php endif; ?>

<form method="post" action="/superadmin/companies/create" class="panel">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Основные данные</h3>
            <div class="field<?= isset($errors['name']) ? ' is-error' : '' ?>">
                <label class="field-label">Наименование <span class="req">*</span></label>
                <input type="text" name="name" class="field-input" required value="<?= e($old['name'] ?? '') ?>">
                <div class="field-msg"<?= isset($errors['name']) ? '' : ' style="display:none"' ?>><?= e($errors['name'] ?? '') ?></div>
            </div>
            <div class="field<?= isset($errors['inn']) ? ' is-error' : '' ?>">
                <label class="field-label">ИНН <span class="req">*</span></label>
                <input type="text" name="inn" class="field-input" required value="<?= e($old['inn'] ?? '') ?>">
                <div class="field-msg"<?= isset($errors['inn']) ? '' : ' style="display:none"' ?>><?= e($errors['inn'] ?? '') ?></div>
            </div>
            <div class="field">
                <label class="field-label">КПП</label>
                <input type="text" name="kpp" class="field-input" value="<?= e($old['kpp'] ?? '') ?>">
            </div>
            <div class="field">
                <label class="field-label">ОГРН</label>
                <input type="text" name="ogrn" class="field-input" value="<?= e($old['ogrn'] ?? '') ?>">
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Адреса</h3>
            <div class="field">
                <label class="field-label">Юридический адрес</label>
                <input type="text" name="legal_address" class="field-input" value="<?= e($old['legal_address'] ?? '') ?>">
            </div>
            <div class="field">
                <label class="field-label">Фактический адрес</label>
                <input type="text" name="physical_address" class="field-input" value="<?= e($old['physical_address'] ?? '') ?>">
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Контакты</h3>
            <div class="field">
                <label class="field-label">Контактное лицо</label>
                <input type="text" name="contact_person" class="field-input" value="<?= e($old['contact_person'] ?? '') ?>">
            </div>
            <div class="field">
                <label class="field-label">Телефон</label>
                <input type="text" name="contact_phone" class="field-input" value="<?= e($old['contact_phone'] ?? '') ?>">
            </div>
            <div class="field">
                <label class="field-label">Email</label>
                <input type="email" name="contact_email" class="field-input" value="<?= e($old['contact_email'] ?? '') ?>">
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
            <button type="submit" class="btn btn-primary">Создать экспедитора</button>
            <a href="/superadmin/companies" class="btn btn-ghost">Отмена</a>
        </div>
    </div>
</form>
