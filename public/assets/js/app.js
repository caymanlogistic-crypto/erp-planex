document.documentElement.classList.add('js-ready');

// ============================================================
// Driver form init — shared between create & edit modes
// ============================================================
(function () {
    var allowedExtensions = ['pdf','doc','docx','rtf','odt','xls','xlsx','csv','ods','jpg','jpeg','png','webp','gif','bmp','tif','tiff','heic','heif','txt'];
    var maxFileSize = 20 * 1024 * 1024;

    function getExt(name) {
        var p = String(name||'').split('.');
        return p.length > 1 ? p.pop().toLowerCase() : '';
    }
    function getKind(name) {
        var e = getExt(name);
        if (e === 'pdf') return 'pdf';
        if (['doc','docx','rtf','odt'].indexOf(e) !== -1) return 'word';
        if (['xls','xlsx','csv','ods'].indexOf(e) !== -1) return 'excel';
        if (['jpg','jpeg','png','webp','gif','bmp','tif','tiff','heic','heif'].indexOf(e) !== -1) return 'photo';
        return 'other';
    }
    function badgeMeta(kind) {
        if (kind === 'pdf') return {t:'PDF',c:'is-pdf'};
        if (kind === 'word') return {t:'DOC',c:'is-doc'};
        if (kind === 'excel') return {t:'XLS',c:'is-xls'};
        if (kind === 'photo') return {t:'IMG',c:'is-img'};
        return {t:'FILE',c:'is-other'};
    }

    function neutralSummary() {
        return {text:'Файл не выбран',badge:{text:'—',cls:'file-type-badge-empty'},filled:false};
    }

    function summarize(files) {
        if (!files||!files.length) return neutralSummary();
        if (files.length === 1) {
            var k = getKind(files[0].name);
            var b = badgeMeta(k);
            return {text:files[0].name,badge:{text:b.t,cls:b.c},filled:true};
        }
        return {text:'Выбрано файлов: '+files.length,badge:{text:'FILE',cls:'is-other'},filled:true};
    }

    function paintRow(row, summary) {
        var badge = row.querySelector('.file-type-badge');
        var meta  = row.querySelector('.file-meta');
        var btnLabel = row.querySelector('.file-action-btn span');
        var clearBtn = row.querySelector('.predef-file-clear') || row.querySelector('.file-remove');
        if (badge) { badge.className = 'file-type-badge ' + (summary.filled ? summary.badge.cls : 'file-type-badge-empty'); badge.textContent = summary.badge.text; }
        if (meta)  { meta.textContent = summary.text; meta.classList.toggle('is-hidden', !summary.text); }
        if (btnLabel) btnLabel.textContent = summary.filled ? 'Заменить' : 'Выбрать';
        if (clearBtn) clearBtn.classList.toggle('is-hidden', !summary.filled);
        row.classList.toggle('is-empty', !summary.filled);
        row.classList.toggle('has-file', summary.filled);
        row.classList.remove('is-loading');
    }

    function existingSummary(row) {
        var exist = row.dataset.hasExisting === '1';
        if (!exist) return neutralSummary();
        var cls = row.getAttribute('data-existing-badge-class') || 'file-type-badge';
        var txt = row.getAttribute('data-existing-badge-text') || '—';
        var metaTxt = row.getAttribute('data-existing-meta') || 'Файл';
        return {
            text: metaTxt,
            badge: {text: txt, cls: cls.replace('file-type-badge ','')},
            filled: true
        };
    }

    window.initDriverForm = function (form) {
        if (!form || form.dataset.driverFormReady === '1') return;
        form.dataset.driverFormReady = '1';
        if (window.initDriverInputValidation) {
            window.initDriverInputValidation(form);
        }

        // ── Extra phones ──
        var phoneBtn = form.querySelector('[data-add-extra-phone-btn]');
        var phoneContainer = form.querySelector('[data-extra-phones-container]');
        if (phoneBtn && phoneContainer) {
            phoneBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var row = document.createElement('div');
                row.className = 'driver-extra-phone-row';
                row.innerHTML = '<div class="field driver-extra-phone-field"><input type="text" name="extra_phones[]" class="field-input" placeholder="+7 900 000-00-00"><div class="field-msg"></div></div><div class="driver-extra-phone-comment-wrap"><input type="text" name="extra_phone_comments[]" class="field-input" placeholder="Комментарий к телефону"><button type="button" class="driver-extra-phone-remove" title="Удалить">×</button></div>';
                row.querySelector('.driver-extra-phone-remove').addEventListener('click', function () {
                    phoneContainer.removeChild(row);
                });
                phoneContainer.appendChild(row);
            });
            phoneContainer.querySelectorAll('.driver-extra-phone-remove').forEach(function (btn) {
                if (btn.dataset.phoneRemoveReady === '1') return;
                btn.dataset.phoneRemoveReady = '1';
                btn.addEventListener('click', function () {
                    var r = btn.closest('.driver-extra-phone-row');
                    if (r) phoneContainer.removeChild(r);
                });
            });
        }

        // ── Custom documents ──
        var addDocBtn = form.querySelector('[data-add-custom-doc-btn]');
        var docContainer = form.querySelector('[data-custom-docs-container]');
        if (addDocBtn && docContainer) {
            var docTypes = [];
            try { docTypes = JSON.parse(form.querySelector('[data-doc-types]') ? form.querySelector('[data-doc-types]').textContent : '[]'); } catch(e){}

            addDocBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var idx = docContainer.children.length;
                var row = document.createElement('div');
                row.className = 'file-item custom-doc-row document-file-row is-empty';
                row.setAttribute('data-file-row','custom');
                row.innerHTML = '<div class="file-type-badge file-type-badge-empty">—</div><div class="file-info"><div class="field custom-doc-title-field"><input type="text" name="custom_doc_type[]" class="field-input custom-doc-type-input" placeholder="Введите название" autocomplete="off"><div class="custom-doc-suggestions"></div><div class="field-msg"></div></div><div class="file-meta is-hidden"></div></div><button type="button" class="btn btn-secondary file-action-btn js-custom-file-pick-btn"><span>Выбрать</span></button><input type="file" class="file-input-hidden js-custom-file-input" name="custom_doc_file[]"><button type="button" class="file-remove" title="Удалить документ">×</button>';
                row.querySelector('.file-remove').addEventListener('click', function () {
                    docContainer.removeChild(row);
                });
                // Doc type suggestions
                var typeInput = row.querySelector('.custom-doc-type-input');
                var suggest = row.querySelector('.custom-doc-suggestions');
                if (typeInput && suggest && docTypes.length) {
                    typeInput.addEventListener('input', function () {
                        var q = typeInput.value.trim().toLowerCase();
                        if (!q) { suggest.innerHTML = ''; suggest.classList.remove('is-open'); return; }
                        var filtered = docTypes.filter(function(d){ return d.name.toLowerCase().indexOf(q) !== -1; }).slice(0,5);
                        if (!filtered.length) { suggest.innerHTML = ''; suggest.classList.remove('is-open'); return; }
                        suggest.innerHTML = filtered.map(function(d){ return '<div class="custom-doc-suggestion">'+d.name.replace(/</g,'&lt;').replace(/>/g,'&gt;')+'</div>'; }).join('');
                        suggest.classList.add('is-open');
                    });
                    suggest.addEventListener('mousedown', function (ev) {
                        if (ev.target.classList.contains('custom-doc-suggestion')) {
                            typeInput.value = ev.target.textContent;
                            suggest.innerHTML = '';
                            suggest.classList.remove('is-open');
                        }
                    });
                    typeInput.addEventListener('blur', function () { setTimeout(function(){ suggest.classList.remove('is-open'); }, 120); });
                }
                // File pick button
                var pickBtn = row.querySelector('.js-custom-file-pick-btn');
                var fileInput = row.querySelector('.js-custom-file-input');
                if (pickBtn && fileInput) {
                    pickBtn.addEventListener('click', function () { fileInput.click(); });
                    fileInput.addEventListener('change', function () {
                        var s = summarize(fileInput.files);
                        paintRow(row, s);
                    });
                }
                docContainer.appendChild(row);
            });
        }

        // ── Predef document rows ──
        var preClickState = {};
        form.querySelectorAll('[data-file-row="predef"]').forEach(function (row) {
            var code = row.getAttribute('data-file-code');
            if (!code) return;
            var fileInput = row.querySelector('input[type="file"]');
            var pickBtn = row.querySelector('.file-action-btn');
            var clearBtn = row.querySelector('.predef-file-clear');
            if (!fileInput) return;

            // Pick button
            if (pickBtn) {
                pickBtn.addEventListener('click', function () {
                    preClickState[code] = {
                        badgeCls: (row.querySelector('.file-type-badge')||{}).className || '',
                        badgeTxt: (row.querySelector('.file-type-badge')||{}).textContent || '',
                        metaTxt: (row.querySelector('.file-meta')||{}).textContent || '',
                        btnTxt: (pickBtn.querySelector('span')||{}).textContent || 'Выбрать',
                        empty: row.classList.contains('is-empty')
                    };
                    var badge = row.querySelector('.file-type-badge');
                    if (badge) { badge.className = 'file-type-badge is-loading'; badge.textContent = ''; }
                    fileInput.click();
                });
            }

            // File input change
            fileInput.addEventListener('change', function () {
                if (fileInput.files && fileInput.files.length > 0) {
                    var delInput = row.querySelector('[data-delete-predef-doc]');
                    if (delInput) delInput.value = '0';
                    paintRow(row, summarize(fileInput.files));
                    delete preClickState[code];
                } else if (preClickState[code]) {
                    var s = preClickState[code];
                    var badge = row.querySelector('.file-type-badge');
                    var meta = row.querySelector('.file-meta');
                    var btnLabel = pickBtn ? pickBtn.querySelector('span') : null;
                    if (badge) { badge.className = s.badgeCls; badge.textContent = s.badgeTxt; }
                    if (meta) meta.textContent = s.metaTxt;
                    if (btnLabel) btnLabel.textContent = s.btnTxt;
                    if (s.empty) row.classList.add('is-empty'); else row.classList.remove('is-empty');
                    delete preClickState[code];
                }
            });

            // Clear button
            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    fileInput.value = '';
                    var delInput = row.querySelector('[data-delete-predef-doc]');
                    if (row.dataset.hasExisting === '1' && delInput) {
                        delInput.value = '1';
                        paintRow(row, neutralSummary());
                        row.classList.remove('is-error','has-error');
                        return;
                    }
                    var exist = row.dataset.hasExisting === '1';
                    if (exist) {
                        paintRow(row, existingSummary(row));
                    } else {
                        paintRow(row, neutralSummary());
                    }
                    row.classList.remove('is-error', 'has-error');
                });
            }
        });

        // ── Upload size check ──
        form.addEventListener('submit', function (e) {
            var files = form.querySelectorAll('input[type="file"]');
            var total = 0;
            for (var j = 0; j < files.length; j++) {
                var f = files[j];
                if (!f.files) continue;
                for (var i = 0; i < f.files.length; i++) {
                    total += f.files[i].size;
                    if (f.files[i].size > maxFileSize) {
                        e.preventDefault();
                        showDriverClientError(form, 'Файл «' + f.files[i].name + '» больше 20 МБ.');
                        return;
                    }
                }
            }
            var maxTotal = 80 * 1024 * 1024;
            if (total > maxTotal) {
                e.preventDefault();
                showDriverClientError(form, 'Общий размер файлов превышает 80 МБ.');
            }
        });

        function showDriverClientError(form, message) {
            var errEl = form.querySelector('.driver-form-client-error');
            if (!errEl) {
                errEl = document.createElement('div');
                errEl.className = 'form-alert alert-error driver-form-client-error';
                errEl.innerHTML = '<div class="alert-mark"><svg width="11" height="11" viewBox="0 0 18 18" fill="none"><path d="M9 2L16.5 15H1.5L9 2Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 7V11M9 13V13.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></div><div class="alert-body"><div class="alert-body-title">Ошибка загрузки</div><div class="alert-body-sub"></div></div>';
                var target = form.querySelector('.driver-fields');
                if (target) {
                    target.before(errEl);
                } else {
                    form.prepend(errEl);
                }
            }
            errEl.querySelector('.alert-body-sub').textContent = message;
            errEl.hidden = false;
            setTimeout(function () { errEl.hidden = true; }, 5000);
        }
    };
})();

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

        var leadingDate = raw.match(/^(\d{8}|\d{4}[./-]\d{1,2}[./-]\d{1,2}|\d{1,2}[\s./-]\d{1,2}[\s./-](?:\d{4}|\d{2}))/);
        if (leadingDate) {
            raw = leadingDate[1];
        }

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
        var popupWidth = popup.offsetWidth || 228;
        var popupHeight = popup.offsetHeight || 290;
        var left = rect.left;
        var top = rect.bottom + 4;

        if (left + popupWidth > window.innerWidth - 8) {
            left = Math.max(8, window.innerWidth - popupWidth - 8);
        }
        if (top + popupHeight > window.innerHeight - 8) {
            top = Math.max(8, rect.top - popupHeight - 4);
        }

        popup.style.left = Math.round(left) + 'px';
        popup.style.top = Math.round(top) + 'px';
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

    function bindDriverInputValidation(form) {
        if (!form || form.dataset.driverInputValidationReady === '1') return;
        form.dataset.driverInputValidationReady = '1';

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
            if (event.target && event.target.classList.contains('js-erp-date-picker')) {
                event.preventDefault();
                var pastedText = '';
                if (event.clipboardData && typeof event.clipboardData.getData === 'function') {
                    pastedText = event.clipboardData.getData('text');
                } else if (window.clipboardData && typeof window.clipboardData.getData === 'function') {
                    pastedText = window.clipboardData.getData('Text');
                }

                var parsedDate = parseDateValue(pastedText);
                event.target.value = parsedDate ? parsedDate.display : collapseSpaces(pastedText);
                normalizeDateInput(event.target, false);
                return;
            }

            if (event.target && event.target.matches('input:not([type="file"]), textarea')) {
                normalizeAfterPaste(event.target);
            }
        });

        form.addEventListener('submit', function (event) {
            var uploadCheck = window.erpCheckUploadSize ? window.erpCheckUploadSize(form) : { ok: true };
            if (!uploadCheck.ok) {
                event.preventDefault();
                return;
            }
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
    }

    window.initDriverInputValidation = bindDriverInputValidation;

    var standaloneCreateForm = document.querySelector('form[action="/company/drivers/create"][method="post"]');
    if (standaloneCreateForm) {
        bindDriverInputValidation(standaloneCreateForm);
    }

    var modalCreateForm = document.querySelector('#driver-create-modal #driver-create-form');
    if (modalCreateForm) {
        bindDriverInputValidation(modalCreateForm);
    }

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

// ============================================================
// ERP Grid: общий frontend-поиск, сортировка и footer
// Атрибуты: data-erp-grid (на .table-card),
//           data-erp-grid-sort (на select внутри .toolbar-right),
//           data-erp-sort-date (на tr, опционально)
// Работает автоматически для всех .table-card[data-erp-grid]
// ============================================================
(function () {
    var DRIVER_GRID_STATE_KEY = 'companyDriversGridState';
    var VEHICLE_SET_GRID_STATE_KEY = 'companyVehicleSetsGridState';

    document.querySelectorAll('.table-card[data-erp-grid]').forEach(function (card) {
        var searchInput = card.querySelector('.toolbar-search, [data-erp-grid-search]');
        if (!searchInput) return;

        var countEl = card.querySelector('.found-label b');
        var table = card.querySelector('table.table');
        if (!table) return;
        var tbody = table.querySelector('tbody');
        if (!tbody) return;

        // Footer elements
        var footerRange = card.querySelector('.footer-range');
        var footerTotal = card.querySelector('.footer-total');
        var totalRows = tbody.querySelectorAll('tr').length;
        if (footerTotal) footerTotal.textContent = totalRows;

        // Sort state (default: date — newest first)
        var sortSelect = card.querySelector('select[data-erp-grid-sort]');
        var sortMode = sortSelect ? sortSelect.value : 'date';
        var stateKey = null;
        if (window.location.pathname === '/company/drivers') {
            stateKey = DRIVER_GRID_STATE_KEY;
        } else if (window.location.pathname === '/company/vehicle-sets') {
            stateKey = VEHICLE_SET_GRID_STATE_KEY;
        }

        function saveGridState() {
            if (!stateKey || !window.sessionStorage) return;
            sessionStorage.setItem(stateKey, JSON.stringify({
                search: searchInput.value || '',
                sort: sortSelect ? sortSelect.value : sortMode
            }));
        }

        function restoreGridState() {
            if (!stateKey || !window.sessionStorage) return;
            var raw = sessionStorage.getItem(stateKey);
            if (!raw) return;

            try {
                var state = JSON.parse(raw);
                if (state && typeof state.search === 'string') {
                    searchInput.value = state.search;
                }
                if (sortSelect && state && typeof state.sort === 'string') {
                    sortSelect.value = state.sort;
                    sortMode = sortSelect.value || 'date';
                }
            } catch (e) {
                sessionStorage.removeItem(stateKey);
            }
        }

        // Создать no-results элемент, если отсутствует
        var noResults = card.querySelector('.table-no-results');
        if (!noResults) {
            noResults = document.createElement('div');
            noResults.className = 'table-no-results is-hidden';
            noResults.innerHTML =
                '<div class="empty-state is-compact">' +
                    '<p class="empty-title">Ничего не найдено</p>' +
                    '<p class="empty-desc">Попробуйте изменить поисковый запрос</p>' +
                '</div>';
            var scroll = card.querySelector('.table-scroll');
            if (scroll) scroll.appendChild(noResults);
        }

        // --- Sort helpers ---

        function getSortValue(tr, mode) {
            if (mode === 'alpha') {
                var main = tr.querySelector('.cell-main');
                if (main) return main.textContent.trim().toLowerCase();
                var firstTd = tr.querySelector('td');
                return (firstTd ? firstTd.textContent : '').trim().toLowerCase();
            }
            if (mode === 'date') {
                var dateVal = tr.getAttribute('data-erp-sort-date');
                if (dateVal !== null) {
                    var num = parseInt(dateVal, 10);
                    return isNaN(num) ? 0 : num;
                }
                // Fallback: extract ID from the first action link href
                var link = tr.querySelector('a[href]');
                if (link) {
                    var match = link.getAttribute('href').match(/\/(\d+)(?:\/|$|\?)/);
                    if (match) {
                        var idNum = parseInt(match[1], 10);
                        return isNaN(idNum) ? 0 : idNum;
                    }
                }
                return 0;
            }
            return '';
        }

        function sortRows() {
            var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
            rows.sort(function (a, b) {
                var va = getSortValue(a, sortMode);
                var vb = getSortValue(b, sortMode);
                if (sortMode === 'date') {
                    return vb - va; // newer first (descending)
                }
                // alpha: ascending
                if (va < vb) return -1;
                if (va > vb) return 1;
                return 0;
            });
            rows.forEach(function (row) { tbody.appendChild(row); });
        }

        // --- Footer update ---

        function updateFooter() {
            var visibleRows = tbody.querySelectorAll('tr:not(.is-hidden)').length;
            if (footerRange) {
                footerRange.textContent = visibleRows === 0 ? '0' : '1–' + visibleRows;
            }
            if (footerTotal) footerTotal.textContent = totalRows;
        }

        // --- Filter + combined update ---

        function filterRows() {
            var query = searchInput.value.trim().toLowerCase();
            var rows = tbody.querySelectorAll('tr');
            var visible = 0;

            rows.forEach(function (tr) {
                var text = tr.textContent.toLowerCase();
                if (query === '' || text.indexOf(query) !== -1) {
                    tr.classList.remove('is-hidden');
                    visible++;
                } else {
                    tr.classList.add('is-hidden');
                }
            });

            if (countEl) {
                countEl.textContent = visible;
            }

            if (query !== '' && visible === 0) {
                table.style.display = 'none';
                noResults.classList.remove('is-hidden');
            } else {
                table.style.display = '';
                noResults.classList.add('is-hidden');
            }

            updateFooter();
        }

        // --- Sort select change ---

        if (sortSelect) {
            sortSelect.addEventListener('change', function () {
                sortMode = sortSelect.value;
                sortRows();
                filterRows();
                saveGridState();
            });
        }

        // --- Search input events ---

        searchInput.addEventListener('input', function () {
            filterRows();
            saveGridState();
        });

        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                searchInput.value = '';
                filterRows();
                saveGridState();
            }
        });

        // --- Initial sort + footer ---
        restoreGridState();
        sortRows();
        filterRows();
        updateFooter();
    });
})();

