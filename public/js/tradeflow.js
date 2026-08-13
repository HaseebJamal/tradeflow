// Sidebar preferences belong to the authenticated account, never to the
// browser as a whole. A missing preference is intentionally expanded.
const sidebarStorageKey = document.body?.dataset.tfSidebarPreferenceKey || null;
const desktopSidebarQuery = window.matchMedia('(min-width: 1200px)');
const tabletSidebarQuery = window.matchMedia('(min-width: 768px) and (max-width: 1199.98px)');
const mobileSidebarQuery = window.matchMedia('(max-width: 767.98px)');
const isDesktopSidebar = () => desktopSidebarQuery.matches;
const isMobileSidebar = () => mobileSidebarQuery.matches;

function savedSidebarIsCollapsed() {
    if (!sidebarStorageKey) return false;

    try {
        return window.localStorage.getItem(sidebarStorageKey) === '1';
    } catch (_) {
        return false;
    }
}

function saveSidebarPreference(isCollapsed) {
    if (!sidebarStorageKey) return;

    try {
        // This is called only by the explicit desktop collapse control. Viewport
        // changes must stay temporary and never replace a user's choice.
        window.localStorage.setItem(sidebarStorageKey, isCollapsed ? '1' : '0');
    } catch (_) {
        // Leave the current visual state intact when storage is unavailable.
    }
}

function syncSidebarControls() {
    const isOpen = document.body.classList.contains('sidebar-open');
    document.querySelectorAll('[data-tf-sidebar-toggle]').forEach((button) => {
        button.setAttribute('aria-expanded', isMobileSidebar() ? String(isOpen) : 'false');
        button.setAttribute('aria-label', isMobileSidebar() && isOpen ? 'Close sidebar' : (isMobileSidebar() ? 'Open sidebar' : 'Toggle sidebar'));
    });
}

function openSidebar() {
    if (!isMobileSidebar()) return;
    document.body.classList.add('sidebar-open');
    syncSidebarControls();
}

function closeSidebar() {
    document.body.classList.remove('sidebar-open');
    syncSidebarControls();
}

function syncSidebarMode() {
    closeSidebar();

    if (isDesktopSidebar()) {
        document.body.classList.toggle('sidebar-collapsed', savedSidebarIsCollapsed());
    } else if (tabletSidebarQuery.matches) {
        // Tablet uses the compact icon rail and never reserves the desktop width.
        document.body.classList.add('sidebar-collapsed');
    } else {
        // Mobile uses an off-canvas drawer with no content offset.
        document.body.classList.remove('sidebar-collapsed');
    }

    syncSidebarControls();
}

function toggleSidebar() {
    if (isDesktopSidebar()) {
        document.body.classList.toggle('sidebar-collapsed');
        saveSidebarPreference(document.body.classList.contains('sidebar-collapsed'));
        syncSidebarControls();
        return;
    }

    if (isMobileSidebar()) {
        document.body.classList.contains('sidebar-open') ? closeSidebar() : openSidebar();
    }
}

window.openSidebar = openSidebar;
window.closeSidebar = closeSidebar;
window.toggleSidebar = toggleSidebar;

syncSidebarMode();

document.querySelectorAll('[data-tf-sidebar-toggle]').forEach((button) => {
    button.addEventListener('click', toggleSidebar);
});

document.querySelectorAll('[data-tf-sidebar-close], [data-tf-sidebar-overlay]').forEach((element) => {
    element.addEventListener('click', closeSidebar);
});

window.addEventListener('resize', syncSidebarMode);

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
        closeSidebar();
    }
});

