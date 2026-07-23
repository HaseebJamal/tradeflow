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

// Every standard select keeps its original element, name, value, and native
// form submission while Tom Select adds search, keyboard navigation, and a
// Bootstrap 5 control. Mark a specialised select with data-native-select to
// opt out. This initializer is also safe for modal and AJAX content.
window.getTradeFlowTomSelect = (element) => element?.tomselect || null;
window.syncTradeFlowTomSelect = (element) => {
    const control = window.getTradeFlowTomSelect(element);
    if (!control) return;
    const value = element.multiple
        ? [...element.selectedOptions].map((option) => option.value)
        : element.value;
    control.setValue(value, true);
};

function positionTradeFlowTomSelectDropdown(control) {
    if (!control?.isOpen || control.settings.dropdownParent !== 'body') return;

    const rect = control.control.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const width = Math.min(rect.width, Math.max(0, viewportWidth - 24));
    const menuHeight = Math.min(control.dropdown.offsetHeight || 280, Math.max(0, window.innerHeight - 24));
    const spaceBelow = window.innerHeight - rect.bottom;
    const spaceAbove = rect.top;
    const opensUp = spaceBelow < Math.min(menuHeight, 260) && spaceAbove > spaceBelow;
    const left = Math.max(12, Math.min(rect.left + window.scrollX, window.scrollX + viewportWidth - width - 12));
    const top = opensUp
        ? window.scrollY + Math.max(12, rect.top - menuHeight)
        : window.scrollY + rect.bottom;

    control.wrapper.classList.toggle('tf-tom-select-up', opensUp);
    Object.assign(control.dropdown.style, {
        left: `${left}px`,
        top: `${top}px`,
        width: `${Math.max(0, width)}px`,
    });
}

function positionOpenTradeFlowTomSelectDropdowns() {
    document.querySelectorAll('select.tomselected').forEach((element) => positionTradeFlowTomSelectDropdown(element.tomselect));
}

function closeTradeFlowTomSelectDropdowns(except = null) {
    document.querySelectorAll('select.tomselected').forEach((element) => {
        if (element.tomselect && element.tomselect !== except) element.tomselect.close();
    });
}

function syncTradeFlowTomSelectSelectedOption(control) {
    if (!control || control.settings.maxItems !== 1 || control.input.dataset.hideSelected === 'true') return;

    const selectedValue = String(control.getValue() ?? '');
    control.dropdown.querySelectorAll('[data-value]').forEach((option) => {
        const selected = selectedValue !== '' && String(option.dataset.value) === selectedValue;
        option.classList.toggle('is-selected', selected);

        let indicator = option.querySelector('.tf-tom-select-option-check');
        if (selected && !indicator) {
            indicator = document.createElement('span');
            indicator.className = 'tf-tom-select-option-check';
            indicator.setAttribute('aria-hidden', 'true');
            indicator.textContent = '✓';
            option.appendChild(indicator);
        }
        if (!selected && indicator) indicator.remove();
    });
}

window.addEventListener('resize', positionOpenTradeFlowTomSelectDropdowns);
window.addEventListener('scroll', positionOpenTradeFlowTomSelectDropdowns, { passive: true });

window.initTradeFlowTomSelect = function initTradeFlowTomSelect(root = document, { force = false } = {}) {
    if (!window.TomSelect) return;

    const selects = root.matches?.('select:not([data-native-select])')
        ? [root]
        : [...(root.querySelectorAll?.('select:not([data-native-select])') || [])];

    selects.forEach((element) => {
        // SweetAlert controls are intentionally native. Its focus trap and
        // transient DOM do not need an enhanced select instance.
        if (element.closest('.swal2-container')) return;

        // A Tom Select dropdown is portaled to <body>. Initializing a select
        // inside a hidden Bootstrap modal can therefore leave the plugin's
        // search input orphaned in the page flow. Defer it until Bootstrap
        // emits shown.bs.modal below.
        const containingModal = element.closest('.modal');
        if (containingModal && !containingModal.classList.contains('show')) return;

        if (element.tomselect && !force) return;
        if (element.tomselect && force) element.tomselect.destroy();
        if (element.disabled) return;

        const placeholderOption = [...element.options].find((option) => option.value === '');
        const isMultiple = element.multiple;
        const canClear = isMultiple || !element.required;
        // The Create Order controls live in a compact inline form.  Portaling
        // their dropdowns (and the dropdown_input search field) to <body>
        // can leave that generated input between form sections.  Keep these
        // dropdowns inside their own Tom Select wrapper instead.  This is
        // intentionally route-scoped; other selects retain body portal logic.
        const useInlineOrderDropdown = Boolean(element.closest('[data-order-form]'));

        const control = new window.TomSelect(element, {
            create: false,
            allowEmptyOption: true,
            maxItems: isMultiple ? null : 1,
            maxOptions: 500,
            closeAfterSelect: true,
            // A normal select must keep its active choice visible when it is
            // reopened. Item-entry screens can opt into hiding selections.
            hideSelected: element.dataset.hideSelected === 'true',
            searchField: ['text'],
            placeholder: element.dataset.placeholder || element.getAttribute('placeholder') || placeholderOption?.textContent?.trim() || 'Select an option',
            plugins: {
                // This plugin is the only source that creates a standalone
                // search <input>.  On Create Order it previously became an
                // orphaned field between the customer and item sections.
                // Native Tom Select search remains available in its control.
                ...(!useInlineOrderDropdown ? { dropdown_input: {} } : {}),
                ...(canClear ? { clear_button: { title: 'Clear selection' } } : {}),
            },
            dropdownParent: useInlineOrderDropdown ? null : 'body',
            position: 'auto',
            render: {
                no_results: () => '<div class="no-results">No matching records found</div>',
                option: (data, escape) => `<div class="tf-tom-select-option"><span class="tf-tom-select-option-label">${escape(data.text)}</span></div>`,
            },
        });

        // When the menu is portaled to body, lock its width to the originating
        // control so it never inherits the page or sidebar width.
        control.on('dropdown_open', () => {
            // Re-render so the selected option stays in the list with its
            // visual indicator after opening an ordinary single-select.
            if (!isMultiple && element.dataset.hideSelected !== 'true') control.refreshOptions(false);
            document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach((toggle) => {
                window.bootstrap?.Dropdown?.getInstance(toggle)?.hide();
            });
            requestAnimationFrame(() => {
                syncTradeFlowTomSelectSelectedOption(control);
                positionTradeFlowTomSelectDropdown(control);
            });
        });
        control.on('item_add', () => requestAnimationFrame(() => syncTradeFlowTomSelectSelectedOption(control)));
        control.on('item_remove', () => requestAnimationFrame(() => syncTradeFlowTomSelectSelectedOption(control)));
        control.on('dropdown_close', () => control.wrapper.classList.remove('tf-tom-select-up'));
    });
};