// ============================================================
// Общий валидатор загрузки документов (20 МБ/файл, 80 МБ/форма)
// ============================================================
(function () {
    var MAX_FILE_SIZE = 20 * 1024 * 1024;   // 20 MB per file
    var MAX_TOTAL_SIZE = 80 * 1024 * 1024;  // 80 MB per form

    var overlay = null;

    function getOverlay() {
        if (overlay) return overlay;
        overlay = document.createElement('div');
        overlay.className = 'upload-error-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Ошибка загрузки документов');
        overlay.innerHTML =
            '<div class="upload-error-panel">' +
                '<div class="upload-error-head">' +
                    '<span class="upload-error-mark"></span>' +
                    '<div class="upload-error-head-text">' +
                        '<div class="upload-error-title" data-ue-title></div>' +
                        '<div class="upload-error-subtitle" data-ue-subtitle></div>' +
                    '</div>' +
                '</div>' +
                '<div class="upload-error-body" data-ue-body></div>' +
                '<div class="upload-error-actions">' +
                    '<button type="button" class="btn btn-secondary" data-upload-error-close>Понятно</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(overlay);

        overlay.querySelector('[data-upload-error-close]').addEventListener('click', closeOverlay);
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeOverlay();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('is-open')) closeOverlay();
        });

        return overlay;
    }

    function closeOverlay() {
        var el = getOverlay();
        el.classList.remove('is-open');
    }

    function showError(title, subtitle, bodyHtml) {
        var el = getOverlay();
        el.querySelector('[data-ue-title]').textContent = title;
        el.querySelector('[data-ue-subtitle]').textContent = subtitle;
        el.querySelector('[data-ue-body]').innerHTML = bodyHtml;
        el.classList.add('is-open');
        el.querySelector('[data-upload-error-close]').focus();
    }

    function formatMB(bytes) {
        return (bytes / (1024 * 1024)).toFixed(1).replace('.0', '') + ' МБ';
    }

    function escapeHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /**
     * Проверить размеры файлов формы перед отправкой.
     * @param {HTMLFormElement} form
     * @returns {{ok: boolean, errorType: string|null}}
     */
    window.erpCheckUploadSize = function (form) {
        if (!form) return { ok: true, errorType: null };

        var fileInputs = form.querySelectorAll('input[type="file"]');
        var totalSize = 0;
        var tooBigFiles = [];

        Array.prototype.forEach.call(fileInputs, function (input) {
            if (!input.files || !input.files.length) return;
            for (var i = 0; i < input.files.length; i++) {
                var file = input.files[i];
                totalSize += file.size;
                if (file.size > MAX_FILE_SIZE) {
                    tooBigFiles.push(escapeHtml(file.name) + ' (' + formatMB(file.size) + ')');
                }
            }
        });

        // Check single file limit
        if (tooBigFiles.length > 0) {
            var singleBody = '<p>Файл слишком большой.</p>' +
                '<p>Максимальный размер одного файла — 20 МБ.</p>';
            if (tooBigFiles.length <= 3) {
                singleBody += '<p>Проблемные файлы: ' + tooBigFiles.join(', ') + '</p>';
            }
            singleBody += '<p>Уменьшите файл или загрузите другой документ.</p>';
            showError('Ошибка загрузки документов', 'Файлы не были отправлены', singleBody);
            return { ok: false, errorType: 'single' };
        }

        // Check total size limit
        if (totalSize > MAX_TOTAL_SIZE) {
            var totalBody = '<p>Общий размер выбранных файлов слишком большой.</p>' +
                '<p>Максимум за одну отправку — 80 МБ.</p>' +
                '<p>Текущий размер: ' + formatMB(totalSize) + '.</p>' +
                '<p>Уменьшите количество файлов или загрузите документы позже из карточки.</p>';
            showError('Ошибка загрузки документов', 'Файлы не были отправлены', totalBody);
            return { ok: false, errorType: 'total' };
        }

        return { ok: true, errorType: null };
    };
})();

