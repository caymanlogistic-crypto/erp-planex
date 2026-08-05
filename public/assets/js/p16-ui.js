'use strict';

(() => {
    const submitting = new WeakSet();

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.dataset.allowDuplicateSubmit === 'true') return;
        if (submitting.has(form)) {
            event.preventDefault();
            return;
        }
        submitting.add(form);
        document.body.classList.add('p16-is-submitting');
        const buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        buttons.forEach((button) => {
            button.dataset.p16OriginalDisabled = button.disabled ? '1' : '0';
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        });
        window.setTimeout(() => {
            submitting.delete(form);
            document.body.classList.remove('p16-is-submitting');
            buttons.forEach((button) => {
                if (button.dataset.p16OriginalDisabled !== '1') button.disabled = false;
                button.removeAttribute('aria-busy');
                delete button.dataset.p16OriginalDisabled;
            });
        }, 12000);
    }, true);

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        const visibleDialog = [...document.querySelectorAll('[role="dialog"], dialog, .modal-overlay.is-open, .modal.is-open')]
            .reverse()
            .find((element) => element instanceof HTMLElement && element.offsetParent !== null);
        if (!visibleDialog) return;
        const closeButton = visibleDialog.querySelector('[data-modal-close], [data-close], .modal-close, button[aria-label*="Закры"], button[aria-label*="Close"]');
        if (closeButton instanceof HTMLElement) closeButton.click();
    });

    const enhanceTable = (table) => {
        if (!(table instanceof HTMLTableElement) || table.dataset.p16Enhanced === '1') return;
        table.dataset.p16Enhanced = '1';
        table.querySelectorAll('td').forEach((cell) => {
            const text = (cell.textContent || '').trim();
            if (text.length > 70 && !cell.title) {
                cell.title = text;
                cell.setAttribute('data-p16-truncate', '');
            }
        });
    };

    const enhance = (root = document) => {
        root.querySelectorAll('table').forEach(enhanceTable);
        root.querySelectorAll('button, a, input, select, textarea').forEach((element) => {
            if (!(element instanceof HTMLElement)) return;
            if (!element.hasAttribute('aria-label') && element.classList.contains('icon-button')) {
                const title = element.getAttribute('title');
                if (title) element.setAttribute('aria-label', title);
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => enhance());
    } else {
        enhance();
    }

    new MutationObserver((records) => {
        records.forEach((record) => record.addedNodes.forEach((node) => {
            if (node instanceof Element) enhance(node);
        }));
    }).observe(document.documentElement, { childList: true, subtree: true });
})();
