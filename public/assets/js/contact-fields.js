(function () {
    function initContactFields(form, options) {
        if (!form || form.dataset.contactFieldsReady === '1') {
            return;
        }

        var root = form.querySelector('[data-contact-fields]');
        if (!root) {
            return;
        }

        form.dataset.contactFieldsReady = '1';

        var settings = options || {};
        var fieldPrefix = settings.fieldPrefix || root.getAttribute('data-field-prefix') || 'contacts';
        var list = root.querySelector('[data-client-contacts-list], [data-contractor-contacts-list]');
        var template = root.parentNode ? root.parentNode.querySelector('[data-contact-template]') : null;
        var addBtn = root.querySelector('[data-add-contact]');

        function normalizeSpaces(value) {
            return String(value || '').replace(/\s+/g, ' ').trim();
        }

        function clearContactFieldError(input) {
            if (!input) return;
            var wrap = input.closest('.contact-cell-wrap');
            if (wrap) wrap.classList.remove('is-error');
            input.classList.remove('is-error');
            var suffix = input.closest('.contact-input-suffix');
            if (suffix) suffix.classList.remove('is-error');
            var err = wrap ? wrap.querySelector('[data-contact-error]') : null;
            if (err) err.textContent = '';
        }

        function setContactFieldError(input, message) {
            if (!input) return;
            var wrap = input.closest('.contact-cell-wrap');
            if (wrap) wrap.classList.add('is-error');
            var suffix = input.closest('.contact-input-suffix');
            if (suffix) {
                suffix.classList.add('is-error');
            } else {
                input.classList.add('is-error');
            }
            var err = wrap ? wrap.querySelector('[data-contact-error]') : null;
            if (err) err.textContent = message || '';
        }

        function normalizePhone(value) {
            var normalized = normalizeSpaces(value);
            if (!normalized) {
                return { value: '', error: '' };
            }
            if (/[^0-9+\s()\-]/.test(normalized)) {
                return { value: normalized, error: 'Телефон: только цифры, +, пробелы, скобки, дефис' };
            }
            var digits = normalized.replace(/\D/g, '');
            if (digits.length === 11 && digits.charAt(0) === '8') {
                digits = '7' + digits.slice(1);
            }
            if (digits.length === 10) {
                digits = '7' + digits;
            }
            if (digits.length !== 11 || digits.charAt(0) !== '7') {
                return { value: normalized, error: 'Телефон: 10-11 цифр' };
            }
            return {
                value: '+7 ' + digits.slice(1, 4) + ' ' + digits.slice(4, 7) + '-' + digits.slice(7, 9) + '-' + digits.slice(9, 11),
                error: ''
            };
        }

        function getRows() {
            return list ? list.querySelectorAll('[data-contact-row]') : [];
        }

        function renameRows() {
            Array.prototype.forEach.call(getRows(), function (row, index) {
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
                        input.name = fieldPrefix + '[' + index + '][' + map[selector] + ']';
                    }
                });
            });
        }

        function ensureSinglePrimary(current) {
            if (!current || !current.checked) {
                return;
            }
            Array.prototype.forEach.call(form.querySelectorAll('[data-contact-primary]'), function (checkbox) {
                if (checkbox !== current) {
                    checkbox.checked = false;
                }
            });
        }

        function clearRow(row) {
            Array.prototype.forEach.call(row.querySelectorAll('input[type="text"], textarea'), function (input) {
                input.value = '';
                clearContactFieldError(input);
            });
            Array.prototype.forEach.call(row.querySelectorAll('input[type="checkbox"]'), function (input, index) {
                input.checked = index === 0 && input.hasAttribute('data-contact-primary');
            });
            Array.prototype.forEach.call(row.querySelectorAll('[data-contact-error]'), function (node) {
                node.textContent = '';
            });
            Array.prototype.forEach.call(row.querySelectorAll('.contact-cell-wrap, .contact-input-suffix'), function (node) {
                node.classList.remove('is-error');
            });
        }

        function bindRow(row) {
            if (!row) {
                return;
            }

            var removeBtn = row.querySelector('[data-remove-contact]');
            var primaryCheckbox = row.querySelector('[data-contact-primary]');
            var personField = row.querySelector('[data-contact-person]');
            var phoneField = row.querySelector('[data-contact-phone]');
            var emailField = row.querySelector('[data-contact-email]');
            var commentField = row.querySelector('[data-contact-comment]');

            if (removeBtn && removeBtn.dataset.bound !== '1') {
                removeBtn.dataset.bound = '1';
                removeBtn.addEventListener('click', function () {
                    var rows = getRows();
                    if (rows.length <= 1) {
                        clearRow(row);
                        return;
                    }
                    row.remove();
                    renameRows();
                });
            }

            if (primaryCheckbox && primaryCheckbox.dataset.bound !== '1') {
                primaryCheckbox.dataset.bound = '1';
                primaryCheckbox.addEventListener('change', function () {
                    ensureSinglePrimary(primaryCheckbox);
                });
            }

            [
                [personField, function () { personField.value = normalizeSpaces(personField.value); }],
                [emailField, function () { emailField.value = String(emailField.value || '').trim(); }],
                [commentField, function () { commentField.value = String(commentField.value || '').trim(); }],
                [phoneField, function () {
                    var normalized = normalizePhone(phoneField.value);
                    phoneField.value = normalized.value;
                    if (normalized.error) {
                        setContactFieldError(phoneField, normalized.error);
                    } else {
                        clearContactFieldError(phoneField);
                    }
                }]
            ].forEach(function (entry) {
                var field = entry[0];
                if (field && field.dataset.bound !== '1') {
                    field.dataset.bound = '1';
                    field.addEventListener('blur', entry[1]);
                }
            });
        }

        function addRow() {
            if (!template || !template.content || !list) {
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

        Array.prototype.forEach.call(getRows(), bindRow);
        renameRows();

        if (addBtn && addBtn.dataset.bound !== '1') {
            addBtn.dataset.bound = '1';
            addBtn.addEventListener('click', addRow);
        }

        form.addEventListener('submit', function (event) {
            var ok = true;
            var primaryFound = false;
            var meaningfulRows = [];

            Array.prototype.forEach.call(getRows(), function (row) {
                var person = row.querySelector('[data-contact-person]');
                var phone = row.querySelector('[data-contact-phone]');
                var email = row.querySelector('[data-contact-email]');
                var comment = row.querySelector('[data-contact-comment]');
                var primary = row.querySelector('[data-contact-primary]');
                var documentEmail = row.querySelector('[data-contact-document-email]');

                if (person) {
                    person.value = normalizeSpaces(person.value);
                    clearContactFieldError(person);
                }
                if (phone) {
                    var phoneResult = normalizePhone(phone.value);
                    phone.value = phoneResult.value;
                    if (phoneResult.error) {
                        setContactFieldError(phone, phoneResult.error);
                        ok = false;
                    } else {
                        clearContactFieldError(phone);
                    }
                }
                if (email) {
                    email.value = String(email.value || '').trim();
                    clearContactFieldError(email);
                }
                if (comment) {
                    comment.value = String(comment.value || '').trim();
                }

                var meaningful = (person && person.value !== '')
                    || (phone && phone.value !== '')
                    || (email && email.value !== '')
                    || (comment && comment.value !== '');

                if (!meaningful) {
                    if (primary) primary.checked = false;
                    if (documentEmail) documentEmail.checked = false;
                    return;
                }

                meaningfulRows.push(row);

                if (email && email.value !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
                    setContactFieldError(email, 'Некорректный email');
                    ok = false;
                }

                if (documentEmail && documentEmail.checked && (!email || email.value === '')) {
                    documentEmail.checked = false;
                }

                if (primary && primary.checked) {
                    if (!primaryFound) {
                        primaryFound = true;
                    } else {
                        primary.checked = false;
                    }
                }
            });

            if (!primaryFound && meaningfulRows.length > 0) {
                var firstPrimary = meaningfulRows[0].querySelector('[data-contact-primary]');
                if (firstPrimary) {
                    firstPrimary.checked = true;
                }
            }

            if (!ok) {
                event.preventDefault();
            }
        });
    }

    window.initContactFields = initContactFields;
})();
