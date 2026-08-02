(function () {
    'use strict';

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
