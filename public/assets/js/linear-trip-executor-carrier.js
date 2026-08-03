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

    function initializeForm(form) {
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-linear-trip-form]')) return;
        deriveCarrierValue(form);
    }

    function scan(root) {
        if (!root || !root.querySelectorAll) return;
        if (root.matches && root.matches('form[data-linear-trip-form]')) initializeForm(root);
        root.querySelectorAll('form[data-linear-trip-form]').forEach(initializeForm);
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
            observer.observe(document.body, {childList: true, subtree: true});
        }, {once: true});
    } else {
        scan(document);
        observer.observe(document.body, {childList: true, subtree: true});
    }
})();