// ============================================================
// FINAL3 Modal system: openModal / closeModal / closeOnOverlay
// ============================================================
window.erpExecuteInlineScripts = function (root) {
    if (!root) return;
    root.querySelectorAll('script').forEach(function (oldScript) {
        var newScript = document.createElement('script');
        Array.prototype.slice.call(oldScript.attributes).forEach(function (attr) {
            newScript.setAttribute(attr.name, attr.value);
        });
        newScript.textContent = oldScript.textContent;
        oldScript.parentNode.replaceChild(newScript, oldScript);
    });
};

window.openModal = function (id) {
    var modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('is-open');
    if (id === 'driver-create-modal' && window.bindDriverCreateModalForm) {
        window.bindDriverCreateModalForm();
    }
    if (id === 'vehicle-set-create-modal' && window.bindVehicleSetCreateModalForm) {
        window.bindVehicleSetCreateModalForm();
    }
};

function resetModalContent(modal) {
    if (!modal || modal.dataset.resetOnClose !== '1') return;
    var body = modal.querySelector('.modal-body');
    if (!body) return;
    if (!modal.dataset.initialBodyHtml) return;

    body.innerHTML = modal.dataset.initialBodyHtml;

    if (modal.id === 'vehicle-set-create-modal') {
        if (typeof window.erpExecuteInlineScripts === 'function') {
            window.erpExecuteInlineScripts(body);
        }
        if (window.bindVehicleSetCreateModalForm) {
            window.bindVehicleSetCreateModalForm();
        }
        return;
    }

    var form = body.querySelector('form');
    if (form) {
        form.dataset.driverFormReady = '0';
        form.dataset.modalSubmitReady = '0';
        form.dataset.modalCreateSubmitReady = '0';
        if (window.initDriverForm) {
            window.initDriverForm(form);
        }
    }
}

