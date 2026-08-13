(function () {
  'use strict';

  function init(form) {
    if (!form || form.id !== 'linear-trip-edit-form' || form.dataset.tripDocumentDeleteReady === '1') return;
    form.dataset.tripDocumentDeleteReady = '1';

    var docs = form.querySelector('.linear-trip-layout-docs');
    if (!docs) return;

    docs.querySelectorAll('.document-file-row.has-existing-file').forEach(function (row) {
      if (row.querySelector('[data-linear-trip-document-delete]')) return;
      var link = row.querySelector('a[href*="/company/documents/view?id="]');
      if (!link) return;
      var url = new URL(link.href, window.location.href);
      var id = parseInt(url.searchParams.get('id') || '0', 10) || 0;
      if (!id) return;

      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'file-remove predef-file-clear';
      button.title = 'Удалить документ';
      button.textContent = '×';
      button.setAttribute('data-linear-trip-document-delete', String(id));
      row.appendChild(button);
    });
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-linear-trip-document-delete]');
    if (!button) return;
    var form = button.closest('#linear-trip-edit-form');
    if (!form) return;
    var id = parseInt(button.getAttribute('data-linear-trip-document-delete') || '0', 10) || 0;
    if (!id || !window.confirm('Удалить документ?')) return;

    button.disabled = true;
    var body = new URLSearchParams();
    body.set('id', String(id));
    body.set('entity_type', 'linear_route');

    window.fetch((window.getErpBasePath ? window.getErpBasePath() : '') + '/company/documents/delete', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
      body: body.toString()
    }).then(function (response) {
      if (!response.ok) throw new Error('HTTP ' + response.status);
      var row = button.closest('.document-file-row');
      if (row) row.remove();
    }).catch(function () {
      button.disabled = false;
      window.alert('Не удалось удалить документ. Обновите форму и попробуйте ещё раз.');
    });
  });

  function scan(root) {
    if (!root || !root.querySelectorAll) return;
    if (root.matches && root.matches('#linear-trip-edit-form')) init(root);
    root.querySelectorAll('#linear-trip-edit-form').forEach(init);
  }

  scan(document);
  new MutationObserver(function (records) {
    records.forEach(function (record) {
      record.addedNodes.forEach(function (node) {
        if (node.nodeType === 1) scan(node);
      });
    });
  }).observe(document.body, {childList: true, subtree: true});
}());
