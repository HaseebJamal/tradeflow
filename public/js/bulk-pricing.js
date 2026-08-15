(() => {
    const form = document.querySelector('[data-bulk-pricing-form]'); if (!form) return;
    const money = (v) => Number.parseFloat(v || 0); const format = (v) => `Rs ${money(v).toFixed(2)}`;
    const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' })[character]);
    const rows = () => [...form.querySelectorAll('[data-pricing-row]')];
    const inputs = (row) => ({ retail: row.querySelector('[data-pricing-input="retail"]'), wholesale: row.querySelector('[data-pricing-input="wholesale"]') });
    const changed = (row) => { const value = inputs(row); return ['retail', 'wholesale'].filter(k => money(value[k].value) !== money(row.dataset[`old${k[0].toUpperCase()}${k.slice(1)}`])); };
    const targetFields = () => { const v = form.querySelector('[data-pricing-target]').value; return v === 'both' ? ['retail', 'wholesale'] : [v]; };
    const categoryName = (row) => row.cells[2]?.textContent.trim() || 'Uncategorized';
    const message = (text, error = false) => { const e = form.querySelector('[data-markup-message]'); e.textContent = text; e.classList.toggle('text-danger', error); };
    const refresh = (row) => { const purchase = row.dataset.purchase === '' ? null : money(row.dataset.purchase); const value = inputs(row); let invalid = false, empty = false; ['retail','wholesale'].forEach(k => { const price = money(value[k].value), bad = purchase && purchase > 0 && price <= purchase, error = row.querySelector(`[data-price-error="${k}"]`); value[k].classList.toggle('is-invalid', Boolean(bad)); error.classList.toggle('d-none', !bad); error.textContent = bad ? 'Must be greater than purchase price.' : ''; row.querySelector(`[data-margin="${k}"]`).textContent = purchase && purchase > 0 ? `${((price - purchase) / purchase * 100).toFixed(1)}%` : '—'; invalid ||= Boolean(bad); empty ||= price <= 0; }); row.querySelector('[data-pricing-status]').innerHTML = empty ? '<span class="tf-badge tf-badge-warning">Needs Pricing</span>' : invalid ? '<span class="tf-badge tf-badge-danger">Pricing Attention</span>' : '<span class="tf-badge tf-badge-success">Valid Pricing</span>'; };
    const label = (key) => `${key === 'retail' ? 'Retail' : 'Wholesale'} ${form.querySelector('[data-pricing-type]').value === 'percentage' ? 'Markup %' : 'Profit Amount'}`;
    const categoryArea = form.querySelector('[data-category-pricing]'); const categoryBody = form.querySelector('[data-category-pricing-rows]');
    const selected = () => rows().filter(row => row.querySelector('[data-pricing-select]').checked);
    const rebuildCategories = () => { const groups = new Map(); selected().forEach(row => { const name = categoryName(row); groups.set(name, [...(groups.get(name) || []), row]); }); categoryBody.innerHTML = [...groups].map(([name, members]) => `<tr data-category-rule="${escapeHtml(name)}"><td>${escapeHtml(name)}</td><td>${members.length}</td><td data-category-cell="retail"><input class="form-control form-control-sm" type="number" min="0" max="1000" step="0.01" data-category-markup="retail" placeholder="0"></td><td data-category-cell="wholesale"><input class="form-control form-control-sm" type="number" min="0" max="1000" step="0.01" data-category-markup="wholesale" placeholder="0"></td></tr>`).join(''); refreshControls(); };
    const refreshControls = () => { const mode = form.querySelector('[data-pricing-mode]').value, fields = targetFields(); form.querySelector('[data-selected-markup-controls]').classList.toggle('d-none', mode === 'category'); categoryArea.classList.toggle('d-none', mode !== 'category'); ['retail','wholesale'].forEach(k => { const visible = fields.includes(k); const field = form.querySelector(`[data-markup-field="${k}"]`); field.hidden = !visible; const input = form.querySelector(`[data-pricing-markup="${k}"]`); input.disabled = !visible; if (!visible) input.value = ''; form.querySelector(`[data-markup-label="${k}"]`).textContent = label(k); form.querySelector(`[data-category-heading="${k}"]`).hidden = !visible; categoryBody.querySelectorAll(`[data-category-cell="${k}"]`).forEach(cell => { cell.hidden = !visible; cell.querySelector('input').disabled = !visible; }); }); };
    const apply = () => { const list = selected(); if (!list.length) return message(form.querySelector('[data-pricing-mode]').value === 'category' ? 'Select at least one product to configure category pricing.' : 'Select at least one product.', true); const mode = form.querySelector('[data-pricing-mode]').value, type = form.querySelector('[data-pricing-type]').value, fields = targetFields(); if (mode === 'category' && !categoryBody.children.length) rebuildCategories(); let skipped = 0, malformed = false; list.forEach(row => { const purchase = money(row.dataset.purchase); if (purchase <= 0) { skipped++; return; } const value = inputs(row); fields.forEach(k => { const source = mode === 'category' ? categoryBody.querySelector(`[data-category-rule="${CSS.escape(categoryName(row))}"] [data-category-markup="${k}"]`) : form.querySelector(`[data-pricing-markup="${k}"]`); const amount = money(source?.value); if (!source || source.value === '' || !Number.isFinite(amount) || amount < 0 || amount > 1000) { malformed = true; return; } value[k].value = (purchase + (type === 'percentage' ? purchase * amount / 100 : amount)).toFixed(2); }); refresh(row); }); if (malformed) return message('Enter a numeric markup from 0 to 1000 for every visible pricing field.', true); message(skipped ? `${skipped} selected product${skipped === 1 ? ' was' : 's were'} skipped because no Purchase Price is available.` : 'Pricing applied to the selected products.'); };
    rows().forEach(row => { refresh(row); Object.values(inputs(row)).forEach(input => input.addEventListener('input', () => refresh(row))); row.querySelector('[data-pricing-select]').addEventListener('change', () => { if (form.querySelector('[data-pricing-mode]').value === 'category') rebuildCategories(); }); });
    form.querySelector('[data-pricing-select-all]')?.addEventListener('change', e => { rows().forEach(row => row.querySelector('[data-pricing-select]').checked = e.target.checked); if (form.querySelector('[data-pricing-mode]').value === 'category') rebuildCategories(); });
    form.querySelector('[data-pricing-mode]').addEventListener('change', () => { if (form.querySelector('[data-pricing-mode]').value === 'category') rebuildCategories(); refreshControls(); }); form.querySelector('[data-pricing-target]').addEventListener('change', refreshControls); form.querySelector('[data-pricing-type]').addEventListener('change', refreshControls); form.querySelector('[data-apply-markup]').addEventListener('click', apply); refreshControls();
    const preview = document.getElementById('bulkPricingPreviewModal'); form.addEventListener('submit', e => { e.preventDefault(); const list = rows().filter(row => changed(row).length); if (!list.length) return message('Change at least one selling price before saving.', true); preview.querySelector('[data-pricing-preview]').innerHTML = list.map(row => { const value = inputs(row), fields = changed(row); return `<tr><td>${escapeHtml(categoryName(row))}<br><strong>${escapeHtml(row.dataset.product)}</strong></td><td>${row.dataset.purchase === '' ? '—' : format(row.dataset.purchase)}</td><td>${fields.includes('retail') ? `${format(row.dataset.oldRetail)} → ${format(value.retail.value)}` : 'Unchanged'}</td><td>${fields.includes('wholesale') ? `${format(row.dataset.oldWholesale)} → ${format(value.wholesale.value)}` : 'Unchanged'}</td></tr>`; }).join(''); window.bootstrap?.Modal.getOrCreateInstance(preview).show(); });
    preview?.querySelector('[data-apply-pricing]').addEventListener('click', () => { const button = preview.querySelector('[data-apply-pricing]'); button.disabled = true; button.textContent = 'Saving...'; rows().forEach(row => { const value = inputs(row), fields = changed(row), id = row.querySelector('input[type="hidden"]'); if (!fields.length) { id.disabled = value.retail.disabled = value.wholesale.disabled = true; } else { value.retail.disabled = !fields.includes('retail'); value.wholesale.disabled = !fields.includes('wholesale'); } }); form.submit(); });
})();

