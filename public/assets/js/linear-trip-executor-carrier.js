(function () {
    'use strict';

    function normalizeLabel(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    }

    function isEditForm(form) {
        return !!(form && (form.id === 'linear-trip-edit-form' || /\/modal-edit(?:$|\?)/.test(String(form.action || ''))));
    }

    function deriveCarrierValue(form) {
        var executor = form.querySelector('select[name="route_executor_id"]');
        var carrier = form.querySelector('select[name="carrier_contractor_id"]');
        if (!executor || !carrier) return;
        var field = carrier.closest('.field');
        if (field) { field.classList.add('is-hidden'); field.hidden = true; }
        carrier.setAttribute('aria-hidden', 'true');
        carrier.tabIndex = -1;
        var selectedExecutor = executor.options[executor.selectedIndex] || null;
        if (!selectedExecutor || !executor.value) { carrier.value = ''; return; }
        var executorLabel = normalizeLabel(selectedExecutor.textContent);
        var matchedValue = '';
        Array.prototype.forEach.call(carrier.options, function (option) {
            if (!option.value || matchedValue) return;
            var carrierLabel = normalizeLabel(option.textContent).split(' · ИНН ')[0];
            if (carrierLabel && (executorLabel === carrierLabel || executorLabel.indexOf(carrierLabel + ' · ') === 0)) matchedValue = option.value;
        });
        if (matchedValue) carrier.value = matchedValue;
    }

    function ensureHiddenDefaults(form, allowDefaults) {
        var cargo = form.querySelector('input[name="cargo_type_name"]');
        if (cargo) {
            var cargoField = cargo.closest('.field');
            if (cargoField) { cargoField.classList.add('is-hidden'); cargoField.hidden = true; }
            if (allowDefaults && !String(cargo.value || '').trim()) cargo.value = 'Не указан';
        }
        form.querySelectorAll('input[name$="[condition_comment]"]').forEach(function (input) {
            var field = input.closest('.field');
            if (field) { field.classList.add('is-hidden'); field.hidden = true; }
        });
    }

    function hideEditOnlyFields(form) {
        if (!isEditForm(form)) return;
        ['planned_unloading_date', 'carrier_contractor_id', 'actual_loading_date', 'actual_unloading_date'].forEach(function (name) {
            var control = form.querySelector('[name="' + name + '"]');
            var field = control ? control.closest('.field') : null;
            if (field) { field.classList.add('is-hidden'); field.hidden = true; }
        });
    }

    function collapseEmptyRows(form) {
        form.querySelectorAll('.linear-trip-row--compact').forEach(function (row) {
            var visible = Array.prototype.some.call(row.children, function (child) {
                if (!(child instanceof HTMLElement) || !child.classList.contains('field')) return false;
                return !child.hidden && !child.classList.contains('is-hidden') && window.getComputedStyle(child).display !== 'none';
            });
            row.classList.toggle('is-layout-empty', !visible);
        });
    }

    function formatAmount(input) {
        var raw = String(input.value || '').replace(/[\s\u00a0]/g, '').replace(',', '.');
        raw = raw.replace(/[^0-9.]/g, '');
        var firstDot = raw.indexOf('.');
        var integerPart = firstDot >= 0 ? raw.slice(0, firstDot) : raw;
        var fractionPart = firstDot >= 0 ? raw.slice(firstDot + 1).replace(/\./g, '').slice(0, 2) : '';
        integerPart = integerPart.replace(/\D/g, '').replace(/^0+(?=\d)/, '');
        if (integerPart === '' && raw !== '') integerPart = '0';
        var grouped = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        if (fractionPart !== '' && !/^0+$/.test(fractionPart)) grouped += ',' + fractionPart;
        input.value = grouped;
    }

    function decorateAmount(input) {
        if (!input || input.dataset.p32Money === '1') return;
        input.dataset.p32Money = '1';
        input.removeAttribute('placeholder');
        formatAmount(input);
        input.addEventListener('input', function () { formatAmount(input); });
        if (!input.parentElement || !input.parentElement.classList.contains('linear-trip-money-shell')) {
            var shell = document.createElement('div');
            shell.className = 'linear-trip-money-shell';
            input.parentNode.insertBefore(shell, input);
            shell.appendChild(input);
            var suffix = document.createElement('span');
            suffix.className = 'linear-trip-money-suffix';
            suffix.textContent = '₽';
            suffix.setAttribute('aria-hidden', 'true');
            shell.appendChild(suffix);
        }
    }

    function normalizeDaysKind(select, allowDefaults) {
        if (!select) return;
        Array.prototype.forEach.call(select.options, function (option) {
            if (option.value === 'working') option.textContent = 'БД';
            if (option.value === 'calendar') option.textContent = 'РД';
        });
        if (allowDefaults) {
            var empty = select.querySelector('option[value=""]');
            if (empty) empty.remove();
            if (!select.value) select.value = 'working';
        }
    }

    function decorateDaysInput(input, allowDefaults) {
        if (!input || input.dataset.p32Days === '1') return;
        input.dataset.p32Days = '1';
        if (allowDefaults && !input.value) input.value = '3';
        var shell = document.createElement('div');
        shell.className = 'linear-trip-days-stepper';
        input.parentNode.insertBefore(shell, input);
        var minus = document.createElement('button');
        minus.type = 'button'; minus.textContent = '−'; minus.setAttribute('aria-label', 'Уменьшить количество дней'); shell.appendChild(minus);
        shell.appendChild(input);
        var plus = document.createElement('button');
        plus.type = 'button'; plus.textContent = '+'; plus.setAttribute('aria-label', 'Увеличить количество дней'); shell.appendChild(plus);
        function setValue(delta) {
            var current = parseInt(input.value, 10);
            if (!Number.isFinite(current) || current < 1) current = 3;
            current = Math.max(1, current + delta);
            input.value = String(current);
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
        minus.addEventListener('click', function () { setValue(-1); });
        plus.addEventListener('click', function () { setValue(1); });
        if (allowDefaults) {
            input.addEventListener('blur', function () { var current = parseInt(input.value, 10); if (!Number.isFinite(current) || current < 1) input.value = '3'; });
        }
    }

    function decorateSearchableSelect(select, placeholder) {
        if (!select || select.dataset.p34Searchable === '1') return;
        select.dataset.p34Searchable = '1';
        var shell = document.createElement('div');
        shell.className = 'linear-trip-searchable';
        select.parentNode.insertBefore(shell, select);
        var input = document.createElement('input');
        input.type = 'text'; input.className = 'field-input linear-trip-searchable-input'; input.placeholder = placeholder; input.autocomplete = 'off'; input.setAttribute('aria-autocomplete', 'list');
        var dropdown = document.createElement('div');
        dropdown.className = 'linear-trip-searchable-menu is-hidden';
        shell.appendChild(input); shell.appendChild(dropdown); shell.appendChild(select);
        select.classList.add('linear-trip-searchable-native'); select.tabIndex = -1; select.setAttribute('aria-hidden', 'true');
        var selectedOption = select.options[select.selectedIndex] || null;
        if (selectedOption && selectedOption.value) input.value = normalizeLabel(selectedOption.textContent);
        function render(query) {
            var needle = normalizeLabel(query).toLowerCase();
            dropdown.innerHTML = '';
            var found = 0;
            Array.prototype.forEach.call(select.options, function (option) {
                if (!option.value) return;
                var label = normalizeLabel(option.textContent);
                if (needle && label.toLowerCase().indexOf(needle) === -1) return;
                var item = document.createElement('button');
                item.type = 'button'; item.className = 'linear-trip-searchable-option'; item.textContent = label;
                item.addEventListener('mousedown', function (event) { event.preventDefault(); });
                item.addEventListener('click', function () {
                    select.value = option.value; input.value = label; dropdown.classList.add('is-hidden'); select.dispatchEvent(new Event('change', { bubbles: true }));
                });
                dropdown.appendChild(item); found += 1;
            });
            if (found === 0) { var empty = document.createElement('div'); empty.className = 'linear-trip-searchable-empty'; empty.textContent = 'Ничего не найдено'; dropdown.appendChild(empty); }
            dropdown.classList.remove('is-hidden');
        }
        input.addEventListener('focus', function () { render(input.value); });
        input.addEventListener('input', function () { select.value = ''; render(input.value); });
        input.addEventListener('keydown', function (event) { if (event.key === 'Escape') dropdown.classList.add('is-hidden'); });
        input.addEventListener('blur', function () { window.setTimeout(function () { dropdown.classList.add('is-hidden'); var selected = select.options[select.selectedIndex] || null; if (selected && selected.value) input.value = normalizeLabel(selected.textContent); }, 120); });
    }

    function decoratePaymentRow(row, allowDefaults) {
        if (!row || !(row instanceof Element)) return;
        var amount = row.querySelector('input[name$="[amount]"]'); if (amount) decorateAmount(amount);
        var daysInput = row.querySelector('input[name$="[days_count]"]'); if (daysInput) decorateDaysInput(daysInput, allowDefaults);
        var daysKind = row.querySelector('select[name$="[days_kind]"]'); if (daysKind) normalizeDaysKind(daysKind, allowDefaults);
    }

    function decorateTripView(root) {
        var scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('.driver-modal-body .driver-view-card').forEach(function (card) {
            if (card.dataset.p37ViewReady === '1') return;
            card.dataset.p37ViewReady = '1';
            var layout = card.closest('.driver-modal-layout');
            var body = card.closest('.driver-modal-body');
            if (layout) layout.classList.add('is-linear-trip-view-layout');
            if (body) body.classList.add('is-linear-trip-view');
            card.querySelectorAll('.driver-view-row').forEach(function (row) {
                var label = row.querySelector('.driver-view-cell-label');
                var text = normalizeLabel(label ? label.textContent : '');
                if (text === 'Оплаты заказчика' || text === 'Оплаты перевозчика') row.style.display = 'none';
                if (text === 'Финансы рейса') row.classList.add('is-trip-view-finance', 'driver-view-row-wide');
                if (text === 'Комментарий') row.classList.add('is-trip-view-comment', 'driver-view-row-wide');
                if (text === 'Принципалы' || text === 'Оплаты принципала') row.classList.add('driver-view-row-wide');
            });
        });
    }

    function applyUnifiedLayout(form) {
        if (form.dataset.p35LayoutReady === '1') return;
        var editMode = isEditForm(form);
        var allowDefaults = !editMode;
        var routeType = form.querySelector('[data-linear-trip-route-type]');
        var date = form.querySelector('[name="planned_loading_date"]');
        var client = form.querySelector('[name="client_id"]');
        var executor = form.querySelector('[name="route_executor_id"]');
        if (!routeType || !date || !client || !executor) return;
        form.dataset.p35LayoutReady = '1';
        ensureHiddenDefaults(form, allowDefaults);
        hideEditOnlyFields(form);
        var routeTypeField = routeType.closest('.field');
        if (routeTypeField) { routeTypeField.classList.add('is-hidden'); routeTypeField.hidden = true; }
        if (allowDefaults && !routeType.value) routeType.value = 'linear';
        var dateField = date.closest('.field');
        var clientField = client.closest('.field');
        var executorField = executor.closest('.field');
        var originalRow = routeType.closest('.linear-trip-row--compact');
        if (dateField && clientField && executorField && originalRow) {
            var primaryRow = document.createElement('div');
            primaryRow.className = 'field-row field-row-group linear-trip-primary-row';
            originalRow.parentNode.insertBefore(primaryRow, originalRow);
            primaryRow.appendChild(dateField); primaryRow.appendChild(clientField); primaryRow.appendChild(executorField);
            var toggle = document.createElement('label');
            toggle.className = 'linear-trip-agency-toggle';
            toggle.innerHTML = '<input type="checkbox" data-linear-trip-agency-toggle> <span>Агентский договор</span>';
            primaryRow.parentNode.insertBefore(toggle, primaryRow.nextSibling);
        }
        decorateSearchableSelect(client, 'Начните вводить заказчика');
        decorateSearchableSelect(executor, 'Начните вводить исполнителя рейса');
        var checkbox = form.querySelector('[data-linear-trip-agency-toggle]');
        if (checkbox) {
            checkbox.checked = routeType.value === 'agency';
            var applyAgency = function () {
                var agency = checkbox.checked;
                routeType.value = agency ? 'agency' : 'linear';
                routeType.dispatchEvent(new Event('change', {bubbles:true}));
                form.querySelectorAll('.is-agency-only').forEach(function (el) { el.classList.toggle('is-hidden', !agency); });
                hideEditOnlyFields(form);
                collapseEmptyRows(form);
            };
            checkbox.addEventListener('change', applyAgency);
            form.querySelectorAll('.is-agency-only').forEach(function (el) { el.classList.toggle('is-hidden', !checkbox.checked); });
        }
        hideEditOnlyFields(form);
        collapseEmptyRows(form);
        form.querySelectorAll('[data-payment-row]').forEach(function (row) { decoratePaymentRow(row, allowDefaults); });
    }

    function initializeForm(form) {
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-linear-trip-form]')) return;
        var editMode = isEditForm(form);
        if (!editMode) deriveCarrierValue(form);
        ensureHiddenDefaults(form, !editMode);
        hideEditOnlyFields(form);
        applyUnifiedLayout(form);
        form.querySelectorAll('[data-payment-row]').forEach(function (row) { decoratePaymentRow(row, !editMode); });
        hideEditOnlyFields(form);
        collapseEmptyRows(form);
    }

    function scan(root) {
        if (!root || !root.querySelectorAll) return;
        if (root.matches && root.matches('form[data-linear-trip-form]')) initializeForm(root);
        root.querySelectorAll('form[data-linear-trip-form]').forEach(initializeForm);
        if (root.matches && root.matches('[data-payment-row]')) {
            var form = root.closest('form[data-linear-trip-form]');
            decoratePaymentRow(root, !isEditForm(form));
        }
        root.querySelectorAll('[data-payment-row]').forEach(function (row) {
            var form = row.closest('form[data-linear-trip-form]');
            decoratePaymentRow(row, !isEditForm(form));
        });
        decorateTripView(root);
    }

    document.addEventListener('change', function (event) {
        var target = event.target;
        if (target instanceof HTMLSelectElement && target.name === 'route_executor_id') {
            var form = target.closest('form[data-linear-trip-form]'); if (form) { deriveCarrierValue(form); hideEditOnlyFields(form); collapseEmptyRows(form); } return;
        }
        if (target instanceof HTMLSelectElement && target.matches('[data-condition-type]')) {
            var row = target.closest('[data-payment-row]'); if (!row) return;
            window.setTimeout(function () {
                var form = row.closest('form[data-linear-trip-form]');
                decoratePaymentRow(row, !isEditForm(form));
                var daysField = row.querySelector('[data-days-count-wrapper]');
                if (daysField && !daysField.classList.contains('is-hidden')) {
                    var daysInput = daysField.querySelector('input[name$="[days_count]"]'); if (daysInput && !daysInput.value) daysInput.value = '3';
                    var kind = row.querySelector('select[name$="[days_kind]"]'); if (kind && !kind.value) kind.value = 'working';
                }
            }, 0);
        }
    }, true);

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (form instanceof HTMLFormElement && form.matches('[data-linear-trip-form]')) {
            var editMode = isEditForm(form);
            if (!editMode) deriveCarrierValue(form);
            ensureHiddenDefaults(form, !editMode);
            var routeType = form.querySelector('[data-linear-trip-route-type]');
            var checkbox = form.querySelector('[data-linear-trip-agency-toggle]');
            if (routeType && checkbox) routeType.value = checkbox.checked ? 'agency' : 'linear';
            form.querySelectorAll('input[name$="[amount]"]').forEach(function (input) { input.value = String(input.value || '').replace(/\s/g, ''); });
        }
    }, true);

    var observer = new MutationObserver(function (records) { records.forEach(function (record) { record.addedNodes.forEach(function (node) { if (node.nodeType === Node.ELEMENT_NODE) scan(node); }); }); });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { scan(document); observer.observe(document.body, {childList:true, subtree:true}); }, {once:true});
    } else { scan(document); observer.observe(document.body, {childList:true, subtree:true}); }
})();