window.closeModal = function (id) {
    var modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.remove('is-open');
    resetModalContent(modal);
};

window.closeOnOverlay = function (event, overlay) {
    if (overlay && overlay.dataset.closeOnOverlay === '0') {
        return;
    }
    if (event.target === overlay) {
        overlay.classList.remove('is-open');
        resetModalContent(overlay);
    }
};

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        var openModals = document.querySelectorAll('.modal-overlay.is-open');
        openModals.forEach(function (m) {
            if (m.dataset.closeOnEscape === '0') return;
            m.classList.remove('is-open');
            resetModalContent(m);
        });
    }
});

// ============================================================
// Driver row double-click → modal view/edit
// ============================================================
(function () {
    var driverTable = document.querySelector('.table-card[data-erp-grid] tbody');
    if (!driverTable) return;

    var createForm = document.querySelector('#driver-create-modal');
    if (!createForm) {
        // Not on the drivers list page — only page with create modal
        // Also check for /company/drivers/create fallback page
        if (!document.querySelector('form[action="/company/drivers/create"]')) return;
    }

    var MODAL_ID = 'driver-view-modal';
    var DRIVER_GRID_STATE_KEY = 'companyDriversGridState';
    var shell = null;

    function getShell() {
        if (shell) return shell;
        shell = document.getElementById(MODAL_ID);
        if (shell) return shell;

        shell = document.createElement('div');
        shell.className = 'modal-overlay driver-view-overlay';
        shell.id = MODAL_ID;
        shell.setAttribute('role', 'dialog');
        shell.setAttribute('aria-modal', 'true');
        shell.dataset.closeOnOverlay = '0';
        shell.dataset.closeOnEscape = '0';
        shell.innerHTML =
            '<div class="modal modal-lg driver-view-modal-inner">' +
            '  <div class="modal-head">' +
            '    <span class="modal-title" data-modal-title></span>' +
            '    <button type="button" class="modal-close" data-modal-close>&times;</button>' +
            '  </div>' +
            '</div>';

        shell.querySelector('[data-modal-close]').addEventListener('click', function () {
            closeDriverModal();
        });
        document.body.appendChild(shell);
        return shell;
    }

    function getModal() {
        return getShell().querySelector('.modal');
    }

    function saveDriverListState() {
        if (window.location.pathname !== '/company/drivers' || !window.sessionStorage) return;

        var card = document.querySelector('.table-card[data-erp-grid]');
        if (!card) return;

        var searchInput = card.querySelector('.toolbar-search, [data-erp-grid-search]');
        var sortSelect = card.querySelector('select[data-erp-grid-sort]');

        sessionStorage.setItem(DRIVER_GRID_STATE_KEY, JSON.stringify({
            search: searchInput ? (searchInput.value || '') : '',
            sort: sortSelect ? sortSelect.value : 'date'
        }));
    }

    function closeDriverModal() {
        var s = getShell();
        var shouldRefreshList = s._refreshListOnClose === true;

        window.closeModal(MODAL_ID);

        if (!shouldRefreshList) return;

        s._refreshListOnClose = false;
        saveDriverListState();
        window.location.reload();
    }

    function getDeleteConfirmShell() {
        var existing = document.getElementById('driver-delete-confirm-modal');
        if (existing) return existing;

        var confirmShell = document.createElement('div');
        confirmShell.className = 'modal-overlay';
        confirmShell.id = 'driver-delete-confirm-modal';
        confirmShell.setAttribute('role', 'dialog');
        confirmShell.setAttribute('aria-modal', 'true');
        confirmShell.dataset.closeOnOverlay = '0';
        confirmShell.dataset.closeOnEscape = '0';
        confirmShell.innerHTML =
            '<div class="modal">' +
            '  <div class="modal-head">' +
            '    <span class="modal-title">\u0423\u0434\u0430\u043b\u0438\u0442\u044c \u0432\u043e\u0434\u0438\u0442\u0435\u043b\u044f?</span>' +
            '    <button type="button" class="modal-close" data-driver-delete-close>&times;</button>' +
            '  </div>' +
            '  <div class="modal-body">' +
            '    <div class="driver-delete-confirm-body">' +
            '      <div class="driver-delete-confirm-head">' +
            '        <div class="modal-icon is-danger" aria-hidden="true">' +
            '          <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 2L16.5 15H1.5L9 2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"></path><path d="M9 7V11M9 13V13.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path></svg>' +
            '        </div>' +
            '        <div class="driver-delete-confirm-title">\u042d\u0442\u043e \u0434\u0435\u0439\u0441\u0442\u0432\u0438\u0435 \u0443\u0434\u0430\u043b\u0438\u0442 \u0432\u043e\u0434\u0438\u0442\u0435\u043b\u044f \u0438\u0437 \u0431\u0430\u0437\u044b \u0438 \u043e\u0447\u0438\u0441\u0442\u0438\u0442 \u0432\u0441\u0435 \u0435\u0433\u043e \u0444\u0430\u0439\u043b\u044b.</div>' +
            '      </div>' +
            '      <div class="driver-delete-confirm-text">\u0414\u043b\u044f \u0437\u0430\u0449\u0438\u0442\u044b \u043e\u0442 \u0441\u043b\u0443\u0447\u0430\u0439\u043d\u043e\u0433\u043e \u0443\u0434\u0430\u043b\u0435\u043d\u0438\u044f \u0432\u0432\u0435\u0434\u0438\u0442\u0435 <b>\u0423\u0414\u0410\u041b\u0418\u0422\u042c</b>.</div>' +
            '      <div class="driver-delete-confirm-name" data-driver-delete-name></div>' +
            '      <div class="field driver-delete-confirm-field">' +
            '        <input type="text" class="field-input" data-driver-delete-input autocomplete="off" placeholder="\u0412\u0432\u0435\u0434\u0438\u0442\u0435 \u0423\u0414\u0410\u041b\u0418\u0422\u042c">' +
            '      </div>' +
            '      <div class="driver-delete-confirm-error is-hidden" data-driver-delete-error></div>' +
            '    </div>' +
            '  </div>' +
            '  <div class="modal-foot">' +
            '    <button type="button" class="btn btn-ghost" data-driver-delete-cancel>\u041e\u0442\u043c\u0435\u043d\u0430</button>' +
            '    <button type="button" class="btn btn-danger" data-driver-delete-confirm disabled>\u0423\u0434\u0430\u043b\u0438\u0442\u044c</button>' +
            '  </div>' +
            '</div>';

        document.body.appendChild(confirmShell);
        return confirmShell;
    }

    function bindDeleteConfirm() {
        var confirmShell = getDeleteConfirmShell();
        var closeBtn = confirmShell.querySelector('[data-driver-delete-close]');
        var cancelBtn = confirmShell.querySelector('[data-driver-delete-cancel]');
        var confirmBtn = confirmShell.querySelector('[data-driver-delete-confirm]');
        var input = confirmShell.querySelector('[data-driver-delete-input]');
        var errorBox = confirmShell.querySelector('[data-driver-delete-error]');

        function closeConfirm() {
            window.closeModal('driver-delete-confirm-modal');
            if (input) input.value = '';
            if (errorBox) {
                errorBox.textContent = '';
                errorBox.classList.add('is-hidden');
            }
            if (confirmBtn) confirmBtn.disabled = true;
        }

        function syncConfirmState() {
            if (!input || !confirmBtn) return;
            confirmBtn.disabled = input.value.trim().toUpperCase() !== '\u0423\u0414\u0410\u041b\u0418\u0422\u042c';
        }

        if (closeBtn && closeBtn.dataset.driverDeleteReady !== '1') {
            closeBtn.dataset.driverDeleteReady = '1';
            closeBtn.addEventListener('click', closeConfirm);
        }
        if (cancelBtn && cancelBtn.dataset.driverDeleteReady !== '1') {
            cancelBtn.dataset.driverDeleteReady = '1';
            cancelBtn.addEventListener('click', closeConfirm);
        }
        if (input && input.dataset.driverDeleteReady !== '1') {
            input.dataset.driverDeleteReady = '1';
            input.addEventListener('input', syncConfirmState);
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !confirmBtn.disabled) {
                    e.preventDefault();
                    confirmBtn.click();
                }
            });
        }
        if (confirmBtn && confirmBtn.dataset.driverDeleteReady !== '1') {
            confirmBtn.dataset.driverDeleteReady = '1';
            confirmBtn.addEventListener('click', function () {
                var s = getShell();
                if (!s._driverId) return;

                confirmBtn.disabled = true;
                if (errorBox) {
                    errorBox.textContent = '';
                    errorBox.classList.add('is-hidden');
                }

                fetch('/company/drivers/' + s._driverId + '/modal-delete', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function (r) { if (!r.ok) throw Error(r.status); return r.json(); })
                .then(function (result) {
                    if (!result || !result.success) {
                        throw new Error(result && result.error ? result.error : '\u041d\u0435 \u0443\u0434\u0430\u043b\u043e\u0441\u044c \u0443\u0434\u0430\u043b\u0438\u0442\u044c \u0432\u043e\u0434\u0438\u0442\u0435\u043b\u044f.');
                    }

                    closeConfirm();
                    s._refreshListOnClose = false;
                    saveDriverListState();
                    window.closeModal(MODAL_ID);
                    window.location.reload();
                })
                .catch(function (error) {
                    if (errorBox) {
                        errorBox.textContent = error && error.message ? error.message : '\u041d\u0435 \u0443\u0434\u0430\u043b\u043e\u0441\u044c \u0443\u0434\u0430\u043b\u0438\u0442\u044c \u0432\u043e\u0434\u0438\u0442\u0435\u043b\u044f.';
                        errorBox.classList.remove('is-hidden');
                    }
                    syncConfirmState();
                });
            });
        }

        return {
            open: function (driverName) {
                var nameBox = confirmShell.querySelector('[data-driver-delete-name]');
                if (nameBox) {
                    nameBox.textContent = driverName || '';
                }
                if (input) {
                    input.value = '';
                }
                if (errorBox) {
                    errorBox.textContent = '';
                    errorBox.classList.add('is-hidden');
                }
                if (confirmBtn) {
                    confirmBtn.disabled = true;
                }
                window.openModal('driver-delete-confirm-modal');
                if (input) input.focus();
            }
        };
    }

    function showModalError(msg) {
        var modal = getModal();
        var oldBody = modal.querySelector('.modal-body');
        var oldFoot = modal.querySelector('.modal-foot');
        if (oldBody) oldBody.remove();
        if (oldFoot) oldFoot.remove();
        var el = document.createElement('div');
        el.className = 'modal-body';
        el.innerHTML = '<div class="notice warn" style="margin:16px">' + msg + '</div>';
        modal.appendChild(el);
    }

    function showLoading(message) {
        var modal = getModal();
        // Remove any existing body/foot
        var oldBody = modal.querySelector('.modal-body');
        var oldFoot = modal.querySelector('.modal-foot');
        if (oldBody) oldBody.remove();
        if (oldFoot) oldFoot.remove();
        var el = document.createElement('div');
        el.className = 'modal-body';
        el.innerHTML = '<div class="driver-modal-loading">' + (message || '...') + '</div>';
        modal.appendChild(el);
    }

    function setContent(html) {
        var modal = getModal();
        // Ensure modal title is set for driver view/edit
        var titleEl = modal.querySelector('[data-modal-title]');
        if (titleEl) titleEl.textContent = '\u0412\u043e\u0434\u0438\u0442\u0435\u043b\u044c';
        // Remove existing body/foot
        var oldBody = modal.querySelector('.modal-body');
        var oldFoot = modal.querySelector('.modal-foot');
        if (oldBody) oldBody.remove();
        if (oldFoot) oldFoot.remove();
        var tmp = document.createElement('div');
        tmp.innerHTML = html;
        var body = tmp.querySelector('.modal-body');
        var foot = tmp.querySelector('.modal-foot');
        if (body) modal.appendChild(body);
        if (foot) modal.appendChild(foot);
    }

    function loadView(driverId) {
        showLoading('...');
        var s = getShell();
        s._driverId = driverId;
        s.classList.add('is-open');

        fetch('/company/drivers/' + driverId + '/modal-view', { credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw Error(r.status); return r.text(); })
            .then(function (html) {
                setContent(html);
                bindViewButtons();
            })
            .catch(function () {
                showModalError('    .');
            });
    }

    function loadEdit(driverId) {
        showLoading('...');

        fetch('/company/drivers/' + driverId + '/modal-edit', { credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw Error(r.status); return r.text(); })
            .then(function (html) {
                setContent(html);
                // Initialize the edit form's interactive elements
                var s = getShell();
                var editForm = s.querySelector('#driver-edit-form');
                if (editForm && window.initDriverForm) {
                    window.initDriverForm(editForm);
                }
                bindEditButtons();
            })
            .catch(function () {
                showModalError('   .');
            });
    }

    function bindViewButtons() {
        var s = getShell();
        var editBtn = s.querySelector('[data-driver-edit-btn]');
        var cancelBtn = s.querySelector('[data-driver-view-cancel]');
        var deleteBtn = s.querySelector('[data-driver-delete-btn]');

        if (editBtn) {
            var newBtn = editBtn.cloneNode(true);
            editBtn.parentNode.replaceChild(newBtn, editBtn);
            newBtn.addEventListener('click', function () {
                loadEdit(s._driverId);
            });
        }
        if (cancelBtn) {
            var newCancel = cancelBtn.cloneNode(true);
            cancelBtn.parentNode.replaceChild(newCancel, cancelBtn);
            newCancel.addEventListener('click', function () {
                closeDriverModal();
            });
        }
        if (deleteBtn) {
            var newDelete = deleteBtn.cloneNode(true);
            deleteBtn.parentNode.replaceChild(newDelete, deleteBtn);
            newDelete.addEventListener('click', function () {
                var nameEl = s.querySelector('.driver-view-name');
                var driverName = nameEl ? nameEl.textContent.trim() : '';
                bindDeleteConfirm().open(driverName);
            });
        }
    }

    function bindEditButtons() {
        var s = getShell();
        var cancelBtn = s.querySelector('[data-driver-cancel-edit-btn]');
        var form = s.querySelector('#driver-edit-form');

        if (cancelBtn) {
            var newCancel = cancelBtn.cloneNode(true);
            cancelBtn.parentNode.replaceChild(newCancel, cancelBtn);
            newCancel.addEventListener('click', function () {
                loadView(s._driverId);
            });
        }

        if (form && form.dataset.modalSubmitReady !== '1') {
            form.dataset.modalSubmitReady = '1';
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var formData = new FormData(form);
                showLoading('...');

                fetch('/company/drivers/' + s._driverId + '/modal-edit', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                })
                .then(function (r) { if (!r.ok) throw Error(r.status); return r.text(); })
                .then(function (html) {
                    var tmp = document.createElement('div');
                    tmp.innerHTML = html;
                    if (tmp.querySelector('#driver-edit-form')) {
                        // Validation errors — stay in edit mode
                        setContent(html);
                        // Re-init the form after re-render
                        var ef = getShell().querySelector('#driver-edit-form');
                        if (ef && window.initDriverForm) {
                            ef.dataset.driverFormReady = '0';
                            ef.dataset.modalSubmitReady = '0';
                            window.initDriverForm(ef);
                        }
                        bindEditButtons();
                    } else {
                        s._refreshListOnClose = true;
                        setContent(html);
                        bindViewButtons();
                    }
                })
                .catch(function () {
                    showModalError('  .');
                });
            });
        }
    }

    // Double-click handler
    driverTable.addEventListener('dblclick', function (e) {
        if (e.target.closest('a, button, input, select, textarea, label')) return;
        var row = e.target.closest('tr');
        if (!row) return;
        var driverId = row.getAttribute('data-driver-id');
        if (!driverId) return;
        loadView(driverId);
    });
})();