// Keep category product detail compact. This only toggles visibility: the
// product inputs remain the single source of pricing state in the main grid.
(() => {
    const form = document.querySelector('[data-bulk-pricing-form]');
    const body = form?.querySelector('[data-category-pricing-rows]');
    if (!form || !body) return;
    const pricingRows = () => [...form.querySelectorAll('[data-pricing-row]')];
    const categoryName = (row) => row.cells[2]?.textContent.trim() || 'Uncategorized';
    const refreshHeaders = () => body.querySelectorAll('[data-category-rule]').forEach((rule) => {
        const category = rule.dataset.categoryRule;
        const members = pricingRows().filter((row) => row.querySelector('[data-pricing-select]')?.checked && categoryName(row) === category);
        const changes = members.filter((row) => {
            const retail = row.querySelector('[data-pricing-input="retail"]'); const wholesale = row.querySelector('[data-pricing-input="wholesale"]');
            return Number(retail.value) !== Number(row.dataset.oldRetail) || Number(wholesale.value) !== Number(row.dataset.oldWholesale);
        }).length;
        const errors = members.filter((row) => row.querySelector('.is-invalid')).length;
        const indicator = rule.querySelector('[data-category-indicator]');
        if (indicator) indicator.innerHTML = errors ? '<span class="tf-badge tf-badge-danger">'+errors+' pricing error'+(errors === 1 ? '' : 's')+'</span>' : changes ? '<span class="tf-badge tf-badge-info">'+changes+' change'+(changes === 1 ? '' : 's')+'</span>' : '';
    });
    const decorate = () => {
        body.querySelectorAll('[data-category-rule]').forEach((rule) => {
            const detail = rule.nextElementSibling;
            if (!detail?.classList.contains('tf-category-product-detail') || rule.dataset.accordionReady === '1') return;
            rule.dataset.accordionReady = '1'; detail.hidden = true;
            const titleCell = rule.cells[0]; const name = titleCell.textContent.trim(); titleCell.textContent = '';
            const button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-link p-0 text-decoration-none fw-semibold text-start'; button.dataset.categoryToggle = '1'; button.setAttribute('aria-expanded', 'false'); button.innerHTML = '<i class="bi bi-chevron-right me-2"></i>'+name;
            const indicator = document.createElement('span'); indicator.className = 'ms-2'; indicator.dataset.categoryIndicator = '1'; titleCell.append(button, indicator);
            button.addEventListener('click', () => { const open = detail.hidden; body.querySelectorAll('.tf-category-product-detail').forEach((item) => { item.hidden = true; const previous = item.previousElementSibling?.querySelector('[data-category-toggle]'); previous?.setAttribute('aria-expanded', 'false'); previous?.querySelector('i')?.classList.replace('bi-chevron-down', 'bi-chevron-right'); }); detail.hidden = !open; button.setAttribute('aria-expanded', String(open)); button.querySelector('i')?.classList.replace(open ? 'bi-chevron-right' : 'bi-chevron-down', open ? 'bi-chevron-down' : 'bi-chevron-right'); });
        });
        refreshHeaders();
    };
    new MutationObserver(() => requestAnimationFrame(decorate)).observe(body, { childList: true });
    form.querySelectorAll('[data-pricing-input], [data-pricing-select]').forEach((input) => input.addEventListener('input', refreshHeaders));
    form.querySelectorAll('[data-pricing-select]').forEach((input) => input.addEventListener('change', refreshHeaders));
    form.querySelector('[data-apply-markup]')?.addEventListener('click', () => requestAnimationFrame(refreshHeaders));
})();

