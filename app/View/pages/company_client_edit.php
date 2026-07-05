<?php
require_once __DIR__ . '/../components/client_contact_fields.php';

$contactValues = $old['contacts'] ?? $contacts ?? [];
$contactErrors = $errors['contacts'] ?? [];
?>

<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Редактировать клиента</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/company/clients') ?>" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>».
</div>

<?php elseif ($client === null): ?>

<div class="page-head">
    <div>
        <h1>Редактировать клиента</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/company/clients') ?>" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Клиент не найден.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Редактировать клиента</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/clients/<?= $client['id'] ?? '' ?>" class="btn btn-ghost">← К карточке клиента</a>
    </div>
</div>

<div class="notice danger">
    <?= e($dbError) ?>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Редактировать клиента</h1>
        <p class="text-muted"><?= e($client['name']) ?> — Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/clients/<?= $client['id'] ?>" class="btn btn-ghost">← К карточке клиента</a>
    </div>
</div>

<?php if (!empty($formError)): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/company/clients/<?= $client['id'] ?>/edit" class="panel" data-contact-form>
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Основные данные</h3>

            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label">Наименование <span class="req">*</span></label>
                    <input type="text" name="name" class="field-input<?= !empty($errors['name']) ? ' is-error' : '' ?>" required
                           value="<?= e($old['name'] ?? $client['name']) ?>">
                    <?php if (!empty($errors['name'])): ?>
                        <div class="field-msg is-error"><?= e($errors['name']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label class="field-label">Статус <span class="req">*</span></label>
                    <select name="status" class="field-select<?= !empty($errors['status']) ? ' is-error' : '' ?>">
                        <?php
                        $statuses = ['active' => 'Активен', 'inactive' => 'Неактивен', 'archived' => 'Архив'];
                        $currentStatus = $old['status'] ?? $client['status'];
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
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Реквизиты</h3>

            <div class="form-grid-3">
                <div class="field">
                    <label class="field-label">ИНН <span class="req">*</span></label>
                    <input type="text" name="inn" class="field-input<?= !empty($errors['inn']) ? ' is-error' : '' ?>" required
                           value="<?= e($old['inn'] ?? $client['inn']) ?>">
                    <?php if (!empty($errors['inn'])): ?>
                        <div class="field-msg is-error"><?= e($errors['inn']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label class="field-label">КПП</label>
                    <input type="text" name="kpp" class="field-input"
                           value="<?= e($old['kpp'] ?? $client['kpp'] ?? '') ?>">
                </div>

                <div class="field">
                    <label class="field-label">ОГРН</label>
                    <input type="text" name="ogrn" class="field-input"
                           value="<?= e($old['ogrn'] ?? $client['ogrn'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Адреса</h3>

            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label">Юридический адрес</label>
                    <textarea name="legal_address" class="field-textarea" rows="2"><?= e($old['legal_address'] ?? $client['legal_address'] ?? '') ?></textarea>
                </div>

                <div class="field">
                    <label class="field-label">Фактический адрес</label>
                    <textarea name="physical_address" class="field-textarea" rows="2"><?= e($old['physical_address'] ?? $client['physical_address'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Контакты</h3>
            <?php renderClientContactFields($contactValues, $contactErrors); ?>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Комментарий</h3>

            <div class="field">
                <textarea name="comments" class="field-textarea" rows="2"><?= e($old['comments'] ?? $client['comments'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Сохранить</button>
            <a href="/company/clients/<?= $client['id'] ?>" class="btn btn-ghost">Отмена</a>
        </div>

    </div>
</form>

<script>
(function () {
    function bind() {
        var form = document.querySelector('[data-contact-form]');
        if (!form || !window.initContactFields) {
            return false;
        }
        window.initContactFields(form, { fieldPrefix: 'contacts' });
        return true;
    }
    if (bind()) {
        return;
    }
    window.addEventListener('load', bind, { once: true });
})();
</script>

<?php endif; ?>