window.reinitializeTradeFlowTomSelect = (root = document) => window.initTradeFlowTomSelect(root, { force: true });

window.initTradeFlowTomSelect();
document.addEventListener('shown.bs.modal', (event) => window.initTradeFlowTomSelect(event.target));
document.addEventListener('tradeflow:content-loaded', (event) => window.initTradeFlowTomSelect(event.target || document));

function initTradeFlowBootstrapDropdowns(root = document) {
    const toggles = root.matches?.('[data-bs-toggle="dropdown"]')
        ? [root]
        : [...(root.querySelectorAll?.('[data-bs-toggle="dropdown"]') || [])];

    toggles.forEach((toggle) => {
        if (toggle.dataset.tradeFlowDropdownReady === '1') return;
        toggle.dataset.tradeFlowDropdownReady = '1';
        toggle.setAttribute('data-bs-display', 'dynamic');
        toggle.setAttribute('data-bs-boundary', 'viewport');
        const menu = toggle.parentElement?.querySelector(':scope > .dropdown-menu');
        if (menu && toggle.closest('.table-responsive, .tf-card, .card')) menu.classList.add('dropdown-menu-end');
    });
}

function initTradeFlowNotificationDropdowns(root = document) {
    const toggles = root.matches?.('[data-tf-notification-toggle]')
        ? [root]
        : [...(root.querySelectorAll?.('[data-tf-notification-toggle]') || [])];

    toggles.forEach((toggle) => {
        if (toggle.dataset.tradeFlowNotificationReady === '1' || !window.bootstrap?.Dropdown) return;
        toggle.dataset.tradeFlowNotificationReady = '1';
        const dropdown = toggle.closest('.tf-notification-dropdown');
        const menu = dropdown?.querySelector('.tf-notification-menu');
        const instance = window.bootstrap.Dropdown.getOrCreateInstance(toggle, {
            boundary: 'viewport',
            display: 'dynamic',
        });

        menu?.addEventListener('click', (event) => {
            if (event.target.closest('a')) instance.hide();
        });

        toggle.addEventListener('shown.bs.dropdown', () => toggle.setAttribute('aria-expanded', 'true'));
        toggle.addEventListener('hidden.bs.dropdown', () => toggle.setAttribute('aria-expanded', 'false'));
    });
}

initTradeFlowBootstrapDropdowns();
initTradeFlowNotificationDropdowns();

// Automatically cover HTML appended by modal, AJAX, or inline form scripts.
let tradeFlowTomSelectFrame;
new MutationObserver((records) => {
    const roots = new Set();
    records.forEach((record) => record.addedNodes.forEach((node) => {
        if (node.nodeType === Node.ELEMENT_NODE) roots.add(node);
    }));
    if (!roots.size) return;
    cancelAnimationFrame(tradeFlowTomSelectFrame);
    tradeFlowTomSelectFrame = requestAnimationFrame(() => roots.forEach((root) => {
        window.initTradeFlowTomSelect(root);
        initTradeFlowBootstrapDropdowns(root);
        initTradeFlowNotificationDropdowns(root);
        initNonNegativeNumberGuards(root);
        initTradeFlowSidebarSubmenus(root);
        initTradeFlowStaffActionDropdowns(root);
    }));
}).observe(document.documentElement, { childList: true, subtree: true });

// Responsive table/card wrappers scroll their contents, which can otherwise
// clip Bootstrap action menus. Open only the active wrapper and restore its
// normal responsive overflow when the menu closes.
function closeTradeFlowStaffActions(except = null) {
    document.querySelectorAll('.staff-table-wrap [data-bs-toggle="dropdown"][aria-expanded="true"]').forEach((toggle) => {
        if (toggle === except) return;
        window.bootstrap?.Dropdown.getInstance(toggle)?.hide();
    });
}

function initTradeFlowStaffActionDropdowns(root = document) {
    const toggles = root.matches?.('.staff-table-wrap [data-bs-toggle="dropdown"]')
        ? [root]
        : [...(root.querySelectorAll?.('.staff-table-wrap [data-bs-toggle="dropdown"]') || [])];

    toggles.forEach((toggle) => {
        if (toggle.dataset.staffActionDropdownReady === '1' || !window.bootstrap?.Dropdown) return;
        toggle.dataset.staffActionDropdownReady = '1';

        window.bootstrap.Dropdown.getOrCreateInstance(toggle, {
            boundary: 'viewport',
            display: 'dynamic',
            popperConfig(defaultConfig) {
                return {
                    ...defaultConfig,
                    strategy: 'fixed',
                    placement: 'bottom-end',
                    modifiers: [
                        ...(defaultConfig.modifiers || []).filter((modifier) => !['flip', 'preventOverflow', 'offset'].includes(modifier.name)),
                        { name: 'offset', options: { offset: [0, 6] } },
                        {
                            name: 'preventOverflow',
                            options: { boundary: 'viewport', padding: 12, altAxis: true },
                        },
                        {
                            name: 'flip',
                            options: {
                                boundary: 'viewport',
                                padding: 12,
                                fallbackPlacements: ['top-end', 'bottom-start', 'top-start'],
                            },
                        },
                    ],
                };
            },
        });
    });
}

initTradeFlowStaffActionDropdowns();

