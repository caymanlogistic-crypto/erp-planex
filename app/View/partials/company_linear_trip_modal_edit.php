<?php

use App\Service\LinearRouteService;

$old = [
    'id' => $route['id'] ?? 0,
    'route_type' => $route['route_type'] ?? '',
    'client_id' => $route['client_id'] ?? '',
    'carrier_contractor_id' => $route['carrier_contractor_id'] ?? '',
    'route_executor_id' => $route['route_executor_id'] ?? '',
    'cargo_type_name' => $route['cargo_type_name'] ?? '',
    'planned_loading_date' => $route['planned_loading_date'] ?? '',
    'planned_unloading_date' => $route['planned_unloading_date'] ?? '',
    'actual_loading_date' => $route['actual_loading_date'] ?? '',
    'actual_unloading_date' => $route['actual_unloading_date'] ?? '',
    'comments' => $route['comments'] ?? '',
    'route_points' => $route['route_points'] ?? ['loading' => [''], 'unloading' => ['']],
    'customer_payments' => $route['payments']['customer'] ?? [],
    'carrier_payments' => $route['payments']['carrier'] ?? [],
    'principal_rows' => [],
];
foreach (($route['principal_items'] ?? []) as $principal) {
    $principalId = (int) ($principal['id'] ?? 0);
    $old['principal_rows'][] = [
        'entity_key' => $principal['entity_key'] ?? '',
        'payments' => $route['payments']['principals'][$principalId] ?? [],
    ];
}

$routeFormMode = 'edit';
$routeFormId = 'linear-trip-edit-form';
$routeFormAction = app_url('/company/trips/linear/' . (int) ($route['id'] ?? 0) . '/modal-edit');
$routeFormDomPrefix = 'linear-trip-edit-' . (int) ($route['id'] ?? 0);
$routeFormClass = 'linear-trip-panel';
$existingDocsByCode = $docsByCode;
?>
<div class="modal-body driver-modal-body">
  <?php require base_path('app/View/partials/company_linear_trip_create_form.php'); ?>
</div>
<div class="modal-foot is-spaced">
  <input type="hidden" name="_linear_trip_save_token" value="<?= e((string) ($linearTripSaveToken ?? '')) ?>" form="linear-trip-edit-form" data-linear-trip-save-token>
  <div class="modal-required-note"><span class="req">*</span> — обязательные поля</div>
  <div class="modal-foot-actions">
    <button type="button" class="btn btn-ghost" data-linear-trip-cancel-edit-btn>Отмена</button>
    <button type="submit" form="linear-trip-edit-form" class="btn btn-primary" data-linear-trip-save-btn>Сохранить</button>
  </div>
