<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">SUPERADMIN / Реестр компаний</span>
        <span class="page-title">Создать экспедитора</span>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies" class="btn btn-ghost">&larr; К реестру</a>
    </div>
</div>

<div class="page-content">

<?php if (isset($formError) && $formError !== null): ?>
    <div class="notice warn">
        <?= e($formError) ?>
    </div>
<?php endif; ?>

<form method="post" action="/superadmin/companies/create" class="panel">
    <div class="panel-body">

        <div class="form-section">
            <p class="section-title">Основные данные</p>
            <div class="field<?= isset($errors['name']) ? ' is-error' : '' ?>">
                <label class="field-label">Наименование <span class="req">*</span></label>
                <input type="text" name="name" class="field-input" required value="<?= e($old['name'] ?? '') ?>">
                <div class="field-msg"<?= isset($errors['name']) ? '' : ' style="display:none"' ?>><?= e($errors['name'] ?? '') ?></div>
            </div>
            <div class="form-grid-2">
                <div class="field<?= isset($errors['inn']) ? ' is-error' : '' ?>">
                    <label class="field-label">ИНН <span class="req">*</span></label>
                    <input type="text" name="inn" class="field-input" required value="<?= e($old['inn'] ?? '') ?>">
                    <div class="field-msg"<?= isset($errors['inn']) ? '' : ' style="display:none"' ?>><?= e($errors['inn'] ?? '') ?></div>
                </div>
                <div class="field">
                    <label class="field-label">КПП</label>
                    <input type="text" name="kpp" class="field-input" value="<?= e($old['kpp'] ?? '') ?>">
                </div>
            </div>
            <div class="field">
                <label class="field-label">ОГРН</label>
                <input type="text" name="ogrn" class="field-input" value="<?= e($old['ogrn'] ?? '') ?>">
            </div>
        </div>

        <div class="form-section">
            <p class="section-title">Адреса</p>
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
            <p class="section-title">Руководитель</p>
            <div class="field">
                <label class="field-label">Должность</label>
                <input type="text" name="director_position" class="field-input" value="<?= e($old['director_position'] ?? '') ?>" placeholder="Например: Генеральный директор">
            </div>
            <div class="field">
                <label class="field-label">ФИО руководителя</label>
                <input type="text" name="director_full_name" class="field-input" value="<?= e($old['director_full_name'] ?? '') ?>" placeholder="Иванов Иван Иванович">
            </div>
            <div class="field-hint">Эти данные используются в реквизитах, счетах и документах. Доступ в ERP для руководителя создаётся отдельно.</div>
        </div>

        <div class="form-section">
            <p class="section-title">Дополнительно</p>
            <div class="field">
                <label class="field-label">Комментарий</label>
                <textarea name="comments" class="field-textarea"><?= e($old['comments'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Создать компанию</button>
            <a href="/superadmin/companies" class="btn btn-ghost">Отмена</a>
        </div>

    </div>
</form>

</div><!-- /.page-content -->
