<?php

$rule = $rule ?? null;
$bankAccounts = $bankAccounts ?? [];
$ddsCategories = $ddsCategories ?? [];
$contractors = $contractors ?? [];
$formError = $formError ?? null;
$isEdit = $rule !== null;
$action = $isEdit ? app_url('/company/finance/settings/matching-rules/edit') : app_url('/company/finance/settings/matching-rules/create');
?>
<?php if ($formError): ?>
<div class="form-alert alert-error"><?= e($formError) ?></div>
<?php endif; ?>
<form action="<?= $action ?>" method="post" class="matching-rule-form">
    <?= csrfField() ?>
    <?php if ($isEdit): ?>
    <input type="hidden" name="id" value="<?= (int)$rule['id'] ?>">
    <?php endif; ?>
    <div class="modal-body">
        <div class="section-title"><?= $isEdit ? 'Редактирование правила разнесения' : 'Новое правило разнесения' ?></div>
        <div class="form-grid-3">
            <div class="field">
                <label class="field-label">Название <span class="field-required">*</span></label>
                <input type="text" name="name" class="field-input" placeholder="Например: Комиссия банка" required
                    value="<?= e($isEdit ? $rule['name'] : '') ?>">
            </div>
            <div class="field">
                <label class="field-label">Приоритет</label>
                <input type="number" name="priority" class="field-input" value="<?= (int)($isEdit ? $rule['priority'] : 100) ?>" min="1">
            </div>
            <div class="field">
                <label class="field-label">Направление</label>
                <select name="direction" class="field-select">
                    <option value="">— Оба направления —</option>
                    <option value="INCOME" <?= $isEdit && $rule['direction'] === 'INCOME' ? 'selected' : '' ?>>Поступление</option>
                    <option value="EXPENSE" <?= $isEdit && $rule['direction'] === 'EXPENSE' ? 'selected' : '' ?>>Расход</option>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Банковский счёт</label>
                <select name="bank_account_id" class="field-select">
                    <option value="">— Любой —</option>
                    <?php foreach ($bankAccounts as $ba): ?>
                    <option value="<?= (int)$ba['id'] ?>" <?= $isEdit && (int)($rule['bank_account_id'] ?? 0) === (int)$ba['id'] ? 'selected' : '' ?>>
                        <?= e($ba['bank_name'] ?? '') ?> — <?= e($ba['account_number'] ?? '') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label">ИНН контрагента</label>
                <input type="text" name="counterparty_inn" class="field-input" placeholder="Точный ИНН"
                    value="<?= e($isEdit ? $rule['counterparty_inn'] ?? '' : '') ?>">
            </div>
            <div class="field">
                <label class="field-label">Тип действия <span class="field-required">*</span></label>
                <select name="action_type" class="field-select" required>
                    <option value="categorize" <?= $isEdit && $rule['action_type'] === 'categorize' ? 'selected' : '' ?>>Назначить категорию ДДС</option>
                    <option value="match_counterparty" <?= $isEdit && $rule['action_type'] === 'match_counterparty' ? 'selected' : '' ?>>Связать контрагента</option>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Целевая статья ДДС</label>
                <select name="target_dds_category_id" class="field-select">
                    <option value="">— Не назначать —</option>
                    <?php foreach ($ddsCategories as $dc): ?>
                    <option value="<?= (int)$dc['id'] ?>" <?= $isEdit && (int)($rule['target_dds_category_id'] ?? 0) === (int)$dc['id'] ? 'selected' : '' ?>>
                        <?= e($dc['code'] ?? '') ?> — <?= e($dc['name'] ?? '') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Целевой контрагент</label>
                <select name="target_counterparty_id" class="field-select">
                    <option value="">— Не назначать —</option>
                    <?php foreach ($contractors as $cp): ?>
                    <option value="<?= (int)$cp['id'] ?>" <?= $isEdit && (int)($rule['target_counterparty_id'] ?? 0) === (int)$cp['id'] ? 'selected' : '' ?>>
                        <?= e($cp['name'] ?? '') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Содержит в назначении</label>
                <input type="text" name="purpose_contains" class="field-input" placeholder="Часть текста назначения"
                    value="<?= e($isEdit ? $rule['purpose_contains'] ?? '' : '') ?>">
            </div>
            <div class="field">
                <label class="field-label">Регулярное выражение</label>
                <input type="text" name="purpose_regex" class="field-input" placeholder="PCRE regex"
                    value="<?= e($isEdit ? $rule['purpose_regex'] ?? '' : '') ?>">
            </div>
            <div class="field">
                <label class="field-label">Сумма от</label>
                <input type="number" name="amount_from" class="field-input" step="0.01" min="0"
                    value="<?= e($isEdit && $rule['amount_from'] !== null ? $rule['amount_from'] : '') ?>">
            </div>
            <div class="field">
                <label class="field-label">Сумма до</label>
                <input type="number" name="amount_to" class="field-input" step="0.01" min="0"
                    value="<?= e($isEdit && $rule['amount_to'] !== null ? $rule['amount_to'] : '') ?>">
            </div>
            <div class="field">
                <label class="field-label">
                    <input type="checkbox" name="auto_apply" value="1"
                        <?= $isEdit && ($rule['auto_apply'] ?? 0) ? 'checked' : '' ?>>
                    Автоматически применять
                </label>
                <p class="field-note">Внимание: авто-применение только для безопасных однозначных совпадений.</p>
            </div>
        </div>
    </div>
    <div class="modal-foot is-spaced">
        <div class="modal-foot-actions">
            <button type="button" class="btn btn-ghost" data-close-modal="<?= $isEdit ? 'matching-rule-edit-modal' : 'matching-rule-create-modal' ?>">Отмена</button>
        </div>
        <div class="modal-foot-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Сохранить' : 'Создать' ?></button>
        </div>
    </div>
</form>
