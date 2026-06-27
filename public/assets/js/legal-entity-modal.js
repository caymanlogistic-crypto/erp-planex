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

        function loadForm() {
            var body = document.getElementById(M + '-body');
            if (!body) return;
            fetch(A, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.text(); })
                .then(function(html) {
                    var tmp = document.createElement('div');
                    tmp.innerHTML = html;
                    var form = tmp.querySelector('form');
                    if (form) {
                        body.innerHTML = '';
                        body.appendChild(form);
                        wireForm(form);
                    } else {
                        body.innerHTML = html;
                    }
                });
        }

        function wireForm(form) {
            form.setAttribute('onsubmit', 'return false');
            var btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    doSubmit(form);
                });
            }
            var innBtn = form.querySelector('[data-inn-autofill-btn]');
            if (innBtn) {
                innBtn.addEventListener('click', function() { doInnLookup(form, innBtn); });
            }
        }

        function doSubmit(form) {
            var fields = ['name','inn',typeField,'kpp','ogrn','director_full_name','director_position',
                'legal_address','physical_address','bank_account','bank_bik','bank_name','bank_corr_account','comments'];
            var params = new URLSearchParams();
            params.set('is_modal', '1');
            fields.forEach(function(name) {
                var el = form.querySelector('[name="' + name + '"]');
                params.set(name, el ? el.value : '');
            });
            fetch(A, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(function(r) { return r.text(); })
            .then(function(html) {
                var tmp = document.createElement('div');
                tmp.innerHTML = html;
                if (tmp.querySelector('[data-le-create-success]')) {
                    window.closeModal(M);
                    window.location.reload();
                    return;
                }
                var nf = tmp.querySelector('form');
                if (nf) {
                    var body = document.getElementById(M + '-body');
                    body.innerHTML = '';
                    body.appendChild(nf);
                    wireForm(nf);
                }
            });
        }

        function doInnLookup(form, btn) {
            var inn = form.querySelector('[name="inn"]');
            if (!inn || !inn.value.trim()) return;
            btn.disabled = true;
            btn.textContent = '\u041f\u043e\u0438\u0441\u043a...';
            fetch('/company/requisites/lookup-by-inn', {
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
