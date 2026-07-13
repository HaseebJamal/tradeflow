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

// Forms opt in to a predictable keyboard sequence. This works for both auth
// forms and multi-step onboarding, while excluding hidden/disabled fields.
function applyTradeFlowTabOrder(form, focusFirst = false) {
    if (!form) return;

    const fields = [...form.querySelectorAll('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])')]
        .filter((field) => field.tabIndex !== -1 && field.offsetParent !== null);

    fields.forEach((field, index) => { field.tabIndex = index + 1; });

    if (focusFirst && fields.length && (!document.activeElement || document.activeElement === document.body)) {
        fields[0].focus();
    }
}

window.applyTradeFlowTabOrder = applyTradeFlowTabOrder;
document.querySelectorAll('form[data-tf-tab-order]').forEach((form) => applyTradeFlowTabOrder(form, true));

// Flash confirmations are temporary. Validation errors remain visible so users
// can correct their input; information alerts opt in with data-tf-auto-dismiss.
function scheduleAutoDismissAlert(alert) {
    if (!alert.matches('.alert-success, [data-tf-auto-dismiss]') || alert.dataset.tfAutoDismissTimer || alert.classList.contains('d-none')) return;
    alert.dataset.tfAutoDismissTimer = '1';

    window.setTimeout(() => {
        alert.classList.add('fade');
        window.setTimeout(() => alert.remove(), 180);
    }, 3000);
}

function scanAutoDismissAlerts(node = document) {
    if (node instanceof Element && node.matches?.('.alert-success, [data-tf-auto-dismiss]')) scheduleAutoDismissAlert(node);
    node.querySelectorAll?.('.alert-success, [data-tf-auto-dismiss]').forEach(scheduleAutoDismissAlert);
}

scanAutoDismissAlerts();
new MutationObserver((changes) => changes.forEach((change) => {
    if (change.type === 'attributes') scheduleAutoDismissAlert(change.target);
    change.addedNodes.forEach(scanAutoDismissAlerts);
})).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });

document.querySelectorAll('[data-tf-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const inputSelector = button.dataset.tfPasswordToggle;
        const iconSelector = button.dataset.tfPasswordIcon || `#${button.querySelector('i')?.id}`;
        togglePassword(inputSelector, iconSelector);
    });
});

function initPasswordResetRequestForm(form) {
    if (!form || form.dataset.passwordResetReady === '1') return;
    form.dataset.passwordResetReady = '1';
    const resend = form.querySelector('[data-resend-reset-button]');
    const countdown = form.querySelector('[data-resend-countdown]');
    const until = Date.parse(form.dataset.resendUntil || '');

    const updateCountdown = () => {
        if (!resend || Number.isNaN(until)) return;
        const remaining = Math.max(0, Math.ceil((until - Date.now()) / 1000));
        resend.disabled = remaining > 0;
        if (countdown) countdown.textContent = remaining > 0 ? `(${remaining}s)` : '';
        if (remaining <= 0) {
            resend.disabled = false;
            return;
        }
        window.setTimeout(updateCountdown, 250);
    };

    updateCountdown();
    form.addEventListener('submit', () => {
        const buttons = form.querySelectorAll('button[type="submit"]');
        buttons.forEach((button) => { button.disabled = true; });
        resend?.replaceChildren(document.createTextNode('Sending...'));
        form.querySelector('[data-send-reset-label]')?.replaceChildren(document.createTextNode('Sending...'));
        form.querySelector('[data-send-reset-spinner]')?.classList.remove('d-none');
    });
}

function initPasswordResetUpdateForm(form) {
    if (!form || form.dataset.passwordResetUpdateReady === '1') return;
    form.dataset.passwordResetUpdateReady = '1';
    form.addEventListener('submit', () => {
        form.querySelector('[data-reset-password-submit]')?.setAttribute('disabled', 'disabled');
        form.querySelector('[data-reset-password-label]')?.replaceChildren(document.createTextNode('Resetting...'));
        form.querySelector('[data-reset-password-spinner]')?.classList.remove('d-none');
    });
}

