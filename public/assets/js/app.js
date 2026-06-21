document.documentElement.classList.add('js-ready');

// ============================================================
// Подменю: раскрытие / закрытие группы «Подрядчики»
// ============================================================
(function () {
    // Клик по родительскому элементу — переключение is-open
    document.querySelectorAll('.nav-item.is-parent').forEach(function (parent) {
        parent.addEventListener('click', function () {
            parent.classList.toggle('is-open');
            var sub = parent.nextElementSibling;
            if (sub && sub.classList.contains('nav-sub')) {
                sub.classList.toggle('is-open');
            }
        });
    });

    // При загрузке: если любой подпункт активен — раскрыть родителя
    document.querySelectorAll('.nav-sub-item.is-active').forEach(function (activeItem) {
        var sub = activeItem.closest('.nav-sub');
        if (sub) {
            sub.classList.add('is-open');
            var parentEl = sub.previousElementSibling;
            if (parentEl && parentEl.classList.contains('is-parent')) {
                parentEl.classList.add('is-open');
            }
        }
    });
})();

// ============================================================
// Страница создания водителя: ERP date-picker и нормализация
// ============================================================
(function () {
    var form = document.querySelector('form[action="/company/drivers/create"][method="post"]');
    if (!form) return;

    var monthNames = [
        'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
        'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'
    ];
    var weekdays = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
    var activeDateInput = null;
    var viewYear = 0;
    var viewMonth = 0;

    function pad2(value) {
        return String(value).padStart(2, '0');
    }

    function collapseSpaces(value) {
        return String(value || '').trim().replace(/\s+/g, ' ');
    }

    function stripDateTail(value) {
        return collapseSpaces(value).replace(/\s*(г(?:\.|ода?|од)?)\s*$/i, '').trim();
    }

    function titleWord(word) {
        return word ? word.charAt(0).toLocaleUpperCase('ru-RU') + word.slice(1).toLocaleLowerCase('ru-RU') : word;
    }

    function fieldFor(input) {
        return input ? input.closest('.field') : null;
    }

    function setFieldError(input, message) {
        var field = fieldFor(input);
        if (!field) return;
        var msg = field.querySelector('.field-msg');
        field.classList.add('is-error');
        if (msg) msg.textContent = message;
    }

    function clearFieldError(input) {
        var field = fieldFor(input);
        if (!field) return;
        var msg = field.querySelector('.field-msg');
        field.classList.remove('is-error');
        if (msg) msg.textContent = '';
    }

    function datePartsToObject(day, month, year) {
        var date = new Date(year, month - 1, day);
        if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) return null;
        return {
            day: day,
            month: month,
            year: year,
            date: date,
            display: pad2(day) + '.' + pad2(month) + '.' + year,
            iso: year + '-' + pad2(month) + '-' + pad2(day)
        };
    }

    function expandYear(year) {
        if (year < 100) return year <= 49 ? 2000 + year : 1900 + year;
        return year;
    }

    function parseDateValue(value) {
        var raw = stripDateTail(value);
        var match;
        if (!raw) return null;

        match = /^(\d{8})$/.exec(raw);
        if (match) {
            return datePartsToObject(
                Number(raw.slice(0, 2)),
                Number(raw.slice(2, 4)),
                Number(raw.slice(4, 8))
            );
        }

        match = /^(\d{4})[./-](\d{1,2})[./-](\d{1,2})$/.exec(raw);
        if (match) {
            return datePartsToObject(Number(match[3]), Number(match[2]), Number(match[1]));
        }

        match = /^(\d{1,2})[\s./-](\d{1,2})[\s./-](\d{2}|\d{4})$/.exec(raw);
        if (match) {
            return datePartsToObject(Number(match[1]), Number(match[2]), expandYear(Number(match[3])));
        }

        return null;
    }

    function normalizeDateInput(input, silent) {
        var value = collapseSpaces(input.value);
        if (!value) {
            clearFieldError(input);
            return true;
        }

        var parsed = parseDateValue(value);
        if (!parsed) {
            if (!silent) setFieldError(input, 'Укажите дату в формате дд.мм.гггг');
            return false;
        }

        input.value = parsed.display;
        input.dataset.isoValue = parsed.iso;
        clearFieldError(input);
        return true;
    }

    function formatPhone(value) {
        var digits = String(value || '').replace(/\D/g, '');
        if (digits.length === 11 && digits.charAt(0) === '8') digits = '7' + digits.slice(1);
        if (digits.length === 10) digits = '7' + digits;
        if (!(digits.length === 11 && digits.charAt(0) === '7')) return null;
        return '+7 ' + digits.slice(1, 4) + ' ' + digits.slice(4, 7) + '-' + digits.slice(7, 9) + '-' + digits.slice(9, 11);
    }

    function formatTenDigitDocument(value) {
        var digits = String(value || '').replace(/\D/g, '');
        return digits.length === 10 ? digits.slice(0, 4) + ' ' + digits.slice(4) : null;
    }

    function normalizeByName(input) {
        var name = input.name;
        var value = input.value;
        var formatted;

        if (name === 'full_name') {
            if (!value.trim()) {
                setFieldError(input, 'Укажите ФИО');
                return false;
            }
            var parts = collapseSpaces(value).split(' ');
            if (parts.length < 3) {
                setFieldError(input, 'ФИО: 3 слова');
                return false;
            }
            if (parts.length > 3) {
                setFieldError(input, 'ФИО: 3 слова');
                return false;
            }
            input.value = parts.map(titleWord).join(' ');
            clearFieldError(input);
            return true;
        }

        if (name === 'phone' || name === 'extra_phones[]') {
            if (!value.trim()) {
                if (name === 'phone') {
                    setFieldError(input, 'Укажите телефон');
                    return false;
                }
                clearFieldError(input);
                return true;
            }
            formatted = formatPhone(value);
            if (!formatted) {
                setFieldError(input, 'Неверный формат');
                return false;
            }
            input.value = formatted;
            clearFieldError(input);
            return true;
        }

        if (!value.trim()) {
            clearFieldError(input);
            return true;
        }

        if (name === 'passport_number' || name === 'license_number') {
            formatted = formatTenDigitDocument(value);
            if (!formatted) {
                setFieldError(input, 'Нужно 10 цифр');
                return false;
            }
            input.value = formatted;
            clearFieldError(input);
            return true;
        }

        if (name === 'passport_department_code') {
            var deptDigits = value.replace(/\D/g, '');
            if (deptDigits.length !== 6) {
                setFieldError(input, 'Формат 000-000');
                return false;
            }
            input.value = deptDigits.slice(0, 3) + '-' + deptDigits.slice(3);
            clearFieldError(input);
            return true;
        }

        if (name === 'snils') {
            var snilsDigits = value.replace(/\D/g, '');
            if (snilsDigits.length !== 11) {
                setFieldError(input, 'Нужно 11 цифр');
                return false;
            }
            input.value = snilsDigits.slice(0, 3) + '-' + snilsDigits.slice(3, 6) + '-' + snilsDigits.slice(6, 9) + ' ' + snilsDigits.slice(9);
            clearFieldError(input);
            return true;
        }

        if (name === 'email') {
            var email = collapseSpaces(value).toLowerCase();
            if (email !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                setFieldError(input, 'Неверный email');
                return false;
            }
            input.value = email;
            clearFieldError(input);
            return true;
        }

        if (name === 'passport_issued_by' || name === 'comments') {
            input.value = collapseSpaces(value);
            clearFieldError(input);
            return true;
        }

        if (input.classList.contains('js-erp-date-picker')) {
            return normalizeDateInput(input, false);
        }

        return true;
    }

    function normalizeAfterPaste(input) {
        window.setTimeout(function () {
            normalizeByName(input);
        }, 0);
    }

    function getPopup() {
        var popup = document.querySelector('.erp-date-popover');
        if (popup) return popup;

        popup = document.createElement('div');
        popup.className = 'erp-date-popover';
        popup.innerHTML =
            '<div class="erp-date-head">' +
                '<button type="button" class="erp-date-nav" data-date-action="prev" aria-label="Предыдущий месяц">‹</button>' +
                '<div class="erp-date-title"></div>' +
                '<button type="button" class="erp-date-nav" data-date-action="next" aria-label="Следующий месяц">›</button>' +
            '</div>' +
            '<div class="erp-date-weekdays"></div>' +
            '<div class="erp-date-grid"></div>' +
            '<div class="erp-date-foot">' +
                '<button type="button" class="btn btn-ghost btn-sm" data-date-action="clear">Очистить</button>' +
                '<button type="button" class="btn btn-secondary btn-sm" data-date-action="today">Сегодня</button>' +
            '</div>';
        popup.querySelector('.erp-date-weekdays').innerHTML = weekdays.map(function (day) {
            return '<span>' + day + '</span>';
        }).join('');
        document.body.appendChild(popup);

        popup.addEventListener('mousedown', function (event) {
            event.preventDefault();
        });
        popup.addEventListener('click', function (event) {
            var action = event.target.closest('[data-date-action]');
            var day = event.target.closest('[data-date-day]');
            if (!activeDateInput) return;

            if (action) {
                var kind = action.getAttribute('data-date-action');
                if (kind === 'prev') {
                    viewMonth -= 1;
                    if (viewMonth < 0) {
                        viewMonth = 11;
                        viewYear -= 1;
                    }
                    renderPopup();
                } else if (kind === 'next') {
                    viewMonth += 1;
                    if (viewMonth > 11) {
                        viewMonth = 0;
                        viewYear += 1;
                    }
                    renderPopup();
                } else if (kind === 'today') {
                    setDateInput(activeDateInput, new Date());
                    closePopup();
                } else if (kind === 'clear') {
                    activeDateInput.value = '';
                    delete activeDateInput.dataset.isoValue;
                    clearFieldError(activeDateInput);
                    closePopup();
                }
                return;
            }

            if (day) {
                setDateInput(activeDateInput, new Date(Number(day.dataset.year), Number(day.dataset.month), Number(day.dataset.day)));
                closePopup();
            }
        });

        return popup;
    }

    function setDateInput(input, date) {
        input.value = pad2(date.getDate()) + '.' + pad2(date.getMonth() + 1) + '.' + date.getFullYear();
        input.dataset.isoValue = date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate());
        clearFieldError(input);
    }

    function renderPopup() {
        var popup = getPopup();
        var grid = popup.querySelector('.erp-date-grid');
        var title = popup.querySelector('.erp-date-title');
        var first = new Date(viewYear, viewMonth, 1);
        var daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
        var leading = (first.getDay() + 6) % 7;
        var selected = activeDateInput ? parseDateValue(activeDateInput.value) : null;
        var today = new Date();
        var html = '';

        title.textContent = monthNames[viewMonth] + ' ' + viewYear;

        for (var i = 0; i < leading; i++) {
            html += '<span class="erp-date-empty"></span>';
        }

        for (var day = 1; day <= daysInMonth; day++) {
            var isSelected = selected && selected.year === viewYear && selected.month === viewMonth + 1 && selected.day === day;
            var isToday = today.getFullYear() === viewYear && today.getMonth() === viewMonth && today.getDate() === day;
            html += '<button type="button" class="erp-date-day' +
                (isSelected ? ' is-selected' : '') +
                (isToday ? ' is-today' : '') +
                '" data-date-day="' + day + '" data-year="' + viewYear + '" data-month="' + viewMonth + '" data-day="' + day + '">' + day + '</button>';
        }

        grid.innerHTML = html;
    }

    function openPopup(input) {
        activeDateInput = input;
        var parsed = parseDateValue(input.value);
        var base = parsed ? parsed.date : new Date();
        viewYear = base.getFullYear();
        viewMonth = base.getMonth();
        renderPopup();

        var popup = getPopup();
        var rect = input.getBoundingClientRect();
        popup.style.left = Math.round(rect.left + window.scrollX) + 'px';
        popup.style.top = Math.round(rect.bottom + window.scrollY + 4) + 'px';
        popup.classList.add('is-open');
    }

    function closePopup() {
        var popup = document.querySelector('.erp-date-popover');
        if (popup) popup.classList.remove('is-open');
        activeDateInput = null;
    }

    function installDatePicker(input) {
        if (input.dataset.erpDateReady === '1') return;
        input.dataset.erpDateReady = '1';

        var wrap = document.createElement('div');
        wrap.className = 'erp-date-field';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'erp-date-trigger';
        button.setAttribute('aria-label', 'Открыть календарь');
        button.innerHTML = '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><rect x="2" y="3" width="10" height="9" stroke="currentColor" stroke-width="1.2"/><path d="M4.5 1.8V4.2M9.5 1.8V4.2M2 5.2H12" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>';
        wrap.appendChild(button);

        if (input.value) normalizeDateInput(input, true);

        input.addEventListener('focus', function () {
            openPopup(input);
        });
        button.addEventListener('click', function () {
            openPopup(input);
            input.focus();
        });
    }

    Array.prototype.forEach.call(form.querySelectorAll('.js-erp-date-picker'), installDatePicker);

    form.addEventListener('focusin', function (event) {
        if (event.target && event.target.classList.contains('js-erp-date-picker')) {
            openPopup(event.target);
        }
    });

    form.addEventListener('click', function (event) {
        var trigger = event.target.closest('.erp-date-trigger');
        if (trigger) {
            var wrappedInput = trigger.closest('.erp-date-field') ? trigger.closest('.erp-date-field').querySelector('.js-erp-date-picker') : null;
            if (wrappedInput) {
                openPopup(wrappedInput);
                wrappedInput.focus();
            }
            return;
        }
        if (event.target && event.target.classList.contains('js-erp-date-picker')) {
            openPopup(event.target);
        }
    });

    form.addEventListener('focusout', function (event) {
        if (event.target && event.target.matches('input:not([type="file"]), textarea')) {
            normalizeByName(event.target);
        }
    });

    form.addEventListener('paste', function (event) {
        if (event.target && event.target.matches('input:not([type="file"]), textarea')) {
            normalizeAfterPaste(event.target);
        }
    });

    form.addEventListener('submit', function (event) {
        var ok = true;
        var textInputs = form.querySelectorAll('input[name]:not([type="file"]), textarea[name]');
        Array.prototype.forEach.call(textInputs, function (input) {
            if (!normalizeByName(input)) ok = false;
        });
        if (typeof window.erpDriverCreateValidateFiles === 'function') {
            if (!window.erpDriverCreateValidateFiles(form)) ok = false;
        }
        if (!ok) {
            event.preventDefault();
            var firstError = form.querySelector('.field.is-error input, .field.is-error textarea');
            if (firstError) firstError.focus();
            return;
        }
        Array.prototype.forEach.call(form.querySelectorAll('.js-erp-date-picker'), function (input) {
            if (input.value.trim()) {
                var parsed = parseDateValue(input.value);
                if (parsed) input.value = parsed.iso;
            }
        });
        if (typeof window.erpDriverCreateBeforeSubmit === 'function') {
            window.erpDriverCreateBeforeSubmit(form);
        }
    });

    document.addEventListener('mousedown', function (event) {
        var popup = document.querySelector('.erp-date-popover');
        if (!popup || !popup.classList.contains('is-open')) return;
        if (popup.contains(event.target)) return;
        if (event.target.closest('.erp-date-field')) return;
        closePopup();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closePopup();
    });
})();
