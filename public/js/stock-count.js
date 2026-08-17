(() => {
    const form = document.querySelector('[data-stock-count-form]');
    if (!form) return;

    const refreshRow = (row) => {
        const system = Number(row.querySelector('[data-system-qty]')?.dataset.systemQty || 0);
        const input = row.querySelector('[data-stock-count-physical]');
        const output = row.querySelector('[data-stock-count-variance]');
        const reason = row.querySelector('[data-stock-count-reason]');
        if (!input || !output) return;
        if (input.value === '') {
            output.textContent = '—';
            output.classList.remove('text-danger', 'text-primary', 'text-success');
            return;
        }
        const variance = Number(input.value) - system;
        output.textContent = Number.isFinite(variance) ? variance.toFixed(3).replace(/\.?(0+)$/, '') : '—';
        output.classList.toggle('text-danger', variance < 0);
        output.classList.toggle('text-primary', variance > 0);
        output.classList.toggle('text-success', variance === 0);
        reason.required = variance !== 0;
    };

    form.querySelectorAll('tbody tr').forEach(refreshRow);
    form.addEventListener('input', (event) => {
        if (event.target.matches('[data-stock-count-physical]')) refreshRow(event.target.closest('tr'));
    });

    const productSelect = document.querySelector('[name="product_id"]');
    const categoryFilter = document.querySelector('[data-stock-count-category-filter]');
    const unitFilter = document.querySelector('[data-stock-count-unit-filter]');
    const filterProducts = () => {
        if (!productSelect) return;
        [...productSelect.options].forEach((option) => {
            if (!option.value) return;
            option.hidden = Boolean(categoryFilter?.value && option.dataset.category !== categoryFilter.value)
                || Boolean(unitFilter?.value && option.dataset.unit !== unitFilter.value);
        });
        if (productSelect.selectedOptions[0]?.hidden) productSelect.value = '';
    };
    categoryFilter?.addEventListener('change', filterProducts);
    unitFilter?.addEventListener('change', filterProducts);
})();
