window.initTradeFlowProductCreateForm = function initTradeFlowProductCreateForm(root = document) {
    const form = root.matches?.('[data-inline-products-form]')
        ? root
        : root.querySelector?.('[data-inline-products-form]');
    if (!form || form.dataset.productCreateReady === '1') return;
    form.dataset.productCreateReady = '1';

    const sections = form.querySelector('#product-sections');
    const template = form.querySelector('#product-section-template');
    const addButton = form.querySelector('[data-add-product-section]');
    const errors = form.querySelector('[data-product-create-errors]');
    const isAsync = form.dataset.productCreateAsync === 'true';
    const fieldFor = (kind) => kind === 'category' ? 'category_id' : 'unit_id';
    const catalogs = {
        category: [...form.querySelectorAll('[data-product-field="category_id"] option')].filter((option) => option.value).map((option) => ({ value: option.value, text: option.text })),
        unit: [...form.querySelectorAll('[data-product-field="unit_id"] option')].filter((option) => option.value).map((option) => ({ value: option.value, text: option.text })),
    };
    let originSelect = null;
    let originTrigger = null;

    const hasOption = (select, value) => [...select.options].some((option) => String(option.value) === String(value));
    const catalogModalIsValid = (modalForm) => {
        const kind = modalForm.dataset.inlineCatalogForm;
        const nameField = modalForm.querySelector(kind === 'category' ? '[name="name"]' : '[name="unit_name"]');
        const statusField = modalForm.querySelector('[name="status"]');
        const typeField = modalForm.querySelector('[name="unit_type"]');

        return Boolean(nameField?.value.trim())
            && Boolean(statusField?.value)
            && (kind !== 'unit' || Boolean(typeField?.value));
    };
    const updateCatalogSubmitState = (modalForm) => {
        const submit = modalForm.querySelector('[data-inline-catalog-submit]');
        if (!submit) return;

        submit.disabled = submit.dataset.submitting === 'true' || !catalogModalIsValid(modalForm);
    };
    const resetCatalogModal = (modalForm) => {
        modalForm.reset();
        const status = modalForm.querySelector('[name="status"]');
        if (status) status.value = 'Active';
        modalForm.querySelectorAll('.is-invalid').forEach((field) => field.classList.remove('is-invalid'));
        const errorBox = modalForm.querySelector('[data-inline-catalog-errors]');
        if (errorBox) {
            errorBox.classList.add('d-none');
            errorBox.textContent = '';
        }
        const submit = modalForm.querySelector('[data-inline-catalog-submit]');
        if (submit) delete submit.dataset.submitting;
        updateCatalogSubmitState(modalForm);
    };
    const clearErrors = () => {
        errors?.classList.add('d-none');
        form.querySelectorAll('.is-invalid').forEach((field) => field.classList.remove('is-invalid'));
    };
    const updateSaveAvailability = () => {
        const ready = ['category', 'unit'].every((kind) => [...form.querySelectorAll(`[data-product-field="${fieldFor(kind)}"]`)]
            .every((select) => [...select.options].some((option) => option.value)));
        form.querySelectorAll('[data-save-products]').forEach((button) => { button.disabled = !ready; });
    };
    const hydrateSelect = (select, kind) => {
        catalogs[kind].forEach((option) => {
            if (hasOption(select, option.value)) return;
            const control = window.getTradeFlowTomSelect?.(select);
            if (control) control.addOption(option);
            else select.add(new Option(option.text, option.value));
        });
        select.disabled = false;
        const control = window.getTradeFlowTomSelect?.(select);
        control?.enable();
        control?.refreshOptions(false);
        select.closest('[data-product-master-fields]')?.querySelector(`[data-product-catalog-empty="${kind}"]`)?.remove();
    };
    const syncCatalogs = (scope = form) => {
        ['category', 'unit'].forEach((kind) => scope.querySelectorAll?.(`[data-product-field="${fieldFor(kind)}"]`)
            .forEach((select) => hydrateSelect(select, kind)));
        updateSaveAvailability();
    };
    const syncBatchFields = (section) => {
        const toggle = section.querySelector('[data-product-batch-toggle]');
        const fields = section.querySelector('[data-product-batch-fields]');
        if (toggle && fields) fields.classList.toggle('d-none', !toggle.checked);
    };
    const updateSections = () => {
        sections?.querySelectorAll('[data-product-section]').forEach((section, index) => {
            section.querySelector('[data-product-heading]').textContent = index === 0 ? 'Product' : `Product ${index + 1}`;
            const removeButton = section.querySelector('[data-remove-product]');
            if (removeButton) removeButton.hidden = index === 0;
            section.querySelectorAll('[data-product-field], [data-product-display-field]').forEach((field) => {
                const key = field.dataset.productField || field.dataset.productDisplayField;
                if (field.dataset.productField) field.name = `products[${index}][${key}]`;
                field.id = `product-${index}-${key}`;
            });
            section.querySelectorAll('label[for]').forEach((label) => {
                const key = label.htmlFor.replace(/^product-(?:__INDEX__|\d+)-/, '');
                if (key) label.htmlFor = `product-${index}-${key}`;
            });
            syncBatchFields(section);
        });
    };
    const initializeSection = (section) => {
        syncBatchFields(section);
        syncCatalogs(section);
        window.initTradeFlowTomSelect?.(section);
    };
    const showValidationErrors = (payload) => {
        const messages = [];
        Object.entries(payload.errors || { form: [payload.message || 'Please correct the highlighted product fields.'] }).forEach(([key, value]) => {
            const message = Array.isArray(value) ? value[0] : value;
            const match = key.match(/^products\.(\d+)\.(.+)$/);
            if (match) {
                const field = form.querySelector(`[name="products[${match[1]}][${match[2]}]"]`);
                field?.classList.add('is-invalid');
                messages.push(`Product ${Number(match[1]) + 1}: ${message}`);
            } else {
                messages.push(message);
            }
        });
        if (errors) {
            errors.textContent = messages.join(' ');
            errors.classList.remove('d-none');
        }
    };
    const resetForNextCreate = () => {
        sections?.querySelectorAll('[data-product-section]').forEach((section, index) => {
            if (index > 0) {
                section.querySelectorAll('select').forEach((select) => select.tomselect?.destroy());
                section.remove();
            }
        });
        form.reset();
        form.querySelectorAll('select').forEach((select) => {
            const control = window.getTradeFlowTomSelect?.(select);
            control?.clear(true);
            control?.setValue(select.value, true);
        });
        updateSections();
        syncCatalogs();
        clearErrors();
    };

    if (isAsync) {
        form.closest('.modal')?.addEventListener('hidden.bs.modal', resetForNextCreate);
    }

    form.addEventListener('click', (event) => {
        const catalogButton = event.target.closest('[data-inline-catalog-open]');
        if (catalogButton) {
            const kind = catalogButton.dataset.inlineCatalogOpen;
            originSelect = catalogButton.closest('[data-product-master-fields]')?.querySelector(`[data-product-field="${fieldFor(kind)}"]`) || null;
            originTrigger = catalogButton;
            const modal = document.getElementById(kind === 'category' ? 'inlineProductCategoryModal' : 'inlineProductUnitModal');
            const modalForm = modal?.querySelector('[data-inline-catalog-form]');
            if (!modal || !modalForm || !window.bootstrap) return;
            resetCatalogModal(modalForm);
            window.bootstrap.Modal.getOrCreateInstance(modal).show();
            setTimeout(() => {
                updateCatalogSubmitState(modalForm);
                modalForm.querySelector('input, select, textarea')?.focus();
            }, 150);
            return;
        }

        if (event.target.closest('[data-add-product-section]') && sections && template) {
            const fragment = template.content.cloneNode(true);
            const section = fragment.querySelector('[data-product-section]');
            sections.appendChild(fragment);
            updateSections();
            initializeSection(section);
            section.querySelector('[data-product-field="product_name"]')?.focus();
            return;
        }

        const removeButton = event.target.closest('[data-remove-product]');
        if (removeButton) {
            const section = removeButton.closest('[data-product-section]');
            section.querySelectorAll('select').forEach((select) => select.tomselect?.destroy());
            section.remove();
            updateSections();
        }
    });

    sections?.addEventListener('change', (event) => {
        if (event.target.matches('[data-product-batch-toggle]')) syncBatchFields(event.target.closest('[data-product-section]'));
    });

    document.querySelectorAll('[data-inline-catalog-form]').forEach((modalForm) => {
        if (modalForm.dataset.catalogReady === '1') return;
        modalForm.dataset.catalogReady = '1';
        modalForm.addEventListener('input', () => {
            modalForm.querySelector('[data-inline-catalog-errors]')?.classList.add('d-none');
            updateCatalogSubmitState(modalForm);
        });
        modalForm.addEventListener('change', () => {
            modalForm.querySelector('[data-inline-catalog-errors]')?.classList.add('d-none');
            updateCatalogSubmitState(modalForm);
        });
        modalForm.closest('.modal')?.addEventListener('shown.bs.modal', () => updateCatalogSubmitState(modalForm));
        modalForm.closest('.modal')?.addEventListener('hidden.bs.modal', () => {
            resetCatalogModal(modalForm);
            originSelect = null;
            originTrigger?.focus();
            originTrigger = null;
        });
        modalForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const kind = modalForm.dataset.inlineCatalogForm;
            const submit = modalForm.querySelector('[data-inline-catalog-submit]');
            const modalErrors = modalForm.querySelector('[data-inline-catalog-errors]');
            if (!catalogModalIsValid(modalForm) || submit.dataset.submitting === 'true') {
                updateCatalogSubmitState(modalForm);
                return;
            }
            submit.dataset.submitting = 'true';
            submit.disabled = true;
            modalErrors.classList.add('d-none');
            try {
                const response = await fetch(form.dataset[kind === 'category' ? 'inlineCategoryUrl' : 'inlineUnitUrl'], {
                    method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]')?.value || '' }, body: new FormData(modalForm),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw payload;
                const item = payload[kind];
                const option = { value: String(item.id), text: kind === 'category' ? item.name : item.unit_name };
                if (!catalogs[kind].some((entry) => entry.value === option.value)) catalogs[kind].push(option);
                syncCatalogs();
                const control = window.getTradeFlowTomSelect?.(originSelect);
                if (control) control.setValue(option.value, true);
                else if (originSelect) { originSelect.value = option.value; originSelect.dispatchEvent(new Event('change', { bubbles: true })); }
                window.bootstrap.Modal.getInstance(modalForm.closest('.modal'))?.hide();
                window.Swal?.fire({ icon: 'success', title: payload.message || `${kind === 'category' ? 'Category' : 'Unit'} created`, timer: 1400, showConfirmButton: false });
            } catch (payload) {
                const messages = Object.values(payload.errors || { form: [payload.message || 'Unable to save this record.'] }).flat();
                modalErrors.textContent = messages.join(' ');
                modalErrors.classList.remove('d-none');
            } finally {
                delete submit.dataset.submitting;
                updateCatalogSubmitState(modalForm);
            }
        });

        updateCatalogSubmitState(modalForm);
    });

    form.addEventListener('submit', async (event) => {
        if (!isAsync) {
            if (!form.checkValidity()) return;
            const submit = form.querySelector('[data-save-products]');
            if (submit?.dataset.submitting === 'true') { event.preventDefault(); return; }
            if (submit) { submit.dataset.submitting = 'true'; submit.disabled = true; submit.textContent = 'Saving Products...'; }
            return;
        }

        event.preventDefault();
        clearErrors();
        if (!form.checkValidity()) { form.reportValidity(); return; }
        const submit = form.querySelector('[data-save-products]');
        if (submit?.dataset.submitting === 'true') return;
        submit.dataset.submitting = 'true';
        submit.disabled = true;
        const originalLabel = submit.textContent;
        submit.textContent = 'Saving Products...';
        try {
            const response = await fetch(form.action, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]')?.value || '' }, body: new FormData(form) });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw payload;
            window.dispatchEvent(new CustomEvent('tradeflow:products-created', { detail: payload.products || [] }));
            window.bootstrap.Modal.getInstance(form.closest('.modal'))?.hide();
            window.Swal?.fire({ icon: 'success', title: payload.message || 'Product(s) created successfully.', timer: 1600, showConfirmButton: false });
        } catch (payload) { showValidationErrors(payload); }
        finally { submit.dataset.submitting = 'false'; submit.disabled = false; submit.textContent = originalLabel; }
    });

    updateSections();
    sections?.querySelectorAll('[data-product-section]').forEach(initializeSection);
};
