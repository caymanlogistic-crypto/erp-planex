<?php

require_once __DIR__ . '/../components/status_badge.php';

?>

<?php if ($company === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Компания не найдена. <a href="<?= app_url('/superadmin/companies') ?>">← К реестру</a>
        </div>
    </div>
</div>

<?php elseif (isset($dbError)): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice danger">
            <?= e($dbError) ?>
        </div>
        <div class="form-actions mt-4">
            <a href="<?= app_url('/superadmin/companies') ?>" class="btn btn-ghost">← К реестру</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">SUPERADMIN / <?= e($company['name']) ?></span>
        <span class="page-title">Редактировать компанию</span>
    </div>
    <div class="page-head-actions">
        <a href="/superadmin/companies/<?= $company['id'] ?>" class="btn btn-ghost">← К карточке</a>
    </div>
</div>

<div class="page-content">

<?php if (!empty($formError)): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/superadmin/companies/<?= $company['id'] ?>/edit" class="panel">
    <div class="panel-body">

        <div class="form-section">
            <p class="section-title">Основные данные</p>

            <div class="field">
                <label class="field-label">Название <span class="req">*</span></label>
                <input type="text" name="name" class="field-input<?= !empty($errors['name']) ? ' is-error' : '' ?>" required
                       value="<?= e($old['name'] ?? $company['name']) ?>">
                <?php if (!empty($errors['name'])): ?>
                    <div class="field-msg is-error"><?= e($errors['name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-grid-2">
                <div class="field<?= !empty($errors['inn']) ? ' is-error' : '' ?>">
                    <label class="field-label">ИНН <span class="req">*</span></label>
                    <input type="text" name="inn" class="field-input" required
                           value="<?= e($old['inn'] ?? $company['inn']) ?>">
                    <?php if (!empty($errors['inn'])): ?>
                        <div class="field-msg is-error"><?= e($errors['inn']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="field">
                    <label class="field-label">КПП</label>
                    <input type="text" name="kpp" class="field-input"
                           value="<?= e($old['kpp'] ?? $company['kpp'] ?? '') ?>">
                </div>
            </div>

            <div class="field">
                <label class="field-label">ОГРН</label>
                <input type="text" name="ogrn" class="field-input"
                       value="<?= e($old['ogrn'] ?? $company['ogrn'] ?? '') ?>">
            </div>
        </div>

        <div class="form-section">
            <p class="section-title">Адреса</p>

            <div class="field">
                <label class="field-label">Юридический адрес</label>
                <textarea name="legal_address" class="field-textarea" rows="2"><?= e($old['legal_address'] ?? $company['legal_address'] ?? '') ?></textarea>
            </div>

            <div class="field">
                <label class="field-label">Фактический адрес</label>
                <textarea name="physical_address" class="field-textarea" rows="2"><?= e($old['physical_address'] ?? $company['physical_address'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-section">
            <p class="section-title">Руководитель</p>
            <div class="field">
                <label class="field-label">Должность</label>
                <input type="text" name="director_position" class="field-input"
                       value="<?= e($old['director_position'] ?? $company['director_position'] ?? '') ?>"
                       placeholder="Например: Генеральный директор">
            </div>
            <div class="field">
                <label class="field-label">ФИО руководителя</label>
                <input type="text" name="director_full_name" class="field-input"
                       value="<?= e($old['director_full_name'] ?? $company['director_full_name'] ?? '') ?>"
                       placeholder="Иванов Иван Иванович">
            </div>
            <div class="field-hint">Эти данные используются в реквизитах, счетах и документах. Доступ в ERP для руководителя создаётся отдельно.</div>
        </div>

        <div class="form-section">
            <p class="section-title">Статус и комментарий</p>

            <div class="field">
                <label class="field-label">Статус <span class="req">*</span></label>
                <select name="status" class="field-select<?= !empty($errors['status']) ? ' is-error' : '' ?>">
                    <?php
                    $statuses = ['active' => 'Активен', 'inactive' => 'Неактивен', 'blocked' => 'Заблокирован', 'archived' => 'Архив'];
                    $currentStatus = $old['status'] ?? $company['status'];
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
                <textarea name="comments" class="field-textarea" rows="3"><?= e($old['comments'] ?? $company['comments'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Сохранить</button>
            <a href="/superadmin/companies/<?= $company['id'] ?>" class="btn btn-ghost">Отмена</a>
        </div>

    </div>
</form>

</div><!-- /.page-content -->

<?php endif; ?>
