<?php

$category = $category ?? null;
$parentCategories = $parentCategories ?? [];
$formError = $formError ?? null;
$isEdit = $category !== null;
$action = $isEdit ? app_url('/company/finance/settings/dds-categories/edit') : app_url('/company/finance/settings/dds-categories/create');
?>
<?php if ($formError): ?>
<div class="form-alert alert-error"><?= e($formError) ?></div>
<?php endif; ?>
<form action="<?= $action ?>" method="post" class="dds-category-form">
    <?= csrfField() ?>
    <?php if ($isEdit): ?>
    <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
    <?php endif; ?>
    <div class="modal-body">
        <div class="section-title"><?= $isEdit ? 'Редактирование статьи ДДС' : 'Новая статья ДДС' ?></div>
        <div class="form-grid-3">
            <div class="field">
                <label class="field-label">Код <span class="field-required">*</span></label>
                <input type="text" name="code" class="field-input" placeholder="INCOME_CODE" required
                    value="<?= e($isEdit ? $category['code'] : '') ?>"
                    <?= $isEdit && ($category['is_system'] ?? 0) ? 'readonly' : '' ?>>
            </div>
            <div class="field">
                <label class="field-label">Название <span class="field-required">*</span></label>
                <input type="text" name="name" class="field-input" placeholder="Название статьи" required
                    value="<?= e($isEdit ? $category['name'] : '') ?>">
            </div>
            <div class="field">
                <label class="field-label">Направление <span class="field-required">*</span></label>
                <select name="direction" class="field-select" required>
                    <option value="INCOME" <?= $isEdit && $category['direction'] === 'INCOME' ? 'selected' : '' ?>>Поступление</option>
                    <option value="EXPENSE" <?= $isEdit && $category['direction'] === 'EXPENSE' ? 'selected' : '' ?>>Расход</option>
                    <option value="BOTH" <?= $isEdit && $category['direction'] === 'BOTH' ? 'selected' : '' ?>>Оба направления</option>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Сортировка</label>
                <input type="number" name="sort_order" class="field-input" value="<?= (int) ($isEdit ? $category['sort_order'] : 100) ?>" min="0">
            </div>
            <div class="field">
                <label class="field-label">Родительская категория</label>
                <select name="parent_id" class="field-select">
                    <option value="">— Нет —</option>
                    <?php foreach ($parentCategories as $pc): ?>
                        <?php if ($isEdit && (int) $pc['id'] === (int) $category['id']) continue; ?>
                    <option value="<?= (int) $pc['id'] ?>" <?= $isEdit && (int) ($category['parent_id'] ?? 0) === (int) $pc['id'] ? 'selected' : '' ?>>
                        <?= e($pc['code']) ?> — <?= e($pc['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
    <div class="modal-foot is-spaced">
        <div class="modal-foot-actions">
            <button type="button" class="btn btn-ghost" data-close-modal="<?= $isEdit ? 'dds-category-edit-modal' : 'dds-category-create-modal' ?>">Отмена</button>
        </div>
        <div class="modal-foot-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Сохранить' : 'Создать' ?></button>
        </div>
    </div>
</form>