// ============================================================
// Init driver forms on page load (create modal + standalone page)
// ============================================================
(function () {
    window.bindDriverCreateModalForm = function () {
        var createModal = document.getElementById('driver-create-modal');
        if (!createModal) return;
        var form = createModal.querySelector('#driver-create-form');
        if (!form || form.dataset.modalCreateSubmitReady === '1') return;

        form.dataset.modalCreateSubmitReady = '1';
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var formData = new FormData(form);
            fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(function (r) { if (!r.ok) throw Error(r.status); return r.text(); })
            .then(function (html) {
                var tmp = document.createElement('div');
                tmp.innerHTML = html;

                var returnedForm = tmp.querySelector('#driver-create-form');
                if (returnedForm) {
                    var modalBody = createModal.querySelector('.modal-body');
                    if (!modalBody) return;

                    var returnedPageContent = tmp.querySelector('.page-content');
                    modalBody.innerHTML = returnedPageContent ? returnedPageContent.innerHTML : returnedForm.outerHTML;

                    var newForm = createModal.querySelector('#driver-create-form');
                    if (newForm) {
                        newForm.dataset.driverFormReady = '0';
                        newForm.dataset.modalCreateSubmitReady = '0';
                        if (window.initDriverForm) {
                            window.initDriverForm(newForm);
                        }
                    }
                    window.bindDriverCreateModalForm();
                    return;
                }

                window.location.reload();
            })
            .catch(function () {
                window.location.reload();
            });
        });
    };

    var createModal = document.getElementById('driver-create-modal');
    if (createModal && createModal.dataset.resetOnClose === '1') {
        var createModalBody = createModal.querySelector('.modal-body');
        if (createModalBody && !createModal.dataset.initialBodyHtml) {
            createModal.dataset.initialBodyHtml = createModalBody.innerHTML;
        }
    }

    // Create modal form
    var createModalForm = document.querySelector('#driver-create-modal #driver-create-form');
    if (createModalForm && window.initDriverForm) {
        window.initDriverForm(createModalForm);
    }
    window.bindDriverCreateModalForm();

    // Standalone create page form
    var standaloneForm = document.querySelector('form[action="/company/drivers/create"][method="post"]');
    if (standaloneForm && !standaloneForm.closest('#driver-create-modal') && window.initDriverForm) {
        window.initDriverForm(standaloneForm);
    }
})();

