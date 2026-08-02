/**
 * LegalEntityModal — creates client/contractor via popup.
 * Configured per-entity: client uses entity_type, contractor uses contractor_type.
 */
(function() {
    var instances = {};

    function createInstance(config) {
        var M = config.modalId;
        var A = config.formAction;
        var typeField = config.typeField;
        var modal = document.getElementById(M);
        if (!modal) return null;
        var loading = false;

        function showInlineError(form, msg) {
            var body = form.closest('.modal-body') || form.parentNode;
            var existing = body.querySelector('.le-modal-error');
            if (existing) existing.remove();
            var errDiv = document.createElement('div');
            errDiv.className = 'le-modal-error form-alert alert-error';
            errDiv.setAttribute('data-le-modal-error', '1');
            errDiv.innerHTML =
                '<div class="alert-mark">' +
                '<svg width="11" height="11" viewBox="0 0 18 18" fill="none"><path d="M9 2L16.5 15H1.5L9 2Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 7V11M9 13V13.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>' +
                '</div>' +
                '<div class="alert-body"><div class="alert-body-title">\u041e\u0448\u0438\u0431\u043a\u0430</div><div class="alert-body-sub">' +
                msg.replace(/</g, '&lt;').replace(/>/g, '&gt;') +
                '</div></div>';
            body.insertBefore(errDiv, form);
        }

        function setSubmitButtonEnabled(form, enabled) {
            var buttons = [];
            form.querySelectorAll('[type="submit"]').forEach(function(b) { buttons.push(b); });
            var formId = form.id;
            if (formId) {
                var safeId = formId.replace(/["\\]/g, '');
                document.querySelectorAll('[type="submit"][form="' + safeId + '"]').forEach(function(b) { buttons.push(b); });
            }
            buttons.forEach(function(btn) {
                btn.disabled = !enabled;
                if (enabled) {
                    btn.classList.remove('disabled');
                } else {
                    btn.classList.add('disabled');
                }
            });
        }

        function removeInlineError(form) {
            var existing = form.parentNode.querySelector('[data-le-modal-error]');
            if (existing) existing.remove();
        }

        function loadForm() {
            var body = document.getElementById(M + '-body');
            if (!body) return;
            loading = true;
            fetch(A, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) {
                    if (!r.ok) throw new Error('\u041e\u0448\u0438\u0431\u043a\u0430 \u0437\u0430\u0433\u0440\u0443\u0437\u043a\u0438 \u0444\u043e\u0440\u043c\u044b (HTTP ' + r.status + ')');
                    return r.text();
                })
                .then(function(html) {
                    var tmp = document.createElement('div');
                    tmp.innerHTML = html;
                    var form = tmp.querySelector('#le-create-form, #le-contractor-create-form, #le-client-create-form, form[action*="/company/contractors/create"], form[action*="/company/clients/create"]');
                    if (form) {
                        body.innerHTML = '';
                        body.appendChild(form);
                        wireForm(form);
                    } else {
                        body.innerHTML = html;
                    }
                })
                .catch(function(err) {
                    body.innerHTML = '<div class="form-alert alert-error"><div class="alert-mark">' +
                        '<svg width="11" height="11" viewBox="0 0 18 18" fill="none"><path d="M9 2L16.5 15H1.5L9 2Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 7V11M9 13V13.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>' +
                        '</div><div class="alert-body"><div class="alert-body-title">\u041e\u0448\u0438\u0431\u043a\u0430</div><div class="alert-body-sub">' +
                        (err.message || '\u041d\u0435\u0443\u0434\u0430\u043b\u043e\u0441\u044c \u0437\u0430\u0433\u0440\u0443\u0437\u0438\u0442\u044c \u0444\u043e\u0440\u043c\u0443') +
                        '</div></div></div>';
                })
                .then(function() { loading = false; });
        }

        function wireForm(form) {
            if (window.initContactFields) {
                window.initContactFields(form, { fieldPrefix: 'contacts' });
            }
            if (window.initLegalEntityDocuments) {
                window.initLegalEntityDocuments(form);
            }
            if (form.dataset.leSubmitReady !== '1') {
                form.dataset.leSubmitReady = '1';
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    doSubmit(form);
                });
            }
            var innBtn = form.querySelector('[data-inn-autofill-btn]');
            if (innBtn && innBtn.dataset.leInnReady !== '1') {
                innBtn.dataset.leInnReady = '1';
                innBtn.addEventListener('click', function() { doInnLookup(form, innBtn); });
            }
        }

        function doSubmit(form) {
            if (loading) return;
            if (window.validateLegalEntityDocuments && !window.validateLegalEntityDocuments(form)) {
                return;
            }
            loading = true;
            removeInlineError(form);
            setSubmitButtonEnabled(form, false);

            var formData = new FormData(form);
            formData.set('is_modal', '1');
            fetch(A, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(function(r) {
                if (!r.ok) throw new Error('\u041e\u0448\u0438\u0431\u043a\u0430 \u0441\u0435\u0440\u0432\u0435\u0440\u0430 (HTTP ' + r.status + ')');
                return r.text();
            })
            .then(function(html) {
                var tmp = document.createElement('div');
                tmp.innerHTML = html;
                if (tmp.querySelector('[data-le-create-success]')) {
                    window.closeModal(M);
                    window.location.reload();
                    return;
                }
                var nf = tmp.querySelector('#le-create-form, #le-contractor-create-form, #le-client-create-form, form[action*="/company/contractors/create"], form[action*="/company/clients/create"]');
                if (nf) {
                    var body = document.getElementById(M + '-body');
                    body.innerHTML = '';
                    body.appendChild(nf);
                    wireForm(nf);
                } else {
                    showInlineError(form, '\u041d\u0435\u043e\u0436\u0438\u0434\u0430\u043d\u043d\u044b\u0439 \u043e\u0442\u0432\u0435\u0442 \u0441\u0435\u0440\u0432\u0435\u0440\u0430. \u041f\u043e\u043f\u0440\u043e\u0431\u0443\u0439\u0442\u0435 \u0435\u0449\u0451 \u0440\u0430\u0437.');
                }
            })
            .catch(function(err) {
                showInlineError(form, err.message || '\u041f\u0440\u043e\u0438\u0437\u043e\u0448\u043b\u0430 \u043e\u0448\u0438\u0431\u043a\u0430. \u041f\u043e\u043f\u0440\u043e\u0431\u0443\u0439\u0442\u0435 \u0435\u0449\u0451 \u0440\u0430\u0437.');
            })
            .then(function() {
                loading = false;
                setSubmitButtonEnabled(form, true);
            });
        }

        function doInnLookup(form, btn) {
            if (window.runLegalEntityInnLookup) {
                window.runLegalEntityInnLookup(form, {
                    button: btn,
                    typeFieldName: typeField,
                    idleButtonText: '\u0417\u0430\u043f\u043e\u043b\u043d\u0438\u0442\u044c \u043f\u043e \u0418\u041d\u041d',
                    loadingButtonText: '\u041f\u043e\u0438\u0441\u043a...'
                });
                return;
            }
            var inn = form.querySelector('[name="inn"]');
            if (!inn || !inn.value.trim()) return;
            btn.disabled = true;
            btn.textContent = '\u041f\u043e\u0438\u0441\u043a...';
            var bp = window.getErpBasePath ? window.getErpBasePath() : '';
            var lookupUrl = form.dataset.innLookupUrl || bp + '/company/requisites/lookup-by-inn';
            fetch(lookupUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ inn: inn.value.trim() })
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                btn.disabled = false;
                btn.textContent = '\u0417\u0430\u043f\u043e\u043b\u043d\u0438\u0442\u044c \u043f\u043e \u0418\u041d\u041d';
                if (d.ok && d.data) {
                    var f = d.data;
                    var map = [['name',f.name],['kpp',f.kpp],['ogrn',f.ogrn],
                        ['legal_address',f.legal_address],['director_full_name',f.director_full_name],
                        ['director_position',f.director_position]];
                    map.push([typeField, f.contractor_type]);
                    map.forEach(function(p) {
                        if (p[1]) { var el = form.querySelector('[name="'+p[0]+'"]'); if (el) el.value = p[1]; }
                    });
                }
            })
            .catch(function() { btn.disabled = false; btn.textContent = '\u0417\u0430\u043f\u043e\u043b\u043d\u0438\u0442\u044c \u043f\u043e \u0418\u041d\u041d'; });
        }

        new MutationObserver(function() {
            if (modal.classList.contains('is-open')) {
                var body = document.getElementById(M + '-body');
                if (body && !body.querySelector('form')) loadForm();
            }
        }).observe(modal, { attributes: true, attributeFilter: ['class'] });

        return { loadForm: loadForm };
    }

    window.LegalEntityModal = {
        init: function(config) {
            instances[config.modalId] = createInstance(config);
        }
    };
})();