document.querySelectorAll('[data-tf-sidebar] a').forEach((link) => {
    link.addEventListener('click', () => {
        if (isMobileSidebar()) closeSidebar();
    });
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

// Verification controls can appear in the Company Details modal. Moving their
// child modals to the document body avoids nested-modal clipping and stale state.
document.querySelectorAll('[data-tf-document-modal]').forEach((modal) => {
    document.body.append(modal);
});

// A secondary confirmation must not hide and recreate the modal it belongs to.
// Keep the parent stable, then layer a single non-Bootstrap backdrop beneath the
// child modal. This prevents focus/backdrop churn when an action is confirmed.
const tradeFlowNestedModalStates = new WeakMap();

window.clearTradeFlowNestedModal = (modal) => {
    const state = tradeFlowNestedModalStates.get(modal);
    state?.backdrop?.remove();
    if (state?.parentModal?.classList.contains('show')) {
        state.parentInstance?._focustrap?.activate();
    }
    tradeFlowNestedModalStates.delete(modal);
};

window.openTradeFlowNestedModal = (modal, parentModal = null) => {
    if (!modal || !window.bootstrap?.Modal) return null;

    window.clearTradeFlowNestedModal(modal);

    if (parentModal && parentModal !== modal) {
        const backdrop = document.createElement('div');
        const parentInstance = window.bootstrap.Modal.getInstance(parentModal);
        backdrop.className = 'tf-nested-modal-backdrop';
        backdrop.setAttribute('aria-hidden', 'true');
        document.body.append(backdrop);
        // Bootstrap focus traps are otherwise both active while the child is
        // visible, which pulls focus back to the parent confirmation trigger.
        parentInstance?._focustrap?.deactivate();
        tradeFlowNestedModalStates.set(modal, { backdrop, parentModal, parentInstance });
    }

    const instance = window.bootstrap.Modal.getOrCreateInstance(modal);
    instance.show();
    return instance;
};

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

function positionTradeFlowTomSelectDropdown(control, force = false) {
    if (!control || (!control.isOpen && !force)) return;

    const rect = control.control.getBoundingClientRect();
    const viewportPadding = 12;
    const maxDropdownHeight = 260;
    let spaceBelow = window.innerHeight - rect.bottom - viewportPadding;
    let spaceAbove = rect.top - viewportPadding;
    const containingModal = control.wrapper?.closest('.modal') || control.input?.closest('.modal');
    if (containingModal) {
        const modalBody = control.wrapper?.closest('.modal-body') || containingModal.querySelector('.modal-body');
        const modalFooter = containingModal.querySelector('.modal-footer');
        const bodyRect = modalBody?.getBoundingClientRect();
        const footerRect = modalFooter?.getBoundingClientRect();
        // The menu is mounted beside modal-content, so clamp it to the visible
        // body range. The footer is a hard boundary: an open menu may never
        // cover the form actions below it.
        if (bodyRect) {
            spaceAbove = Math.min(spaceAbove, Math.max(0, rect.top - bodyRect.top - 8));
            spaceBelow = Math.min(spaceBelow, Math.max(0, (footerRect?.top ?? bodyRect.bottom) - rect.bottom - 8));
        }
    }
    const dropdownContent = control.dropdown.querySelector('.ts-dropdown-content');
    const dropdownSearch = control.dropdown.querySelector('.dropdown-input-wrap');
    const searchHeight = dropdownSearch?.offsetHeight || 0;
    const naturalOptionsHeight = Math.min(220, Math.max(40, dropdownContent?.scrollHeight || 40));
    const preferredHeight = Math.min(maxDropdownHeight, searchHeight + naturalOptionsHeight);
    // Keep the normal direction downward whenever there is enough room for
    // the search input and one compact option. The remaining options can
    // scroll; a shortened menu below the trigger is less surprising than an
    // unnecessary upward flip after the page has been scrolled.
    const minimumDownwardHeight = Math.min(preferredHeight, Math.max(72, searchHeight + 40));
    const openUpward = spaceBelow < minimumDownwardHeight && spaceAbove > spaceBelow;
    const usableSpace = openUpward ? spaceAbove : spaceBelow;
    const availableHeight = Math.min(maxDropdownHeight, Math.max(0, usableSpace));
    const optionListHeight = Math.max(0, Math.min(220, availableHeight - searchHeight));

    Object.assign(control.dropdown.style, {
        maxHeight: `${availableHeight}px`,
    });
    if (dropdownContent) {
        Object.assign(dropdownContent.style, {
            maxHeight: `${optionListHeight}px`,
            overflowY: 'auto',
        });
    }

    if (control.dropdown.parentElement === control.wrapper) {
        // Inline menus move with their own control while any dashboard, form,
        // or modal scrolls. This avoids the stale viewport position a body
        // portal can retain after a dynamically-added form section moves.
        // Modal-body remains a hard visual boundary, so its footer is never
        // covered by an open select menu.
        control.wrapper.classList.toggle('tf-tom-select-up', openUpward);
        Object.assign(control.dropdown.style, {
            bottom: openUpward ? 'calc(100% + 4px)' : 'auto',
            left: '0',
            top: openUpward ? 'auto' : 'calc(100% + 4px)',
            width: '100%',
        });
        return;
    }

    // Use the actual mounted parent instead of the original configuration.
    // Tom Select versions may normalize `dropdownParent` to an element.
    if (control.dropdown.parentElement !== document.body) return;

    const viewportWidth = window.innerWidth;
    const width = Math.min(rect.width, Math.max(0, viewportWidth - (viewportPadding * 2)));
    const left = Math.max(
        viewportPadding,
        Math.min(rect.left + window.scrollX, window.scrollX + viewportWidth - width - viewportPadding)
    );
    const dropdownHeight = Math.min(control.dropdown.offsetHeight || availableHeight, availableHeight);
    const top = window.scrollY + (openUpward ? rect.top - dropdownHeight : rect.bottom);

    control.wrapper.classList.toggle('tf-tom-select-up', openUpward);
    const safeWidth = `${Math.max(0, width)}px`;
    // Tom Select's own placement routine and third-party CSS both position
    // body portals. A body portal is document-positioned, so its live viewport
    // rectangle must be converted to document coordinates with scrollX/Y.
    // Mixing those coordinates with `position: fixed` is what made menus jump
    // above their trigger after scrolling.
    [
        ['bottom', 'auto'],
        ['left', `${left}px`],
        ['min-width', safeWidth],
        ['position', 'absolute'],
        ['top', `${top}px`],
        ['transform', 'none'],
        ['width', safeWidth],
        ['z-index', '1080'],
    ].forEach(([property, value]) => control.dropdown.style.setProperty(property, value, 'important'));
}

function positionOpenTradeFlowTomSelectDropdowns() {
    document.querySelectorAll('select.tomselected').forEach((element) => positionTradeFlowTomSelectDropdown(element.tomselect));
}

let tradeFlowTomSelectPositionFrame = null;
function queueTradeFlowTomSelectPosition() {
    if (tradeFlowTomSelectPositionFrame !== null) return;

    tradeFlowTomSelectPositionFrame = requestAnimationFrame(() => {
        tradeFlowTomSelectPositionFrame = null;
        positionOpenTradeFlowTomSelectDropdowns();
    });
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

window.addEventListener('resize', queueTradeFlowTomSelectPosition);
document.addEventListener('scroll', queueTradeFlowTomSelectPosition, { capture: true, passive: true });

window.initTradeFlowTomSelect = function initTradeFlowTomSelect(root = document, { force = false } = {}) {
    if (!window.TomSelect) return;

    const selects = root.matches?.('select:not([data-native-select])')
        ? [root]
        : [...(root.querySelectorAll?.('select:not([data-native-select])') || [])];

    selects.forEach((element) => {
        // SweetAlert controls are intentionally native. Its focus trap and
        // transient DOM do not need an enhanced select instance.
        if (element.closest('.swal2-container')) return;

        // Initializing a select inside a hidden Bootstrap modal can leave its
        // enhanced search input in an invalid layout. Defer it until
        // Bootstrap emits shown.bs.modal below.
        const containingModal = element.closest('.modal');
        if (containingModal && !containingModal.classList.contains('show')) return;

        if (element.tomselect && !force) return;
        if (element.tomselect && force) element.tomselect.destroy();
        if (element.disabled) return;

        const placeholderOption = [...element.options].find((option) => option.value === '');
        const isMultiple = element.multiple;
        const canClear = isMultiple || !element.required;
        // A page/card can clip an inline menu (especially dynamic product
        // sections). Every non-modal selector therefore uses one body portal.
        // The shared fixed-coordinate positioner above keeps that portal
        // visually attached to its control during any scroll.
        const dropdownParent = containingModal ? null : 'body';

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
            // Keep the filter input inside the open menu, above the option
            // list. The closed control must display only its selected value.
            plugins: {
                dropdown_input: {},
                ...(canClear ? { clear_button: { title: 'Clear selection' } } : {}),
            },
            dropdownParent,
            // The shared positioner owns the only upward-flip decision. Start
            // every Tom Select downward so its internal `auto` heuristic
            // cannot momentarily place a menu above an otherwise usable field.
            position: 'bottom',
            render: {
                no_results: () => '<div class="no-results">No matching records found</div>',
                option: (data, escape) => `<div class="tf-tom-select-option"><span class="tf-tom-select-option-label">${escape(data.text)}</span></div>`,
            },
        });

        const dropdownSearchInput = control.dropdown.querySelector('.dropdown-input');
        if (dropdownSearchInput) {
            dropdownSearchInput.placeholder = 'Search options…';
            dropdownSearchInput.setAttribute('aria-label', 'Search options');
        }

        // Tom Select runs its own placement routine after opening. Replace it
        // with the shared viewport-based routine so a body-portaled menu never
        // uses stale page-load offsets after the dashboard has scrolled.
        if (typeof control.positionDropdown === 'function') {
            control.positionDropdown = () => positionTradeFlowTomSelectDropdown(control, true);
        }

        // When the menu is portaled to body, lock its width to the originating
        // control so it never inherits the page or sidebar width.
        control.on('dropdown_open', () => {
            // Re-render so the selected option stays in the list with its
            // visual indicator after opening an ordinary single-select.
            if (!isMultiple && element.dataset.hideSelected !== 'true') control.refreshOptions(false);
            document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach((toggle) => {
                window.bootstrap?.Dropdown?.getInstance(toggle)?.hide();
            });
            requestAnimationFrame(() => requestAnimationFrame(() => {
                syncTradeFlowTomSelectSelectedOption(control);
                positionTradeFlowTomSelectDropdown(control);
            }));
        });
        control.on('item_add', () => requestAnimationFrame(() => syncTradeFlowTomSelectSelectedOption(control)));
        control.on('item_remove', () => requestAnimationFrame(() => syncTradeFlowTomSelectSelectedOption(control)));
        control.on('dropdown_close', () => control.wrapper.classList.remove('tf-tom-select-up'));
    });
};

window.reinitializeTradeFlowTomSelect = (root = document) => window.initTradeFlowTomSelect(root, { force: true });

window.initTradeFlowTomSelect();
document.addEventListener('shown.bs.modal', (event) => {
    const modal = event.target;
    const modalDialog = modal?.querySelector('.modal-dialog');
    // Rebuild only stale instances. This retains each native
    // select value while ensuring each modal owns its own menu stack.
    const hasStaleModalSelect = modalDialog && [...modal.querySelectorAll('select')]
        .some((element) => {
            const expectedParent = element.tomselect?.wrapper;
            return element.tomselect && element.tomselect.dropdown.parentElement !== expectedParent;
        });

    window.initTradeFlowTomSelect(modal, { force: Boolean(hasStaleModalSelect) });
});
document.addEventListener('hidden.bs.modal', (event) => {
    event.target?.querySelectorAll('select.tomselected').forEach((element) => element.tomselect?.close());
});
document.addEventListener('tradeflow:content-loaded', (event) => window.initTradeFlowTomSelect(event.target || document));

// Opt-in only: server-paginated tables remain untouched. The Unit and
// Category directories use client-side DataTables over their scoped records.
window.initTradeFlowDataTables = function initTradeFlowDataTables(root = document) {
    if (!window.jQuery?.fn?.DataTable) return;

    const containers = root.matches?.('[data-tf-datatable]')
        ? [root]
        : [...(root.querySelectorAll?.('[data-tf-datatable]') || [])];

    containers.forEach((container) => {
        const table = container.matches?.('table') ? container : container.querySelector('table');
        if (!table || window.jQuery.fn.dataTable.isDataTable(table)) return;

        table.querySelectorAll('[data-tf-datatable-empty]').forEach((row) => row.remove());
        window.jQuery(table).DataTable({
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            searching: false,
            order: [],
            autoWidth: false,
            columnDefs: [{ orderable: false, targets: -1 }],
            language: {
                emptyTable: 'No records found.',
                info: 'Showing _START_ to _END_ of _TOTAL_ results',
                infoEmpty: 'Showing 0 to 0 of 0 results',
            },
        });
    });
};

window.initTradeFlowDataTables();

// Tables declare their action column in the header. Standardize that final
// column and its controls without changing the links, forms, or dropdowns
// rendered by individual modules.
function initTradeFlowTableActionColumns(root = document) {
    const tables = new Set();
    if (root instanceof HTMLTableElement) tables.add(root);
    root.closest?.('table') && tables.add(root.closest('table'));
    root.querySelectorAll?.('table').forEach((table) => tables.add(table));

    tables.forEach((table) => {
        // The POS cart owns a compact, stateful action layout (Edit/Delete or
        // Save/Cancel), so leave its purpose-built column sizing untouched.
        if (table.classList.contains('tf-pos-cart-table')) return;

        const headerRow = table.tHead?.rows[table.tHead.rows.length - 1];
        const header = headerRow?.cells[headerRow.cells.length - 1];
        if (!header || header.textContent.replace(/\s+/g, ' ').trim().toLowerCase() !== 'actions') return;

        table.classList.add('tf-has-actions-column');
        header.classList.add('tf-table-action-cell');

        [...table.tBodies].forEach((body) => [...body.rows].forEach((row) => {
            const cell = row.cells[row.cells.length - 1];
            if (!cell || cell.colSpan > 1) return;
            cell.classList.add('tf-table-action-cell');
            if (cell.querySelector(':scope > .tf-table-action-group')) return;

            const directElements = [...cell.children];
            const contentNodes = [...cell.childNodes].filter((node) => node.nodeType !== Node.TEXT_NODE || node.textContent.trim());
            const reusableGroup = directElements.length === 1
                && contentNodes.length === 1
                && directElements[0].matches('.btn-group, .dropdown, .d-flex, .d-inline-flex');
            const group = reusableGroup ? directElements[0] : document.createElement('div');

            ['d-flex', 'd-inline-flex', 'gap-1', 'gap-2', 'gap-3', 'align-items-center', 'justify-content-center', 'justify-content-end'].forEach((className) => cell.classList.remove(className));
            group.classList.add('tf-table-action-group');
            if (!reusableGroup) {
                [...cell.childNodes].forEach((node) => group.appendChild(node));
                cell.appendChild(group);
            }
        }));
    });
}

window.initTradeFlowTableActionColumns = initTradeFlowTableActionColumns;
initTradeFlowTableActionColumns();

function initTradeFlowSemanticActions(root = document) {
    const menus = new Set();
    if (root instanceof Element && root.matches('.dropdown-menu')) menus.add(root);
    root.querySelectorAll?.('.dropdown-menu').forEach((menu) => menus.add(menu));

    menus.forEach((menu) => {
        if (menu.matches('.iti__country-list, .ts-dropdown')) return;

        const toggle = menu.__tradeFlowActionToggle
            || menu.closest('.dropdown, .btn-group')?.querySelector('[data-bs-toggle="dropdown"]');
        const isActionLabel = toggle?.textContent.replace(/\s+/g, ' ').trim().toLowerCase() === 'actions';
        const isActionMenu = Boolean(toggle && (
            isActionLabel || toggle.closest('tr, .table-responsive, .dataTables_wrapper, .dataTables_scrollBody, .staff-table-wrap, [data-tf-action-dropdown]')
        ));
        if (!isActionMenu) return;

        let hasAction = false;
        menu.querySelectorAll('.dropdown-item').forEach((item) => {
            const label = item.textContent.replace(/\s+/g, ' ').trim().toLowerCase();
            if (!label) return;

            const variant = /permanently\s+delete|\bdelete\b|\bremove\b|\breject\b|end\s+(?:trial|access)|close\s+(?:register|permanently)|\bvoid\b/.test(label)
                ? 'danger'
                : /\bsuspend\b|\barchive\b|\bexpire\b|\bretry\b/.test(label)
                    ? 'warning'
                    : /\brestore\b|\breactivate\b|\bactivate\b|\bapprove\b|mark\s+delivered|\brenew\b|\bextend\b|\bverify\b/.test(label)
                        ? 'success'
                        : /\bmanage\b|\bedit\b|\bconfigure\b|\bassign\b|\baccess\b|\bstart\b/.test(label)
                            ? 'primary'
                            : 'neutral';

            item.classList.remove(
                'tf-action-item--neutral',
                'tf-action-item--primary',
                'tf-action-item--success',
                'tf-action-item--warning',
                'tf-action-item--danger',
            );
            item.classList.add(`tf-action-item--${variant}`);
            hasAction = true;
        });

        menu.classList.toggle('tf-semantic-action-menu', hasAction);
    });
}

window.initTradeFlowSemanticActions = initTradeFlowSemanticActions;
initTradeFlowSemanticActions();

function initTradeFlowBootstrapDropdowns(root = document) {
    const toggles = root.matches?.('[data-bs-toggle="dropdown"]')
        ? [root]
        : [...(root.querySelectorAll?.('[data-bs-toggle="dropdown"]') || [])];

    toggles.forEach((toggle) => {
        const menu = toggle.parentElement?.querySelector(':scope > .dropdown-menu');
        const isProfileImageMenu = Boolean(toggle.closest('.tf-profile-image-dropdown'));
        const isActionLabel = toggle.textContent.replace(/\s+/g, ' ').trim().toLowerCase() === 'actions';
        const isActionMenu = Boolean(menu && !isProfileImageMenu && (
            isActionLabel || toggle.closest('tr, .table-responsive, .dataTables_wrapper, .dataTables_scrollBody, .staff-table-wrap, [data-tf-action-dropdown]')
        ));

        if (!isActionMenu) {
            if (toggle.dataset.tradeFlowDropdownReady !== '1') {
                toggle.dataset.tradeFlowDropdownReady = '1';
                toggle.setAttribute('data-bs-display', 'dynamic');
                toggle.setAttribute('data-bs-boundary', 'viewport');
            }
            return;
        }

        if (toggle.dataset.tfActionDropdownReady === '1') return;

        // Row Actions menus are portaled only while open so responsive table
        // overflow cannot crop them. Their fixed coordinates are managed by
        // the shared action-menu controller below, not by Popper.
        toggle.dataset.tradeFlowDropdownReady = '1';
        toggle.dataset.tfActionDropdown = '1';
        toggle.dataset.tfActionDropdownReady = '1';
        toggle.setAttribute('data-bs-display', 'static');
        toggle.setAttribute('data-bs-boundary', 'viewport');
        menu.classList.remove('tf-action-dropdown-portal');
        menu.classList.add('dropdown-menu-end');
        ['display', 'position', 'inset', 'top', 'right', 'bottom', 'left', 'transform', 'width', 'max-width', 'height', 'min-height', 'max-height', 'overflow', 'overflow-x', 'overflow-y', 'visibility', 'z-index'].forEach((property) => {
            menu.style.removeProperty(property);
        });

        if (!window.bootstrap?.Dropdown) return;

        window.bootstrap.Dropdown.getInstance(toggle)?.dispose();
        window.bootstrap.Dropdown.getOrCreateInstance(toggle, {
            boundary: 'viewport',
            display: 'static',
        });
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
        window.initTradeFlowDataTables(root);
        initTradeFlowTableActionColumns(root);
        initTradeFlowSemanticActions(root);
        initTradeFlowBootstrapDropdowns(root);
        initTradeFlowNotificationDropdowns(root);
        initNonNegativeNumberGuards(root);
        initTradeFlowSidebarSubmenus(root);
    }));
}).observe(document.documentElement, { childList: true, subtree: true });