(function () {
    window.bindVehicleSetCreateModalForm = function () {
        var createModal = document.getElementById('vehicle-set-create-modal');
        if (!createModal) return;

        var form = createModal.querySelector('#vehicle-set-create-form');
        if (!form || form.dataset.modalCreateSubmitReady === '1') return;

        form.dataset.modalCreateSubmitReady = '1';
        form.addEventListener('submit', function (event) {
            if (event.defaultPrevented) {
                return;
            }

            event.preventDefault();

            var formData = new FormData(form);
            fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
                .then(function (response) {
                    if (!response.ok) throw new Error(String(response.status));
                    return response.text();
                })
                .then(function (html) {
                    var tmp = document.createElement('div');
                    tmp.innerHTML = html;

                    var returnedForm = tmp.querySelector('#vehicle-set-create-form');
                    if (returnedForm) {
                        var modalBody = createModal.querySelector('.modal-body');
                        if (!modalBody) return;

                        var returnedPageContent = tmp.querySelector('.page-content');
                        modalBody.innerHTML = returnedPageContent ? returnedPageContent.innerHTML : returnedForm.outerHTML;

                        if (typeof window.erpExecuteInlineScripts === 'function') {
                            window.erpExecuteInlineScripts(modalBody);
                        }

                        var newForm = createModal.querySelector('#vehicle-set-create-form');
                        if (newForm) {
                            newForm.dataset.modalCreateSubmitReady = '0';
                        }

                        window.bindVehicleSetCreateModalForm();
                        return;
                    }

                    window.location.reload();
                })
                .catch(function () {
                    window.location.reload();
                });
        });
    };

    var createModal = document.getElementById('vehicle-set-create-modal');
    if (createModal && createModal.dataset.resetOnClose === '1') {
        var createModalBody = createModal.querySelector('.modal-body');
        if (createModalBody && !createModal.dataset.initialBodyHtml) {
            createModal.dataset.initialBodyHtml = createModalBody.innerHTML;
        }
    }

    window.bindVehicleSetCreateModalForm();
})();

