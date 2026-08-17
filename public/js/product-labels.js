(() => {
    const modalElement = document.getElementById('productLabelModal');
    if (!modalElement || !window.bootstrap) return;

    const modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
    const form = modalElement.querySelector('[data-label-print-form]');
    const rows = modalElement.querySelector('[data-label-selection-rows]');
    const summary = modalElement.querySelector('[data-label-selection-summary]');
    const message = modalElement.querySelector('[data-label-print-message]');
    const hiddenFields = modalElement.querySelector('[data-label-hidden-fields]');
    const priceType = modalElement.querySelector('[data-label-price-type]');
    const formatInput = modalElement.querySelector('[data-label-format-input]');
    const selectAll = document.querySelector('[data-label-select-all]');
    const selectionButton = document.querySelector('[data-label-print-selected]');
    const selectedCount = document.querySelector('[data-label-selected-count]');
    const selected = new Map();

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
    }[character]));

    const money = (value) => `Rs ${Number(value || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

    const productFromControl = (control) => ({
        id: String(control.dataset.labelProductId),
        name: control.dataset.labelProductName || 'Product',
        barcode: control.dataset.labelProductBarcode || '',
        sku: control.dataset.labelProductSku || '',
        retail: Number(control.dataset.labelProductRetail || 0),
        wholesale: Number(control.dataset.labelProductWholesale || 0),
        quantity: selected.get(String(control.dataset.labelProductId))?.quantity || 1,
    });

    const labelPrice = (product) => priceType.value === 'retail'
        ? product.retail
        : (priceType.value === 'wholesale' ? product.wholesale : null);

    const setMessage = (text = '') => {
        message.textContent = text;
        message.classList.toggle('d-none', !text);
    };

    const updateTableCheckboxes = () => {
        document.querySelectorAll('[data-label-select]').forEach((control) => {
            control.checked = selected.has(String(control.dataset.labelProductId));
        });
        const visible = [...document.querySelectorAll('[data-label-select]')];
        if (selectAll) {
            selectAll.checked = visible.length > 0 && visible.every((control) => control.checked);
            selectAll.indeterminate = visible.some((control) => control.checked) && !selectAll.checked;
        }
        if (selectedCount) {
            selectedCount.textContent = `(${selected.size})`;
            selectedCount.classList.toggle('d-none', selected.size === 0);
        }
    };

    const refreshSummary = () => {
        const quantityInputs = [...rows.querySelectorAll('[data-label-quantity]')];
        const total = quantityInputs.reduce((sum, input) => sum + (Number(input.value) || 0), 0);
        summary.textContent = `${selected.size} ${selected.size === 1 ? 'product' : 'products'} · ${total} ${total === 1 ? 'label' : 'labels'}`;
    };

    const renderRows = () => {
        const selectedProducts = [...selected.values()];
        rows.innerHTML = selectedProducts.map((product) => {
            const price = labelPrice(product);
            const unavailable = !product.barcode || (priceType.value !== 'none' && price <= 0);
            const warning = !product.barcode
                ? 'Barcode not available'
                : (priceType.value !== 'none' && price <= 0 ? `${priceType.value === 'retail' ? 'Retail' : 'Wholesale'} price not set` : '');

            return `<tr data-label-row="${product.id}">
                <td><strong class="d-block">${escapeHtml(product.name)}</strong>${warning ? `<span class="text-danger small">${warning}</span>` : ''}</td>
                <td class="text-nowrap">${product.barcode ? escapeHtml(product.barcode) : '<span class="tf-muted">—</span>'}</td>
                <td class="text-nowrap">${money(product.retail)}</td>
                <td class="text-nowrap">${money(product.wholesale)}</td>
                <td><input class="form-control form-control-sm tf-label-quantity-input ${unavailable ? 'is-invalid' : ''}" type="number" min="1" max="500" step="1" inputmode="numeric" value="${product.quantity}" data-label-quantity data-label-id="${product.id}" aria-label="Print quantity for ${escapeHtml(product.name)}"></td>
                <td class="text-end"><button class="btn btn-sm btn-link text-danger p-0" type="button" data-label-remove="${product.id}" aria-label="Remove ${escapeHtml(product.name)}"><i class="bi bi-x-lg"></i></button></td>
            </tr>`;
        }).join('') || '<tr><td colspan="6" class="text-center tf-muted py-3">No products selected.</td></tr>';
        refreshSummary();
    };

    const openSelection = () => {
        if (selected.size === 0) {
            setMessage('Select at least one product.');
            modal.show();
            return;
        }
        setMessage();
        renderRows();
        modal.show();
    };

    document.querySelectorAll('[data-label-select]').forEach((control) => control.addEventListener('change', () => {
        const product = productFromControl(control);
        if (control.checked) selected.set(product.id, product);
        else selected.delete(product.id);
        updateTableCheckboxes();
    }));

    selectAll?.addEventListener('change', () => {
        document.querySelectorAll('[data-label-select]').forEach((control) => {
            const product = productFromControl(control);
            if (selectAll.checked) selected.set(product.id, product);
            else selected.delete(product.id);
        });
        updateTableCheckboxes();
    });

    selectionButton?.addEventListener('click', openSelection);

    document.querySelectorAll('[data-label-print-row]').forEach((button) => button.addEventListener('click', () => {
        const checkbox = [...document.querySelectorAll('[data-label-select]')]
            .find((control) => String(control.dataset.labelProductId) === String(button.dataset.labelProductId));
        if (!checkbox) return;
        const product = productFromControl(checkbox);
        selected.clear();
        selected.set(product.id, product);
        updateTableCheckboxes();
        openSelection();
    }));

    priceType.addEventListener('change', () => {
        setMessage();
        renderRows();
    });

    modalElement.querySelectorAll('[data-label-format-option]').forEach((button) => button.addEventListener('click', () => {
        const format = button.dataset.labelFormatOption;
        formatInput.value = format;
        modalElement.querySelectorAll('[data-label-format-option]').forEach((option) => {
            const active = option.dataset.labelFormatOption === format;
            option.classList.toggle('btn-tf-primary', active);
            option.classList.toggle('btn-outline-primary', !active);
            option.setAttribute('aria-pressed', String(active));
        });
    }));

    rows.addEventListener('input', (event) => {
        const input = event.target.closest('[data-label-quantity]');
        if (!input) return;
        const product = selected.get(String(input.dataset.labelId));
        if (!product) return;
        product.quantity = Math.min(500, Math.max(1, Math.floor(Number(input.value) || 1)));
        refreshSummary();
    });

    rows.addEventListener('change', (event) => {
        const input = event.target.closest('[data-label-quantity]');
        if (!input) return;
        const product = selected.get(String(input.dataset.labelId));
        if (!product) return;
        input.value = product.quantity;
    });

    rows.addEventListener('click', (event) => {
        const button = event.target.closest('[data-label-remove]');
        if (!button) return;
        selected.delete(String(button.dataset.labelRemove));
        updateTableCheckboxes();
        renderRows();
    });

    form.addEventListener('submit', (event) => {
        const products = [...selected.values()];
        const invalid = products.find((product) => !product.barcode || (priceType.value !== 'none' && labelPrice(product) <= 0));
        const total = products.reduce((sum, product) => sum + (Number(product.quantity) || 0), 0);
        if (products.length === 0 || invalid || total > 2000) {
            event.preventDefault();
            setMessage(total > 2000
                ? 'A preview can contain at most 2,000 labels. Reduce the print quantities.'
                : (invalid ? `Remove ${invalid.name} or choose a valid barcode and price option before continuing.` : 'Select at least one product.'));
            return;
        }

        hiddenFields.innerHTML = products.map((product, index) => `
            <input type="hidden" name="products[${index}][id]" value="${escapeHtml(product.id)}">
            <input type="hidden" name="products[${index}][quantity]" value="${escapeHtml(product.quantity)}">
        `).join('');
    });

    updateTableCheckboxes();
})();