function isTradeFlowActionDropdown(toggle) {
    return toggle instanceof Element
        && toggle.matches('[data-bs-toggle="dropdown"]')
        && !toggle.closest('.tf-profile-image-dropdown')
        && (toggle.dataset.tfActionDropdown === '1' || toggle.textContent.replace(/\s+/g, ' ').trim().toLowerCase() === 'actions');
}

function getTradeFlowActionMenu(toggle) {
    // Bootstrap button groups are valid dropdown hosts too. Super Admin
    // tables use a View/Manage button beside a three-dot trigger, so both
    // structures must resolve to the same direct action menu.
    return toggle?.closest('.dropdown, .btn-group')?.querySelector(':scope > .dropdown-menu') || null;
}

function positionTradeFlowActionMenu(toggle) {
    const menu = toggle?.__tradeFlowActionMenu;
    if (!menu || menu.parentElement !== document.body) return;

    const padding = 8;
    const gap = 6;
    const rect = toggle.getBoundingClientRect();

    // Super Admin action menus stay as one compact list. They open below by
    // default and flip only when that is required to keep every item visible.
    menu.style.setProperty('left', '0px', 'important');
    menu.style.setProperty('top', '0px', 'important');
    menu.style.setProperty('max-height', 'none', 'important');
    menu.style.setProperty('visibility', 'hidden', 'important');

    const menuRect = menu.getBoundingClientRect();
    const menuWidth = Math.min(menuRect.width || 220, Math.max(0, window.innerWidth - (padding * 2)));
    const left = Math.max(padding, Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - padding));
    const below = rect.bottom + gap;
    const availableBelow = Math.max(72, window.innerHeight - below - padding);
    const actualBelow = Math.max(0, window.innerHeight - below - padding);
    const actualAbove = Math.max(0, rect.top - gap - padding);
    const isSuperAdminMenu = menu.classList.contains('tf-super-admin-action-menu');
    // Keep Super Admin menus opening downward. If the table is near the
    // viewport edge, move the page just enough to reveal the whole compact
    // menu; a last-resort upward flip is used only at the document bottom.
    if (isSuperAdminMenu && actualBelow < menuRect.height) {
        const documentBottom = Math.max(document.documentElement.scrollHeight, document.body.scrollHeight);
        const remainingPageScroll = Math.max(0, documentBottom - (window.scrollY + window.innerHeight));
        const requiredScroll = Math.ceil(menuRect.height - actualBelow + padding);
        if (remainingPageScroll > 0) {
            window.scrollBy({ top: Math.min(requiredScroll, remainingPageScroll), behavior: 'auto' });
            return;
        }
    }
    const shouldFlipUp = isSuperAdminMenu
        && actualBelow < menuRect.height
        && actualAbove >= menuRect.height;
    const maxHeight = isSuperAdminMenu ? 'none' : `${availableBelow}px`;
    const top = shouldFlipUp ? rect.top - gap - menuRect.height : below;

    menu.style.setProperty('left', `${Math.round(left)}px`, 'important');
    menu.style.setProperty('top', `${Math.round(top)}px`, 'important');
    menu.style.setProperty('max-height', maxHeight, 'important');
    menu.style.setProperty('overflow-y', isSuperAdminMenu ? 'visible' : 'auto', 'important');
    menu.style.setProperty('visibility', 'visible', 'important');
}

