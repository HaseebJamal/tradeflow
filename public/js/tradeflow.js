const sidebarStorageKey = 'tradeflow_sidebar_collapsed';
const isDesktopSidebar = () => window.matchMedia('(min-width: 992px)').matches;

function openSidebar() {
    document.body.classList.add('sidebar-open');
}

function closeSidebar() {
    document.body.classList.remove('sidebar-open');
}

function toggleSidebar() {
    if (isDesktopSidebar()) {
        document.body.classList.toggle('sidebar-collapsed');
        localStorage.setItem(sidebarStorageKey, document.body.classList.contains('sidebar-collapsed') ? '1' : '0');
    } else {
        document.body.classList.contains('sidebar-open') ? closeSidebar() : openSidebar();
    }
}

window.openSidebar = openSidebar;
window.closeSidebar = closeSidebar;
window.toggleSidebar = toggleSidebar;

if (localStorage.getItem(sidebarStorageKey) === '1' && isDesktopSidebar()) {
    document.body.classList.add('sidebar-collapsed');
}

document.querySelectorAll('[data-tf-sidebar-toggle]').forEach((button) => {
    button.addEventListener('click', toggleSidebar);
});

document.querySelectorAll('[data-tf-sidebar-close], [data-tf-sidebar-overlay]').forEach((element) => {
    element.addEventListener('click', closeSidebar);
});

window.addEventListener('resize', () => {
    if (isDesktopSidebar()) {
        closeSidebar();
        document.body.classList.toggle('sidebar-collapsed', localStorage.getItem(sidebarStorageKey) === '1');
    } else {
        document.body.classList.remove('sidebar-collapsed');
    }
});

document.querySelectorAll('[data-tf-smooth]').forEach((link) => {
    link.addEventListener('click', (event) => {
        const url = new URL(link.href, window.location.origin);
        const samePage = url.origin === window.location.origin
            && url.pathname.replace(/\/$/, '') === window.location.pathname.replace(/\/$/, '');
        const target = url.hash ? document.querySelector(url.hash) : null;

        if (samePage && target) {
            event.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            history.replaceState(null, '', url.hash);
        }
    });
});

function togglePassword(inputId, iconId) {
    const input = document.querySelector(inputId);
    const icon = document.querySelector(iconId);
    if (!input || !icon) return;

    const showing = input.type === 'text';
    input.type = showing ? 'password' : 'text';
    icon.classList.toggle('bi-eye', showing);
    icon.classList.toggle('bi-eye-slash', !showing);
}

window.togglePassword = togglePassword;

document.querySelectorAll('[data-tf-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const inputSelector = button.dataset.tfPasswordToggle;
        const iconSelector = button.dataset.tfPasswordIcon || `#${button.querySelector('i')?.id}`;
        togglePassword(inputSelector, iconSelector);
    });
});

document.querySelector('[data-tf-forgot-form]')?.addEventListener('submit', (event) => {
    event.preventDefault();
    document.querySelector('[data-tf-reset-message]')?.classList.remove('d-none');
});

document.querySelector('[data-tf-subscribe-form]')?.addEventListener('submit', (event) => {
    event.preventDefault();
    document.querySelector('[data-tf-subscribe-success]')?.classList.remove('d-none');
    event.currentTarget.reset();
});

