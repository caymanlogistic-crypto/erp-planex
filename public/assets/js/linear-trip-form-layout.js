(() => {
  const init = () => {
    const modal = document.getElementById('linear-trip-create-modal');
    if (!modal || modal.dataset.p30LayoutReady === '1') return;
    const form = modal.querySelector('[data-linear-trip-form]');
    if (!form) return;
    modal.dataset.p30LayoutReady = '1';

    const routeType = form.querySelector('[data-linear-trip-route-type]');
    const date = form.querySelector('[name="planned_loading_date"]')?.closest('.field');
    const client = form.querySelector('[name="client_id"]')?.closest('.field');
    const executor = form.querySelector('[name="route_executor_id"]')?.closest('.field');
    if (!routeType || !date || !client || !executor) return;

    const routeTypeField = routeType.closest('.field');
    routeTypeField?.classList.add('is-hidden');
    if (!routeType.value) routeType.value = 'linear';

    let mainRow = form.querySelector('.linear-trip-primary-row');
    if (!mainRow) {
      mainRow = document.createElement('div');
      mainRow.className = 'field-row field-row-group linear-trip-primary-row';
      routeTypeField?.closest('.linear-trip-row--compact')?.before(mainRow);
      mainRow.append(date, client, executor);

      const toggle = document.createElement('label');
      toggle.className = 'linear-trip-agency-toggle';
      toggle.innerHTML = '<input type="checkbox" data-linear-trip-agency-toggle> <span>Агентский договор</span>';
      mainRow.after(toggle);
    }

    const checkbox = form.querySelector('[data-linear-trip-agency-toggle]');
    if (!checkbox) return;
    checkbox.checked = routeType.value === 'agency';

    const applyAgency = () => {
      const agency = checkbox.checked;
      routeType.value = agency ? 'agency' : 'linear';
      routeType.dispatchEvent(new Event('change', { bubbles: true }));
      form.querySelectorAll('.is-agency-only').forEach((el) => el.classList.toggle('is-hidden', !agency));
    };
    checkbox.addEventListener('change', applyAgency);
    applyAgency();

    const formatAmount = (input) => {
      const digits = String(input.value || '').replace(/\D/g, '');
      input.value = digits.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    };
    form.querySelectorAll('input[name$="[amount]"]').forEach((input) => {
      formatAmount(input);
      input.addEventListener('input', () => formatAmount(input));
    });

    const observer = new MutationObserver(() => {
      form.querySelectorAll('input[name$="[amount]"]').forEach((input) => {
        if (input.dataset.p30Money === '1') return;
        input.dataset.p30Money = '1';
        formatAmount(input);
        input.addEventListener('input', () => formatAmount(input));
      });
    });
    observer.observe(form, { childList: true, subtree: true });
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();