document.addEventListener('show.bs.dropdown', (event) => {
    const toggle = event.target;
    // A filter select (for example, the Company Plan filter) must never stay
    // open over an Actions menu. Closing it keeps each control usable without
    // resetting its selected value.
    closeTradeFlowTomSelectDropdowns();
    const wrapper = toggle.closest('.table-responsive, .tf-card, .card');
    wrapper?.classList.add('tf-dropdown-open');

    if (!toggle.closest('.staff-table-wrap')) return;

    closeTradeFlowStaffActions(toggle);
});

document.addEventListener('hidden.bs.dropdown', (event) => {
    const toggle = event.target;
    toggle.closest('.table-responsive, .tf-card, .card')?.classList.remove('tf-dropdown-open');
});

document.addEventListener('click', (event) => {
    const item = event.target.closest('.staff-table-wrap .dropdown-menu .dropdown-item');
    if (!item) return;
    window.bootstrap?.Dropdown.getInstance(item.closest('.dropdown')?.querySelector('[data-bs-toggle="dropdown"]'))?.hide();
});

window.addEventListener('resize', () => closeTradeFlowStaffActions());
document.addEventListener('scroll', (event) => {
    if (event.target instanceof Element && event.target.closest('.staff-table-wrap .dropdown-menu')) return;
    closeTradeFlowStaffActions();
}, true);

function initTradeFlowSidebarSubmenus(root = document) {
    const toggles = root.matches?.('[data-tf-sidebar-submenu-toggle]')
        ? [root]
        : [...(root.querySelectorAll?.('[data-tf-sidebar-submenu-toggle]') || [])];

    toggles.forEach((toggle) => {
        if (toggle.dataset.submenuReady === '1') return;
        toggle.dataset.submenuReady = '1';
        toggle.addEventListener('click', (event) => {
            event.preventDefault();
            const submenu = document.getElementById(toggle.getAttribute('aria-controls'));
            if (!submenu) return;
            const shouldOpen = !submenu.classList.contains('is-open');
            document.querySelectorAll('.tf-sidebar-submenu.is-open').forEach((openSubmenu) => {
                openSubmenu.classList.remove('is-open');
                document.querySelector(`[aria-controls="${openSubmenu.id}"]`)?.setAttribute('aria-expanded', 'false');
            });
            submenu.classList.toggle('is-open', shouldOpen);
            toggle.setAttribute('aria-expanded', String(shouldOpen));
        });
    });
}

initTradeFlowSidebarSubmenus();

// Exact identifiers (barcode, SKU, PO, sale number, invoice, or reference)
// should behave like scanner input: resolve once and open the matching record.
function initTradeFlowCodeLookups(root = document) {
    const forms = root.matches?.('[data-code-lookup-form]')
        ? [root]
        : [...(root.querySelectorAll?.('[data-code-lookup-form]') || [])];

    forms.forEach((form) => {
        if (form.dataset.codeLookupReady === '1') return;
        const input = form.querySelector('[data-code-lookup]');
        const endpoint = form.dataset.codeLookupUrl;
        if (!input || !endpoint) return;
        form.dataset.codeLookupReady = '1';
        let timer;
        let lastCode = '';
        let pending = false;

        const resolve = async () => {
            const code = input.value.trim();
            if (!code || code === lastCode || pending) return false;
            lastCode = code;
            pending = true;
            try {
                const response = await fetch(`${endpoint}?code=${encodeURIComponent(code)}`, { headers: { Accept: 'application/json' } });
                if (!response.ok) return false;
                const result = await response.json();
                if (result.found && result.url) {
                    window.location.assign(result.url);
                    return true;
                }
            } catch (_) {
                // A normal filter submit remains available if lookup is offline.
            } finally {
                pending = false;
            }
            return false;
        };

        input.addEventListener('input', () => {
            window.clearTimeout(timer);
            lastCode = '';
            if (input.value.trim().length < 2) return;
            timer = window.setTimeout(resolve, 180);
        });
        input.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            lastCode = '';
            resolve().then((found) => { if (!found) form.requestSubmit(); });
        });
        if (document.activeElement === document.body) input.focus();
    });
}

window.initTradeFlowCodeLookups = initTradeFlowCodeLookups;
initTradeFlowCodeLookups();

function initNonNegativeNumberGuards(root = document) {
    const selector = 'input[type="number"]:not([data-allow-negative]):not([data-allow-decimal]), [data-non-negative]';
    const fields = root.matches?.(selector)
        ? [root]
        : [...(root.querySelectorAll?.(selector) || [])];
    fields.forEach((field) => {
        if (field.dataset.nonNegativeReady === '1') return;
        field.dataset.nonNegativeReady = '1';
        const descriptor = [
            field.name,
            field.id,
            field.placeholder,
            ...[...field.attributes].map((attribute) => `${attribute.name} ${attribute.value}`),
        ].filter(Boolean).join(' ').toLowerCase();
        const requiresPositiveQuantity = /(quantity|qty|received|returned|return|damaged|damage)/.test(descriptor);
        const preventsAccidentalStepperChanges = /(quantity|qty|stock|price|cost|discount|tax|amount|payment|paid|received|return|damaged|damage|balance)/.test(descriptor);

        if (preventsAccidentalStepperChanges) {
            field.classList.add('js-no-number-spinner', 'js-no-wheel-change');
        }

        if (!field.hasAttribute('min')) field.min = requiresPositiveQuantity ? '1' : '0';
        field.step = '1';
        field.inputMode = 'numeric';

        const feedbackFor = () => {
            if (!field.id) field.id = `tradeflow-number-${Math.random().toString(36).slice(2)}`;
            let feedback = document.querySelector(`[data-tradeflow-number-feedback="${field.id}"]`);
            if (feedback) return feedback;
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback d-none';
            feedback.dataset.tradeflowNumberFeedback = field.id;
            feedback.textContent = 'Only whole numbers are allowed.';
            (field.closest('.input-group') || field).insertAdjacentElement('afterend', feedback);
            return feedback;
        };

        const setWholeNumberValidity = () => {
            const valid = field.value === '' || /^\d+$/.test(field.value);
            const feedback = feedbackFor();
            field.classList.toggle('is-invalid', !valid);
            feedback.classList.toggle('d-none', valid);
            field.setCustomValidity(valid ? '' : 'Only whole numbers are allowed.');
            return valid;
        };

        const rejectNegativeValue = () => {
            return setWholeNumberValidity();
        };

        field.addEventListener('keydown', (event) => {
            if (['-', '+', 'e', 'E', '.', ','].includes(event.key)) event.preventDefault();
        });
        field.addEventListener('beforeinput', (event) => {
            if (event.data && /[^\d]/.test(event.data)) event.preventDefault();
        });
        field.addEventListener('paste', (event) => {
            const pasted = event.clipboardData?.getData('text')?.trim() || '';
            if (!/^\d+$/.test(pasted)) {
                event.preventDefault();
                field.classList.add('is-invalid');
                feedbackFor().classList.remove('d-none');
                field.setCustomValidity('Only whole numbers are allowed.');
            }
        });
        field.addEventListener('input', rejectNegativeValue);
        field.addEventListener('change', rejectNegativeValue);
    });
}