const wizard = document.querySelector('[data-tf-register-form]');
if (wizard) {
    const draftKey = 'tradeflow.registerBusinessDraft';
    let currentStep = 0;
    const tabs = [...document.querySelectorAll('[data-tf-step-tab]')];
    const panels = [...document.querySelectorAll('[data-tf-step-panel]')];
    const back = document.querySelector('[data-tf-step-back]');
    const next = document.querySelector('[data-tf-step-next]');
    const submit = document.querySelector('[data-tf-step-submit]');

    const fieldsForPanel = (panel) => [...panel.querySelectorAll('input, select, textarea')]
        .filter((field) => !['file', 'hidden'].includes(field.type));

    const saveDraft = () => {
        const data = {};
        fieldsForPanel(wizard).forEach((field) => {
            if (!field.name || field.type === 'password') return;
            if (field.type === 'radio') {
                if (field.checked) data[field.name] = field.value;
            } else if (field.type === 'checkbox') {
                data[field.name] = field.checked;
            } else {
                data[field.name] = field.value;
            }
        });
        localStorage.setItem(draftKey, JSON.stringify(data));
    };

    const loadDraft = () => {
        const data = JSON.parse(localStorage.getItem(draftKey) || '{}');
        fieldsForPanel(wizard).forEach((field) => {
            if (!field.name || !(field.name in data)) return;
            if (field.type === 'radio') field.checked = field.value === data[field.name];
            else if (field.type === 'checkbox') field.checked = Boolean(data[field.name]);
            else field.value = data[field.name];
            field.dispatchEvent(new Event('change', { bubbles: true }));
        });
    };

    const currentStepIsValid = () => {
        const businessTypeAlert = document.querySelector('[data-tf-business-type-alert]');
        if (panels[currentStep]?.querySelector('input[name="business_type"]')) {
            const selectedType = wizard.querySelector('input[name="business_type"]:checked');
            const valid = Boolean(selectedType);
            businessTypeAlert?.classList.toggle('d-none', valid);
            panels[currentStep].querySelectorAll('.tf-business-type-card').forEach((card) => {
                card.classList.toggle('is-invalid', !valid);
            });
            if (!valid) return false;
        } else {
            businessTypeAlert?.classList.add('d-none');
        }

        const fields = fieldsForPanel(panels[currentStep]);
        return fields.every((field) => field.reportValidity());
    };

    const canMoveTo = (targetIndex) => {
        if (targetIndex <= currentStep) return true;
        for (let i = 0; i < targetIndex; i += 1) {
            currentStep = i;
            if (!currentStepIsValid()) {
                showStep(i, false);
                return false;
            }
        }
        return true;
    };

    const showStep = (index, validate = true) => {
        if (validate && !canMoveTo(index)) return;
        currentStep = Math.max(0, Math.min(index, panels.length - 1));
        tabs.forEach((tab, i) => tab.classList.toggle('active', i === currentStep));
        panels.forEach((panel, i) => {
            const active = i === currentStep;
            panel.classList.toggle('active', active);
            panel.querySelectorAll('input, select, textarea').forEach((field) => {
                field.disabled = !active;
            });
        });
        if (back) back.disabled = currentStep === 0;
        next?.classList.toggle('d-none', currentStep === panels.length - 1);
        submit?.classList.toggle('d-none', currentStep !== panels.length - 1);
    };

    tabs.forEach((tab) => tab.addEventListener('click', () => showStep(Number(tab.dataset.tfStepTab))));

    back?.addEventListener('click', () => showStep(currentStep - 1));
    next?.addEventListener('click', () => {
        if (currentStepIsValid()) showStep(currentStep + 1);
    });
    wizard.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target.tagName !== 'TEXTAREA') {
            event.preventDefault();
            if (currentStep < panels.length - 1 && currentStepIsValid()) showStep(currentStep + 1);
        }
    });
    wizard.addEventListener('input', saveDraft);
    wizard.addEventListener('change', saveDraft);
    wizard.addEventListener('submit', () => {
        localStorage.removeItem(draftKey);
        panels.forEach((panel) => {
            panel.querySelectorAll('input, select, textarea').forEach((field) => {
                field.disabled = false;
            });
        });
    });
    loadDraft();
    showStep(0, false);
}

document.querySelectorAll('.tf-business-type-card input').forEach((input) => {
    const refresh = () => {
        document.querySelectorAll('.tf-business-type-card').forEach((card) => {
            card.classList.toggle('active', card.querySelector('input')?.checked);
            card.classList.remove('is-invalid');
        });
        document.querySelector('[data-tf-business-type-alert]')?.classList.add('d-none');
    };
    input.addEventListener('change', refresh);
    refresh();
});

const profileInput = document.querySelector('[data-tf-profile-input]');
if (profileInput) {
    profileInput.addEventListener('change', () => {
        const file = profileInput.files?.[0];
        const preview = document.querySelector('[data-tf-profile-preview]');
        const empty = document.querySelector('[data-tf-profile-empty]');
        const remove = document.querySelector('[data-tf-profile-remove]');

        if (!file || !preview) return;

        preview.src = URL.createObjectURL(file);
        preview.classList.remove('d-none');
        empty?.classList.add('d-none');
        if (remove) remove.checked = false;
    });
}

const TradeFlowPermissions = {
    syncGroup(group) {
        const parent = group?.querySelector('[data-permission-parent]');
        const children = [...(group?.querySelectorAll('[data-permission-child]') || [])];
        if (!parent || !children.length) return;

        parent.checked = children.every((child) => child.checked);
        parent.indeterminate = false;
    },
    syncForm(form) {
        if (!form) return;

        form.querySelectorAll('[data-permission-group]').forEach((group) => {
            this.syncGroup(group);
        });

        const global = form.querySelector('[data-permission-global]');
        const children = [...form.querySelectorAll('[data-permission-child]')];
        if (!global || !children.length) return;

        global.checked = children.every((child) => child.checked);
        global.indeterminate = false;
    },
};