function portalTradeFlowActionMenu(toggle) {
    if (!isTradeFlowActionDropdown(toggle)) return;

    const menu = getTradeFlowActionMenu(toggle);
    if (!menu || menu.parentElement === document.body) return;

    const placeholder = document.createComment('tradeflow-action-menu');
    menu.parentNode.insertBefore(placeholder, menu);
    toggle.__tradeFlowActionMenu = menu;
    toggle.__tradeFlowActionMenuPlaceholder = placeholder;
    toggle.__tradeFlowActionMenuStyle = menu.getAttribute('style');
    menu.__tradeFlowActionToggle = toggle;
    menu.classList.add('tf-action-dropdown-portal');
    if (window.location.pathname.includes('/admin/')) {
        menu.classList.add('tf-super-admin-action-menu');
    }
    document.body.appendChild(menu);

    menu.style.setProperty('display', 'block', 'important');
    menu.style.setProperty('position', 'fixed', 'important');
    menu.style.setProperty('inset', 'auto', 'important');
    menu.style.setProperty('right', 'auto', 'important');
    menu.style.setProperty('bottom', 'auto', 'important');
    menu.style.setProperty('transform', 'none', 'important');
    menu.style.setProperty('z-index', '1080', 'important');
    menu.style.setProperty('visibility', 'hidden', 'important');
}