initNonNegativeNumberGuards();

// Delegation also covers dynamically added sale, purchase, and return
// rows. Only a wheel event on the focused transactional input is prevented,
// so regular page scrolling remains available everywhere else.
document.addEventListener('wheel', (event) => {
    const field = document.activeElement;
    if (!(field instanceof HTMLInputElement) || !field.classList.contains('js-no-wheel-change')) return;
    if (event.target !== field) return;
    event.preventDefault();
}, { passive: false });

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

// Keep personal/company names, telephone numbers, and CNIC values clean at
// the point of entry. Server-side rules remain authoritative for every
// business and staff workflow.
function initTradeFlowFieldGuards(root = document) {
    const fields = root.querySelectorAll?.('input, textarea') || [];
    fields.forEach((field) => {
        const name = (field.name || '').toLowerCase();
        // International phone components submit their normalized E.164 value
        // through a hidden field. Never turn that transport field back into a
        // visible legacy telephone input based only on its name.
        const isPhone = field.type !== 'hidden'
            && !field.matches('[data-tf-phone-visible], [data-tf-phone-value], [data-tf-phone-standalone]')
            && (field.matches('[data-tf-phone]') || name.includes('phone'));
        const isCnic = field.matches('[data-tf-cnic]') || name === 'cnic' || name.endsWith('_cnic');
        const isName = field.matches('[data-tf-name-only]') || [
            'name', 'owner_name', 'father_name', 'customer_name', 'supplier_name',
            'business_name', 'company_name', 'custom_role_name', 'city',
        ].includes(name) || ((name.endsWith('_name') || name.endsWith('_city')) && name !== 'product_name');

        if (isPhone) {
            field.type = 'tel';
            field.inputMode = 'numeric';
            field.autocomplete = 'tel';
            field.maxLength = 11;
            field.addEventListener('input', () => {
                field.value = field.value.replace(/\D/g, '').slice(0, 11);
            });
        }

        if (isCnic) {
            field.type = 'tel';
            field.inputMode = 'numeric';
            field.autocomplete = 'off';
            field.maxLength = 13;
            field.addEventListener('input', () => {
                field.value = field.value.replace(/\D/g, '').slice(0, 13);
            });
        }

        if (isName) {
            field.addEventListener('input', () => {
                field.value = field.value.replace(/[^\p{L}\s]/gu, '').replace(/\s{2,}/g, ' ');
            });
        }
    });
}

function initOtherBusinessDescription(root = document) {
    root.querySelectorAll?.('[data-tf-other-business-description]').forEach((container) => {
        const form = container.closest('form') || document;
        const description = container.querySelector('textarea, input');
        const typeFields = [...form.querySelectorAll('[data-tf-business-type], input[name="business_type"]')];
        if (!description || !typeFields.length || container.dataset.tfOtherBusinessReady === '1') return;
        container.dataset.tfOtherBusinessReady = '1';

        const sync = () => {
            const selected = typeFields.find((field) => field.tagName === 'SELECT' || field.checked);
            const isOther = selected?.value === 'Other';
            container.classList.toggle('d-none', !isOther);
            description.disabled = !isOther;
            description.required = isOther;
            if (!isOther) description.value = '';
        };

        typeFields.forEach((field) => field.addEventListener('change', sync));
        sync();
    });
}

initTradeFlowFieldGuards();
initOtherBusinessDescription();

// Use one professional notification and confirmation experience throughout the
// application. Server flash messages and legacy Bootstrap alerts are promoted
// to SweetAlert automatically, while validation feedback stays inline.
function sweetAlertIcon(alert) {
    if (alert.classList.contains('alert-success')) return 'success';
    if (alert.classList.contains('alert-warning')) return 'warning';
    if (alert.classList.contains('alert-danger')) return 'error';
    return 'info';
}

function showTradeFlowAlert(alert) {
    // Context banners deliberately remain visible until the user leaves that
    // workspace. They are not transient notifications.
    if (!alert || alert.hasAttribute('data-tf-persistent-alert') || alert.dataset.tfSweetAlertShown === '1' || alert.classList.contains('d-none')) return;
    if (!window.Swal) return;

    alert.dataset.tfSweetAlertShown = '1';
    const icon = sweetAlertIcon(alert);
    const message = (alert.textContent || '').replace(/\s+/g, ' ').trim();
    if (!message) return;

    window.Swal.fire({
        icon,
        title: alert.dataset.tfAlertTitle || (icon === 'success' ? 'Completed' : icon === 'error' ? 'Please review' : 'TradeFlow'),
        text: message,
        toast: icon !== 'error',
        position: icon === 'error' ? 'center' : 'top-end',
        showConfirmButton: icon === 'error',
        timer: icon === 'error' ? undefined : 3500,
        timerProgressBar: icon !== 'error',
        confirmButtonText: 'OK',
    });
    alert.remove();
}