window.TradeFlowPermissions = TradeFlowPermissions;

document.addEventListener('change', (event) => {
    const global = event.target.closest('[data-permission-global]');
    const parent = event.target.closest('[data-permission-parent]');
    const child = event.target.closest('[data-permission-child]');

    if (global) {
        const form = global.closest('[data-staff-form]');
        form?.querySelectorAll('[data-permission-child]').forEach((input) => {
            input.checked = global.checked;
        });
        TradeFlowPermissions.syncForm(form);
        return;
    }

    if (parent) {
        const group = parent.closest('[data-permission-group]');
        group?.querySelectorAll('[data-permission-child]').forEach((input) => {
            input.checked = parent.checked;
        });
        TradeFlowPermissions.syncForm(parent.closest('[data-staff-form]'));
        return;
    }

    if (child) {
        TradeFlowPermissions.syncForm(child.closest('[data-staff-form]'));
    }
});

document.querySelectorAll('[data-staff-form]').forEach((form) => TradeFlowPermissions.syncForm(form));

function updateOrderPreview() {
    const form = document.querySelector('[data-order-form]');
    if (!form) return;

    let subtotal = 0;
    form.querySelectorAll('[data-order-line]').forEach((line) => {
        const price = Number.parseFloat(line.dataset.price || '0') || 0;
        const quantity = Number.parseFloat(line.querySelector('[data-order-qty]')?.value || '0') || 0;
        subtotal += price * quantity;
    });

    const discountInput = form.querySelector('[data-order-discount]');
    const discount = Math.min(100, Math.max(0, Number.parseFloat(discountInput?.value || '0') || 0));
    const discountAmount = subtotal * (discount / 100);
    const grandTotal = Math.max(0, subtotal - discountAmount);
    const formatMoney = (value) => `Rs ${Math.round(value).toLocaleString()}`;

    form.querySelector('[data-order-subtotal]') && (form.querySelector('[data-order-subtotal]').textContent = formatMoney(subtotal));
    form.querySelector('[data-order-discount-label]') && (form.querySelector('[data-order-discount-label]').textContent = `${discount.toFixed(2)}%`);
    form.querySelector('[data-order-discount-amount]') && (form.querySelector('[data-order-discount-amount]').textContent = formatMoney(discountAmount));
    form.querySelector('[data-order-grand-total]') && (form.querySelector('[data-order-grand-total]').textContent = formatMoney(grandTotal));
}

document.querySelector('[data-order-form]')?.addEventListener('input', (event) => {
    if (event.target.matches('[data-order-qty], [data-order-discount]')) {
        updateOrderPreview();
    }
});
updateOrderPreview();

function updateEditOrderPreview() {
    const form = document.querySelector('[data-edit-order-form]');
    if (!form) return;

    let subtotal = 0;
    form.querySelectorAll('[data-edit-order-row]').forEach((row) => {
        if (row.classList.contains('d-none')) return;
        const price = Number.parseFloat(row.dataset.price || '0') || 0;
        const qty = Number.parseFloat(row.querySelector('[data-edit-order-qty]')?.value || '0') || 0;
        const lineTotal = price * qty;
        subtotal += lineTotal;
        const lineTarget = row.querySelector('[data-edit-order-line-total]');
        if (lineTarget) lineTarget.textContent = `Rs ${Math.round(lineTotal).toLocaleString()}`;
    });

    const discount = Math.min(100, Math.max(0, Number.parseFloat(form.querySelector('[data-edit-order-discount]')?.value || '0') || 0));
    const discountAmount = subtotal * (discount / 100);
    const grandTotal = Math.max(0, subtotal - discountAmount);
    const paidText = form.querySelector('[data-edit-order-balance]')?.closest('strong')?.textContent || '';
    const paidMatch = paidText.match(/Rs\s*([\d,]+)/);
    const paid = paidMatch ? Number.parseFloat(paidMatch[1].replaceAll(',', '')) : 0;
    const balance = Math.max(0, grandTotal - paid);
    const money = (value) => `Rs ${Math.round(value).toLocaleString()}`;

    form.querySelector('[data-edit-order-subtotal]') && (form.querySelector('[data-edit-order-subtotal]').textContent = money(subtotal));
    form.querySelector('[data-edit-order-discount-amount]') && (form.querySelector('[data-edit-order-discount-amount]').textContent = money(discountAmount));
    form.querySelector('[data-edit-order-grand-total]') && (form.querySelector('[data-edit-order-grand-total]').textContent = money(grandTotal));
    form.querySelector('[data-edit-order-balance]') && (form.querySelector('[data-edit-order-balance]').textContent = money(balance));
}