function restoreTradeFlowActionMenu(toggle) {
    const menu = toggle?.__tradeFlowActionMenu;
    const placeholder = toggle?.__tradeFlowActionMenuPlaceholder;
    if (!menu || !placeholder?.parentNode) return;

    placeholder.parentNode.insertBefore(menu, placeholder);
    placeholder.remove();
    menu.classList.remove('tf-action-dropdown-portal');
    menu.classList.remove('tf-super-admin-action-menu');
    if (toggle.__tradeFlowActionMenuStyle === null) menu.removeAttribute('style');
    else menu.setAttribute('style', toggle.__tradeFlowActionMenuStyle);
    delete menu.__tradeFlowActionToggle;
    delete toggle.__tradeFlowActionMenu;
    delete toggle.__tradeFlowActionMenuPlaceholder;
    delete toggle.__tradeFlowActionMenuStyle;
}

function closeTradeFlowActionDropdowns(except = null) {
    document.querySelectorAll('[data-bs-toggle="dropdown"][aria-expanded="true"]').forEach((toggle) => {
        if (toggle === except) return;
        if (isTradeFlowActionDropdown(toggle)) window.bootstrap?.Dropdown.getInstance(toggle)?.hide();
    });
}

let tradeFlowActionMenuUpdateFrame;
function updateTradeFlowActionDropdowns() {
    cancelAnimationFrame(tradeFlowActionMenuUpdateFrame);
    tradeFlowActionMenuUpdateFrame = requestAnimationFrame(() => {
        document.querySelectorAll('[data-bs-toggle="dropdown"][aria-expanded="true"]').forEach((toggle) => {
            if (isTradeFlowActionDropdown(toggle)) positionTradeFlowActionMenu(toggle);
        });
    });
}

document.addEventListener('show.bs.dropdown', (event) => {
    const toggle = event.target;
    if (!isTradeFlowActionDropdown(toggle)) return;

    // Keep Actions menus mutually exclusive before lifting the new one above
    // responsive table overflow.
    closeTradeFlowTomSelectDropdowns();
    closeTradeFlowActionDropdowns(toggle);
    portalTradeFlowActionMenu(toggle);
});

document.addEventListener('shown.bs.dropdown', (event) => {
    const toggle = event.target;
    if (isTradeFlowActionDropdown(toggle)) positionTradeFlowActionMenu(toggle);
});

document.addEventListener('hidden.bs.dropdown', (event) => {
    const toggle = event.target;
    if (isTradeFlowActionDropdown(toggle)) restoreTradeFlowActionMenu(toggle);
});

document.addEventListener('click', (event) => {
    const item = event.target.closest('.dropdown-menu .dropdown-item');
    if (!item) return;
    const toggle = item.closest('.dropdown')?.querySelector('[data-bs-toggle="dropdown"]')
        || item.closest('.dropdown-menu')?.__tradeFlowActionToggle;
    if (isTradeFlowActionDropdown(toggle)) window.bootstrap?.Dropdown.getInstance(toggle)?.hide();
});

window.addEventListener('resize', updateTradeFlowActionDropdowns);
document.addEventListener('scroll', (event) => {
    if (event.target instanceof Element && event.target.closest('.dropdown-menu')) return;
    updateTradeFlowActionDropdowns();
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
    const selector = 'input[type="number"]:not([data-allow-negative]):not([data-allow-decimal]), [data-non-negative], input[data-money-input], input[data-tf-money-field]';
    const candidates = root.matches?.(selector)
        ? [root]
        : [...(root.querySelectorAll?.(selector) || [])];
    const fields = candidates.filter((field) => {
        // Calculated totals and other transport/display values must never
        // participate in input validation. Their values are not user-entered
        // and may legitimately be formatted (for example, 1,030.00).
        return !field.hasAttribute('data-allow-decimal')
            && !field.disabled
            && !field.readOnly
            && field.type !== 'hidden'
            && !field.matches('[data-display-only], [data-calculated], [data-generated]');
    });
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
        if (field.type === 'number') field.step = '1';
        field.inputMode = 'numeric';

        const existing = String(field.value ?? '').replace(/,/g, '').trim();
        if (/^\d+\.\d+$/.test(existing)) field.value = String(Math.round(Number(existing)));

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
            const raw = String(field.value ?? '').replace(/,/g, '');
            const valid = raw === '' || /^\d+$/.test(raw);
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
        const isPhone = ['text', 'tel'].includes(field.type)
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
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const submitter = event.submitter instanceof HTMLButtonElement
        ? event.submitter
        : form.querySelector('button[type="submit"], button:not([type])');
    const action = submitter?.textContent?.replace(/\s+/g, ' ').trim() || 'Continue';
    const proceed = () => {
        form.dataset.tfConfirmApproved = '1';
        if (submitter && form.dataset.tfConfirmSavingText) {
            submitter.disabled = true;
            submitter.textContent = form.dataset.tfConfirmSavingText;
        }
        form.requestSubmit();
    };
    if (!window.Swal) return proceed();

    window.Swal.fire({
        icon: form.dataset.tfConfirmIcon || 'warning',
        title: form.dataset.tfConfirmTitle || `${action}?`,
        text: form.dataset.tfConfirmMessage,
        showCancelButton: true,
        confirmButtonText: form.dataset.tfConfirmButton || action,
        cancelButtonText: 'Cancel',
        confirmButtonColor: form.dataset.tfConfirmColor || '#dc3545',
        reverseButtons: true,
    }).then((result) => { if (result.isConfirmed) proceed(); });
}, true);

// A save confirmation is required whenever an existing record is being
// updated. Keep this delegated so it also protects forms rendered in modals
// or injected after the initial page load. Purpose-built confirmations (for
// example archive/delete/status actions) retain their more specific copy.
function tradeFlowEffectiveFormMethod(form) {
    const spoofedMethod = form.querySelector('input[name="_method"]')?.value;
    return (spoofedMethod || form.method || 'GET').toUpperCase();
}

function tradeFlowSaveSubmitter(form, submitter) {
    if (submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement) return submitter;
    return form.querySelector('button[type="submit"], input[type="submit"], button:not([type])');
}

