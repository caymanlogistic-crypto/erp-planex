(function () {
    'use strict';

    function normalizeLabel(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
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

    function ensureHiddenDefaults(form) {
        var cargo = form.querySelector('input[name="cargo_type_name"]');
        if (cargo && !String(cargo.value || '').trim()) cargo.value = 'Не указан';
    }

    function formatAmount(input) {
        var digits = String(input.value || '').replace(/\D/g, '');
        input.value = digits.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
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

    function normalizeDaysKind(select) {
        if (!select) return;
        Array.prototype.forEach.call(select.options, function (option) {
            if (option.value === 'working') option.textContent = 'БД';
            if (option.value === 'calendar') option.textContent = 'РД';
        });
        var empty = select.querySelector('option[value=""]');
        if (empty) empty.remove();
        if (!select.value) select.value = 'working';
    }

    function decorateDaysInput(input) {
        if (!input || input.dataset.p32Days === '1') return;
        input.dataset.p32Days = '1';
        if (!input.value) input.value = '3';
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
        input.addEventListener('blur', function () { var current = parseInt(input.value, 10); if (!Number.isFinite(current) || current < 1) input.value = '3'; });
    }

    function decorateSearchableSelect(select, placeholder) {
        if (!select || select.dataset.p34Searchable === '1') return;
        select.dataset.p34Searchable = '1';

        var shell = document.createElement('div');
        shell.className = 'linear-trip-searchable';
        select.parentNode.insertBefore(shell, select);

        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'field-input linear-trip-searchable-input';
        input.placeholder = placeholder;
        input.autocomplete = 'off';
        input.setAttribute('aria-autocomplete', 'list');

        var dropdown = document.createElement('div');
        dropdown.className = 'linear-trip-searchable-menu is-hidden';

        shell.appendChild(input);
        shell.appendChild(dropdown);
        shell.appendChild(select);
        select.classList.add('linear-trip-searchable-native');
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');

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
                item.type = 'button';
                item.className = 'linear-trip-searchable-option';
                item.textContent = label;
                item.dataset.value = option.value;
                item.addEventListener('mousedown', function (event) { event.preventDefault(); });
                item.addEventListener('click', function () {
                    select.value = option.value;
                    input.value = label;
                    dropdown.classList.add('is-hidden');
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                });
                dropdown.appendChild(item);
                found += 1;
            });
            if (found === 0) {
                var empty = document.createElement('div');
                empty.className = 'linear-trip-searchable-empty';
                empty.textContent = 'Ничего не найдено';
                dropdown.appendChild(empty);
            }
            dropdown.classList.remove('is-hidden');
        }

        input.addEventListener('focus', function () { render(input.value); });
        input.addEventListener('input', function () {
            select.value = '';
            render(input.value);
        });
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') dropdown.classList.add('is-hidden');
        });
        input.addEventListener('blur', function () {
            window.setTimeout(function () {
                dropdown.classList.add('is-hidden');
                var selected = select.options[select.selectedIndex] || null;
                if (selected && selected.value) input.value = normalizeLabel(selected.textContent);
            }, 120);
        });
    }

    function decoratePaymentRow(row) {
        if (!row || !(row instanceof Element)) return;
        var amount = row.querySelector('input[name$="[amount]"]'); if (amount) decorateAmount(amount);
        var daysInput = row.querySelector('input[name$="[days_count]"]'); if (daysInput) decorateDaysInput(daysInput);
        var daysKind = row.querySelector('select[name$="[days_kind]"]'); if (daysKind) normalizeDaysKind(daysKind);
    }

    function applyCreateLayout(form) {
        var modal = form.closest('#linear-trip-create-modal');
        if (!modal || form.dataset.p30LayoutReady === '1') return;
        var routeType = form.querySelector('[data-linear-trip-route-type]');
        var date = form.querySelector('[name="planned_loading_date"]');
        var client = form.querySelector('[name="client_id"]');
        var executor = form.querySelector('[name="route_executor_id"]');
        if (!routeType || !date || !client || !executor) return;
        form.dataset.p30LayoutReady = '1';
        ensureHiddenDefaults(form);
        var routeTypeField = routeType.closest('.field');
        if (routeTypeField) { routeTypeField.classList.add('is-hidden'); routeTypeField.hidden = true; }
        if (!routeType.value) routeType.value = 'linear';
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
            };
            checkbox.addEventListener('change', applyAgency); applyAgency();
        }
        form.querySelectorAll('[data-payment-row]').forEach(decoratePaymentRow);
    }

    function initializeForm(form) {
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-linear-trip-form]')) return;
        deriveCarrierValue(form); ensureHiddenDefaults(form); applyCreateLayout(form); form.querySelectorAll('[data-payment-row]').forEach(decoratePaymentRow);
    }

    function scan(root) {
        if (!root || !root.querySelectorAll) return;
        if (root.matches && root.matches('form[data-linear-trip-form]')) initializeForm(root);
        root.querySelectorAll('form[data-linear-trip-form]').forEach(initializeForm);
        if (root.matches && root.matches('[data-payment-row]')) decoratePaymentRow(root);
        root.querySelectorAll('[data-payment-row]').forEach(decoratePaymentRow);
    }

    document.addEventListener('change', function (event) {
        var target = event.target;
        if (target instanceof HTMLSelectElement && target.name === 'route_executor_id') {
            var form = target.closest('form[data-linear-trip-form]'); if (form) deriveCarrierValue(form); return;
        }
        if (target instanceof HTMLSelectElement && target.matches('[data-condition-type]')) {
            var row = target.closest('[data-payment-row]'); if (!row) return;
            window.setTimeout(function () {
                decoratePaymentRow(row);
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
            deriveCarrierValue(form); ensureHiddenDefaults(form);
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
