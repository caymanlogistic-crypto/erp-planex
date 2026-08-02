(function () {
    'use strict';

    // Native list-box selects (size > 1 / multiple) must not inherit the
    // decorative arrow used by ordinary single-line dropdowns. Chromium can
    // paint that SVG for every visible option, which creates a repeated pattern.
    var selectStyle = document.createElement('style');
    selectStyle.setAttribute('data-erp-select-listbox-fix', '1');
    selectStyle.textContent = [
        '.field-select[multiple],',
        '.field-select[size]:not([size="1"]) {',
        '  -webkit-appearance: auto;',
        '  appearance: auto;',
        '  min-height: var(--control-h);',
        '  padding: 4px 8px;',
        '  background-image: none !important;',
        '  background-color: var(--surface-field);',
        '  line-height: 1.35;',
        '}',
        '.field-select[multiple]:focus,',
        '.field-select[size]:not([size="1"]):focus {',
        '  background-image: none !important;',
        '  background-color: var(--surface-strong);',
        '}',
        '.field-select[multiple] option,',
        '.field-select[size]:not([size="1"]) option {',
        '  padding: 3px 6px;',
        '  background: var(--surface-field);',
        '  color: var(--text-main);',
        '}'
    ].join('\n');
    document.head.appendChild(selectStyle);

    var meta = document.querySelector('meta[name="csrf-token"]');
    var token = meta ? meta.getAttribute('content') : '';
    if (!token || typeof window.fetch !== 'function') {
        return;
    }

    var originalFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
        var options = init ? Object.assign({}, init) : {};
        var method = String(options.method || (input instanceof Request ? input.method : 'GET')).toUpperCase();

        if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
            var headers = new Headers(options.headers || (input instanceof Request ? input.headers : undefined));
            if (!headers.has('X-CSRF-Token')) {
                headers.set('X-CSRF-Token', token);
            }
            options.headers = headers;
        }

        return originalFetch(input, options);
    };
}());