function requiresTradeFlowSaveConfirmation(form, submitter) {
    if (!form || form.dataset.tfSaveConfirmApproved === '1') return false;

    // These controls either do not save record details or already use a
    // tailored confirmation flow. Never layer a generic prompt on top.
    if (form.matches('[data-tf-confirm-message], [data-tf-company-delete], [data-tf-status-switch-form], [data-access-trial-confirm], [data-footer-newsletter], [data-footer-newsletter-legacy], [data-inline-products-form]')) return false;

    const method = tradeFlowEffectiveFormMethod(form);
    if (!['PUT', 'PATCH'].includes(method)) return false;

    const button = tradeFlowSaveSubmitter(form, submitter);
    const label = (button?.textContent || button?.value || '').replace(/\s+/g, ' ').trim().toLowerCase();
    const nonSaveAction = /\b(activate|deactivate|suspend|archive|restore|delete|remove|destroy|approve|reject|cancel|void|issue|reissue|reopen|start|deliver|fail|read|unread|mark|extend|renew)\b/;

    return !nonSaveAction.test(label);
}

document.addEventListener('submit', (event) => {
    const form = event.target.closest?.('form');
    if (!requiresTradeFlowSaveConfirmation(form, event.submitter)) return;

    event.preventDefault();
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const submitter = tradeFlowSaveSubmitter(form, event.submitter);
    const submitLabel = (submitter?.textContent || submitter?.value || 'Save Changes').replace(/\s+/g, ' ').trim();
    const proceed = () => {
        form.dataset.tfSaveConfirmApproved = '1';
        if (submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement) {
            submitter.disabled = true;
            submitter.dataset.tfOriginalLabel = submitter.textContent || submitter.value || submitLabel;
            if (submitter instanceof HTMLInputElement) submitter.value = 'Saving...';
            else submitter.textContent = 'Saving...';
        }
        form.requestSubmit(submitter || undefined);
    };

    // Every dashboard layout loads SweetAlert. If a custom/minimal layout
    // intentionally omits it, allow the server-side update rather than
    // leaving the form blocked with no available dialog.
    if (!window.Swal) {
        proceed();
        return;
    }

    askTradeFlowConfirmation({
        title: 'Save changes?',
        text: 'Please confirm that you want to save these changes.',
        confirmButtonText: submitLabel,
        confirmButtonColor: '#2563eb',
    }, proceed);
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
        initTradeFlowImageUploads(node);
        initTradeFlowProfileImageMenu(node);
    });
})).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });

// Monetary fields show grouped thousands for readability, while the form
// payload remains a plain decimal string. The matcher intentionally excludes
// stock, quantities, percentages, identifiers, and telephone inputs.
function tradeFlowMoneyDescriptor(field) {
    return [
        field.name,
        field.id,
        field.placeholder,
        field.getAttribute('aria-label'),
        ...[...field.attributes].map((attribute) => `${attribute.name} ${attribute.value}`),
    ].filter(Boolean).join(' ').toLowerCase();
}

function isTradeFlowMoneyField(field) {
    if (!(field instanceof HTMLInputElement) || field.disabled || field.type === 'hidden') return false;
    if (field.matches('[data-tf-phone-visible], [data-tf-phone-value], [data-tf-phone-standalone]')) return false;
    if (field.hasAttribute('data-money-input') || field.dataset.tfMoneyField === '1') return true;
    if (field.readOnly) return false;

    const descriptor = tradeFlowMoneyDescriptor(field);
    if (!descriptor || /(?:quantity|\bqty\b|stock|percentage|percent|(?:tax|discount)[_\s-]?(?:type|rate|percent)|barcode|invoice|reference|phone|mobile|whatsapp|\bcnic\b|trial[_\s-]?days|sort[_\s-]?order|product[_\s-]?limit|staff[_\s-]?limit|order[_\s-]?limit|\b(?:id|code)\b)/.test(descriptor)) return false;

    return /(?:^|[^a-z])(?:amount|price|cost|balance|payable|receivable|salary|debit|credit|shipping|cash|other[_\s-]?charges?|subtotal|grand[_\s-]?total|total|due|change|tax|discount)(?:$|[^a-z])/.test(descriptor);
}

function tradeFlowRawMoney(value) {
    return String(value ?? '').replace(/,/g, '').trim();
}

function normalizeTradeFlowMoney(value) {
    const raw = tradeFlowRawMoney(value);
    if (raw === '') return '';

    // Historical decimal values are rendered as whole-number business input.
    // New decimal keystrokes and pastes are blocked by the shared guard.
    if (/^\d+\.\d+$/.test(raw)) return String(Math.round(Number(raw)));

    return raw.replace(/[^\d]/g, '').replace(/^0+(?=\d)/, '');
}

function formatTradeFlowMoney(value) {
    const clean = normalizeTradeFlowMoney(value);
    if (clean === '') return '';

    return (clean || '0').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function tradeFlowMoneyFields(root = document) {
    const candidates = root instanceof HTMLInputElement
        ? [root]
        : [...(root.querySelectorAll?.('input') || [])];
    return candidates.filter(isTradeFlowMoneyField);
}

function normalizeTradeFlowMoneyFields(root = document) {
    tradeFlowMoneyFields(root).forEach((field) => {
        field.value = normalizeTradeFlowMoney(field.value);
    });
}

function formatTradeFlowMoneyField(field, preserveCaret = false) {
    const original = field.value;
    const originalCaret = field.selectionStart ?? original.length;
    const rawPrefix = tradeFlowRawMoney(original.slice(0, originalCaret)).replace(/[^\d]/g, '');
    field.value = formatTradeFlowMoney(original);

    if (!preserveCaret || document.activeElement !== field) return;

    let consumed = '';
    let caret = 0;
    for (const character of field.value) {
        caret += 1;
        if (/\d/.test(character)) consumed += character;
        if (consumed.length >= rawPrefix.length) break;
    }
    field.setSelectionRange(caret, caret);
}

let tradeFlowMoneyFormatPending = false;
let tradeFlowMoneyFormatScope = document;
let tradeFlowMoneyActiveField = null;

function queueTradeFlowMoneyFormatting(root = document, activeField = null) {
    tradeFlowMoneyFormatScope = root || document;
    tradeFlowMoneyActiveField = activeField instanceof HTMLInputElement ? activeField : null;
    if (tradeFlowMoneyFormatPending) return;
    tradeFlowMoneyFormatPending = true;
    queueMicrotask(() => {
        tradeFlowMoneyFormatPending = false;
        const active = tradeFlowMoneyActiveField;
        tradeFlowMoneyFields(tradeFlowMoneyFormatScope).forEach((field) => {
            formatTradeFlowMoneyField(field, field === active);
        });
        tradeFlowMoneyActiveField = null;
    });
}

function initTradeFlowMoneyForm(form) {
    if (!form || form.dataset.tfMoneyFormReady === '1') return;
    form.dataset.tfMoneyFormReady = '1';

    // The formdata event covers regular submissions and fetch(new FormData(form))
    // requests, including inline/modal forms.
    form.addEventListener('formdata', (event) => {
        tradeFlowMoneyFields(form).forEach((field) => {
            if (field.name) event.formData.set(field.name, normalizeTradeFlowMoney(field.value));
        });
    });
}

// Currency inputs are switched to text only because a native number input
// cannot display commas. Existing listeners see clean values during their
// event cycle; display grouping resumes in the following microtask.
function initTradeFlowMoneyInputs(root = document) {
    const fields = tradeFlowMoneyFields(root);

    fields.forEach((field) => {
        if (field.dataset.moneyInputReady === '1') return;
        field.dataset.moneyInputReady = '1';
        field.dataset.tfMoneyField = '1';
        if (field.type === 'number') {
            field.dataset.tfMoneyOriginalType = 'number';
            field.type = 'text';
        }
        field.inputMode = 'numeric';
        field.value = formatTradeFlowMoney(field.value);
        field.addEventListener('blur', () => formatTradeFlowMoneyField(field));
        initTradeFlowMoneyForm(field.closest('form'));
    });

    // Text-based money controls are marked above, then receive the same
    // whole-number guard as native number controls.
    initNonNegativeNumberGuards(root);
}

function initTradeFlowMoneyDelegation() {
    if (document.documentElement.dataset.tfMoneyDelegationReady === '1') return;
    document.documentElement.dataset.tfMoneyDelegationReady = '1';

    const scopeFor = (target) => target instanceof Element ? (target.closest('form') || document) : document;
    const normalizeForExistingHandlers = (event) => {
        const scope = scopeFor(event.target);
        const fields = tradeFlowMoneyFields(scope);
        if (!fields.length) return;
        normalizeTradeFlowMoneyFields(scope);
        queueTradeFlowMoneyFormatting(scope, isTradeFlowMoneyField(event.target) ? event.target : null);
    };

    document.addEventListener('input', normalizeForExistingHandlers, true);
    document.addEventListener('change', normalizeForExistingHandlers, true);
    document.addEventListener('click', normalizeForExistingHandlers, true);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') normalizeForExistingHandlers(event);
    }, true);
    document.addEventListener('submit', (event) => {
        const scope = scopeFor(event.target);
        normalizeTradeFlowMoneyFields(scope);
        queueTradeFlowMoneyFormatting(scope);
    }, true);
}

