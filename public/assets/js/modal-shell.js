// ============================================================
// ModalShell — universal modal view/edit/delete controller
// Used by DriverModal, VehicleSetModal, and future entity modals.
// Requires: erp-ui.css (.modal-overlay, .modal, .is-open, etc.)
// ============================================================
window.ModalShell = (function () {
    var registry = {};

    function create(config) {
        var state = {
            shell: null,
            confirmShell: null,
            currentId: null,
            refreshOnClose: false,
            config: config
        };

        function getShell() {
            if (state.shell) return state.shell;
            var existing = document.getElementById(config.modalId);
            if (existing) { state.shell = existing; return existing; }

            var el = document.createElement('div');
            el.className = 'modal-overlay ' + (config.overlayClass || '');
            el.id = config.modalId;
            el.setAttribute('role', 'dialog');
            el.setAttribute('aria-modal', 'true');
            el.dataset.closeOnOverlay = '0';
            el.dataset.closeOnEscape = '1';
            el.innerHTML =
                '<div class="modal ' + (config.modalInnerClass || 'modal-lg') + '">' +
                '  <div class="modal-head">' +
                '    <span class="modal-title" data-modal-title>' + (config.title || '') + '</span>' +
                '    <button type="button" class="modal-close" data-modal-close>&times;</button>' +
                '  </div>' +
                '</div>';
            el.querySelector('[data-modal-close]').addEventListener('click', function () {
                close();
            });
            document.body.appendChild(el);
            state.shell = el;
            return el;
        }

        function getModal() {
            return getShell().querySelector('.modal');
        }

        function setEntityId(id) {
            state.currentId = id;
            state.shell = null;
            getShell();
            state.shell._entityId = id;
        }

        function showError(msg) {
            var modal = getModal();
            var oldBody = modal.querySelector('.modal-body');
            var oldFoot = modal.querySelector('.modal-foot');
            if (oldBody) oldBody.remove();
            if (oldFoot) oldFoot.remove();
            var el = document.createElement('div');
            el.className = 'modal-body';
            el.innerHTML = '<div class="notice warn modal-notice">' + msg + '</div>';
            modal.appendChild(el);
        }

        function showLoading(message) {
            var modal = getModal();
            var oldBody = modal.querySelector('.modal-body');
            var oldFoot = modal.querySelector('.modal-foot');
            if (oldBody) oldBody.remove();
            if (oldFoot) oldFoot.remove();
            var el = document.createElement('div');
            el.className = 'modal-body';
            el.innerHTML = '<div class="' + (config.loadingClass || 'driver-modal-loading') + '">' + (message || '...') + '</div>';
            modal.appendChild(el);
        }

        function setContent(html) {
            var modal = getModal();
            var titleEl = modal.querySelector('[data-modal-title]');
            if (titleEl && config.title) titleEl.textContent = config.title;
            var oldBody = modal.querySelector('.modal-body');
            var oldFoot = modal.querySelector('.modal-foot');
            if (oldBody) oldBody.remove();
            if (oldFoot) oldFoot.remove();
            modal.querySelectorAll('script[data-modal-inline-script="1"]').forEach(function (s) { s.remove(); });
            var tmp = document.createElement('div');
            tmp.innerHTML = html;
            var body = tmp.querySelector('.modal-body');
            var foot = tmp.querySelector('.modal-foot');
            if (body) modal.appendChild(body);
            if (foot) modal.appendChild(foot);
            tmp.querySelectorAll('script').forEach(function (oldScript) {
                var newScript = document.createElement('script');
                Array.prototype.slice.call(oldScript.attributes).forEach(function (attr) {
                    newScript.setAttribute(attr.name, attr.value);
                });
                newScript.textContent = oldScript.textContent;
                newScript.setAttribute('data-modal-inline-script', '1');
                modal.appendChild(newScript);
            });
        }

        function fetchHtml(url) {
            return fetch(url, { credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) throw Error(r.status); return r.text(); });
        }

        function loadView(id) {
            showLoading('...');
            setEntityId(id);
            var s = getShell();
            s.classList.add('is-open');
            fetchHtml(config.endpoints.view(id))
                .then(function (html) {
                    setContent(html);
                    bindViewButtons();
                })
                .catch(function () {
                    showError(config.errorMessages.loadFailed || '...');
                });
        }

        function loadEdit(id) {
            showLoading('...');
            setEntityId(id);
            var s = getShell();
            s.classList.add('is-open');
            fetchHtml(config.endpoints.edit(id))
                .then(function (html) {
                    setContent(html);
                    if (config.onContentLoaded) config.onContentLoaded(getShell(), 'edit');
                    bindEditButtons();
                })
                .catch(function () {
                    showError(config.errorMessages.loadFailed || '...');
                });
        }

        function bindViewButtons() {
            var s = getShell();
            var vs = config.viewBtnSelectors || {};
            var editBtn = s.querySelector(vs.edit || '[data-edit-btn]');
            var closeBtn = s.querySelector(vs.close || '[data-close-btn]');
            var deleteBtn = s.querySelector(vs.delete || '[data-delete-btn]');

            if (editBtn) {
                var nb = editBtn.cloneNode(true);
                editBtn.parentNode.replaceChild(nb, editBtn);
                nb.addEventListener('click', function () { loadEdit(s._entityId); });
            }
            if (closeBtn) {
                var nc = closeBtn.cloneNode(true);
                closeBtn.parentNode.replaceChild(nc, closeBtn);
                nc.addEventListener('click', function () { close(); });
            }
            if (deleteBtn) {
                var nd = deleteBtn.cloneNode(true);
                deleteBtn.parentNode.replaceChild(nd, deleteBtn);
                nd.addEventListener('click', function () {
                    var nameEl = s.querySelector(config.nameSelector);
                    confirmDelete(nameEl ? nameEl.textContent.trim() : '');
                });
            }
        }

        function bindEditButtons() {
            var s = getShell();
            var es = config.editBtnSelectors || {};
            var cancelBtn = s.querySelector(es.cancel || '[data-cancel-edit-btn]');
            var form = s.querySelector(config.editFormSelector);

            if (cancelBtn) {
                var nc = cancelBtn.cloneNode(true);
                cancelBtn.parentNode.replaceChild(nc, cancelBtn);
                nc.addEventListener('click', function () { loadView(s._entityId); });
            }

            if (form && form.dataset.modalSubmitReady !== '1') {
                form.dataset.modalSubmitReady = '1';
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    var formData = new FormData(form);
                    showLoading('...');
                    fetch(config.endpoints.edit(s._entityId), {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData
                    })
                    .then(function (r) { if (!r.ok) throw Error(r.status); return r.text(); })
                    .then(function (html) {
                        var tmp = document.createElement('div');
                        tmp.innerHTML = html;
                        if (tmp.querySelector(config.editFormSelector)) {
                            setContent(html);
                            if (config.onContentLoaded) config.onContentLoaded(getShell(), 'edit');
                            bindEditButtons();
                        } else {
                            state.refreshOnClose = true;
                            getShell()._refreshListOnClose = true;
                            setContent(html);
                            bindViewButtons();
                        }
                    })
                    .catch(function () {
                        showError(config.errorMessages.saveFailed || '...');
                    });
                });
            }
        }

        function getDeleteConfirmShell() {
            if (state.confirmShell) return state.confirmShell;
            var id = config.deleteConfirmId;
            var existing = document.getElementById(id);
            if (existing) { state.confirmShell = existing; return existing; }

            var c = document.createElement('div');
            c.className = 'modal-overlay';
            c.id = id;
            c.setAttribute('role', 'dialog');
            c.setAttribute('aria-modal', 'true');
            c.dataset.closeOnOverlay = '0';
            c.dataset.closeOnEscape = '1';
            c.innerHTML =
                '<div class="modal">' +
                '  <div class="modal-head">' +
                '    <span class="modal-title">' + config.deleteConfirm.title + '</span>' +
                '    <button type="button" class="modal-close" data-confirm-close>&times;</button>' +
                '  </div>' +
                '  <div class="modal-body">' +
                '    <div class="driver-delete-confirm-body">' +
                '      <div class="driver-delete-confirm-head">' +
                '        <div class="modal-icon is-danger" aria-hidden="true">' +
                '          <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 2L16.5 15H1.5L9 2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"></path><path d="M9 7V11M9 13V13.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path></svg>' +
                '        </div>' +
                '        <div class="driver-delete-confirm-title">' + config.deleteConfirm.warning + '</div>' +
                '      </div>' +
                '      <div class="driver-delete-confirm-text">' + config.deleteConfirm.instruction + '</div>' +
                '      <div class="driver-delete-confirm-name" data-confirm-name></div>' +
                '      <div class="field driver-delete-confirm-field">' +
                '        <input type="text" class="field-input" data-confirm-input autocomplete="off" placeholder="' + config.deleteConfirm.placeholder + '">' +
                '      </div>' +
                '      <div class="driver-delete-confirm-error is-hidden" data-confirm-error></div>' +
                '    </div>' +
                '  </div>' +
                '  <div class="modal-foot">' +
                '    <button type="button" class="btn btn-ghost" data-confirm-cancel>' + config.deleteConfirm.cancelBtn + '</button>' +
                '    <button type="button" class="btn btn-danger" data-confirm-action disabled>' + config.deleteConfirm.confirmBtn + '</button>' +
                '  </div>' +
                '</div>';

            document.body.appendChild(c);
            state.confirmShell = c;
            return c;
        }

        function bindDeleteConfirm() {
            var c = getDeleteConfirmShell();
            var closeBtn = c.querySelector('[data-confirm-close]');
            var cancelBtn = c.querySelector('[data-confirm-cancel]');
            var confirmBtn = c.querySelector('[data-confirm-action]');
            var input = c.querySelector('[data-confirm-input]');
            var errorBox = c.querySelector('[data-confirm-error]');

            function closeConfirm() {
                window.closeModal(config.deleteConfirmId);
                if (input) input.value = '';
                if (errorBox) {
                    errorBox.textContent = '';
                    errorBox.classList.add('is-hidden');
                }
                if (confirmBtn) confirmBtn.disabled = true;
            }

            function syncConfirmState() {
                if (!input || !confirmBtn) return;
                confirmBtn.disabled = input.value.trim().toUpperCase() !== config.deleteConfirm.confirmWord;
            }

            if (closeBtn && !closeBtn.dataset.confirmReady) {
                closeBtn.dataset.confirmReady = '1';
                closeBtn.addEventListener('click', closeConfirm);
            }
            if (cancelBtn && !cancelBtn.dataset.confirmReady) {
                cancelBtn.dataset.confirmReady = '1';
                cancelBtn.addEventListener('click', closeConfirm);
            }
            if (input && !input.dataset.confirmReady) {
                input.dataset.confirmReady = '1';
                input.addEventListener('input', syncConfirmState);
                input.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' && !confirmBtn.disabled) {
                        e.preventDefault();
                        confirmBtn.click();
                    }
                });
            }
            if (confirmBtn && !confirmBtn.dataset.confirmReady) {
                confirmBtn.dataset.confirmReady = '1';
                confirmBtn.addEventListener('click', function () {
                    var s = getShell();
                    if (!s._entityId) return;
                    confirmBtn.disabled = true;
                    if (errorBox) {
                        errorBox.textContent = '';
                        errorBox.classList.add('is-hidden');
                    }
                    fetch(config.endpoints.delete(s._entityId), {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(function (r) { if (!r.ok) throw Error(r.status); return r.json(); })
                    .then(function (result) {
                        if (!result || !result.success) {
                            throw new Error(result && result.error ? result.error : config.errorMessages.deleteFailed);
                        }
                        closeConfirm();
                        state.refreshOnClose = false;
                        saveGridState();
                        window.closeModal(config.modalId);
                        window.location.reload();
                    })
                    .catch(function (error) {
                        if (errorBox) {
                            errorBox.textContent = error && error.message ? error.message : config.errorMessages.deleteFailed;
                            errorBox.classList.remove('is-hidden');
                        }
                        syncConfirmState();
                    });
                });
            }

            return {
                open: function (name) {
                    var nameBox = c.querySelector('[data-confirm-name]');
                    if (nameBox) nameBox.textContent = name || '';
                    if (input) input.value = '';
                    if (errorBox) {
                        errorBox.textContent = '';
                        errorBox.classList.add('is-hidden');
                    }
                    if (confirmBtn) confirmBtn.disabled = true;
                    window.openModal(config.deleteConfirmId);
                    if (input) input.focus();
                }
            };
        }

        function confirmDelete(name) {
            bindDeleteConfirm().open(name);
        }

        function saveGridState() {
            if (!config.gridStateKey || !config.gridSelector) return;
            if (!window.sessionStorage) return;
            var card = document.querySelector(config.gridSelector);
            if (!card) return;
            var searchInput = card.querySelector('.toolbar-search, [data-erp-grid-search]');
            var sortSelect = card.querySelector('select[data-erp-grid-sort]');
            sessionStorage.setItem(config.gridStateKey, JSON.stringify({
                search: searchInput ? (searchInput.value || '') : '',
                sort: sortSelect ? sortSelect.value : 'date'
            }));
        }

        function close() {
            var s = getShell();
            var shouldRefresh = s._refreshListOnClose === true || state.refreshOnClose === true;
            window.closeModal(config.modalId);
            state.refreshOnClose = false;
            if (!shouldRefresh) return;
            s._refreshListOnClose = false;
            saveGridState();
            window.location.reload();
        }

        return {
            loadView: loadView,
            loadEdit: loadEdit,
            close: close,
            getShell: getShell,
            getState: function () { return state; }
        };
    }

    function get(name) {
        return registry[name] || null;
    }

    function register(name, instance) {
        registry[name] = instance;
    }

    return { create: create, get: get, register: register };
})();

