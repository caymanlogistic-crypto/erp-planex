<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Создать клиента</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/clients" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Создание клиентов недоступно.
</div>

<?php elseif ($success): ?>

<div class="page-head">
    <div>
        <h1>Клиент создан</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/clients" class="btn btn-primary">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Клиент успешно создан.
        </div>

        <div class="kv mt-4">
            <div class="kv-row">
                <span class="kv-key">Наименование</span>
                <span class="kv-value"><?= e($createdClient['name']) ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">ИНН</span>
                <span class="kv-value"><code><?= e($createdClient['inn']) ?></code></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Статус</span>
                <span class="kv-value">Активен</span>
            </div>
        </div>

        <div class="form-actions mt-4">
            <a href="/company/clients" class="btn btn-primary">← К списку клиентов</a>
            <a href="/company/clients/create" class="btn btn-ghost">Создать ещё</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Создать клиента</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/clients" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<?php
$fieldError = static function (string $field) use ($errors): string {
    $message = trim((string)($errors[$field] ?? ''));
    if ($field === 'name' && $message === 'Обязательное поле') {
        return 'Укажите наименование';
    }
    if ($field === 'inn' && $message === 'Обязательное поле') {
        return 'Укажите ИНН';
    }
    return $message;
};
?>

<div class="page-content">
    <form method="post" action="/company/clients/create" class="panel" novalidate>
        <div class="panel-body">

            <div class="form-section">
                <div class="section-title">ДАННЫЕ КЛИЕНТА</div>

                <?php if ($formError): ?>
                    <div class="form-alert alert-error">
                        <div class="alert-mark">
                            <svg width="11" height="11" viewBox="0 0 18 18" fill="none"><path d="M9 2L16.5 15H1.5L9 2Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 7V11M9 13V13.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        </div>
                        <div class="alert-body">
                            <div class="alert-body-title">Ошибка при сохранении</div>
                            <div class="alert-body-sub"><?= e($formError) ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-grid-2">
                    <div class="field<?= $fieldError('name') !== '' ? ' is-error' : '' ?>">
                        <label class="field-label" for="client-name">Наименование <span class="req">*</span></label>
                        <input
                            type="text"
                            id="client-name"
                            name="name"
                            class="field-input"
                            placeholder='ООО "РОМАШКА"'
                            value="<?= e($old['name'] ?? '') ?>"
                        >
                        <div class="field-msg"><?= e($fieldError('name')) ?></div>
                    </div>

                    <div class="field<?= $fieldError('inn') !== '' ? ' is-error' : '' ?>">
                        <label class="field-label" for="client-inn">ИНН <span class="req">*</span></label>
                        <input
                            type="text"
                            id="client-inn"
                            name="inn"
                            class="field-input"
                            placeholder="7701234567"
                            inputmode="numeric"
                            value="<?= e($old['inn'] ?? '') ?>"
                        >
                        <div class="field-msg"><?= e($fieldError('inn')) ?></div>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="field">
                        <label class="field-label" for="client-kpp">КПП</label>
                        <input
                            type="text"
                            id="client-kpp"
                            name="kpp"
                            class="field-input"
                            placeholder="770101001"
                            inputmode="numeric"
                            value="<?= e($old['kpp'] ?? '') ?>"
                        >
                        <div class="field-msg"></div>
                    </div>

                    <div class="field">
                        <label class="field-label" for="client-ogrn">ОГРН</label>
                        <input
                            type="text"
                            id="client-ogrn"
                            name="ogrn"
                            class="field-input"
                            placeholder="1027700132195"
                            inputmode="numeric"
                            value="<?= e($old['ogrn'] ?? '') ?>"
                        >
                        <div class="field-msg"></div>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="client-legal-address">Юридический адрес</label>
                    <textarea
                        id="client-legal-address"
                        name="legal_address"
                        class="field-textarea"
                        rows="2"
                        placeholder="Юридический адрес клиента"
                    ><?= e($old['legal_address'] ?? '') ?></textarea>
                    <div class="field-msg"></div>
                </div>

                <div class="field">
                    <label class="field-label" for="client-physical-address">Фактический адрес</label>
                    <textarea
                        id="client-physical-address"
                        name="physical_address"
                        class="field-textarea"
                        rows="2"
                        placeholder="Фактический адрес клиента"
                    ><?= e($old['physical_address'] ?? '') ?></textarea>
                    <div class="field-msg"></div>
                </div>

                <div class="form-grid-3">
                    <div class="field">
                        <label class="field-label" for="client-contact-person">Контактное лицо</label>
                        <input
                            type="text"
                            id="client-contact-person"
                            name="contact_person"
                            class="field-input"
                            placeholder="Иванов Иван Иванович"
                            value="<?= e($old['contact_person'] ?? '') ?>"
                        >
                        <div class="field-msg"></div>
                    </div>

                    <div class="field">
                        <label class="field-label" for="client-contact-phone">Телефон</label>
                        <input
                            type="text"
                            id="client-contact-phone"
                            name="contact_phone"
                            class="field-input"
                            placeholder="+7 900 000-00-00"
                            inputmode="tel"
                            value="<?= e($old['contact_phone'] ?? '') ?>"
                        >
                        <div class="field-msg"></div>
                    </div>

                    <div class="field">
                        <label class="field-label" for="client-contact-email">Email</label>
                        <input
                            type="text"
                            id="client-contact-email"
                            name="contact_email"
                            class="field-input"
                            placeholder="client@example.ru"
                            inputmode="email"
                            value="<?= e($old['contact_email'] ?? '') ?>"
                        >
                        <div class="field-msg"></div>
                    </div>
                </div>

                <div class="field field-w-comment">
                    <label class="field-label" for="client-comments">Комментарий</label>
                    <textarea
                        id="client-comments"
                        name="comments"
                        class="field-textarea"
                        rows="3"
                        placeholder="Примечания по клиенту"
                    ><?= e($old['comments'] ?? '') ?></textarea>
                    <div class="field-msg"></div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Создать клиента</button>
            </div>

        </div>
    </form>