document.querySelectorAll('[data-password-reset-request-form]').forEach(initPasswordResetRequestForm);
document.querySelectorAll('[data-password-reset-update-form]').forEach(initPasswordResetUpdateForm);

const subscribeForm = document.querySelector('[data-tf-subscribe-form]');
if (subscribeForm) {
    const monthly = Number.parseFloat(subscribeForm.dataset.monthly || '0') || 0;
    const yearly = Number.parseFloat(subscribeForm.dataset.yearly || '0') || 0;
    const planName = subscribeForm.dataset.planName || 'Selected Plan';
    const money = (value) => `Rs ${Math.round(value).toLocaleString()}`;

    const updateSubscribeSummary = () => {
        const cycle = subscribeForm.querySelector('[data-billing-cycle]:checked')?.value || 'Monthly';
        const payment = subscribeForm.querySelector('[data-subscribe-payment]')?.value || 'Cash';
        const yearlyBeforeDiscount = monthly * 12;
        const subtotal = cycle === 'Yearly' ? yearlyBeforeDiscount : monthly;
        const discount = cycle === 'Yearly' ? Math.max(0, yearlyBeforeDiscount - yearly) : 0;
        const total = cycle === 'Yearly' ? yearly : monthly;

        document.querySelector('[data-summary-plan]') && (document.querySelector('[data-summary-plan]').textContent = planName);
        document.querySelector('[data-summary-cycle]') && (document.querySelector('[data-summary-cycle]').textContent = cycle);
        document.querySelector('[data-summary-subtotal]') && (document.querySelector('[data-summary-subtotal]').textContent = money(subtotal));
        document.querySelector('[data-summary-discount]') && (document.querySelector('[data-summary-discount]').textContent = money(discount));
        document.querySelector('[data-summary-total]') && (document.querySelector('[data-summary-total]').textContent = money(total));
        document.querySelector('[data-summary-payment]') && (document.querySelector('[data-summary-payment]').textContent = payment);
    };

    subscribeForm.addEventListener('change', (event) => {
        if (event.target.matches('[data-billing-cycle], [data-subscribe-payment]')) {
            updateSubscribeSummary();
        }
    });

    subscribeForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const cycle = subscribeForm.querySelector('[data-billing-cycle]:checked')?.value || 'Monthly';
        const phone = subscribeForm.querySelector('[data-subscribe-phone]')?.value || '-';

        document.querySelector('[data-success-plan]') && (document.querySelector('[data-success-plan]').textContent = planName);
        document.querySelector('[data-success-cycle]') && (document.querySelector('[data-success-cycle]').textContent = cycle);
        document.querySelector('[data-success-phone]') && (document.querySelector('[data-success-phone]').textContent = phone);
        document.querySelector('[data-tf-subscribe-success]')?.classList.remove('d-none');
        document.querySelector('[data-tf-subscribe-success]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        subscribeForm.reset();
        updateSubscribeSummary();
    });

    updateSubscribeSummary();
}