// P11 Full HD keyboard contract: Escape closes only the topmost visible modal.
// Legacy data-close-on-escape="0" values are intentionally overridden.
// A modal that must remain locked must opt in explicitly with data-escape-locked="1".
(function initErpModalKeyboardController() {
    'use strict';

    if (window.__erpModalKeyboardControllerReady) return;
    window.__erpModalKeyboardControllerReady = true;

    function isVisible(modal) {
        if (!modal || !modal.classList.contains('is-open')) return false;
        var style = window.getComputedStyle(modal);
        return style.display !== 'none' && style.visibility !== 'hidden';
    }

    function zIndexOf(modal) {
        var parsed = parseInt(window.getComputedStyle(modal).zIndex, 10);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function topmostOpenModal() {
        var modals = Array.prototype.filter.call(
            document.querySelectorAll('.modal-overlay.is-open'),
            isVisible
        );

        return modals.reduce(function (top, candidate) {
            if (!top) return candidate;
            var topZ = zIndexOf(top);
            var candidateZ = zIndexOf(candidate);
            if (candidateZ > topZ) return candidate;
            if (candidateZ < topZ) return top;
            return (top.compareDocumentPosition(candidate) & Node.DOCUMENT_POSITION_FOLLOWING)
                ? candidate
                : top;
        }, null);
    }

    function closeTopmostModal(modal) {
        if (!modal) return;
        if (modal.id && typeof window.closeModal === 'function') {
            window.closeModal(modal.id);
            return;
        }
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape' || event.defaultPrevented || event.isComposing) return;

        var modal = topmostOpenModal();
        if (!modal || modal.dataset.escapeLocked === '1') return;

        event.preventDefault();
        event.stopImmediatePropagation();
        closeTopmostModal(modal);
    }, true);
})();
