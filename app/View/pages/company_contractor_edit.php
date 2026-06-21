<?php

require_once __DIR__ . '/../components/status_badge.php';
require_once __DIR__ . '/../components/contractor_contact_fields.php';

$contactValues = $old['contacts'] ?? $contacts ?? [];
$contactErrors = $errors['contacts'] ?? [];

?>

<?php if ($company === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Компания не найдена. <a href="/company/contractors">← К списку</a>
        </div>
    </div>
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Редактировать перевозчика</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>».
</div>

<?php elseif (isset($dbError)): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice danger">
            <?= e($dbError) ?>
        </div>
        <div class="form-actions mt-4">
            <a href="/company/contractors" class="btn btn-ghost">← К списку</a>
        </div>
    </div>
</div>

<?php elseif ($contractor === null): ?>

<div class="page-head">
    <div>
        <h1>Перевозчик не найден</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">
            Перевозчик с указанным ID не найден.
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Редактировать перевозчика</h1>
        <p class="text-muted"><?= e($contractor['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors/<?= $contractor['id'] ?>" class="btn btn-ghost">← К карточке</a>
    </div>
</div>

<?php if (!empty($formError)): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/company/contractors/<?= $contractor['id'] ?>/edit" class="panel" data-contractor-contact-form>
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Основные данные</h3>

            <div class="field">
                <label class="field-label">Наименование <span class="req">*</span></label>
                <input type="text" name="name" class="field-input<?= !empty($errors['name']) ? ' is-error' : '' ?>" required
                       value="<?= e($old['name'] ?? $contractor['name']) ?>">
                <?php if (!empty($errors['name'])): ?>
                    <div class="field-msg is-error"><?= e($errors['name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">ИНН <span class="req">*</span></label>
                <input type="text" name="inn" class="field-input<?= !empty($errors['inn']) ? ' is-error' : '' ?>" required
                       value="<?= e($old['inn'] ?? $contractor['inn']) ?>">
                <?php if (!empty($errors['inn'])): ?>
                    <div class="field-msg is-error"><?= e($errors['inn']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label class="field-label">КПП</label>
                <input type="text" name="kpp" class="field-input"
                       value="<?= e($old['kpp'] ?? $contractor['kpp'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">ОГРН</label>
                <input type="text" name="ogrn" class="field-input"
                       value="<?= e($old['ogrn'] ?? $contractor['ogrn'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Тип перевозчика</label>
                <select name="contractor_type" class="field-select">
                    <?php $contractorType = $old['contractor_type'] ?? $contractor['contractor_type'] ?? ''; ?>
                    <option value="">— Не указан —</option>
                    <option value="legal_entity" <?= $contractorType === 'legal_entity' ? 'selected' : '' ?>>Юридическое лицо</option>
                    <option value="individual" <?= $contractorType === 'individual' ? 'selected' : '' ?>>Индивидуальный предприниматель</option>
                    <option value="self_employed" <?= $contractorType === 'self_employed' ? 'selected' : '' ?>>Самозанятый</option>
                    <option value="private_person" <?= $contractorType === 'private_person' ? 'selected' : '' ?>>Физическое лицо</option>
                </select>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Адреса</h3>

            <div class="field">
                <label class="field-label">Юридический адрес</label>
                <textarea name="legal_address" class="field-textarea" rows="2"><?= e($old['legal_address'] ?? $contractor['legal_address'] ?? '') ?></textarea>
            </div>

            <div class="field">
                <label class="field-label">Фактический адрес</label>
                <textarea name="physical_address" class="field-textarea" rows="2"><?= e($old['physical_address'] ?? $contractor['physical_address'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Контакты</h3>
            <?php renderContractorContactFields($contactValues, $contactErrors); ?>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Банковские реквизиты</h3>

            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label">Расчётный счёт</label>
                    <input type="text" name="bank_account" class="field-input"
                           value="<?= e($old['bank_account'] ?? $contractor['bank_account'] ?? '') ?>">
                </div>

                <div class="field">
                    <label class="field-label">БИК</label>
                    <input type="text" name="bank_bik" class="field-input"
                           value="<?= e($old['bank_bik'] ?? $contractor['bank_bik'] ?? '') ?>">
                </div>
            </div>

            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label">Банк</label>
                    <input type="text" name="bank_name" class="field-input"
                           value="<?= e($old['bank_name'] ?? $contractor['bank_name'] ?? '') ?>">
                </div>

                <div class="field">
                    <label class="field-label">Корр. счёт</label>
                    <input type="text" name="bank_corr_account" class="field-input"
                           value="<?= e($old['bank_corr_account'] ?? $contractor['bank_corr_account'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Статус и комментарий</h3>

            <div class="field">
                <label class="field-label">Статус <span class="req">*</span></label>
                <select name="status" class="field-select<?= !empty($errors['status']) ? ' is-error' : '' ?>">
                    <?php
                    $statuses = ['active' => 'Активен', 'inactive' => 'Неактивен', 'archived' => 'Архив'];
                    $currentStatus = $old['status'] ?? $contractor['status'];
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
                <textarea name="comments" class="field-textarea" rows="3"><?= e($old['comments'] ?? $contractor['comments'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Сохранить</button>
            <a href="/company/contractors/<?= $contractor['id'] ?>" class="btn btn-ghost">Отмена</a>
        </div>

    </div>
</form>

<script>
(function () {
    var form = document.querySelector('[data-contractor-contact-form]');
    if (!form) {
        return;
    }

    var list = form.querySelector('[data-contractor-contacts-list]');
    var template = form.querySelector('[data-contact-template]');
    var addBtn = form.querySelector('[data-add-contact]');

    function normalizeSpaces(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    }

    function renameRows() {
        var rows = list.querySelectorAll('[data-contact-row]');
        Array.prototype.forEach.call(rows, function (row, index) {
            var title = row.querySelector('.contractor-contact-title');
            if (title) {
                title.textContent = 'Контакт #' + (index + 1);
            }

            var map = {
                '[data-contact-person]': 'contact_person',
                '[data-contact-phone]': 'phone',
                '[data-contact-email]': 'email',
                '[data-contact-comment]': 'comment',
                '[data-contact-primary]': 'is_primary',
                '[data-contact-document-email]': 'is_document_email'
            };

            Object.keys(map).forEach(function (selector) {
                var input = row.querySelector(selector);
                if (input) {
                    input.name = 'contacts[' + index + '][' + map[selector] + ']';
                }
            });
        });
    }

    function clearFieldError(field) {
        if (!field) {
            return;
        }
        field.classList.remove('is-error');
        var msg = field.querySelector('.field-msg');
        if (msg) {
            msg.textContent = '';
        }
    }

    function setFieldError(input, message) {
        var field = input ? input.closest('.field') : null;
        if (!field) {
            return;
        }
        field.classList.add('is-error');
        var msg = field.querySelector('.field-msg');
        if (msg) {
            msg.textContent = message || '';
        }
    }

    function clearRowErrors(row) {
        Array.prototype.forEach.call(row.querySelectorAll('.field'), clearFieldError);
    }

    function ensureSinglePrimary(current) {
        if (!current.checked) {
            return;
        }
        Array.prototype.forEach.call(list.querySelectorAll('[data-contact-primary]'), function (checkbox) {
            if (checkbox !== current) {
                checkbox.checked = false;
            }
        });
    }

    function bindRow(row) {
        var removeBtn = row.querySelector('[data-remove-contact]');
        var primaryCheckbox = row.querySelector('[data-contact-primary]');

        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                var rows = list.querySelectorAll('[data-contact-row]');
                if (rows.length <= 1) {
                    Array.prototype.forEach.call(row.querySelectorAll('input[type="text"], textarea'), function (input) {
                        input.value = '';
                    });
                    Array.prototype.forEach.call(row.querySelectorAll('input[type="checkbox"]'), function (input, index) {
                        input.checked = index === 0;
                    });
                    clearRowErrors(row);
                    return;
                }
                row.remove();
                renameRows();
            });
        }

        if (primaryCheckbox) {
            primaryCheckbox.addEventListener('change', function () {
                ensureSinglePrimary(primaryCheckbox);
            });
        }
    }

    function addRow() {
        if (!template || !template.content) {
            return;
        }
        var fragment = template.content.cloneNode(true);
        var row = fragment.querySelector('[data-contact-row]');
        list.appendChild(fragment);
        if (row) {
            bindRow(row);
        }
        renameRows();
    }

    Array.prototype.forEach.call(list.querySelectorAll('[data-contact-row]'), bindRow);
    renameRows();

    if (addBtn) {
        addBtn.addEventListener('click', function () {
            addRow();
        });
    }

    form.addEventListener('submit', function (event) {
        var ok = true;
        var primaryFound = false;
        var rows = list.querySelectorAll('[data-contact-row]');

        Array.prototype.forEach.call(rows, function (row, index) {
            clearRowErrors(row);

            var person = row.querySelector('[data-contact-person]');
            var phone = row.querySelector('[data-contact-phone]');
            var email = row.querySelector('[data-contact-email]');
            var comment = row.querySelector('[data-contact-comment]');
            var primary = row.querySelector('[data-contact-primary]');
            var documentEmail = row.querySelector('[data-contact-document-email]');

            person.value = normalizeSpaces(person.value);
            phone.value = normalizeSpaces(phone.value);
            email.value = String(email.value || '').trim();
            comment.value = String(comment.value || '').trim();

            var meaningful = person.value !== '' || phone.value !== '' || email.value !== '' || comment.value !== '';
            if (!meaningful) {
                if (primary) {
                    primary.checked = false;
                }
                if (documentEmail) {
                    documentEmail.checked = false;
                }
                return;
            }

            if (email.value !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
                setFieldError(email, 'Некорректный email');
                ok = false;
            }

            if (documentEmail && documentEmail.checked && email.value === '') {
                documentEmail.checked = false;
            }

            if (primary && primary.checked) {
                if (!primaryFound) {
                    primaryFound = true;
                } else {
                    primary.checked = false;
                }
            }

            if (!primaryFound && index === rows.length - 1) {
                var meaningfulRows = Array.prototype.filter.call(rows, function (candidate) {
                    return normalizeSpaces(candidate.querySelector('[data-contact-person]').value) !== ''
                        || normalizeSpaces(candidate.querySelector('[data-contact-phone]').value) !== ''
                        || String(candidate.querySelector('[data-contact-email]').value || '').trim() !== ''
                        || String(candidate.querySelector('[data-contact-comment]').value || '').trim() !== '';
                });
                if (meaningfulRows.length > 0) {
                    meaningfulRows[0].querySelector('[data-contact-primary]').checked = true;
                }
            }
        });

        if (!ok) {
            event.preventDefault();
        }
    });
})();
</script>

<?php endif; ?>