function scanTradeFlowAlerts(node = document) {
    if (node instanceof Element && node.matches?.('.alert-success, .alert-warning, .alert-danger, .alert-info')) showTradeFlowAlert(node);
    node.querySelectorAll?.('.alert-success, .alert-warning, .alert-danger, .alert-info').forEach(showTradeFlowAlert);
}

function initSweetAlertConfirmations(node = document) {
    const forms = node instanceof HTMLFormElement ? [node] : [...node.querySelectorAll?.('form[onsubmit*="confirm"]') || []];
    forms.forEach((form) => {
        const source = form.getAttribute('onsubmit') || '';
        const match = source.match(/confirm\(\s*['\"]([^'\"]+)['\"]\s*\)/);
        if (!match || form.dataset.tfConfirmMessage) return;
        form.dataset.tfConfirmMessage = match[1];
        form.removeAttribute('onsubmit');
        form.onsubmit = null;
    });
}

function askTradeFlowConfirmation({ title = 'Confirm action', text, confirmButtonText = 'Continue', confirmButtonColor = '#dc3545' }, onConfirm) {
    if (!window.Swal) {
        // Do not fall back to a browser confirmation dialog. SweetAlert is
        // loaded by every TradeFlow layout before this script.
        return;
    }
    window.Swal.fire({
        icon: 'warning', title, text, showCancelButton: true,
        confirmButtonText, cancelButtonText: 'Cancel', confirmButtonColor, reverseButtons: true,
    }).then((result) => { if (result.isConfirmed) onConfirm(); });
}

window.askTradeFlowConfirmation = askTradeFlowConfirmation;

function initManualConfirmationFields(node = document) {
    const fields = node instanceof HTMLInputElement
        ? (node.matches('[data-tf-manual-confirmation], input[name$="password_confirmation"]') ? [node] : [])
        : [...node.querySelectorAll?.('[data-tf-manual-confirmation], input[name$="password_confirmation"]') || []];

    fields.forEach((field) => {
        if (field.dataset.tfManualConfirmationReady === '1') return;
        field.dataset.tfManualConfirmationReady = '1';
        ['paste', 'drop', 'copy', 'cut'].forEach((eventName) => field.addEventListener(eventName, (event) => {
            event.preventDefault();
            if ((eventName === 'paste' || eventName === 'drop') && window.Swal) {
                window.Swal.fire({
                    icon: 'info',
                    title: 'Manual confirmation required',
                    text: 'For security, please type the confirmation password manually.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3200,
                    timerProgressBar: true,
                });
            }
        }));
    });
}

function initCompanyDeletionForms(node = document) {
    const forms = node instanceof HTMLFormElement ? [node] : [...node.querySelectorAll?.('form[data-tf-company-delete]') || []];
    forms.forEach((form) => { form.dataset.tfCompanyDeleteReady = '1'; });
}

function closeCompanyDeleteBackgroundControls() {
    // The confirmation is rendered in SweetAlert above the page. Close only
    // transient UI controls; their instances and selected values remain intact
    // when the dialog is dismissed.
    document.querySelectorAll('select.tomselected').forEach((select) => select.tomselect?.close());

    document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach((toggle) => {
        window.bootstrap?.Dropdown?.getInstance(toggle)?.hide();
    });

    if (window.jQuery?.fn?.select2) {
        window.jQuery('.select2-hidden-accessible').each(function closeSelect2() {
            window.jQuery(this).select2('close');
        });
    }

    if (document.activeElement instanceof HTMLSelectElement) document.activeElement.blur();
}

document.addEventListener('submit', (event) => {
    const deleteForm = event.target.closest?.('form[data-tf-company-delete]');
    if (deleteForm && deleteForm.dataset.tfCompanyDeleteApproved !== '1') {
        event.preventDefault();
        if (!window.Swal) return;
        const companyName = deleteForm.dataset.companyName || 'this company';
        const trigger = event.submitter instanceof HTMLElement
            ? event.submitter
            : deleteForm.querySelector('button[type="submit"], button:not([type])');
        closeCompanyDeleteBackgroundControls();
        window.Swal.fire({
            icon: 'warning',
            title: 'Permanently delete company?',
            text: companyName + ' and all of its operational records, staff accounts, transactions, invoices, and activity data will be permanently removed. Enter your Super Admin password to continue.',
            input: 'password',
            inputPlaceholder: 'Super Admin password',
            inputAttributes: { autocomplete: 'current-password', 'aria-label': 'Super Admin password' },
            showCancelButton: true,
            confirmButtonText: 'Verify & permanently delete',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#dc3545',
            reverseButtons: true,
            focusConfirm: false,
            allowEscapeKey: true,
            allowOutsideClick: true,
            returnFocus: true,
            willOpen: () => {
                closeCompanyDeleteBackgroundControls();
                document.body.classList.add('tf-company-delete-modal-open');
            },
            didOpen: (popup) => popup.querySelector('input[type="password"]')?.focus(),
            willClose: () => {
                closeCompanyDeleteBackgroundControls();
                document.body.classList.remove('tf-company-delete-modal-open');
            },
            didClose: () => {
                if (trigger?.isConnected) trigger.focus({ preventScroll: true });
            },
            inputValidator: (value) => value ? undefined : 'Enter your Super Admin password to continue.',
        }).then((result) => {
            if (!result.isConfirmed) return;
            const password = document.createElement('input');
            password.type = 'hidden';
            password.name = 'admin_password';
            password.value = result.value;
            deleteForm.querySelector('input[name="admin_password"]')?.remove();
            deleteForm.append(password);
            deleteForm.dataset.tfCompanyDeleteApproved = '1';
            deleteForm.requestSubmit();
        });
        return;
    }

    const form = event.target.closest?.('form[data-tf-confirm-message]');
    if (!form || form.dataset.tfConfirmApproved === '1') return;

    event.preventDefault();
    const action = form.querySelector('button[type="submit"], button:not([type])')?.textContent?.replace(/\s+/g, ' ').trim() || 'Continue';
    const proceed = () => {
        form.dataset.tfConfirmApproved = '1';
        form.requestSubmit();
    };
    if (!window.Swal) return proceed();

    window.Swal.fire({
        icon: 'warning',
        title: `${action}?`,
        text: form.dataset.tfConfirmMessage,
        showCancelButton: true,
        confirmButtonText: action,
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc3545',
        reverseButtons: true,
    }).then((result) => { if (result.isConfirmed) proceed(); });
}, true);

scanTradeFlowAlerts();
initSweetAlertConfirmations();
initManualConfirmationFields();
initCompanyDeletionForms();
new MutationObserver((changes) => changes.forEach((change) => {
    if (change.type === 'attributes') {
        scanTradeFlowAlerts(change.target);
        return;
    }
    change.addedNodes.forEach((node) => {
        if (!(node instanceof Element)) return;
        scanTradeFlowAlerts(node);
        initSweetAlertConfirmations(node);
        initManualConfirmationFields(node);
        initCompanyDeletionForms(node);
        initTradeFlowCodeLookups(node);
        initNonNegativeNumberGuards(node);
        initTradeFlowMoneyInputs(node);
        initTradeFlowPasswordControls(node);
    });
})).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });

// Monetary text inputs display grouped thousands for readability but submit a
// plain decimal value, so existing Laravel numeric validation/calculations
// continue to receive raw values.
function initTradeFlowMoneyInputs(root = document) {
    const fields = root.matches?.('[data-money-input]')
        ? [root]
        : [...(root.querySelectorAll?.('[data-money-input]') || [])];

    const raw = (value) => String(value || '').replace(/,/g, '').trim();
    const format = (value) => {
        const clean = raw(value);
        if (clean === '' || !/^\d*(?:\.\d{0,2})?$/.test(clean)) return clean;
        const [whole = '0', decimal] = clean.split('.');
        const grouped = Number.parseInt(whole || '0', 10).toLocaleString('en-US');
        return decimal === undefined ? grouped : `${grouped}.${decimal}`;
    };

    fields.forEach((field) => {
        if (field.dataset.moneyInputReady === '1') return;
        field.dataset.moneyInputReady = '1';
        field.value = format(field.value);
        field.addEventListener('focus', () => { field.value = raw(field.value); });
        field.addEventListener('blur', () => { field.value = format(field.value); });
        field.addEventListener('input', () => {
            const clean = raw(field.value).replace(/[^\d.]/g, '');
            const [whole = '', ...decimals] = clean.split('.');
            field.value = decimals.length ? `${whole}.${decimals.join('').slice(0, 2)}` : whole;
        });
        field.closest('form')?.addEventListener('submit', () => { field.value = raw(field.value); });
    });
}

window.initTradeFlowMoneyInputs = initTradeFlowMoneyInputs;
initTradeFlowMoneyInputs();

let tradeFlowPasswordFieldId = 0;

function passwordFeedback(input, kind, fallbackSelector = '') {
    const form = input.closest('form');
    const existing = fallbackSelector ? form?.querySelector(fallbackSelector) : null;
    if (existing) return existing;

    const marker = `tfPassword${kind}Feedback`;
    if (input.dataset[marker]) return document.getElementById(input.dataset[marker]);

    const feedback = document.createElement('div');
    feedback.className = 'invalid-feedback';
    feedback.dataset.tfPasswordFeedback = kind;
    const host = input.closest('.input-group') || input;
    host.insertAdjacentElement('afterend', feedback);
    feedback.id = `tf-password-${kind}-${++tradeFlowPasswordFieldId}`;
    input.dataset[marker] = feedback.id;
    return feedback;
}

function initTradeFlowPasswordControls(root = document) {
    const passwordInputs = root.matches?.('input[type="password"], input[type="text"][data-tf-password-field]')
        ? [root]
        : [...(root.querySelectorAll?.('input[type="password"], input[type="text"][data-tf-password-field]') || [])];

    passwordInputs.forEach((input) => {
        if (!input.name || !/(^|_)password(?:_confirmation)?$/.test(input.name)) return;
        input.dataset.tfPasswordField = '1';
        if (!input.id) input.id = `tf-password-field-${++tradeFlowPasswordFieldId}`;

        let group = input.closest('.input-group');
        if (!group) {
            group = document.createElement('div');
            group.className = 'input-group';
            input.parentNode?.insertBefore(group, input);
            group.append(input);
        }

        if (group.querySelector(`[data-tf-password-toggle="#${CSS.escape(input.id)}"]`)) return;
        const button = document.createElement('button');
        const icon = document.createElement('i');
        const iconId = `tf-password-icon-${++tradeFlowPasswordFieldId}`;
        button.type = 'button';
        button.className = 'btn btn-outline-secondary tf-password-toggle';
        button.dataset.tfPasswordToggle = `#${input.id}`;
        button.dataset.tfPasswordIcon = `#${iconId}`;
        button.setAttribute('aria-label', input.name.includes('confirmation') ? 'Show password confirmation' : 'Show password');
        icon.id = iconId;
        icon.className = 'bi bi-eye';
        button.append(icon);
        group.append(button);
    });

    const toggles = root.matches?.('[data-tf-password-toggle]')
        ? [root]
        : [...(root.querySelectorAll?.('[data-tf-password-toggle]') || [])];
    toggles.forEach((button) => {
        if (button.dataset.tfPasswordToggleReady === '1') return;
        button.dataset.tfPasswordToggleReady = '1';
        button.addEventListener('click', () => {
            const inputSelector = button.dataset.tfPasswordToggle;
            const iconSelector = button.dataset.tfPasswordIcon || `#${button.querySelector('i')?.id}`;
            togglePassword(inputSelector, iconSelector);
        });
    });

    const forms = root.matches?.('form') ? [root] : [...(root.querySelectorAll?.('form') || [])];
    forms.forEach((form) => {
        // These two wizard forms already provide their own single validation
        // state and inline feedback; keep them authoritative to avoid a
        // second listener/message for the same password fields.
        if (form.matches('[data-tf-register-form], [data-company-create-form]')) return;
        if (form.dataset.tfPasswordValidationReady === '1') return;
        const confirmations = [...form.querySelectorAll('input[name$="password_confirmation"]')];
        if (!confirmations.length) return;
        form.dataset.tfPasswordValidationReady = '1';

        const validate = () => {
            let valid = true;
            confirmations.forEach((confirmation) => {
                const passwordName = confirmation.name.replace(/_confirmation$/, '');
                const password = form.querySelector(`input[name="${CSS.escape(passwordName)}"]`);
                if (!password) return;

                password.minLength = 8;
                const strengthInvalid = Boolean(password.value) && !/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/.test(password.value);
                const mismatch = Boolean(confirmation.value) && password.value !== confirmation.value;
                const strengthMessage = 'Use at least 8 characters with uppercase, lowercase, number, and special character.';
                const matchMessage = 'Password and confirm password do not match.';
                const strength = passwordFeedback(password, 'strength');
                const match = passwordFeedback(confirmation, 'match', '[data-staff-password-match-error], [data-company-password-error]');

                password.classList.toggle('is-invalid', strengthInvalid);
                password.setCustomValidity(strengthInvalid ? strengthMessage : '');
                strength.textContent = strengthInvalid ? strengthMessage : '';
                strength.classList.toggle('d-block', strengthInvalid);

                confirmation.classList.toggle('is-invalid', mismatch);
                confirmation.setCustomValidity(mismatch ? matchMessage : '');
                match.textContent = mismatch ? matchMessage : '';
                match.classList.toggle('d-block', mismatch);
                valid = valid && !strengthInvalid && !mismatch;
            });
            return valid;
        };

        form.addEventListener('input', (event) => {
            if (event.target instanceof HTMLInputElement && /(password|password_confirmation)$/.test(event.target.name)) validate();
        });
        form.addEventListener('submit', (event) => {
            if (!validate()) {
                event.preventDefault();
                form.reportValidity();
            }
        });
        validate();
    });
}

window.initTradeFlowPasswordControls = initTradeFlowPasswordControls;
initTradeFlowPasswordControls();

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

// Staff/user drafts are intentionally disabled: credentials and uploads must
// never be persisted in browser storage.

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
        if (field.id && field.id.startsWith('bulkExpiryTracking')) field.id = `bulkExpiryTracking${index}`;
        if (field.tagName === 'INPUT') {
            field.value = field.type === 'number' && field.name.includes('low_stock_alert_qty') ? '10' : '';
            if (field.matches('[data-bulk-expiry-toggle]')) field.checked = false;
            if (field.matches('[data-bulk-expiry-date]')) field.disabled = true;
        }
    });
    template.querySelectorAll('label[for^="bulkExpiryTracking"]').forEach((label) => {
        label.htmlFor = `bulkExpiryTracking${index}`;
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

    const syncDeliveryViewDependency = () => {
        const deliveryGroup = groups.find((group) => group.querySelector('[data-permission-child][value="deliveries.view"]'));
        if (!deliveryGroup) return;

        const view = deliveryGroup.querySelector('[data-permission-child][value="deliveries.view"]');
        const dependentActions = [
            'deliveries.record_collection',
            'deliveries.update_status',
            'deliveries.upload_proof',
        ].map((permission) => deliveryGroup.querySelector(`[data-permission-child][value="${permission}"]`)).filter(Boolean);
        const hasDependentAction = dependentActions.some((input) => input.checked);

        if (hasDependentAction) view.checked = true;
        view.disabled = hasDependentAction;
        deliveryGroup.querySelector('[data-delivery-view-dependency]')?.classList.toggle('d-none', !hasDependentAction);
    };

    const syncGroup = (group) => {
        const parent = group.querySelector('[data-permission-module]');
        const children = [...group.querySelectorAll('[data-permission-child]')];
        if (!parent || !children.length) return;
        const selected = children.filter((child) => child.checked).length;
        parent.checked = selected === children.length;
        parent.indeterminate = selected > 0 && selected < children.length;
        group.querySelector('[data-permission-selected-count]')?.replaceChildren(document.createTextNode(String(selected)));
        group.classList.toggle('has-selected-permissions', selected > 0);
    };

    const syncMaster = () => {
        if (!master) return;
        const children = [...form.querySelectorAll('[data-permission-child]')];
        const selected = children.filter((child) => child.checked).length;
        master.checked = children.length > 0 && selected === children.length;
        master.indeterminate = selected > 0 && selected < children.length;
        form.querySelector('[data-permission-total-selected]')?.replaceChildren(document.createTextNode(`(${selected} selected)`));
    };

    const syncAll = () => {
        syncDeliveryViewDependency();
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
    const password = form.querySelector('[data-company-password]');
    const confirmation = form.querySelector('[data-company-password-confirmation]');
    const passwordError = form.querySelector('[data-company-password-error]');
    const submit = form.querySelector('[data-company-create-submit]');
    const validationStatus = form.querySelector('[data-company-create-status]');
    const permissionInputs = [...form.querySelectorAll('[data-permission-child]')];
    const phoneInputs = [...form.querySelectorAll('[data-tf-phone-visible]')];
    const passwordRule = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/;

    // Only inspect controls the browser can actually validate. In particular,
    // the phone component uses hidden transport inputs and permission inputs
    // are optional; neither should determine the message shown to the user.
    const validatableFields = () => [...form.elements].filter((field) => (
        !permissionInputs.includes(field)
        && field.willValidate
        && !field.disabled
        && field.type !== 'hidden'
    ));

    const fieldLabel = (field) => {
        const label = field.labels?.[0]?.textContent
            ?.replace(/\*/g, '')
            .replace(/\bOptional\b/gi, '')
            .replace(/\s+/g, ' ')
            .trim();

        return label || 'This field';
    };

    const validate = () => {
        // The visible control is authoritative for the user, while its hidden
        // sibling carries the normalized E.164 value to the server. Sync
        // first so both Company Phone and Owner Phone report their own state.
        phoneInputs.forEach((field) => window.TradeFlowPhone?.sync(field));
        const value = password?.value || '';
        const matches = value !== '' && value === (confirmation?.value || '');
        const strong = passwordRule.test(value);
        const showError = Boolean(confirmation?.value) && !matches;
        confirmation?.classList.toggle('is-invalid', showError);
        password?.classList.toggle('is-invalid', Boolean(value) && !strong);
        passwordError?.classList.toggle('d-block', showError);
        if (password) password.setCustomValidity(value && !strong ? 'Use a stronger password.' : '');
        if (confirmation) confirmation.setCustomValidity(showError ? 'Password and confirm password do not match.' : '');
        const invalidField = validatableFields().find((field) => !field.checkValidity());
        let message = '';
        if (value && !strong) message = 'Temporary Password must include uppercase, lowercase, number, and special character.';
        else if (confirmation?.value && !matches) message = 'Password and confirm password do not match.';
        else if (invalidField) message = invalidField.validity.valueMissing
            ? `${fieldLabel(invalidField)} is required.`
            : `${fieldLabel(invalidField)} is invalid.`;

        validationStatus?.classList.toggle('d-none', !message);
        if (validationStatus) validationStatus.textContent = message;
        if (submit) submit.disabled = Boolean(message) || !form.checkValidity() || !strong || !matches;
    };

    form.querySelectorAll('input, select, textarea').forEach((field) => {
        field.addEventListener('input', validate);
        field.addEventListener('change', validate);
        field.addEventListener('blur', validate);
    });
    password?.addEventListener('input', validate);
    confirmation?.addEventListener('input', validate);
    permissionInputs.forEach((input) => input.addEventListener('change', validate));
    form.addEventListener('submit', (event) => {
        validate();
        if (!form.checkValidity() || submit?.disabled) {
            event.preventDefault();
            form.reportValidity();
            return;
        }
        if (submit) {
            submit.disabled = true;
            submit.dataset.submitting = '1';
            submit.textContent = 'Creating Company…';
        }
    });
    validate();
    // Browsers may restore or autofill fields after the initial synchronous
    // validation pass without dispatching input/change events. Re-check on
    // the next paint so a completed Company Name never leaves a stale error.
    requestAnimationFrame(validate);
}

document.querySelectorAll('[data-company-create-form]').forEach(initCompanyCreateForm);

// Reusable, opt-in integer guard. Pages opt in with .js-whole-number so
// existing decimal-based financial workflows remain untouched.
(() => {
    const selector = 'input.js-whole-number, input[data-whole-number]';
    const blockedKeys = new Set(['e', 'E', '+', '-', '.', ',']);

    const feedbackFor = (input) => {
        if (!input.id) input.id = `whole-number-${Math.random().toString(36).slice(2)}`;

        let feedback = document.querySelector(`[data-whole-number-feedback="${input.id}"]`);
        if (feedback) return feedback;

        feedback = document.createElement('div');
        feedback.className = 'invalid-feedback d-none';
        feedback.dataset.wholeNumberFeedback = input.id;
        feedback.textContent = 'Only whole numbers are allowed.';
        (input.closest('.input-group') || input).insertAdjacentElement('afterend', feedback);
        return feedback;
    };

    const setError = (input, invalid) => {
        const feedback = feedbackFor(input);
        input.classList.toggle('is-invalid', invalid);
        feedback.classList.toggle('d-none', !invalid);
    };

    const normalize = (input) => {
        const value = input.value.trim();
        const valid = value === '' || /^\d+$/.test(value);
        setError(input, !valid);
        return valid;
    };

    const initialize = (root = document) => {
        root.querySelectorAll?.(selector).forEach((input) => {
            input.setAttribute('step', '1');
            input.setAttribute('inputmode', 'numeric');
            normalize(input);
        });
    };

    window.initTradeFlowWholeNumberInputs = initialize;

    document.addEventListener('DOMContentLoaded', () => initialize());

    document.addEventListener('keydown', (event) => {
        const input = event.target.closest?.(selector);
        if (!input || event.ctrlKey || event.metaKey || event.altKey) return;
        if (blockedKeys.has(event.key)) {
            event.preventDefault();
            setError(input, true);
        }
    });

    document.addEventListener('paste', (event) => {
        const input = event.target.closest?.(selector);
        if (!input) return;

        const pasted = event.clipboardData?.getData('text')?.trim() || '';
        if (!/^\d+$/.test(pasted)) {
            event.preventDefault();
            setError(input, true);
        }
    });

    document.addEventListener('input', (event) => {
        const input = event.target.closest?.(selector);
        if (input) normalize(input);
    });
})();

// Laravel returns invalid fields after a failed submission. Once the user
// starts correcting one of those fields, clear only that field's stale
// server-side warning; the next submit still runs the normal browser and
// backend validation rules.
(() => {
    const fieldSelector = 'input, select, textarea';

    const feedbackScope = (field) => {
        if (field.matches('[data-tf-phone-visible]')) {
            return field.closest('[data-tf-phone-field]');
        }

        return field.closest('.input-group')?.parentElement || field.parentElement;
    };

    const markServerFeedback = (field) => {
        if (!field.classList.contains('is-invalid')) return;

        field.dataset.tfServerInvalid = '1';
        feedbackScope(field)?.querySelectorAll('.invalid-feedback').forEach((feedback) => {
            feedback.dataset.tfServerFeedback = '1';
        });
    };

    const clearServerFeedback = (field) => {
        if (field.dataset.tfServerInvalid !== '1') return;

        field.classList.remove('is-invalid');
        delete field.dataset.tfServerInvalid;

        feedbackScope(field)?.querySelectorAll('[data-tf-server-feedback="1"]').forEach((feedback) => {
            feedback.classList.remove('d-block');
            feedback.classList.add('d-none');
            delete feedback.dataset.tfServerFeedback;
        });
    };

    const initialise = () => {
        document.querySelectorAll(fieldSelector + '.is-invalid').forEach(markServerFeedback);
    };

    document.addEventListener('DOMContentLoaded', initialise);
    if (document.readyState !== 'loading') initialise();

    ['input', 'change'].forEach((eventName) => {
        document.addEventListener(eventName, (event) => {
            const field = event.target.closest?.(fieldSelector);
            if (field) clearServerFeedback(field);
        });
    });
})();
