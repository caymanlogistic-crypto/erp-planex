(function () {
  'use strict';

  function normalizeIndexes(group) {
    var type = group.getAttribute('data-route-point-group');
    group.querySelectorAll('[data-route-point-row]').forEach(function (row, index) {
      var input = row.querySelector('input[data-route-point-input]');
      if (input) input.name = 'route_points[' + type + '][]';
      var number = row.querySelector('[data-route-point-number]');
      if (number) number.textContent = String(index + 1);
      var remove = row.querySelector('[data-remove-route-point]');
      if (remove) remove.classList.toggle('is-hidden', index === 0 && group.querySelectorAll('[data-route-point-row]').length === 1);
    });
  }

  function addRow(group) {
    var type = group.getAttribute('data-route-point-group');
    var list = group.querySelector('[data-route-point-list]');
    if (!list) return;
    var row = document.createElement('div');
    row.className = 'linear-trip-route-point-row';
    row.setAttribute('data-route-point-row', '');
    row.innerHTML = '' +
      '<div class="linear-trip-route-point-index" data-route-point-number></div>' +
      '<input type="text" class="field-input" data-route-point-input autocomplete="off" placeholder="' + (type === 'loading' ? 'Адрес или место загрузки' : 'Адрес или место выгрузки') + '">' +
      '<button type="button" class="linear-trip-route-point-remove" data-remove-route-point aria-label="Удалить точку">×</button>';
    list.appendChild(row);
    normalizeIndexes(group);
    var input = row.querySelector('input');
    if (input) input.focus();
  }

  function initForm(form) {
    if (!form || form.dataset.routePointsReady === '1') return;
    form.dataset.routePointsReady = '1';

    form.querySelectorAll('[data-route-point-group]').forEach(function (group) {
      normalizeIndexes(group);
      var add = group.querySelector('[data-add-route-point]');
      if (add) add.addEventListener('click', function () { addRow(group); });
      group.addEventListener('click', function (event) {
        var remove = event.target.closest('[data-remove-route-point]');
        if (!remove) return;
        var rows = group.querySelectorAll('[data-route-point-row]');
        var row = remove.closest('[data-route-point-row]');
        if (!row) return;
        if (rows.length <= 1) {
          var input = row.querySelector('input[data-route-point-input]');
          if (input) input.value = '';
          return;
        }
        row.remove();
        normalizeIndexes(group);
      });
    });
  }

  function scan(root) {
    (root || document).querySelectorAll('[data-linear-trip-form]').forEach(initForm);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { scan(document); });
  } else {
    scan(document);
  }

  new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
      mutation.addedNodes.forEach(function (node) {
        if (node.nodeType !== 1) return;
        if (node.matches && node.matches('[data-linear-trip-form]')) initForm(node);
        scan(node);
      });
    });
  }).observe(document.documentElement, {childList: true, subtree: true});
})();