// ============================================================
// Vehicle sets list modal view/edit/delete
// ============================================================
(function () {
    var vehicleGrid = document.querySelector('.table-card[data-vehicle-sets-grid]');
    if (!vehicleGrid) return;

    var tableBody = vehicleGrid.querySelector('tbody');
    if (!tableBody) return;

    var MODAL_ID = 'vehicle-set-view-modal';
    var CONFIRM_MODAL_ID = 'vehicle-set-delete-confirm-modal';
    var VEHICLE_GRID_STATE_KEY = 'companyVehicleSetsGridState';
    var shell = null;

    function getShell() {
        if (shell) return shell;
        shell = document.getElementById(MODAL_ID);
        if (shell) return shell;

        shell = document.createElement('div');
        shell.className = 'modal-overlay driver-view-overlay';
        shell.id = MODAL_ID;
        shell.setAttribute('role', 'dialog');
        shell.setAttribute('aria-modal', 'true');
        shell.dataset.closeOnOverlay = '0';
        shell.dataset.closeOnEscape = '0';
        shell.innerHTML =
            '<div class="modal modal-lg driver-view-modal-inner">' +
            '  <div class="modal-head">' +
            '    <span class="modal-title" data-modal-title>\u0422\u0440\u0430\u043d\u0441\u043f\u043e\u0440\u0442</span>' +
            '    <button type="button" class="modal-close" data-modal-close>&times;</button>' +
            '  </div>' +
            '</div>';

        shell.querySelector('[data-modal-close]').addEventListener('click', function () {
            closeVehicleModal();
        });
        document.body.appendChild(shell);
        return shell;
    }

    function getModal() {
        return getShell().querySelector('.modal');
    }

    function saveVehicleGridState() {
        if (window.location.pathname !== '/company/vehicle-sets' || !window.sessionStorage) return;

        var card = document.querySelector('.table-card[data-vehicle-sets-grid]');
        if (!card) return;

        var searchInput = card.querySelector('.toolbar-search, [data-erp-grid-search]');
        var sortSelect = card.querySelector('select[data-erp-grid-sort]');

        sessionStorage.setItem(VEHICLE_GRID_STATE_KEY, JSON.stringify({
            search: searchInput ? (searchInput.value || '') : '',
            sort: sortSelect ? sortSelect.value : 'date'
        }));
    }

    function closeVehicleModal() {
        var currentShell = getShell();
        var shouldRefreshList = currentShell._refreshListOnClose === true;

        window.closeModal(MODAL_ID);

        if (!shouldRefreshList) return;

        currentShell._refreshListOnClose = false;
        saveVehicleGridState();
        window.location.reload();
    }

    function showModalError(message) {
        var modal = getModal();
        var oldBody = modal.querySelector('.modal-body');
        var oldFoot = modal.querySelector('.modal-foot');
        if (oldBody) oldBody.remove();
        if (oldFoot) oldFoot.remove();
        var body = document.createElement('div');
        body.className = 'modal-body';
        body.innerHTML = '<div class="notice warn" style="margin:16px">' + message + '</div>';
        modal.appendChild(body);
    }

    function showLoading(message) {
        var modal = getModal();
        var oldBody = modal.querySelector('.modal-body');
        var oldFoot = modal.querySelector('.modal-foot');
        if (oldBody) oldBody.remove();
        if (oldFoot) oldFoot.remove();
        var body = document.createElement('div');
        body.className = 'modal-body';
        body.innerHTML = '<div class="driver-modal-loading">' + (message || '\u0417\u0430\u0433\u0440\u0443\u0437\u043a\u0430...') + '</div>';
        modal.appendChild(body);
    }

    function setContent(html) {
        var modal = getModal();
        var oldBody = modal.querySelector('.modal-body');
        var oldFoot = modal.querySelector('.modal-foot');
        modal.querySelectorAll('script[data-modal-inline-script="1"]').forEach(function (script) {
            script.remove();
        });
        if (oldBody) oldBody.remove();
        if (oldFoot) oldFoot.remove();

        var tmp = document.createElement('div');
        tmp.innerHTML = html;

        var body = tmp.querySelector('.modal-body');
        var foot = tmp.querySelector('.modal-foot');
        if (body) modal.appendChild(body);
        if (foot) modal.appendChild(foot);
        tmp.querySelectorAll('script').forEach(function (script) {
            var cloned = script.cloneNode(true);
            cloned.setAttribute('data-modal-inline-script', '1');
            (body || modal).appendChild(cloned);
        });

        if (typeof window.erpExecuteInlineScripts === 'function') {
            window.erpExecuteInlineScripts(modal);
        }
    }

    function getDeleteConfirmShell() {
        var existing = document.getElementById(CONFIRM_MODAL_ID);
        if (existing) return existing;

        var confirmShell = document.createElement('div');
        confirmShell.className = 'modal-overlay';
        confirmShell.id = CONFIRM_MODAL_ID;
        confirmShell.setAttribute('role', 'dialog');
        confirmShell.setAttribute('aria-modal', 'true');
        confirmShell.dataset.closeOnOverlay = '0';
        confirmShell.dataset.closeOnEscape = '0';
        confirmShell.innerHTML =
            '<div class="modal">' +
            '  <div class="modal-head">' +
            '    <span class="modal-title">\u0423\u0434\u0430\u043b\u0438\u0442\u044c \u0442\u0440\u0430\u043d\u0441\u043f\u043e\u0440\u0442?</span>' +
            '    <button type="button" class="modal-close" data-vehicle-set-delete-close>&times;</button>' +
            '  </div>' +
            '  <div class="modal-body">' +
            '    <div class="driver-delete-confirm-body">' +
            '      <div class="driver-delete-confirm-head">' +
            '        <div class="modal-icon is-danger" aria-hidden="true">' +
            '          <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 2L16.5 15H1.5L9 2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"></path><path d="M9 7V11M9 13V13.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path></svg>' +
            '        </div>' +
            '        <div class="driver-delete-confirm-title">\u042d\u0442\u043e \u0434\u0435\u0439\u0441\u0442\u0432\u0438\u0435 \u0443\u0434\u0430\u043b\u0438\u0442 \u0442\u0440\u0430\u043d\u0441\u043f\u043e\u0440\u0442 \u0438\u0437 \u0431\u0430\u0437\u044b \u0438 \u043e\u0447\u0438\u0441\u0442\u0438\u0442 \u0432\u0441\u0435 \u0435\u0433\u043e \u0444\u0430\u0439\u043b\u044b.</div>' +
            '      </div>' +
            '      <div class="driver-delete-confirm-text">\u0414\u043b\u044f \u0437\u0430\u0449\u0438\u0442\u044b \u043e\u0442 \u0441\u043b\u0443\u0447\u0430\u0439\u043d\u043e\u0433\u043e \u0443\u0434\u0430\u043b\u0435\u043d\u0438\u044f \u0432\u0432\u0435\u0434\u0438\u0442\u0435 <b>\u0423\u0414\u0410\u041b\u0418\u0422\u042c</b>.</div>' +
            '      <div class="driver-delete-confirm-name" data-vehicle-set-delete-name></div>' +
            '      <div class="field driver-delete-confirm-field">' +
            '        <input type="text" class="field-input" data-vehicle-set-delete-input autocomplete="off" placeholder="\u0412\u0432\u0435\u0434\u0438\u0442\u0435 \u0423\u0414\u0410\u041b\u0418\u0422\u042c">' +
            '      </div>' +
            '      <div class="driver-delete-confirm-error is-hidden" data-vehicle-set-delete-error></div>' +
            '    </div>' +
            '  </div>' +
            '  <div class="modal-foot">' +
            '    <button type="button" class="btn btn-ghost" data-vehicle-set-delete-cancel>\u041e\u0442\u043c\u0435\u043d\u0430</button>' +
            '    <button type="button" class="btn btn-danger" data-vehicle-set-delete-confirm disabled>\u0423\u0434\u0430\u043b\u0438\u0442\u044c</button>' +
            '  </div>' +
            '</div>';

        document.body.appendChild(confirmShell);
        return confirmShell;
    }

    function bindDeleteConfirm() {
        var confirmShell = getDeleteConfirmShell();
        var closeBtn = confirmShell.querySelector('[data-vehicle-set-delete-close]');
        var cancelBtn = confirmShell.querySelector('[data-vehicle-set-delete-cancel]');
        var confirmBtn = confirmShell.querySelector('[data-vehicle-set-delete-confirm]');
        var input = confirmShell.querySelector('[data-vehicle-set-delete-input]');
        var errorBox = confirmShell.querySelector('[data-vehicle-set-delete-error]');

        function closeConfirm() {
            window.closeModal(CONFIRM_MODAL_ID);
            if (input) input.value = '';
            if (errorBox) {
                errorBox.textContent = '';
                errorBox.classList.add('is-hidden');
            }
            if (confirmBtn) confirmBtn.disabled = true;
        }

        function syncConfirmState() {
            if (!input || !confirmBtn) return;
            confirmBtn.disabled = input.value.trim().toUpperCase() !== '\u0423\u0414\u0410\u041b\u0418\u0422\u042c';
        }

        if (closeBtn && closeBtn.dataset.ready !== '1') {
            closeBtn.dataset.ready = '1';
            closeBtn.addEventListener('click', closeConfirm);
        }
        if (cancelBtn && cancelBtn.dataset.ready !== '1') {
            cancelBtn.dataset.ready = '1';
            cancelBtn.addEventListener('click', closeConfirm);
        }
        if (input && input.dataset.ready !== '1') {
            input.dataset.ready = '1';
            input.addEventListener('input', syncConfirmState);
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && !confirmBtn.disabled) {
                    event.preventDefault();
                    confirmBtn.click();
                }
            });
        }
        if (confirmBtn && confirmBtn.dataset.ready !== '1') {
            confirmBtn.dataset.ready = '1';
            confirmBtn.addEventListener('click', function () {
                var currentShell = getShell();
                if (!currentShell._vehicleSetId) return;

                confirmBtn.disabled = true;
                if (errorBox) {
                    errorBox.textContent = '';
                    errorBox.classList.add('is-hidden');
                }

                fetch('/company/vehicle-sets/' + currentShell._vehicleSetId + '/modal-delete', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (response) {
                        if (!response.ok) throw new Error(String(response.status));
                        return response.json();
                    })
                    .then(function (result) {
                        if (!result || !result.success) {
                            throw new Error(result && result.error ? result.error : '\u041d\u0435 \u0443\u0434\u0430\u043b\u043e\u0441\u044c \u0443\u0434\u0430\u043b\u0438\u0442\u044c \u0442\u0440\u0430\u043d\u0441\u043f\u043e\u0440\u0442.');
                        }

                        closeConfirm();
                        currentShell._refreshListOnClose = false;
                        saveVehicleGridState();
                        window.closeModal(MODAL_ID);
                        window.location.reload();
                    })
                    .catch(function (error) {
                        if (errorBox) {
                            errorBox.textContent = error && error.message ? error.message : '\u041d\u0435 \u0443\u0434\u0430\u043b\u043e\u0441\u044c \u0443\u0434\u0430\u043b\u0438\u0442\u044c \u0442\u0440\u0430\u043d\u0441\u043f\u043e\u0440\u0442.';
                            errorBox.classList.remove('is-hidden');
                        }
                        syncConfirmState();
                    });
            });
        }

        return {
            open: function (vehicleName) {
                var nameBox = confirmShell.querySelector('[data-vehicle-set-delete-name]');
                if (nameBox) {
                    nameBox.textContent = vehicleName || '';
                }
                if (input) {
                    input.value = '';
                }
                if (errorBox) {
                    errorBox.textContent = '';
                    errorBox.classList.add('is-hidden');
                }
                if (confirmBtn) {
                    confirmBtn.disabled = true;
                }
                window.openModal(CONFIRM_MODAL_ID);
                if (input) input.focus();
            }
        };
    }

    function loadView(vehicleSetId) {
        showLoading('\u0417\u0430\u0433\u0440\u0443\u0437\u043a\u0430...');
        var currentShell = getShell();
        currentShell._vehicleSetId = vehicleSetId;
        currentShell.classList.add('is-open');

        fetch('/company/vehicle-sets/' + vehicleSetId + '/modal-view', { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) throw new Error(String(response.status));
                return response.text();
            })
            .then(function (html) {
                setContent(html);
                bindViewButtons();
            })
            .catch(function () {
                showModalError('\u041d\u0435 \u0443\u0434\u0430\u043b\u043e\u0441\u044c \u0437\u0430\u0433\u0440\u0443\u0437\u0438\u0442\u044c \u0434\u0430\u043d\u043d\u044b\u0435 \u0442\u0440\u0430\u043d\u0441\u043f\u043e\u0440\u0442\u0430.');
            });
    }

    function loadEdit(vehicleSetId) {
        showLoading('\u0417\u0430\u0433\u0440\u0443\u0437\u043a\u0430...');

        fetch('/company/vehicle-sets/' + vehicleSetId + '/modal-edit', { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) throw new Error(String(response.status));
                return response.text();
            })
            .then(function (html) {
                setContent(html);
                if (typeof window.erpExecuteInlineScripts === 'function') {
                    window.erpExecuteInlineScripts(getShell());
                }
                bindEditButtons();
            })
            .catch(function () {
                showModalError('\u041d\u0435 \u0443\u0434\u0430\u043b\u043e\u0441\u044c \u0437\u0430\u0433\u0440\u0443\u0437\u0438\u0442\u044c \u0444\u043e\u0440\u043c\u0443.');
            });
    }

    function bindViewButtons() {
        var currentShell = getShell();
        var editBtn = currentShell.querySelector('[data-vehicle-set-edit-btn]');
        var closeBtn = currentShell.querySelector('[data-vehicle-set-close-btn]');
        var deleteBtn = currentShell.querySelector('[data-vehicle-set-delete-btn]');

        if (editBtn) {
            var newEditBtn = editBtn.cloneNode(true);
            editBtn.parentNode.replaceChild(newEditBtn, editBtn);
            newEditBtn.addEventListener('click', function () {
                loadEdit(currentShell._vehicleSetId);
            });
        }

        if (closeBtn) {
            var newCloseBtn = closeBtn.cloneNode(true);
            closeBtn.parentNode.replaceChild(newCloseBtn, closeBtn);
            newCloseBtn.addEventListener('click', function () {
                closeVehicleModal();
            });
        }

        if (deleteBtn) {
            var newDeleteBtn = deleteBtn.cloneNode(true);
            deleteBtn.parentNode.replaceChild(newDeleteBtn, deleteBtn);
            newDeleteBtn.addEventListener('click', function () {
                var nameEl = currentShell.querySelector('.driver-view-name');
                bindDeleteConfirm().open(nameEl ? nameEl.textContent.trim() : '');
            });
        }
    }

    function bindEditButtons() {
        var currentShell = getShell();
        var cancelBtn = currentShell.querySelector('[data-vehicle-set-cancel-edit-btn]');
        var form = currentShell.querySelector('#vehicle-set-edit-form');

        if (cancelBtn) {
            var newCancelBtn = cancelBtn.cloneNode(true);
            cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
            newCancelBtn.addEventListener('click', function () {
                loadView(currentShell._vehicleSetId);
            });
        }

        if (form && form.dataset.modalSubmitReady !== '1') {
            form.dataset.modalSubmitReady = '1';
            form.addEventListener('submit', function (event) {
                event.preventDefault();

                var formData = new FormData(form);
                showLoading('\u0421\u043e\u0445\u0440\u0430\u043d\u0435\u043d\u0438\u0435...');

                fetch('/company/vehicle-sets/' + currentShell._vehicleSetId + '/modal-edit', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                })
                    .then(function (response) {
                        if (!response.ok) throw new Error(String(response.status));
                        return response.text();
                    })
                    .then(function (html) {
                        var tmp = document.createElement('div');
                        tmp.innerHTML = html;
                        if (tmp.querySelector('#vehicle-set-edit-form')) {
                            setContent(html);
                            if (typeof window.erpExecuteInlineScripts === 'function') {
                                window.erpExecuteInlineScripts(getShell());
                            }
                            bindEditButtons();
                        } else {
                            currentShell._refreshListOnClose = true;
                            setContent(html);
                            bindViewButtons();
                        }
                    })
                    .catch(function () {
                        showModalError('\u041d\u0435 \u0443\u0434\u0430\u043b\u043e\u0441\u044c \u0441\u043e\u0445\u0440\u0430\u043d\u0438\u0442\u044c.');
                    });
            });
        }
    }

    function resolveVehicleSetId(target) {
        var direct = target.closest('[data-vehicle-set-id]');
        if (direct && direct.getAttribute('data-vehicle-set-id')) {
            return direct.getAttribute('data-vehicle-set-id');
        }
        var row = target.closest('tr[data-vehicle-set-id]');
        return row ? row.getAttribute('data-vehicle-set-id') : '';
    }

    tableBody.addEventListener('click', function (event) {
        var viewBtn = event.target.closest('[data-vehicle-view-btn]');
        if (viewBtn) {
            var viewId = resolveVehicleSetId(viewBtn);
            if (viewId) loadView(viewId);
            return;
        }

        var editBtn = event.target.closest('[data-vehicle-edit-btn]');
        if (editBtn) {
            var editId = resolveVehicleSetId(editBtn);
            if (editId) loadEdit(editId);
        }
    });

    tableBody.addEventListener('dblclick', function (event) {
        if (event.target.closest('a, button, input, select, textarea, label')) return;
        var row = event.target.closest('tr[data-vehicle-set-id]');
        if (!row) return;
        var vehicleSetId = row.getAttribute('data-vehicle-set-id');
        if (!vehicleSetId) return;
        loadView(vehicleSetId);
    });
})();

// ============================================================
// Document preview popup windows
// ============================================================
document.addEventListener('click', function (event) {
    var link = event.target.closest('.js-doc-popup-window');
    if (!link) return;

    var href = link.getAttribute('href');
    if (!href) return;

    event.preventDefault();

    var screenWidth = window.screen && window.screen.availWidth ? window.screen.availWidth : window.innerWidth;
    var screenHeight = window.screen && window.screen.availHeight ? window.screen.availHeight : window.innerHeight;
    var popupWidth = Math.max(640, Math.round(screenWidth * 0.5));
    var popupHeight = Math.max(720, Math.round(screenHeight * 0.8));
    var popupLeft = Math.max(0, Math.round((screenWidth - popupWidth) / 2));
    var popupTop = Math.max(0, Math.round((screenHeight - popupHeight) / 2));
    var features = [
        'popup=yes',
        'toolbar=no',
        'location=yes',
        'status=no',
        'menubar=no',
        'scrollbars=yes',
        'resizable=yes',
        'width=' + popupWidth,
        'height=' + popupHeight,
        'left=' + popupLeft,
        'top=' + popupTop
    ].join(',');

    var popupWindow = window.open(href, '_blank', features);
    if (!popupWindow) {
        window.location.href = href;
    }
});