</div>
<script>
(function initP13LinearTripSave() {
    'use strict';
    var form = document.getElementById('linear-trip-edit-form');
    if (!form || form.dataset.p13SaveReady === '1') return;
    form.dataset.p13SaveReady = '1';

    var modal = form.closest('.modal');
    var shell = form.closest('.modal-overlay');
    var saveButton = modal ? modal.querySelector('[data-linear-trip-save-btn]') : null;
    var tokenInput = modal ? modal.querySelector('[data-linear-trip-save-token]') : null;

    function ensureErrorBox() {
        var box = form.querySelector('[data-p13-linear-trip-error]');
        if (box) return box;
        box = document.createElement('div');
        box.className = 'form-alert alert-error';
        box.setAttribute('data-p13-linear-trip-error', '');
        box.innerHTML = '<div class="alert-body"><div class="alert-body-title">Не удалось сохранить рейс</div><div class="alert-body-sub" data-p13-linear-trip-error-text></div></div>';
        var main = form.querySelector('.linear-trip-layout-main') || form.firstElementChild;
        if (main) main.insertBefore(box, main.firstChild);
        return box;
    }

    function showError(message, errors) {
        var box = ensureErrorBox();
        var text = box.querySelector('[data-p13-linear-trip-error-text]');
        if (text) text.textContent = message || 'Проверьте данные формы.';
        box.hidden = false;
        form.querySelectorAll('.field.is-error').forEach(function (field) { field.classList.remove('is-error'); });

        Object.keys(errors || {}).forEach(function (key) {
            if (key === 'documents') {
                var docs = form.querySelector('.linear-trip-layout-docs');
                if (docs) docs.classList.add('has-error');
                return;
            }
            var name = key.replace(/\.([0-9]+)(?=\.|$)/g, '[$1]').replace(/\.([^.\[]+)/g, '[$1]');
            var input = form.querySelector('[name="' + CSS.escape(name) + '"]') || form.querySelector('[name="' + CSS.escape(key) + '"]');
            if (!input) return;
            var field = input.closest('.field');
            if (field) {
                field.classList.add('is-error');
                var msg = field.querySelector('.field-msg');
                if (msg) msg.textContent = String(errors[key] || '');
            }
        });

        if (errors && errors.documents) {
            var docsBox = form.querySelector('.linear-trip-layout-docs .field-msg');
            if (docsBox) docsBox.textContent = String(errors.documents);
        }
        box.scrollIntoView({block: 'nearest', behavior: 'smooth'});
    }

    function setBusy(isBusy) {
        form.dataset.submitting = isBusy ? '1' : '0';
        form.setAttribute('aria-busy', isBusy ? 'true' : 'false');
        if (saveButton) {
            if (!saveButton.dataset.originalText) saveButton.dataset.originalText = saveButton.textContent || 'Сохранить';
            saveButton.disabled = isBusy;
            saveButton.textContent = isBusy ? 'Сохранение…' : saveButton.dataset.originalText;
            saveButton.classList.toggle('is-loading', isBusy);
        }
        form.querySelectorAll('button, input[type="submit"]').forEach(function (control) {
            if (control !== saveButton) control.disabled = isBusy;
        });
    }

    function showSuccess(message) {
        var toast = document.createElement('div');
        toast.className = 'notice ok';
        toast.setAttribute('role', 'status');
        toast.textContent = message || 'Рейс успешно сохранён.';
        toast.style.position = 'fixed';
        toast.style.right = '24px';
        toast.style.top = '24px';
        toast.style.zIndex = '100000';
        toast.style.maxWidth = '420px';
        document.body.appendChild(toast);
        window.setTimeout(function () { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 4000);
    }

    function addClearButton(row, input) {
        if (row.querySelector('[data-p13-clear-selected-file]')) return;
        var initialBadge = row.querySelector('.file-type-badge');
        var initialMeta = row.querySelector('.file-meta');
        var initialLabel = row.querySelector('.file-action-btn span');
        var initialState = {
            badgeClass: initialBadge ? initialBadge.className : '',
            badgeText: initialBadge ? initialBadge.textContent : '',
            metaText: initialMeta ? initialMeta.textContent : '',
            labelText: initialLabel ? initialLabel.textContent : '',
            rowClass: row.className
        };
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'file-remove is-hidden';
        button.title = 'Убрать выбранный файл';
        button.textContent = '×';
        button.setAttribute('data-p13-clear-selected-file', '');
        button.addEventListener('click', function () {
            input.value = '';
            button.classList.add('is-hidden');
            var badge = row.querySelector('.file-type-badge');
            var meta = row.querySelector('.file-meta');
            var label = row.querySelector('.file-action-btn span');
            row.className = initialState.rowClass;
            if (badge) { badge.className = initialState.badgeClass; badge.textContent = initialState.badgeText; }
            if (meta) meta.textContent = initialState.metaText;
            if (label) label.textContent = initialState.labelText;
        });
        row.appendChild(button);
        input.addEventListener('change', function () { button.classList.toggle('is-hidden', !(input.files && input.files.length)); });
    }

    form.querySelectorAll('.document-file-row input[type="file"]').forEach(function (input) {
        var row = input.closest('.document-file-row');
        if (row) addClearButton(row, input);
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        event.stopImmediatePropagation();
        if (form.dataset.submitting === '1') return;

        var errorBox = form.querySelector('[data-p13-linear-trip-error]');
        if (errorBox) errorBox.hidden = true;
        setBusy(true);

        var formData = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            body: formData
        })
            .then(function (response) {
                return response.text().then(function (text) {
                    var data;
                    try { data = JSON.parse(text); }
                    catch (error) { data = {success: false, message: 'Сервер вернул некорректный ответ.'}; }
                    return {response: response, data: data};
                });
            })
            .then(function (result) {
                var data = result.data || {};
                if (!result.response.ok || data.success !== true) {
                    if (tokenInput && data.retry_token) tokenInput.value = data.retry_token;
                    showError(data.message || 'Не удалось сохранить рейс.', data.errors || {});
                    return;
                }
                if (shell) shell._refreshListOnClose = true;
                showSuccess(data.message);
                var controller = window.ModalShell && window.ModalShell.get ? window.ModalShell.get('linearTrip') : null;
                if (controller && data.route_id) controller.loadView(String(data.route_id));
                else window.location.reload();
            })
            .catch(function () {
                showError('Не удалось связаться с сервером. Проверьте соединение и повторите сохранение.', {});
            })
            .finally(function () { setBusy(false); });
    }, true);
})();
</script>