// Category-Wise mode is a presentation helper only.  These compact detail
// rows mirror the main grid's inputs so there is never a category-level final
// price or a second unsynchronised pricing draft.
(() => {
    const form = document.querySelector('[data-bulk-pricing-form]');
    if (!form) return;
    const categoryBody = form.querySelector('[data-category-pricing-rows]');
    const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' })[character]);
    const selectedRows = () => [...form.querySelectorAll('[data-pricing-row]')].filter((row) => row.querySelector('[data-pricing-select]')?.checked);
    const categoryName = (row) => row.cells[2]?.textContent.trim() || 'Uncategorized';
    const productId = (row) => row.querySelector('input[type="hidden"]')?.value;
    const renderDetails = () => {
        if (form.querySelector('[data-pricing-mode]')?.value !== 'category') return;
        categoryBody.querySelectorAll('.tf-category-product-detail').forEach((row) => row.remove());
        const groups = new Map();
        selectedRows().forEach((row) => { const name = categoryName(row); groups.set(name, [...(groups.get(name) || []), row]); });
        [...groups].forEach(([name, members]) => {
            const rule = [...categoryBody.querySelectorAll('[data-category-rule]')].find((row) => row.dataset.categoryRule === name);
            if (!rule) return;
            const detail = document.createElement('tr'); detail.className = 'tf-category-product-detail';
            detail.innerHTML = `<td colspan="4"><div class="small fw-semibold mb-2">Products in ${escapeHtml(name)}</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Product</th><th>Unit</th><th>Purchase</th><th>Current retail</th><th>Current wholesale</th><th>New retail</th><th>New wholesale</th><th>Retail margin</th><th>Wholesale margin</th></tr></thead><tbody>${members.map((row) => { const id = productId(row); const cells = row.cells; const retail = row.querySelector('[data-pricing-input="retail"]'); const wholesale = row.querySelector('[data-pricing-input="wholesale"]'); return `<tr data-detail-product="${id}"><td>${escapeHtml(row.dataset.product)}</td><td>${escapeHtml(cells[3]?.textContent.trim() || '-')}</td><td>${escapeHtml(cells[4]?.textContent.trim() || '—')}</td><td>${escapeHtml(cells[5]?.textContent.trim() || '—')}</td><td>${escapeHtml(cells[6]?.textContent.trim() || '—')}</td><td><input class="form-control form-control-sm" data-detail-price="retail" value="${escapeHtml(retail.value)}" type="number" min="0" step="0.01"></td><td><input class="form-control form-control-sm" data-detail-price="wholesale" value="${escapeHtml(wholesale.value)}" type="number" min="0" step="0.01"></td><td data-detail-margin="retail">${escapeHtml(cells[9]?.textContent.trim() || '—')}</td><td data-detail-margin="wholesale">${escapeHtml(cells[10]?.textContent.trim() || '—')}</td></tr>`; }).join('')}</tbody></table></div></td>`;
            rule.after(detail);
        });
        categoryBody.querySelectorAll('[data-detail-product]').forEach((detail) => detail.querySelectorAll('[data-detail-price]').forEach((input) => input.addEventListener('input', () => {
            const main = [...form.querySelectorAll('[data-pricing-row]')].find((row) => productId(row) === detail.dataset.detailProduct);
            const field = input.dataset.detailPrice; const mainInput = main?.querySelector(`[data-pricing-input="${field}"]`);
            if (mainInput) { mainInput.value = input.value; mainInput.dispatchEvent(new Event('input', { bubbles: true })); }
        })));
    };
    const syncDetails = () => {
        categoryBody.querySelectorAll('[data-detail-product]').forEach((detail) => {
            const main = [...form.querySelectorAll('[data-pricing-row]')].find((row) => productId(row) === detail.dataset.detailProduct);
            if (!main) return;
            ['retail', 'wholesale'].forEach((field) => {
                const input = main.querySelector(`[data-pricing-input="${field}"]`);
                const detailInput = detail.querySelector(`[data-detail-price="${field}"]`);
                if (detailInput && document.activeElement !== detailInput) detailInput.value = input.value;
                const margin = detail.querySelector(`[data-detail-margin="${field}"]`);
                if (margin) margin.textContent = main.querySelector(`[data-margin="${field}"]`)?.textContent || '—';
            });
        });
    };
    form.querySelector('[data-pricing-mode]')?.addEventListener('change', () => requestAnimationFrame(renderDetails));
    form.querySelector('[data-pricing-select-all]')?.addEventListener('change', () => requestAnimationFrame(renderDetails));
    form.querySelectorAll('[data-pricing-select]').forEach((input) => input.addEventListener('change', () => requestAnimationFrame(renderDetails)));
    form.querySelector('[data-apply-markup]')?.addEventListener('click', () => requestAnimationFrame(() => { renderDetails(); syncDetails(); }));
    form.querySelectorAll('[data-pricing-input]').forEach((input) => input.addEventListener('input', syncDetails));
})();
