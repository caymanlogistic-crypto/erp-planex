(function () {
    'use strict';

    var labels = {
        start_day: 'На загрузке',
        end_day: 'На выгрузке'
    };

    function normalizeLabels(root) {
        var scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('select[data-condition-type]').forEach(function (select) {
            Array.prototype.forEach.call(select.options, function (option) {
                if (Object.prototype.hasOwnProperty.call(labels, option.value)) {
                    option.textContent = labels[option.value];
                }
            });
        });
    }

    function init() {
        normalizeLabels(document);

        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) return;
                    if (node.matches && node.matches('select[data-condition-type]')) {
                        normalizeLabels(node.parentNode || document);
                        return;
                    }
                    normalizeLabels(node);
                });
            });
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
