(() => {
  function renumber(form) {
    form.querySelectorAll('[data-crew-driver-row]').forEach((row, index) => {
      const n = row.querySelector('.crew-driver-number');
      if (n) n.textContent = `Водитель ${index + 1}`;
      const remove = row.querySelector('[data-remove-crew-driver]');
      if (remove) remove.hidden = index === 0;
    });
  }

  function selectedValues(form) {
    return Array.from(form.querySelectorAll('select[name="driver_ids[]"]')).map(s => s.value).filter(Boolean);
  }

  document.addEventListener('click', (event) => {
    const add = event.target.closest('[data-add-crew-driver]');
    if (add) {
      const form = add.closest('[data-crew-driver-form]');
      const list = form?.querySelector('[data-crew-driver-list]');
      const first = list?.querySelector('[data-crew-driver-row]');
      if (!form || !list || !first) return;
      const row = first.cloneNode(true);
      const select = row.querySelector('select[name="driver_ids[]"]');
      if (select) select.value = '';
      let remove = row.querySelector('[data-remove-crew-driver]');
      if (!remove) {
        remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-ghost crew-driver-remove';
        remove.setAttribute('data-remove-crew-driver', '');
        remove.textContent = 'Удалить';
        row.appendChild(remove);
      }
      remove.hidden = false;
      list.appendChild(row);
      renumber(form);
      select?.focus();
      return;
    }

    const remove = event.target.closest('[data-remove-crew-driver]');
    if (remove) {
      const form = remove.closest('[data-crew-driver-form]');
      const rows = form?.querySelectorAll('[data-crew-driver-row]') || [];
      if (rows.length <= 1) return;
      remove.closest('[data-crew-driver-row]')?.remove();
      if (form) renumber(form);
    }
  });

  document.addEventListener('change', (event) => {
    const select = event.target.closest('select[name="driver_ids[]"]');
    if (!select) return;
    const form = select.closest('[data-crew-driver-form]');
    if (!form || !select.value) return;
    const values = selectedValues(form);
    if (values.filter(v => v === select.value).length > 1) {
      select.value = '';
      select.setCustomValidity('Этот водитель уже добавлен в экипаж.');
      select.reportValidity();
      setTimeout(() => select.setCustomValidity(''), 0);
    }
  });
})();
