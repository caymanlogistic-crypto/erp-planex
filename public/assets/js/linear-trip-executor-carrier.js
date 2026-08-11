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
        if (field) {
            field.classList.add('is-hidden');
            field.hidden = true;
        }
        carrier.setAttribute('aria-hidden', 'true');
        carrier.tabIndex = -1;

        var selectedExecutor = executor.options[executor.selectedIndex] || null;
        if (!selectedExecutor || !executor.value) {
            carrier.value = '';
            return;
        }

        var executorLabel = normalizeLabel(selectedExecutor.textContent);
        var matchedValue = '';
        Array.prototype.forEach.call(carrier.options, function (option) {
            if (!option.value || matchedValue) return;
            var carrierLabel = normalizeLabel(option.textContent).split(' · ИНН ')[0];
            if (carrierLabel && (executorLabel === carrierLabel || executorLabel.indexOf(carrierLabel + ' · ') === 0)) {
                matchedValue = option.value;
            }
        });

        if (matchedValue) carrier.value = matchedValue;
    }

    function formatAmount(input) {
        var digits = String(input.value || '').replace(/\D/g, '');
        input.value = digits.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
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
        var routeTypeField = routeType.closest('.field');
        if (routeTypeField) {
            routeTypeField.classList.add('is-hidden');
            routeTypeField.hidden = true;
        }
        if (!routeType.value) routeType.value = 'linear';

        var dateField = date.closest('.field');
        var clientField = client.closest('.field');
        var executorField = executor.closest('.field');
        var originalRow = routeType.closest('.linear-trip-row--compact');
        if (dateField && clientField && executorField && originalRow) {
            var primaryRow = document.createElement('div');
            primaryRow.className = 'field-row field-row-group linear-trip-primary-row';
            originalRow.parentNode.insertBefore(primaryRow, originalRow);
            primaryRow.appendChild(dateField);
            primaryRow.appendChild(clientField);
            primaryRow.appendChild(executorField);

            var toggle = document.createElement('label');
            toggle.className = 'linear-trip-agency-toggle';
            toggle.innerHTML = '<input type="checkbox" data-linear-trip-agency-toggle> <span>Агентский договор</span>';
            primaryRow.parentNode.insertBefore(toggle, primaryRow.nextSibling);
        }

        var checkbox = form.querySelector('[data-linear-trip-agency-toggle]');
        if (checkbox) {
            checkbox.checked = routeType.value === 'agency';
            var applyAgency = function () {
                var agency = checkbox.checked;
                routeType.value = agency ? 'agency' : 'linear';
                routeType.dispatchEvent(new Event('change', {bubbles:true}));
                form.querySelectorAll('.is-agency-only').forEach(function (el) {
                    el.classList.toggle('is-hidden', !agency);
                });
            };
            checkbox.addEventListener('change', applyAgency);
            applyAgency();
        }

        form.querySelectorAll('input[name$="[amount]"]').forEach(function (input) {
            if (input.dataset.p30Money === '1') return;
            input.dataset.p30Money = '1';
            formatAmount(input);
            input.addEventListener('input', function () { formatAmount(input); });
        });
    }

    function initializeForm(form) {
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-linear-trip-form]')) return;
        deriveCarrierValue(form);
        applyCreateLayout(form);
    }

    function scan(root) {
        if (!root || !root.querySelectorAll) return;
        if (root.matches && root.matches('form[data-linear-trip-form]')) initializeForm(root);
        root.querySelectorAll('form[data-linear-trip-form]').forEach(initializeForm);
        root.querySelectorAll('input[name$="[amount]"]').forEach(function (input) {
            if (!input.closest('#linear-trip-create-modal') || input.dataset.p30Money === '1') return;
            input.dataset.p30Money = '1';
            formatAmount(input);
            input.addEventListener('input', function () { formatAmount(input); });
        });
    }

    document.addEventListener('change', function (event) {
        var target = event.target;
        if (!(target instanceof HTMLSelectElement) || target.name !== 'route_executor_id') return;
        var form = target.closest('form[data-linear-trip-form]');
        if (form) deriveCarrierValue(form);
    }, true);

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (form instanceof HTMLFormElement && form.matches('[data-linear-trip-form]')) {
            deriveCarrierValue(form);
            var routeType = form.querySelector('[data-linear-trip-route-type]');
            var checkbox = form.querySelector('[data-linear-trip-agency-toggle]');
            if (routeType && checkbox) routeType.value = checkbox.checked ? 'agency' : 'linear';
            form.querySelectorAll('input[name$="[amount]"]').forEach(function (input) {
                input.value = String(input.value || '').replace(/\s/g, '');
            });
        }
    }, true);

    var observer = new MutationObserver(function (records) {
        records.forEach(function (record) {
            record.addedNodes.forEach(function (node) {
                if (node.nodeType === Node.ELEMENT_NODE) scan(node);
            });
        });
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            scan(document);
            observer.observe(document.body, {childList:true, subtree:true});
        }, {once:true});
    } else {
        scan(document);
        observer.observe(document.body, {childList:true, subtree:true});
    }
})();
