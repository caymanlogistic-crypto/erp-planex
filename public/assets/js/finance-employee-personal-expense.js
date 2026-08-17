(function () {
  'use strict';

  function basePath() {
    if (typeof window.getErpBasePath === 'function') return window.getErpBasePath() || '';
    return window.location.pathname.indexOf('/erpv2/') === 0 ? '/erpv2' : '';
  }

  function endpoint(path) { return basePath() + path; }

  function selectedEmployeeRef() {
    var select = document.querySelector('.employee-report-filter select[name="employee_ref"]');
    return select ? (select.value || '') : '';
  }

  async function fetchHtml(url) {
    var response = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    var html = await response.text();
    if (!response.ok) throw new Error(html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim() || 'Ошибка загрузки формы.');
    return html;
  }

  function closeModal(modal) { if (modal && modal.parentNode) modal.parentNode.removeChild(modal); }

  function decorateLedgerRows(report) {
    report.querySelectorAll('.employee-report-table tbody tr').forEach(function (row) {
      var cells = row.querySelectorAll('td');
      if (cells.length < 4) return;
      var basis = String(cells[3].textContent || '').toLocaleLowerCase('ru-RU');
      if (basis.indexOf('оплата расхода компании из личных средств') === -1 && basis.indexOf('оплачено сотрудником за компанию') === -1) return;
      cells[1].textContent = 'Оплата за компанию';
      cells[2].textContent = 'Личные средства';
      row.classList.add('employee-personal-expense-ledger-row');
      row.title = 'Расход компании, оплаченный сотрудником из личных средств';
    });
  }

  function initExpenseForm(modal) {
    var form = modal.querySelector('[data-personal-expense-form]');
    if (!form) return;
    var cfu = form.querySelector('[data-personal-expense-cfu]');
    var dds = form.querySelector('[data-personal-expense-dds]');
    if (cfu && dds) {
      var allowed = {};
      try { allowed = JSON.parse(form.getAttribute('data-allowed-expense-dds-map') || '{}'); } catch (_) {}
      var initialSelected = String(dds.getAttribute('data-selected-dds') || dds.value || '');
      var syncDds = function (keepInitial) {
        var ids = (allowed[String(cfu.value)] || []).map(String);
        Array.prototype.forEach.call(dds.options, function (option) {
          if (!option.value) { option.hidden = false; option.disabled = false; return; }
          var enabled = ids.indexOf(String(option.value)) !== -1;
          option.hidden = !enabled;
          option.disabled = !enabled;
        });
        var preferred = keepInitial ? initialSelected : String(dds.value || '');
        if (preferred && ids.indexOf(preferred) !== -1) dds.value = preferred;
        else if (dds.value && ids.indexOf(String(dds.value)) === -1) dds.value = '';
      };
      cfu.addEventListener('change', function () { syncDds(false); });
      syncDds(true);
    }

    modal.querySelectorAll('[data-personal-expense-close]').forEach(function (button) {
      button.addEventListener('click', function () { closeModal(modal); });
    });
    modal.addEventListener('click', function (event) { if (event.target === modal) closeModal(modal); });

    var cancelButton = modal.querySelector('[data-personal-expense-cancel-event]');
    if (cancelButton) {
      cancelButton.addEventListener('click', async function () {
        if (!window.confirm('Отменить эту операцию? Связанные поступление и расход в Основной кассе также будут отменены.')) return;
        cancelButton.disabled = true;
        try {
          var eventId = cancelButton.getAttribute('data-personal-expense-cancel-event');
          var formData = new FormData();
          form.querySelectorAll('input[type="hidden"]').forEach(function (input) { formData.append(input.name, input.value); });
          formData.set('employee_ref', cancelButton.getAttribute('data-employee-ref') || selectedEmployeeRef());
          formData.set('reason', 'Отменено пользователем из раздела «Выплаты сотрудникам»');
          var response = await fetch(endpoint('/company/finance/employee-payments/personal-expense/' + encodeURIComponent(eventId) + '/cancel'), {
            method: 'POST', body: formData, credentials: 'same-origin'
          });
          if (!response.ok) throw new Error('Не удалось отменить операцию.');
          window.location.href = response.url || endpoint('/company/finance/employee-payments');
        } catch (error) {
          cancelButton.disabled = false;
          window.alert(error.message || 'Не удалось отменить операцию.');
        }
      });
    }
  }

  async function openForm(url) {
    try {
      var html = await fetchHtml(url);
      var shell = document.createElement('div');
      shell.innerHTML = html.trim();
      var modal = shell.firstElementChild;
      if (!modal) throw new Error('Форма не загружена.');
      document.body.appendChild(modal);
      initExpenseForm(modal);
    } catch (error) { window.alert(error.message || 'Не удалось открыть форму.'); }
  }

  async function loadList(container) {
    var ref = selectedEmployeeRef();
    if (!ref) { container.innerHTML = ''; return; }
    try {
      container.innerHTML = '<div class="table-card" style="margin-top:14px;padding:18px;">Загрузка расходов сотрудника…</div>';
      container.innerHTML = await fetchHtml(endpoint('/company/finance/employee-payments/personal-expenses?employee_ref=' + encodeURIComponent(ref)));
      container.querySelectorAll('[data-personal-expense-edit]').forEach(function (button) {
        button.addEventListener('click', function () {
          openForm(endpoint('/company/finance/employee-payments/personal-expense/' + encodeURIComponent(button.getAttribute('data-personal-expense-edit')) + '/edit'));
        });
      });
    } catch (error) {
      container.innerHTML = '<div class="notice warn" style="margin-top:14px;">' + String(error.message || 'Не удалось загрузить расходы сотрудника.').replace(/[&<>"']/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[c]; }) + '</div>';
    }
  }

  function init() {
    if (window.location.pathname.indexOf('/company/finance/employee-payments') === -1) return;
    var report = document.querySelector('.employee-report');
    if (!report) return;
    decorateLedgerRows(report);

    var headRight = report.querySelector('.page-head-right');
    if (headRight && !document.getElementById('employee-personal-expense-open')) {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'btn btn-primary btn--toolbar';
      button.id = 'employee-personal-expense-open';
      button.textContent = 'Оплачено сотрудником';
      button.title = 'Зафиксировать расход компании, оплаченный сотрудником из личных средств';
      headRight.insertBefore(button, headRight.firstChild);
      button.addEventListener('click', function () {
        var ref = selectedEmployeeRef();
        var url = endpoint('/company/finance/employee-payments/personal-expense/create');
        if (ref) url += '?employee_ref=' + encodeURIComponent(ref);
        openForm(url);
      });
    }

    var card = report.querySelector('.employee-report-card');
    if (card && !document.getElementById('employee-personal-expense-list-host')) {
      var host = document.createElement('div');
      host.id = 'employee-personal-expense-list-host';
      card.insertAdjacentElement('afterend', host);
      loadList(host);
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    var modal = document.querySelector('.employee-personal-expense-modal.is-open');
    if (modal) closeModal(modal);
  });
})();
