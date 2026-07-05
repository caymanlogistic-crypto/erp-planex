<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1><?= $isEdit ? 'Редактировать тип документа' : 'Создать тип документа' ?></h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/company/document-types') ?>" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Редактирование типов документов недоступно.
</div>

<?php elseif ($success): ?>

<div class="page-head">
    <div>
        <h1><?= $isEdit ? 'Тип документа обновлён' : 'Тип документа создан' ?></h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/company/document-types') ?>" class="btn btn-primary">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            <?= $isEdit ? 'Тип документа успешно обновлён.' : 'Тип документа успешно создан.' ?>
        </div>

        <div class="kv mt-4">
            <div class="kv-row">
                <span class="kv-key">Название</span>
                <span class="kv-value"><?= e($createdType['name']) ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Код</span>
                <span class="kv-value"><code><?= e($createdType['code'] ?? '—') ?></code></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Тип сущности</span>
                <span class="kv-value"><?= e(ui_entity_type($createdType['entity_type'] ?? '') ?: 'Все') ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Категория</span>
                <span class="kv-value">
                    <?php if (($createdType['category'] ?? '') === 'predefined'): ?>
                        <span class="badge badge-ok">Предопределённый</span>
                    <?php else: ?>
                        <span class="badge badge-neutral">Пользовательский</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <div class="form-actions mt-4">
            <a href="<?= app_url('/company/document-types') ?>" class="btn btn-primary">← К списку типов</a>
            <a href="<?= app_url('/company/document-types/create') ?>" class="btn btn-ghost">Создать ещё</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1><?= $isEdit ? 'Редактировать тип документа' : 'Создать тип документа' ?></h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/company/document-types') ?>" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<?php if ($formError): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/company/document-types/<?= $isEdit ? $editId . '/edit' : 'create' ?>" class="panel">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Основные данные</h3>

            <div class="field">
                <label class="field-label">Название <span class="req">*</span></label>
                <input type="text" name="name" class="field-input" required
                       value="<?= e($old['name'] ?? $existingType['name'] ?? '') ?>"
                       placeholder="Например: Паспорт, Договор, СТС">
                <?php if (!empty($errors['name'])): ?>
                    <div class="field-msg is-error"><?= e($errors['name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">Код</label>
                <input type="text" name="code" class="field-input"
                       value="<?= e($old['code'] ?? $existingType['code'] ?? '') ?>"
                       placeholder="Например: passport, contract">
                <div class="field-msg">Необязательное поле. Используется для программной идентификации типа.</div>
                <?php if (!empty($errors['code'])): ?>
                    <div class="field-msg is-error"><?= e($errors['code']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Привязка к сущности</h3>

            <div class="field">
                <label class="field-label">Тип сущности</label>
                <select name="entity_type" class="field-input">
                    <option value="">— Все сущности —</option>
                    <option value="driver" <?= ($old['entity_type'] ?? $existingType['entity_type'] ?? '') === 'driver' ? 'selected' : '' ?>>Водитель</option>
                    <option value="vehicle_set" <?= ($old['entity_type'] ?? $existingType['entity_type'] ?? '') === 'vehicle_set' ? 'selected' : '' ?>>Транспорт</option>
                    <option value="vehicle_unit" <?= ($old['entity_type'] ?? $existingType['entity_type'] ?? '') === 'vehicle_unit' ? 'selected' : '' ?>>Транспортная единица</option>
                    <option value="contractor" <?= ($old['entity_type'] ?? $existingType['entity_type'] ?? '') === 'contractor' ? 'selected' : '' ?>>Перевозчик</option>
                    <option value="client" <?= ($old['entity_type'] ?? $existingType['entity_type'] ?? '') === 'client' ? 'selected' : '' ?>>Клиент</option>
                    <option value="crew" <?= ($old['entity_type'] ?? $existingType['entity_type'] ?? '') === 'crew' ? 'selected' : '' ?>>Экипаж</option>
                    <option value="driver_vehicle_block" <?= ($old['entity_type'] ?? $existingType['entity_type'] ?? '') === 'driver_vehicle_block' ? 'selected' : '' ?>>Водители+ТС</option>
                    <option value="linear_route" <?= ($old['entity_type'] ?? $existingType['entity_type'] ?? '') === 'linear_route' ? 'selected' : '' ?>>Линейный рейс</option>
                </select>
                <div class="field-msg">Оставьте пустым, если тип документа применим к любым сущностям.</div>
            </div>

            <div class="field">
                <label class="field-label">Порядок сортировки</label>
                <input type="number" name="sort_order" class="field-input"
                       value="<?= e($old['sort_order'] ?? $existingType['sort_order'] ?? '0') ?>"
                       min="0" max="999">
                <div class="field-msg">Определяет порядок отображения. Меньше — выше.</div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Сохранить' : 'Создать тип' ?></button>
            <a href="<?= app_url('/company/document-types') ?>" class="btn btn-ghost">Отмена</a>
        </div>

    </div>
</form>

<?php endif; ?>