</div>

<script>
(function () {
    var form = document.querySelector('form[action="/company/clients/create"][method="post"]');
    if (!form) return;

    var fields = {
        name: form.querySelector('[name="name"]'),
        inn: form.querySelector('[name="inn"]'),
        kpp: form.querySelector('[name="kpp"]'),
        ogrn: form.querySelector('[name="ogrn"]'),
        legal_address: form.querySelector('[name="legal_address"]'),
        physical_address: form.querySelector('[name="physical_address"]'),
        contact_person: form.querySelector('[name="contact_person"]'),
        contact_phone: form.querySelector('[name="contact_phone"]'),
        contact_email: form.querySelector('[name="contact_email"]'),
        comments: form.querySelector('[name="comments"]')
    };

    function trimSpaces(value) {
        return String(value || '').trim();
    }

    function collapseSpaces(value) {
        return trimSpaces(value).replace(/\s+/g, ' ');
    }

    function digitsOnly(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    function formatPhone(value) {
        var digits = digitsOnly(value);
        if (digits.length === 11 && digits.charAt(0) === '8') {
            digits = '7' + digits.slice(1);
        }
        if (digits.length === 10) {
            digits = '7' + digits;
        }
        if (digits.length !== 11 || digits.charAt(0) !== '7') {
            return trimSpaces(value);
        }
        return '+7 ' + digits.slice(1, 4) + ' ' + digits.slice(4, 7) + '-' + digits.slice(7, 9) + '-' + digits.slice(9, 11);
    }

    function setFieldState(input, message) {
        var field = input ? input.closest('.field') : null;
        var msg = field ? field.querySelector('.field-msg') : null;
        if (!field || !msg) return;
        field.classList.toggle('is-error', message !== '');
        msg.textContent = message;
    }

    function normalizeField(name) {
        var input = fields[name];
        if (!input) return;

        if (name === 'name' || name === 'contact_person') {
            input.value = collapseSpaces(input.value);
            return;
        }

        if (name === 'inn' || name === 'kpp' || name === 'ogrn') {
            input.value = digitsOnly(input.value);
            return;
        }

        if (name === 'contact_phone') {
            input.value = formatPhone(input.value);
            return;
        }

        input.value = trimSpaces(input.value);
    }

    function validateField(name) {
        var input = fields[name];
        if (!input) return '';

        var value = input.value;

        if (name === 'name') {
            return value === '' ? 'Укажите наименование' : '';
        }

        if (name === 'inn') {
            var innDigits = digitsOnly(value);
            if (innDigits === '') return 'Укажите ИНН';
            if (innDigits.length !== 10 && innDigits.length !== 12) return 'ИНН: 10 или 12 цифр';
            return '';
        }

        if (name === 'kpp') {
            var kppDigits = digitsOnly(value);
            if (kppDigits !== '' && kppDigits.length !== 9) return 'КПП: 9 цифр';
            return '';
        }

        if (name === 'ogrn') {
            var ogrnDigits = digitsOnly(value);
            if (ogrnDigits !== '' && ogrnDigits.length !== 13 && ogrnDigits.length !== 15) return 'ОГРН: 13 или 15 цифр';
            return '';
        }

        if (name === 'contact_phone') {
            var phoneDigits = digitsOnly(value);
            if (phoneDigits === '') return '';
            if (phoneDigits.length !== 10 && phoneDigits.length !== 11) return 'Телефон: 10-11 цифр';
            return '';
        }

        if (name === 'contact_email') {
            if (value === '') return '';
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) ? '' : 'Некорректный email';
        }

        return '';
    }

    [
        'name',
        'inn',
        'kpp',
        'ogrn',
        'legal_address',
        'physical_address',
        'contact_person',
        'contact_phone',
        'contact_email',
        'comments'
    ].forEach(function (name) {
        var input = fields[name];
        if (!input) return;
        input.addEventListener('blur', function () {
            normalizeField(name);
            setFieldState(input, validateField(name));
        });
        input.addEventListener('input', function () {
            var field = input.closest('.field');
            if (field && field.classList.contains('is-error')) {
                setFieldState(input, '');
            }
        });
    });

    form.addEventListener('submit', function (event) {
        var hasErrors = false;

        Object.keys(fields).forEach(function (name) {
            normalizeField(name);
        });

        ['name', 'inn', 'kpp', 'ogrn', 'contact_phone', 'contact_email'].forEach(function (name) {
            var input = fields[name];
            var message = validateField(name);
            setFieldState(input, message);
            if (message !== '') {
                hasErrors = true;
            }
        });

        if (hasErrors) {
            event.preventDefault();
        }
    });
})();
</script>

<?php endif; ?>