window.initTradeFlowMoneyInputs = initTradeFlowMoneyInputs;
window.formatTradeFlowMoney = formatTradeFlowMoney;
window.tradeFlowRawMoney = tradeFlowRawMoney;
initTradeFlowMoneyDelegation();
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

        // Some premium surfaces provide their own in-boundary control. Bind
        // that button below, but never wrap the field or generate a duplicate.
        if (input.dataset.tfPasswordControl === 'manual') return;

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

function initTradeFlowImageUploads(root = document) {
    const inputs = root.matches?.('[data-tf-image-upload]')
        ? [root]
        : [...(root.querySelectorAll?.('[data-tf-image-upload]') || [])];
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    const allowedExtension = /\.(?:jpe?g|png|webp)$/i;

    inputs.forEach((input) => {
        if (!(input instanceof HTMLInputElement) || input.dataset.tfImageUploadReady === '1') return;
        input.dataset.tfImageUploadReady = '1';
        const form = input.closest('form');
        const container = input.parentElement;
        const error = container?.querySelector('[data-tf-image-error]');
        const status = container?.querySelector('[data-tf-image-file-status]');

        const setError = (message = '') => {
            input.classList.toggle('is-invalid', Boolean(message));
            if (error) {
                error.textContent = message;
                error.classList.toggle('d-block', Boolean(message));
            }
            if (form) {
                const hasInvalidImage = [...form.querySelectorAll('[data-tf-image-upload]')]
                    .some((field) => field.classList.contains('is-invalid'));
                form.querySelectorAll('button[type="submit"], button:not([type])').forEach((button) => {
                    button.disabled = hasInvalidImage;
                });
            }
        };

        const validate = () => {
            const file = input.files?.[0];
            if (!file) {
                setError('');
                return true;
            }

            let message = '';
            if (!allowedExtension.test(file.name) || !allowedTypes.includes(file.type)) {
                message = 'Please upload a JPG, JPEG, PNG, or WebP image.';
            } else if (file.size > 2 * 1024 * 1024) {
                message = 'Image must not exceed 2MB.';
            }

            if (message) {
                input.value = '';
                status && (status.textContent = '');
                setError(message);
                return false;
            }

            setError('');
            if (status) status.textContent = `${file.name} selected.`;

            if (input.matches('[data-tf-profile-input]')) {
                const preview = form?.querySelector('[data-tf-profile-preview]');
                const empty = form?.querySelector('[data-tf-profile-empty]');
                const remove = form?.querySelector('[data-tf-profile-remove]');
                if (preview) {
                    preview.src = URL.createObjectURL(file);
                    preview.classList.remove('d-none');
                    empty?.classList.add('d-none');
                }
                if (remove) remove.checked = false;
            }

            return true;
        };

        input._tradeFlowValidateImage = validate;
        input.addEventListener('change', validate);

        if (form && form.dataset.tfImageUploadFormReady !== '1') {
            form.dataset.tfImageUploadFormReady = '1';
            form.addEventListener('submit', (event) => {
                const valid = [...form.querySelectorAll('[data-tf-image-upload]')]
                    .every((field) => field._tradeFlowValidateImage?.() !== false);
                if (!valid) event.preventDefault();
            });
        }
    });
}

function initTradeFlowProfileImageMenu(root = document) {
    const menus = root.matches?.('[data-tf-profile-image-controls]')
        ? [root]
        : [...(root.querySelectorAll?.('[data-tf-profile-image-controls]') || [])];

    menus.forEach((menu) => {
        if (menu.dataset.tfProfileImageMenuReady === '1') return;
        menu.dataset.tfProfileImageMenuReady = '1';
        const form = menu.closest('form');
        const removeAction = menu.querySelector('[data-tf-profile-remove-action]');
        const input = form?.querySelector('[data-tf-profile-input]');
        const remove = form?.querySelector('[data-tf-profile-remove]');

        removeAction?.addEventListener('click', () => {
            if (!remove || !form) return;
            askTradeFlowConfirmation({
                title: 'Remove profile image?',
                text: 'Your custom profile image will be removed and the default avatar will be used.',
                confirmButtonText: 'Remove image',
            }, () => {
                remove.checked = true;
                input && (input.value = '');
                form.requestSubmit();
            });
        });
    });
}