const legacyWizard = document.querySelector('[data-tf-register-form][data-use-legacy-wizard]');
if (legacyWizard) {
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

        const selected = children.filter((child) => child.checked).length;
        parent.checked = selected === children.length;
        parent.indeterminate = selected > 0 && selected < children.length;
    },
    syncForm(form) {
        if (!form) return;

        form.querySelectorAll('[data-permission-group]').forEach((group) => {
            this.syncGroup(group);
        });

        const global = form.querySelector('[data-permission-global]');
        const children = [...form.querySelectorAll('[data-permission-child]')];
        if (!global || !children.length) return;

        const selected = children.filter((child) => child.checked).length;
        global.checked = selected === children.length;
        global.indeterminate = selected > 0 && selected < children.length;
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

function initStaffPasswordForm(form) {
    if (!form || form.dataset.staffPasswordReady === '1') return;
    form.dataset.staffPasswordReady = '1';
    const password = form.querySelector('[data-staff-password]');
    const confirmation = form.querySelector('[data-staff-password-confirmation]');
    const error = form.querySelector('[data-staff-password-match-error]');

    const validate = () => {
        if (!password || !confirmation) return true;
        const mismatch = Boolean(confirmation.value) && password.value !== confirmation.value;
        confirmation.classList.toggle('is-invalid', mismatch);
        error?.classList.toggle('d-block', mismatch);
        confirmation.setCustomValidity(mismatch ? 'Password and confirm password do not match.' : '');
        return !mismatch;
    };

    password?.addEventListener('input', validate);
    confirmation?.addEventListener('input', validate);
    form.addEventListener('submit', (event) => {
        if (!validate() || !form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
        }
    });
}

document.querySelectorAll('[data-staff-password-form]').forEach(initStaffPasswordForm);

function initStaffDraftForm(form) {
    if (!form || form.dataset.staffDraftReady === '1') return;
    form.dataset.staffDraftReady = '1';
    const key = form.dataset.staffDraftKey;
    if (!key) return;

    const alert = form.querySelector('[data-staff-draft-alert]');
    const clear = form.querySelector('[data-clear-staff-draft]');
    const fields = [...form.querySelectorAll('input, select, textarea')].filter((field) => {
        return field.name && !['password', 'password_confirmation'].includes(field.name)
            && !['file', 'hidden', 'submit', 'button'].includes(field.type);
    });
    let dirty = false;
    let submitting = false;

    const save = () => {
        const values = {};
        fields.forEach((field) => {
            if (field.type === 'checkbox') {
                if (!values[field.name]) values[field.name] = [];
                if (field.checked) values[field.name].push(field.value || '1');
            } else if (field.type === 'radio') {
                if (field.checked) values[field.name] = field.value;
            } else {
                values[field.name] = field.value;
            }
        });
        sessionStorage.setItem(key, JSON.stringify({ savedAt: Date.now(), values, scrollY: window.scrollY }));
    };

    const restore = () => {
        if (form.dataset.staffDraftCreated === '1') {
            sessionStorage.removeItem(key);
            return;
        }
        try {
            const draft = JSON.parse(sessionStorage.getItem(key) || 'null');
            if (!draft?.values) return;
            fields.forEach((field) => {
                if (!(field.name in draft.values)) return;
                if (field.type === 'checkbox') {
                    field.checked = Array.isArray(draft.values[field.name]) && draft.values[field.name].includes(field.value || '1');
                } else if (field.type === 'radio') {
                    field.checked = draft.values[field.name] === field.value;
                } else {
                    field.value = draft.values[field.name];
                }
                field.dispatchEvent(new Event('change', { bubbles: true }));
            });
            window.TradeFlowPermissions?.syncForm(form);
            alert?.classList.remove('d-none');
            if (Number.isFinite(draft.scrollY)) window.setTimeout(() => window.scrollTo({ top: draft.scrollY, behavior: 'auto' }), 0);
        } catch (_) {
            sessionStorage.removeItem(key);
        }
    };

    restore();
    fields.forEach((field) => {
        field.addEventListener('input', () => { dirty = true; save(); });
        field.addEventListener('change', () => { dirty = true; save(); });
    });
    clear?.addEventListener('click', () => {
        if (!window.confirm('Are you sure you want to clear this staff draft?')) return;
        sessionStorage.removeItem(key);
        form.reset();
        alert?.classList.add('d-none');
        window.TradeFlowPermissions?.syncForm(form);
        form.querySelector('[data-staff-role]')?.dispatchEvent(new Event('change', { bubbles: true }));
        dirty = false;
    });
    form.addEventListener('submit', () => { submitting = true; });
    window.addEventListener('beforeunload', (event) => {
        if (!dirty || submitting) return;
        event.preventDefault();
        event.returnValue = '';
    });
}

document.querySelectorAll('[data-staff-create-form]').forEach(initStaffDraftForm);

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

function validateOrderStock(input) {
    if (!input) return true;
    const available = Number.parseInt(input.max || '0', 10);
    const requested = Number.parseInt(input.value || '0', 10);
    const insufficient = Number.isFinite(available) && Number.isFinite(requested) && requested > available;
    input.setCustomValidity(insufficient ? `Insufficient stock. Only ${available} units are available.` : '');
    input.classList.toggle('is-invalid', insufficient);
    return !insufficient;
}

document.querySelector('[data-order-form]')?.addEventListener('input', (event) => {
    if (event.target.matches('[data-order-qty], [data-order-discount]')) {
        if (event.target.matches('[data-order-qty]')) validateOrderStock(event.target);
        updateOrderPreview();
    }
});

document.querySelector('[data-order-form]')?.addEventListener('submit', (event) => {
    const inputs = [...event.currentTarget.querySelectorAll('[data-order-qty]')];
    if (!inputs.every(validateOrderStock)) {
        event.preventDefault();
        event.currentTarget.querySelector('[data-order-qty].is-invalid')?.focus();
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

function updateJournalTotals() {
    const form = document.querySelector('[data-journal-form]');
    if (!form) return;

    const debit = [...form.querySelectorAll('[data-journal-debit]')].reduce((sum, input) => sum + (Number.parseFloat(input.value || '0') || 0), 0);
    const credit = [...form.querySelectorAll('[data-journal-credit]')].reduce((sum, input) => sum + (Number.parseFloat(input.value || '0') || 0), 0);
    const difference = Math.round((debit - credit) * 100) / 100;
    const submit = form.querySelector('[data-journal-submit]');

    form.querySelector('[data-journal-total-debit]') && (form.querySelector('[data-journal-total-debit]').textContent = debit.toLocaleString());
    form.querySelector('[data-journal-total-credit]') && (form.querySelector('[data-journal-total-credit]').textContent = credit.toLocaleString());
    form.querySelector('[data-journal-difference]') && (form.querySelector('[data-journal-difference]').textContent = difference.toLocaleString());
    if (submit) submit.disabled = debit <= 0 || credit <= 0 || Math.abs(difference) > 0.009;
}

document.querySelector('[data-journal-form]')?.addEventListener('input', updateJournalTotals);
updateJournalTotals();

const bulkTable = document.querySelector('[data-bulk-products]');
document.querySelector('[data-add-bulk-row]')?.addEventListener('click', () => {
    if (!bulkTable) return;
    const tbody = bulkTable.querySelector('tbody');
    const template = tbody.querySelector('[data-bulk-row]')?.cloneNode(true);
    if (!template) return;
    const index = tbody.querySelectorAll('[data-bulk-row]').length;
    template.querySelectorAll('input, select').forEach((field) => {
        field.name = field.name.replace(/products\[\d+\]/, `products[${index}]`);
        if (field.tagName === 'INPUT') field.value = field.type === 'number' && field.name.includes('low_stock_alert_qty') ? '10' : '';
    });
    tbody.appendChild(template);
});

bulkTable?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-remove-bulk-row]');
    if (!button) return;
    const rows = bulkTable.querySelectorAll('[data-bulk-row]');
    if (rows.length <= 1) return;
    button.closest('[data-bulk-row]')?.remove();
});

function syncBatchFields() {
    const toggle = document.querySelector('[data-batch-toggle]');
    if (!toggle) return;
    document.querySelectorAll('[data-batch-field]').forEach((field) => {
        field.classList.toggle('d-none', !toggle.checked);
    });
}

document.querySelector('[data-batch-toggle]')?.addEventListener('change', syncBatchFields);
syncBatchFields();

function initPermissionHierarchy(form) {
    if (!form || form.dataset.permissionHierarchyReady === '1') return;
    form.dataset.permissionHierarchyReady = '1';

    const master = form.querySelector('[data-permission-master]');
    const groups = [...form.querySelectorAll('[data-permission-group]')];

    const syncGroup = (group) => {
        const parent = group.querySelector('[data-permission-module]');
        const children = [...group.querySelectorAll('[data-permission-child]')];
        if (!parent || !children.length) return;
        const selected = children.filter((child) => child.checked).length;
        parent.checked = selected === children.length;
        parent.indeterminate = selected > 0 && selected < children.length;
    };

    const syncMaster = () => {
        if (!master) return;
        const children = [...form.querySelectorAll('[data-permission-child]')];
        const selected = children.filter((child) => child.checked).length;
        master.checked = children.length > 0 && selected === children.length;
        master.indeterminate = selected > 0 && selected < children.length;
    };

    const syncAll = () => {
        groups.forEach(syncGroup);
        syncMaster();
    };

    master?.addEventListener('change', () => {
        form.querySelectorAll('[data-permission-child]').forEach((child) => { child.checked = master.checked; });
        syncAll();
    });

    groups.forEach((group) => {
        group.querySelector('[data-permission-module]')?.addEventListener('change', (event) => {
            group.querySelectorAll('[data-permission-child]').forEach((child) => { child.checked = event.target.checked; });
            syncAll();
        });
        group.querySelectorAll('[data-permission-child]').forEach((child) => child.addEventListener('change', syncAll));
    });

    syncAll();
}

document.querySelectorAll('[data-company-permission-form]').forEach(initPermissionHierarchy);

function initCompanyCreateForm(form) {
    if (!form || form.dataset.companyCreateReady === '1') return;
    form.dataset.companyCreateReady = '1';
    const storageKey = 'tradeflow_super_admin_create_company_draft';
    const password = form.querySelector('[data-company-password]');
    const confirmation = form.querySelector('[data-company-password-confirmation]');
    const passwordError = form.querySelector('[data-company-password-error]');
    const submit = form.querySelector('[data-company-create-submit]');
    const draftAlert = form.querySelector('[data-company-draft-alert]');
    const fields = [...form.querySelectorAll('input, select, textarea')].filter((field) => field.name && !['temporary_password', 'temporary_password_confirmation', 'permissions[]'].includes(field.name) && field.type !== 'file' && field.type !== 'hidden');
    const passwordRule = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/;

    const saveDraft = () => {
        const draft = {};
        fields.forEach((field) => {
            if (field.type === 'radio') { if (field.checked) draft[field.name] = field.value; }
            else if (field.type === 'checkbox') draft[field.name] = field.checked;
            else draft[field.name] = field.value;
        });
        localStorage.setItem(storageKey, JSON.stringify({ savedAt: Date.now(), fields: draft }));
    };

    const restoreDraft = () => {
        try {
            const stored = JSON.parse(localStorage.getItem(storageKey) || 'null');
            if (!stored?.fields) return;
            fields.forEach((field) => {
                if (!(field.name in stored.fields)) return;
                if (field.type === 'radio') field.checked = stored.fields[field.name] === field.value;
                else if (field.type === 'checkbox') field.checked = Boolean(stored.fields[field.name]);
                else field.value = stored.fields[field.name];
            });
            draftAlert?.classList.remove('d-none');
        } catch (_) {
            localStorage.removeItem(storageKey);
        }
    };

    const validate = () => {
        const value = password?.value || '';
        const matches = value !== '' && value === (confirmation?.value || '');
        const strong = passwordRule.test(value);
        const showError = Boolean(confirmation?.value) && !matches;
        confirmation?.classList.toggle('is-invalid', showError);
        password?.classList.toggle('is-invalid', Boolean(value) && !strong);
        passwordError?.classList.toggle('d-block', showError);
        if (password) password.setCustomValidity(value && !strong ? 'Use a stronger password.' : '');
        if (confirmation) confirmation.setCustomValidity(showError ? 'Password and confirm password do not match.' : '');
        if (submit) submit.disabled = !form.checkValidity() || !strong || !matches;
    };

    restoreDraft();
    fields.forEach((field) => field.addEventListener('input', () => { saveDraft(); validate(); }));
    fields.forEach((field) => field.addEventListener('change', () => { saveDraft(); validate(); }));
    password?.addEventListener('input', validate);
    confirmation?.addEventListener('input', validate);
    form.querySelector('[data-clear-company-draft]')?.addEventListener('click', () => {
        if (window.confirm('Are you sure you want to clear this company draft?')) {
            localStorage.removeItem(storageKey);
            form.reset();
            draftAlert?.classList.add('d-none');
            validate();
        }
    });
    form.addEventListener('submit', (event) => {
        validate();
        if (!form.checkValidity() || submit?.disabled) {
            event.preventDefault();
            form.reportValidity();
        }
    });
    validate();
}

document.querySelectorAll('[data-company-create-form]').forEach(initCompanyCreateForm);
