(function () {
  'use strict';

  function clean(value) {
    return String(value || '').replace(/\s+/g, ' ').trim();
  }

  function legacyPoints(form) {
    var loadingGroup = form.querySelector('[data-route-point-group="loading"]');
    var unloadingGroup = form.querySelector('[data-route-point-group="unloading"]');
    if (!loadingGroup || !unloadingGroup) return null;

    var loading = Array.from(loadingGroup.querySelectorAll('input[data-route-point-input]')).map(function (input) { return clean(input.value); });
    var unloading = Array.from(unloadingGroup.querySelectorAll('input[data-route-point-input]')).map(function (input) { return clean(input.value); });
    var max = Math.max(loading.length, unloading.length);
    var points = [];

    for (var i = 0; i < max; i++) {
      var loadAddress = loading[i] || '';
      var unloadAddress = unloading[i] || '';
      if (loadAddress && unloadAddress && loadAddress === unloadAddress) {
        points.push({address: loadAddress, loading: true, unloading: true});
      } else {
        if (loadAddress) points.push({address: loadAddress, loading: true, unloading: false});
        if (unloadAddress) points.push({address: unloadAddress, loading: false, unloading: true});
      }
    }

    if (!points.length) {
      points = [
        {address: '', loading: true, unloading: false},
        {address: '', loading: false, unloading: true}
      ];
    }
    return points;
  }

  function setToggleState(button, hidden, active) {
    button.classList.toggle('is-active', !!active);
    button.setAttribute('aria-pressed', active ? 'true' : 'false');
    hidden.disabled = !active;
  }

  function syncLegacyProjection(row) {
    var address = clean((row.querySelector('[data-route-point-input]') || {}).value);
    ['loading', 'unloading'].forEach(function (type) {
      var legacy = row.querySelector('[data-point-legacy="' + type + '"]');
      var active = !!row.querySelector('[data-point-toggle="' + type + '"].is-active');
      if (legacy) legacy.value = active ? address : '';
    });
  }

  function createPointRow(point) {
    var row = document.createElement('div');
    row.className = 'linear-trip-route-point-row linear-trip-route-point-row--combined';
    row.setAttribute('data-route-point-row', '');

    row.innerHTML = '' +
      '<div class="linear-trip-route-point-index" data-route-point-number></div>' +
      '<div class="linear-trip-route-point-control">' +
        '<input type="text" class="field-input linear-trip-route-point-input" data-route-point-input autocomplete="off" placeholder="Адрес или место загрузки / выгрузки">' +
        '<div class="linear-trip-route-point-suffixes" role="group" aria-label="Тип операции">' +
          '<button type="button" class="linear-trip-route-point-toggle" data-point-toggle="loading" aria-pressed="false">Загрузка</button>' +
          '<button type="button" class="linear-trip-route-point-toggle" data-point-toggle="unloading" aria-pressed="false">Выгрузка</button>' +
        '</div>' +
      '</div>' +
      '<input type="hidden" value="1" data-point-flag="loading">' +
      '<input type="hidden" value="1" data-point-flag="unloading">' +
      '<input type="hidden" value="" data-point-legacy="loading">' +
      '<input type="hidden" value="" data-point-legacy="unloading">' +
      '<button type="button" class="linear-trip-route-point-remove" data-remove-route-point aria-label="Удалить точку">×</button>';

    var addressInput = row.querySelector('[data-route-point-input]');
    addressInput.value = point.address || '';
    addressInput.addEventListener('input', function () { syncLegacyProjection(row); });

    ['loading', 'unloading'].forEach(function (type) {
      var button = row.querySelector('[data-point-toggle="' + type + '"]');
      var hidden = row.querySelector('[data-point-flag="' + type + '"]');
      setToggleState(button, hidden, !!point[type]);
      button.addEventListener('click', function () {
        setToggleState(button, hidden, !button.classList.contains('is-active'));
        row.classList.remove('is-operation-error');
        syncLegacyProjection(row);
      });
    });
    syncLegacyProjection(row);
    return row;
  }

  function normalizeCombinedIndexes(group) {
    var rows = Array.from(group.querySelectorAll('[data-route-point-row]'));
    rows.forEach(function (row, index) {
      var input = row.querySelector('[data-route-point-input]');
      if (input) input.name = 'route_points[points][' + index + '][address]';
      ['loading', 'unloading'].forEach(function (type) {
        var hidden = row.querySelector('[data-point-flag="' + type + '"]');
        if (hidden) hidden.name = 'route_points[points][' + index + '][' + type + ']';
        var legacy = row.querySelector('[data-point-legacy="' + type + '"]');
        if (legacy) legacy.name = 'route_points[' + type + '][]';
      });
      syncLegacyProjection(row);
      var number = row.querySelector('[data-route-point-number]');
      if (number) number.textContent = String(index + 1);
      var remove = row.querySelector('[data-remove-route-point]');
      if (remove) remove.classList.toggle('is-hidden', rows.length <= 2);
    });
  }

  function buildCombinedGroup(form, points) {
    var wrap = form.querySelector('.linear-trip-route-points-wrap');
    if (!wrap) return;

    var oldMessages = Array.from(wrap.querySelectorAll('.linear-trip-route-point-msg')).map(function (el) { return clean(el.textContent); }).filter(Boolean);
    wrap.innerHTML = '';
    wrap.classList.add('linear-trip-route-points-wrap--combined');

    var group = document.createElement('div');
    group.className = 'linear-trip-route-point-group linear-trip-route-point-group--combined';
    group.setAttribute('data-route-point-combined-group', '');
    group.innerHTML = '' +
      '<div class="linear-trip-route-point-head">' +
        '<div class="linear-trip-route-point-title">Загрузка / выгрузка <span class="req">*</span></div>' +
        '<button type="button" class="linear-trip-route-point-add" data-add-route-point>+ Добавить точку</button>' +
      '</div>' +
      '<div data-route-point-list></div>' +
      '<div class="linear-trip-route-point-msg" data-route-point-message></div>';
    wrap.appendChild(group);

    var list = group.querySelector('[data-route-point-list]');
    points.forEach(function (point) { list.appendChild(createPointRow(point)); });
    group.querySelector('[data-route-point-message]').textContent = oldMessages.join(' ');
    normalizeCombinedIndexes(group);

    group.querySelector('[data-add-route-point]').addEventListener('click', function () {
      var row = createPointRow({address: '', loading: false, unloading: false});
      list.appendChild(row);
      normalizeCombinedIndexes(group);
      var input = row.querySelector('[data-route-point-input]');
      if (input) input.focus();
    });

    group.addEventListener('click', function (event) {
      var remove = event.target.closest('[data-remove-route-point]');
      if (!remove) return;
      var rows = group.querySelectorAll('[data-route-point-row]');
      if (rows.length <= 2) return;
      var row = remove.closest('[data-route-point-row]');
      if (row) row.remove();
      normalizeCombinedIndexes(group);
    });

    form.addEventListener('submit', function (event) {
      var invalid = null;
      group.querySelectorAll('[data-route-point-row]').forEach(function (row) {
        syncLegacyProjection(row);
        var address = clean((row.querySelector('[data-route-point-input]') || {}).value);
        var loading = row.querySelector('[data-point-toggle="loading"].is-active');
        var unloading = row.querySelector('[data-point-toggle="unloading"].is-active');
        row.classList.toggle('is-operation-error', !!address && !loading && !unloading);
        if (!invalid && address && !loading && !unloading) invalid = row;
      });
      if (invalid) {
        event.preventDefault();
        var message = group.querySelector('[data-route-point-message]');
        if (message) message.textContent = 'Для каждой заполненной точки выберите загрузку, выгрузку или обе операции.';
        invalid.scrollIntoView({block: 'nearest'});
      }
    });
  }

  function initForm(form) {
    if (!form || form.dataset.routePointsReady === '1') return;
    form.dataset.routePointsReady = '1';
    var points = legacyPoints(form);
    if (points) buildCombinedGroup(form, points);
  }

  function extractViewAddresses(row) {
    if (!row) return [];
    return Array.from(row.querySelectorAll('.trip-view-point')).map(function (point) {
      var spans = point.querySelectorAll('span');
      return clean(spans.length ? spans[spans.length - 1].textContent : point.textContent);
    });
  }

  function combineAligned(loading, unloading) {
    var max = Math.max(loading.length, unloading.length);
    var points = [];
    for (var i = 0; i < max; i++) {
      var a = loading[i] || '';
      var b = unloading[i] || '';
      if (a && b && a === b) {
        points.push({address: a, loading: true, unloading: true});
      } else {
        if (a) points.push({address: a, loading: true, unloading: false});
        if (b) points.push({address: b, loading: false, unloading: true});
      }
    }
    return points;
  }

  function polishView(scope) {
    var table = scope.querySelector ? scope.querySelector('.trip-view-table') : null;
    if (!table || table.dataset.routePointsCombined === '1') return;
    var rows = Array.from(table.querySelectorAll(':scope > tbody > tr'));
    var loadingRow = null;
    var unloadingRow = null;
    rows.forEach(function (row) {
      var label = clean((row.cells && row.cells[0]) ? row.cells[0].textContent : '');
      if (label === 'Загрузка') loadingRow = row;
      if (label === 'Выгрузка') unloadingRow = row;
    });
    if (!loadingRow || !unloadingRow) return;

    var points = combineAligned(extractViewAddresses(loadingRow), extractViewAddresses(unloadingRow));
    loadingRow.cells[0].textContent = 'Загрузка / выгрузка';
    var valueCell = loadingRow.cells[1];
    valueCell.innerHTML = '';
    var list = document.createElement('div');
    list.className = 'trip-view-route-points-combined';
    points.forEach(function (point, index) {
      var item = document.createElement('div');
      item.className = 'trip-view-route-point-combined';
      var tags = '';
      if (point.loading) tags += '<span class="trip-view-operation-tag is-loading">Загрузка</span>';
      if (point.unloading) tags += '<span class="trip-view-operation-tag is-unloading">Выгрузка</span>';
      item.innerHTML = '<span class="trip-view-point-num">' + (index + 1) + '.</span><span class="trip-view-point-address"></span><span class="trip-view-operation-tags">' + tags + '</span>';
      item.querySelector('.trip-view-point-address').textContent = point.address;
      list.appendChild(item);
    });
    if (!points.length) valueCell.textContent = '—';
    else valueCell.appendChild(list);
    unloadingRow.remove();
    table.dataset.routePointsCombined = '1';
  }

  function syncViewTitle(root) {
    var scope = root || document;
    var marker = scope.querySelector ? scope.querySelector('[data-trip-view-title]') : null;
    if (!marker) return;
    var title = marker.getAttribute('data-trip-view-title') || '';
    if (!title) return;
    var modal = marker.closest('.modal') || marker.closest('.modal-overlay');
    var titleNode = modal ? modal.querySelector('.modal-title') : null;
    if (titleNode) titleNode.textContent = title;
  }

  function scan(root) {
    var scope = root || document;
    if (scope.querySelectorAll) scope.querySelectorAll('[data-linear-trip-form]').forEach(initForm);
    syncViewTitle(scope);
    polishView(scope);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { scan(document); });
  else scan(document);

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