initTradeFlowImageUploads();
initTradeFlowProfileImageMenu();

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
        const selectableChildren = children.filter((child) => !child.disabled);
        const stateChildren = selectableChildren.length ? selectableChildren : children;
        const selected = stateChildren.filter((child) => child.checked).length;
        const selectedTotal = children.filter((child) => child.checked).length;
        parent.checked = selected === stateChildren.length;
        parent.indeterminate = selected > 0 && selected < stateChildren.length;
        parent.disabled = selectableChildren.length === 0;
        group.querySelector('[data-permission-selected-count]')?.replaceChildren(document.createTextNode(String(selectedTotal)));
        group.classList.toggle('has-selected-permissions', selectedTotal > 0);
    };

    const syncMaster = () => {
        if (!master) return;
        const children = [...form.querySelectorAll('[data-permission-child]')];
        const selectableChildren = children.filter((child) => !child.disabled);
        const stateChildren = selectableChildren.length ? selectableChildren : children;
        const selected = stateChildren.filter((child) => child.checked).length;
        const selectedTotal = children.filter((child) => child.checked).length;
        master.checked = stateChildren.length > 0 && selected === stateChildren.length;
        master.indeterminate = selected > 0 && selected < stateChildren.length;
        master.disabled = children.length > 0 && selectableChildren.length === 0;
        form.querySelector('[data-permission-total-selected]')?.replaceChildren(document.createTextNode(`(${selectedTotal} selected)`));
    };

    const syncAll = () => {
        syncDeliveryViewDependency();
        groups.forEach(syncGroup);
        syncMaster();
    };

    master?.addEventListener('change', () => {
        form.querySelectorAll('[data-permission-child]').forEach((child) => {
            if (!child.disabled) child.checked = master.checked;
        });
        syncAll();
    });

    groups.forEach((group) => {
        group.querySelector('[data-permission-module]')?.addEventListener('change', (event) => {
            group.querySelectorAll('[data-permission-child]').forEach((child) => {
                if (!child.disabled) child.checked = event.target.checked;
            });
            syncAll();
        });
    });

    form.querySelectorAll('[data-permission-child]').forEach((child) => child.addEventListener('change', syncAll));

    syncAll();
}

function initPermissionHierarchies(root = document) {
    const forms = root.matches?.('[data-company-permission-form]')
        ? [root]
        : [...(root.querySelectorAll?.('[data-company-permission-form]') || [])];

    forms.forEach(initPermissionHierarchy);
}

// One guarded initializer powers both Super Admin and business permission
// trees. It is safe to call again after a modal is populated with fresh HTML.
window.TradeFlowPermissions = { init: initPermissionHierarchies };
initPermissionHierarchies();
document.addEventListener('shown.bs.modal', (event) => initPermissionHierarchies(event.target));

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

    const isEditable = (input) => !input.disabled
        && !input.readOnly
        && input.type !== 'hidden'
        && !input.matches('[data-display-only], [data-calculated], [data-generated]');

    const normalize = (input) => {
        if (!isEditable(input)) return true;
        const value = input.value.trim().replace(/,/g, '').replace(/^Rs\s*/i, '');
        if (value !== input.value) input.value = value;
        const valid = value === '' || /^\d+$/.test(value);
        setError(input, !valid);
        return valid;
    };

    const initialize = (root = document) => {
        root.querySelectorAll?.(selector).forEach((input) => {
            if (!isEditable(input)) return;
            input.setAttribute('step', '1');
            input.setAttribute('inputmode', 'numeric');
            normalize(input);
        });
    };

    window.initTradeFlowWholeNumberInputs = initialize;

    document.addEventListener('DOMContentLoaded', () => initialize());

    document.addEventListener('keydown', (event) => {
        const input = event.target.closest?.(selector);
        if (!input || !isEditable(input) || event.ctrlKey || event.metaKey || event.altKey) return;
        if (blockedKeys.has(event.key)) {
            event.preventDefault();
            setError(input, true);
        }
    });

    document.addEventListener('paste', (event) => {
        const input = event.target.closest?.(selector);
        if (!input || !isEditable(input)) return;

        const pasted = event.clipboardData?.getData('text')?.trim() || '';
        if (!/^\d+$/.test(pasted)) {
            event.preventDefault();
            setError(input, true);
        }
    });

    document.addEventListener('input', (event) => {
        const input = event.target.closest?.(selector);
        if (input && isEditable(input)) normalize(input);
    });
})();

// Inline binary status switches reuse the existing PATCH endpoints. The UI is
// only updated after the server confirms the change, keeping it accurate when
// permissions or business rules reject a request.
(() => {
    const toast = (icon, title) => {
        if (window.Swal) {
            window.Swal.fire({ toast: true, position: 'top-end', icon, title, showConfirmButton: false, timer: 2400, timerProgressBar: true });
            return;
        }
        window.alert(title);
    };

    const applyStatus = (form, status) => {
        const active = status === 'Active';
        const button = form.querySelector('.tf-inline-status-switch');
        const input = form.elements.status;
        if (!button || !input) return;

        button.classList.toggle('is-active', active);
        button.classList.toggle('is-inactive', !active);
        button.setAttribute('aria-checked', active ? 'true' : 'false');
        button.setAttribute('aria-label', `${active ? 'Deactivate' : 'Activate'} ${form.dataset.tfStatusEntity || 'record'}`);
        button.querySelector('.tf-inline-status-text').textContent = active ? 'Active' : 'Inactive';
        input.value = active ? 'Inactive' : 'Active';
    };

    document.addEventListener('submit', (event) => {
        const form = event.target.closest?.('[data-tf-status-switch-form]');
        if (!form) return;

        event.preventDefault();
        const button = form.querySelector('.tf-inline-status-switch');
        if (!button || button.disabled) return;

        const nextStatus = form.elements.status?.value;
        const isActivation = nextStatus === 'Active';
        const entity = form.dataset.tfStatusEntity || 'this record';
        const action = isActivation ? 'Activate' : 'Deactivate';
        const sendUpdate = async () => {
            button.disabled = true;
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(form),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(payload.message || 'Unable to update status.');
                const updatedStatus = payload.status || payload.subscriber?.status;
                if (!updatedStatus) throw new Error(payload.message || 'The updated status was not returned.');

                applyStatus(form, updatedStatus);
                const planRow = form.closest('[data-plan-row]');
                if (planRow) planRow.dataset.planStatus = updatedStatus;
                form.dispatchEvent(new CustomEvent('tf:status-updated', { bubbles: true, detail: payload }));
                toast('success', payload.message || 'Status updated successfully.');
            } catch (error) {
                // The control is not changed until a successful response, so a
                // rejected request always retains the real previous state.
                toast('error', error.message || 'Unable to update status.');
            } finally {
                button.disabled = false;
            }
        };

        window.askTradeFlowConfirmation?.({
            title: `${action} ${entity}?`,
            text: isActivation
                ? `${entity} will be available again.`
                : `${entity} will become inactive until it is reactivated.`,
            confirmButtonText: `Confirm ${action}`,
            confirmButtonColor: isActivation ? '#2563eb' : '#f59e0b',
        }, sendUpdate);
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

// Shared visual file-upload controls retain the real native input (and its
// existing server-side validation) while exposing the selected filename in a
// consistent, accessible label across dashboard forms.
(() => {
    const initialise = (root = document) => {
        root.querySelectorAll?.('[data-tf-file-upload]').forEach((input) => {
            if (input.dataset.tfFileUploadReady === '1') return;
            input.dataset.tfFileUploadReady = '1';

            const container = input.closest('.tf-file-upload');
            const name = container?.querySelector('[data-tf-file-upload-name]');
            if (!name) return;

            const defaultText = name.textContent.trim() || 'No file selected';
            const update = () => {
                const file = input.files?.[0];
                name.textContent = file ? file.name : defaultText;
                name.title = file ? file.name : '';
            };

            input.addEventListener('change', update);
            update();
        });
    };

    document.addEventListener('DOMContentLoaded', () => initialise());
    if (document.readyState !== 'loading') initialise();
})();
