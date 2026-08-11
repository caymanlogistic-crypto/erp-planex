(function () {
    function isDriverForm(form) {
        if (!form) return false;
        if (form.id === 'driver-create-form' || form.id === 'driver-edit-form') return true;
        var action = form.getAttribute('action') || '';
        return action.indexOf('/company/drivers') !== -1;
    }

    function clearPhoneError(input) {
        if (!input) return;
        var field = input.closest('.field');
        if (!field) return;
        field.classList.remove('is-error');
        var msg = field.querySelector('.field-msg');
        if (msg) msg.textContent = '';
    }

    // app.js historically treated the main phone as mandatory on blur/submit.
    // The backend and current business rule allow an empty phone. Intercept only
    // the empty primary phone; non-empty phones still use the normal validator.
    document.addEventListener('focusout', function (event) {
        var input = event.target;
        if (!input || input.name !== 'phone') return;
        var form = input.closest('form');
        if (!isDriverForm(form)) return;
        if (String(input.value || '').trim() !== '') return;

        clearPhoneError(input);
        event.stopPropagation();
    }, true);

    document.addEventListener('input', function (event) {
        var input = event.target;
        if (!input || input.name !== 'phone') return;
        var form = input.closest('form');
        if (!isDriverForm(form)) return;
        if (String(input.value || '').trim() === '') clearPhoneError(input);
    }, true);

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!isDriverForm(form)) return;
        var input = form.querySelector('input[name="phone"]');
        if (!input || String(input.value || '').trim() !== '') return;

        clearPhoneError(input);
        input.name = 'phone_optional_empty';
        window.setTimeout(function () {
            if (input && input.name === 'phone_optional_empty') input.name = 'phone';
        }, 0);
    }, true);
})();