function reindexEditOrderRows() {
    document.querySelectorAll('[data-edit-order-rows] [data-edit-order-row]').forEach((row, index) => {
        row.querySelectorAll('input, select').forEach((field) => {
            if (field.hasAttribute('data-new-product-input')) field.name = `items[${index}][product_id]`;
            if (field.hasAttribute('data-edit-order-qty')) field.name = `items[${index}][quantity]`;
            if (field.hasAttribute('data-edit-order-remove')) field.name = `items[${index}][remove]`;
            if (field.name?.includes('[item_id]')) field.name = `items[${index}][item_id]`;
            if (field.name?.includes('[product_id]') && !field.hasAttribute('data-new-product-input')) field.name = `items[${index}][product_id]`;
        });
    });
}

document.querySelector('[data-add-order-row]')?.addEventListener('click', () => {
    const template = document.querySelector('[data-edit-order-template]');
    const target = document.querySelector('[data-edit-order-rows]');
    if (!template || !target) return;
    target.appendChild(template.content.cloneNode(true));
    reindexEditOrderRows();
    updateEditOrderPreview();
});

document.querySelector('[data-edit-order-form]')?.addEventListener('click', (event) => {
    const removeExisting = event.target.closest('[data-remove-order-row]');
    const removeNew = event.target.closest('[data-delete-new-order-row]');
    if (removeExisting) {
        const row = removeExisting.closest('[data-edit-order-row]');
        row?.classList.add('d-none');
        const removeInput = row?.querySelector('[data-edit-order-remove]');
        if (removeInput) removeInput.value = '1';
        updateEditOrderPreview();
    }
    if (removeNew) {
        removeNew.closest('[data-edit-order-row]')?.remove();
        reindexEditOrderRows();
        updateEditOrderPreview();
    }
});

document.querySelector('[data-edit-order-form]')?.addEventListener('input', (event) => {
    if (event.target.matches('[data-edit-order-qty], [data-edit-order-discount]')) {
        updateEditOrderPreview();
    }
});

document.querySelector('[data-edit-order-form]')?.addEventListener('change', (event) => {
    const select = event.target.closest('[data-new-product-select]');
    if (!select) return;
    const row = select.closest('[data-edit-order-row]');
    const selected = select.selectedOptions[0];
    const price = Number.parseFloat(selected?.dataset.price || '0') || 0;
    const stock = selected?.dataset.stock || '-';
    const unit = selected?.dataset.unit || '';
    row.dataset.price = price;
    row.querySelector('[data-new-product-input]').value = select.value;
    row.querySelector('[data-new-product-stock]').textContent = select.value ? `${stock} ${unit}` : '-';
    row.querySelector('[data-new-product-rate]').textContent = `Rs ${Math.round(price).toLocaleString()}`;
    const qty = row.querySelector('[data-edit-order-qty]');
    if (qty && select.value) qty.max = stock;
    updateEditOrderPreview();
});

reindexEditOrderRows();
updateEditOrderPreview();

document.querySelector('[data-save-quick-customer]')?.addEventListener('click', () => {
    const getValue = (selector) => document.querySelector(selector)?.value.trim() || '';
    const name = getValue('[data-modal-customer-name]');
    const phone = getValue('[data-modal-customer-phone]');
    const error = document.querySelector('[data-quick-customer-error]');

    if (!name && !phone) {
        error?.classList.remove('d-none');
        return;
    }

    error?.classList.add('d-none');
    const values = {
        '[data-new-customer-name]': name,
        '[data-new-customer-shop]': getValue('[data-modal-customer-shop]'),
        '[data-new-customer-phone]': phone,
        '[data-new-customer-city]': getValue('[data-modal-customer-city]'),
        '[data-new-customer-address]': getValue('[data-modal-customer-address]'),
        '[data-new-customer-type]': getValue('[data-modal-customer-type]') || 'Retailer',
        '[data-new-customer-credit-limit]': getValue('[data-modal-customer-credit-limit]'),
    };

    Object.entries(values).forEach(([selector, value]) => {
        const field = document.querySelector(selector);
        if (field) field.value = value;
    });

    const select = document.querySelector('[data-order-customer-select]');
    if (select) {
        let option = select.querySelector('option[value="new_customer"]');
        if (!option) {
            option = new Option('', 'new_customer');
            select.add(option, 1);
        }
        option.text = name || phone;
        option.selected = true;
    }

    const selected = document.querySelector('[data-quick-customer-selected]');
    if (selected) {
        selected.textContent = `New customer ready: ${name || phone}`;
        selected.classList.remove('d-none');
    }

    const modal = document.querySelector('#quickCustomerModal');
    if (modal && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(modal).hide();
    }
});
