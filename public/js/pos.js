(() => {
    document.querySelectorAll('[data-pos-print]').forEach((button) => {
        if (button.dataset.posPrintReady === '1') return;
        button.dataset.posPrintReady = '1';
        button.addEventListener('click', () => window.print());
    });

    const root = document.querySelector('[data-pos-root]');
    if (!root || root.dataset.posInitialized === '1') return;
    root.dataset.posInitialized = '1';

    const config = JSON.parse(root.dataset.posConfig || '{}');
    const $ = (selector, scope = root) => scope.querySelector(selector);
    const $$ = (selector, scope = root) => [...scope.querySelectorAll(selector)];
    const cart = new Map();
    let activeProduct = -1;
    let selectedCartId = null;
    let searchTimer = null;
    let searchVersion = 0;
    let resumingHeldSale = false;
    let holdingSale = false;
    let resumeSearchTimer = null;
    let historySearchTimer = null;
    let resumeMatches = [];
    let historyMatches = [];
    let activeResumeMatch = -1;
    let activeHistoryMatch = -1;
    let resumeLookupMessage = '';
    let currentHeldSale = null;
    let submitting = false;
    let draftSyncTimer = null;
    let draftGeneration = Number(config.draftGeneration || 0);
    let draftSyncController = null;
    let pendingDraftClear = null;
    let draftClearError = null;
    let clearingCart = false;
    let keyboardProductSelection = false;
    let splitPayments = [];
    let finishCompletedSale = () => {};

    const barcode = $('[data-pos-barcode]');
    const search = $('[data-pos-search]');
    const grid = $('[data-pos-product-grid]');
    const cartBody = $('[data-pos-cart-body]');
    const customer = $('[data-pos-customer]');
    const quickCustomerPanel = $('[data-pos-quick-customer]');
    const quickCustomerName = $('[data-pos-customer-name]');
    const quickCustomerPhone = $('[data-pos-customer-phone]');
    const quickCustomerCity = $('[data-pos-customer-city]');
    const quickCustomerAddress = $('[data-pos-customer-address]');
    const deliveryRequired = $('[data-pos-delivery-required]');
    const deliveryDetails = $('[data-pos-delivery-details]');
    const deliveryAddress = $('[data-pos-delivery-address]');
    const discount = $('[data-pos-discount]');
    const tax = $('[data-pos-tax]');
    const paymentType = $('[data-pos-payment-type]');
    const paymentMode = $('[data-pos-payment-mode]');
    const paymentMethod = $('[data-pos-payment-method]');
    const singlePaymentPanel = $('[data-pos-single-payment]');
    const splitPaymentPanel = $('[data-pos-split-payment]');
    const splitPaymentRows = $('[data-pos-split-payment-rows]');
    const addSplitPayment = $('[data-pos-add-split-payment]');
    const splitEntered = $('[data-pos-split-entered]');
    const splitRemaining = $('[data-pos-split-remaining]');
    const splitChangeRow = $('[data-pos-split-change-row]');
    const splitChange = $('[data-pos-split-change]');
    const cash = $('[data-pos-cash]');
    const tenderLabel = $('[data-pos-tender-label]');
    const changeRow = $('[data-pos-change-row]');
    const changeReturn = $('[data-pos-change]');
    const reference = $('[data-pos-reference]');
    const completeButton = $('[data-pos-complete]');
    const clearButton = $('[data-pos-clear]');
    const checkoutPanel = $('.tf-pos-checkout-panel');
    const registerStatus = $('[data-pos-register-status]');
    const registerLabel = $('[data-pos-register-label]');
    const openingCash = $('[data-pos-opening-cash]');
    const registerAction = $('[data-pos-register-action]');
    const cashActions = $('[data-pos-cash-actions]');
    const registerRequired = $('[data-pos-register-required]');
    const holdInput = $('[data-pos-hold-input]');
    const resumeInput = $('[data-pos-resume-input]');
    const resumeSuggestions = $('[data-pos-resume-suggestions]');
    const resumeError = $('[data-pos-resume-error]');
    const historyInput = $('[data-pos-history-input]');
    const historySuggestions = $('[data-pos-history-suggestions]');
    const historyError = $('[data-pos-history-error]');
    const focusElement = (element, select = false, allowModalFocus = false) => requestAnimationFrame(() => {
        if (!element) return;
        if (!allowModalFocus && document.querySelector('.swal2-container')) return;

        const checkoutPanel = element.closest('.tf-pos-checkout-panel');
        const scrollContainer = element.closest('.tf-pos-checkout-scroll')
            || checkoutPanel?.querySelector('.tf-pos-checkout-scroll')
            || element.closest('.tf-pos-cart-scroll');

        if (scrollContainer) {
            if (element === completeButton) {
                scrollContainer.scrollTop = scrollContainer.scrollHeight;
            } else {
                const containerRect = scrollContainer.getBoundingClientRect();
                const elementRect = element.getBoundingClientRect();
                const topGap = 12;
                const bottomGap = 12;

                if (elementRect.top < containerRect.top + topGap) {
                    scrollContainer.scrollTop += elementRect.top - containerRect.top - topGap;
                } else if (elementRect.bottom > containerRect.bottom - bottomGap) {
                    scrollContainer.scrollTop += elementRect.bottom - containerRect.bottom + bottomGap;
                }
            }
        }

        element.focus({ preventScroll: true });
        if (select) element.select?.();
    });

    const rawMoney = (value) => {
        const normalized = typeof window.tradeFlowRawMoney === 'function'
            ? window.tradeFlowRawMoney(value)
            : String(value ?? '').replace(/,/g, '').trim();

        return String(normalized).replace(/^Rs\.?\s*/i, '').trim();
    };
    const whole = (value) => {
        const raw = rawMoney(value);
        return /^\d+$/.test(raw) ? Math.max(0, Math.trunc(Number(raw))) : 0;
    };
    const authoritativePrice = (value) => {
        const raw = rawMoney(value);
        return /^\d+(?:\.\d+)?$/.test(raw) ? Math.max(0, Math.trunc(Number(raw))) : 0;
    };
    const sellingPrice = (product) => {
        if (product?.price !== undefined && product.price !== null) {
            return authoritativePrice(product.price);
        }

        const retailPrice = authoritativePrice(product?.retail_price);
        return retailPrice > 0 ? retailPrice : authoritativePrice(product?.wholesale_price);
    };
    const currency = (amount) => `Rs ${whole(amount).toLocaleString()}`;
    const roundCash = (value) => whole(value);
    const cashIsValid = () => /^\d+$/.test(rawMoney(cash.value));
    const currentCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const flash = (icon, title, text = '') => window.Swal
        ? Swal.fire({
            icon,
            title,
            text,
            timer: icon === 'success' ? 1800 : undefined,
            showConfirmButton: icon !== 'success',
            allowEnterKey: true,
            focusConfirm: icon !== 'success',
            didOpen: (popup) => {
                if (icon === 'success') return;
                const confirm = Swal.getConfirmButton();
                if (!confirm) return;
                confirm.focus({ preventScroll: true });
                popup.addEventListener('keydown', (event) => {
                    if (event.key !== 'Enter') return;
                    event.preventDefault();
                    Swal.clickConfirm();
                });
            },
        })
        : window.alert(`${title}${text ? `\n${text}` : ''}`);
    // Register dialogs are rendered by SweetAlert instead of a native form, so
    // the browser has no form-submit action to associate with Enter. Keep that
    // keyboard action explicit for both opening and closing a register, while
    // allowing Enter to remain a normal newline inside the optional note.
    const submitRegisterDialogOnEnter = (popup) => {
        popup.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' || event.isComposing || event.repeat) return;
            if (event.target.closest('textarea')) return;
            if (Swal.isLoading()) return;

            event.preventDefault();
            event.stopPropagation();
            Swal.clickConfirm();
        }, true);
    };
    const showRegisterFeedback = (state) => flash('success', `Register ${state}`);
    const showReceiptActions = async (payload) => {
        if (!window.Swal) {
            finishCompletedSale();
            return;
        }

        const result = await Swal.fire({
            icon: 'success',
            title: 'Sale completed',
            text: payload.order?.order_number || 'Your POS sale has been completed.',
            html: payload.can_print_receipt ? `<div class="tf-pos-receipt-actions">
                <button type="button" class="btn btn-outline-primary" data-pos-receipt-view><i class="bi bi-eye"></i>View Receipt</button>
                <button type="button" class="btn btn-outline-secondary" data-pos-receipt-print><i class="bi bi-printer"></i>Print Receipt</button>
                <a class="btn btn-outline-success" href="${escapeHtml(payload.receipt_download_url || '#')}" data-pos-receipt-download><i class="bi bi-download"></i>Download PDF</a>
            </div>` : '',
            showCancelButton: false,
            showConfirmButton: true,
            confirmButtonText: 'Done',
            buttonsStyling: false,
            allowOutsideClick: false,
            allowEscapeKey: false,
            customClass: {
                popup: 'tf-pos-receipt-modal',
                actions: 'tf-pos-receipt-modal-actions',
                confirmButton: 'btn btn-tf-primary',
            },
            didOpen: (popup) => {
                const viewButton = popup.querySelector('[data-pos-receipt-view]');
                const printButton = popup.querySelector('[data-pos-receipt-print]');
                const downloadButton = popup.querySelector('[data-pos-receipt-download]');
                const doneButton = Swal.getConfirmButton();
                const actions = [viewButton, printButton, downloadButton, doneButton].filter(Boolean);

                viewButton?.addEventListener('click', () => {
                    window.open(payload.receipt_url, '_blank', 'noopener');
                });
                printButton?.addEventListener('click', () => {
                    window.open(payload.receipt_print_url, '_blank', 'noopener');
                });
                actions[0]?.focus({ preventScroll: true });

                popup.addEventListener('keydown', (event) => {
                    if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) return;
                    event.preventDefault();

                    const currentIndex = Math.max(0, actions.indexOf(document.activeElement));
                    let nextIndex = currentIndex;
                    if (['ArrowRight', 'ArrowDown'].includes(event.key)) nextIndex = Math.min(actions.length - 1, currentIndex + 1);
                    if (['ArrowLeft', 'ArrowUp'].includes(event.key)) nextIndex = Math.max(0, currentIndex - 1);
                    actions[nextIndex]?.focus({ preventScroll: true });
                });
            },
        });

        if (result.isConfirmed) finishCompletedSale();
    };
    const request = async (url, method = 'GET', body = null, options = {}) => {
        const token = currentCsrfToken();
        const headers = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };
        if (!['GET', 'HEAD'].includes(method.toUpperCase())) {
            headers['X-CSRF-TOKEN'] = token;
        }
        if (body) {
            headers['Content-Type'] = 'application/json';
        }
        const response = await fetch(url, {
            method,
            headers,
            body: body ? JSON.stringify(body) : null,
            credentials: 'same-origin',
            signal: options.signal,
        });
        const payload = await response.json().catch(() => ({}));
        if (response.status === 419) {
            const error = new Error('Your session has expired. Please refresh or sign in again.');
            error.status = response.status;
            throw error;
        }
        if (!response.ok) {
            const error = new Error(payload.message || Object.values(payload.errors || {}).flat()[0] || 'Unable to process this POS action.');
            error.status = response.status;
            error.payload = payload;
            throw error;
        }
        return payload;
    };
    const clearInlineError = (element) => {
        if (!element) return;
        element.textContent = '';
        element.hidden = true;
    };
    const showInlineError = (element, message) => {
        if (!element) return;
        element.textContent = message;
        element.hidden = false;
    };
    const closeSuggestions = (input, container) => {
        container?.classList.add('d-none');
        input?.setAttribute('aria-expanded', 'false');
    };
    const renderSuggestions = (input, container, matches, activeIndex, render, choose) => {
        if (!input || !container) return;
        container.replaceChildren();
        if (!matches.length) {
            closeSuggestions(input, container);
            return;
        }
        matches.forEach((match, index) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = `tf-pos-top-suggestion${index === activeIndex ? ' is-active' : ''}`;
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', index === activeIndex ? 'true' : 'false');
            item.addEventListener('mousedown', (event) => event.preventDefault());
            item.addEventListener('click', () => choose(index));
            render(item, match);
            container.append(item);
        });
        container.classList.remove('d-none');
        input.setAttribute('aria-expanded', 'true');
    };
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
    }[character]));

    const lineDiscountAmount = (line) => {
        const base = line.quantity * line.price;
        const type = line.discountType || (whole(line.discount) > 0 ? 'percentage' : 'none');
        const value = Number(line.discountValue ?? line.discount ?? 0) || 0;
        return type === 'percentage' ? base * Math.min(100, Math.max(0, value)) / 100 : (type === 'fixed' ? Math.min(base, Math.max(0, value)) : 0);
    };
    const lineTotal = (line) => {
        const base = line.quantity * line.price;
        const lineDiscount = lineDiscountAmount(line);
        const lineTax = (base - lineDiscount) * (line.tax / 100);
        return base - lineDiscount + lineTax;
    };
    const isSplitPayment = () => paymentMode?.value === 'split';
    const isWholePositive = (value) => /^\d+$/.test(rawMoney(value)) && whole(value) > 0;
    const splitPaymentSummary = (grand) => {
        const methods = new Set();
        let entered = 0;
        let cashTendered = 0;
        let valid = splitPayments.length > 0;
        splitPayments.forEach((payment) => {
            const amount = whole(payment.amount);
            const method = String(payment.method || '');
            if (!method || methods.has(method) || !isWholePositive(payment.amount)) valid = false;
            methods.add(method);
            entered += amount;
            if (method === 'Cash') cashTendered += amount;
        });
        const nonCash = entered - cashTendered;
        if (nonCash > grand) valid = false;
        const cashApplied = Math.min(cashTendered, Math.max(0, grand - nonCash));
        const paid = Math.min(grand, nonCash + cashApplied);
        return { entered, cashTendered, nonCash, paid, remaining: Math.max(0, grand - paid), change: Math.max(0, cashTendered - cashApplied), valid };
    };
    const splitMethodOptions = (selected) => (config.splitPaymentMethods || []).map((method) => `<option value="${escapeHtml(method)}" ${method === selected ? 'selected' : ''} ${method !== selected && splitPayments.some((payment) => payment.method === method) ? 'disabled' : ''}>${escapeHtml(method)}</option>`).join('');
    const renderSplitPayments = () => {
        if (!splitPaymentRows) return;
        splitPaymentRows.innerHTML = splitPayments.map((payment, index) => `<div class="row g-2 align-items-end mb-2" data-pos-split-row="${index}">
            <div class="col-5"><label class="form-label small mb-1">Method</label><select class="form-select form-select-sm" data-pos-split-method>${splitMethodOptions(payment.method)}</select></div>
            <div class="col-4"><label class="form-label small mb-1">Amount</label><input class="form-control form-control-sm js-whole-number" data-pos-split-amount type="number" min="1" step="1" inputmode="numeric" value="${escapeHtml(payment.amount)}"></div>
            <div class="col-3 d-flex justify-content-end"><button type="button" class="btn btn-sm btn-outline-danger" data-pos-remove-split-payment aria-label="Remove ${escapeHtml(payment.method)} payment"><i class="bi bi-x-lg"></i></button></div>
            <div class="col-12 ${payment.method === 'Cash' ? 'd-none' : ''}" data-pos-split-reference-wrap><input class="form-control form-control-sm" data-pos-split-reference maxlength="255" value="${escapeHtml(payment.reference || '')}" placeholder="Reference (optional)"></div>
        </div>`).join('');
        if (addSplitPayment) addSplitPayment.disabled = splitPayments.length >= (config.splitPaymentMethods || []).length;
    };
    const addSplitPaymentRow = () => {
        const method = (config.splitPaymentMethods || []).find((candidate) => !splitPayments.some((payment) => payment.method === candidate));
        if (!method) return;
        splitPayments.push({ method, amount: '', reference: '' });
        renderSplitPayments();
        updateTotals();
        focusElement($$('[data-pos-split-amount]').at(-1), true);
    };
    const setPaymentMode = (mode, { preserve = false } = {}) => {
        const split = mode === 'split' && config.canUseSplitPayment === true;
        if (paymentMode) paymentMode.value = split ? 'split' : 'single';
        paymentType.value = split ? 'Split' : paymentMethod.value;
        singlePaymentPanel?.classList.toggle('d-none', split);
        splitPaymentPanel?.classList.toggle('d-none', !split);
        if (split && !splitPayments.length) {
            splitPayments = [{ method: (config.splitPaymentMethods || ['Cash'])[0], amount: '', reference: '' }];
        }
        if (!preserve && !split) splitPayments = [];
        renderSplitPayments();
    };
    const totals = () => {
        let grossSubtotal = 0;
        let lineDiscounts = 0;
        let subtotal = 0;
        cart.forEach((line) => {
            line.lineTotal = lineTotal(line);
            grossSubtotal += line.quantity * line.price;
            lineDiscounts += lineDiscountAmount(line);
            subtotal += line.lineTotal;
        });
        const orderDiscount = subtotal * (whole(discount.value) / 100);
        const taxAmount = (subtotal - orderDiscount) * (whole(tax.value) / 100);
        const grand = Math.round(subtotal - orderDiscount + taxAmount);
        const roundedCash = roundCash(cash.value);
        const split = splitPaymentSummary(grand);
        const singleCredit = !isSplitPayment() && paymentMethod.value === 'Credit';
        return {
            grossSubtotal,
            lineDiscounts,
            subtotal,
            discount: orderDiscount,
            tax: taxAmount,
            grand,
            paid: isSplitPayment() ? split.paid : (singleCredit ? 0 : Math.min(roundedCash, grand)),
            due: isSplitPayment() ? split.remaining : Math.max(0, grand - (singleCredit ? 0 : Math.min(roundedCash, grand))),
            change: isSplitPayment() ? split.change : Math.max(0, roundedCash - grand),
            split,
        };
    };
    const isQuickCustomer = () => customer.value === '__new__';
    const quickCustomerIsValid = () => !isQuickCustomer()
        || Boolean(quickCustomerName?.value.trim() || quickCustomerPhone?.value.trim());
    const updateTotals = () => {
        const values = totals();
        const payable = $('[data-total="grand"]');
        if (payable) payable.textContent = currency(values.grand);
        $('[data-total="gross"]') && ($('[data-total="gross"]').textContent = currency(values.grossSubtotal));
        $('[data-total="line-discounts"]') && ($('[data-total="line-discounts"]').textContent = `- ${currency(values.lineDiscounts)}`);
        $('[data-total="net-subtotal"]') && ($('[data-total="net-subtotal"]').textContent = currency(values.subtotal));
        $('[data-total="invoice-discount"]') && ($('[data-total="invoice-discount"]').textContent = `- ${currency(values.discount)}`);
        $('[data-total="tax"]') && ($('[data-total="tax"]').textContent = currency(values.tax));
        const splitMode = isSplitPayment();
        paymentType.value = splitMode ? 'Split' : paymentMethod.value;
        const isCash = !splitMode && paymentMethod.value === 'Cash';
        if (tenderLabel) tenderLabel.textContent = isCash ? 'Cash Received' : 'Payment Amount';
        if (changeRow) changeRow.classList.toggle('d-none', !isCash);
        if (changeReturn) changeReturn.value = currency(isCash ? values.change : 0);
        cash.max = isCash ? '' : String(values.grand);
        const tenderInvalid = cash.value !== '' && !cashIsValid();
        const nonCashOverpayment = !isCash && paymentType.value !== 'Credit' && roundCash(cash.value) > values.grand;
        cash.setCustomValidity(tenderInvalid
            ? 'Enter a whole-number payment amount.'
            : (nonCashOverpayment ? `Payment amount cannot exceed ${currency(values.grand)} for this payment method.` : ''));
        if (splitEntered) splitEntered.textContent = currency(values.split.entered);
        if (splitRemaining) splitRemaining.textContent = currency(values.split.remaining);
        if (splitChangeRow) splitChangeRow.classList.toggle('d-none', !splitMode || values.split.change <= 0);
        if (splitChange) splitChange.textContent = currency(values.split.change);
        const customerAllowed = values.due === 0 || (Boolean(customer.value) && !isQuickCustomer() && quickCustomerIsValid());
        const roundedCash = roundCash(cash.value);
        const paymentAllowed = splitMode
            ? values.split.valid
            : paymentMethod.value === 'Credit'
            || (paymentType.value === 'Cash'
                ? cashIsValid() && roundedCash >= values.grand
                : cashIsValid() && roundedCash > 0 && roundedCash <= values.grand);
        completeButton.disabled = !config.registerId || cart.size === 0 || !customerAllowed || !paymentAllowed || submitting;
        return values;
    };

    const visibleProductCards = () => [...grid.querySelectorAll('[data-product]')].filter((card) => (
        card.offsetParent !== null && !card.hidden && window.getComputedStyle(card).visibility !== 'hidden'
    ));
    const setActiveProduct = (index, { focus = false, ensureVisible = true } = {}) => {
        const cards = visibleProductCards();
        activeProduct = cards.length ? Math.max(0, Math.min(cards.length - 1, index)) : -1;
        cards.forEach((card, cardIndex) => {
            const selected = cardIndex === activeProduct;
            card.classList.toggle('is-keyboard-selected', selected);
            card.setAttribute('aria-selected', String(selected));
        });
        const selectedCard = cards[activeProduct];
        if (ensureVisible) selectedCard?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        if (focus) selectedCard?.focus({ preventScroll: true });
        return selectedCard;
    };
    const visualProductRows = (cards) => cards.reduce((rows, card) => {
        const bounds = card.getBoundingClientRect();
        const row = rows.find((candidate) => Math.abs(candidate.top - bounds.top) < 8);
        const entry = { card, center: bounds.left + (bounds.width / 2) };
        if (row) row.cards.push(entry);
        else rows.push({ top: bounds.top, cards: [entry] });
        return rows;
    }, []).sort((left, right) => left.top - right.top).map((row) => ({
        ...row,
        cards: row.cards.sort((left, right) => left.center - right.center),
    }));
    const moveActiveProduct = (direction, focus = false) => {
        const cards = visibleProductCards();
        if (!cards.length) return;

        const currentIndex = activeProduct < 0 ? 0 : Math.min(activeProduct, cards.length - 1);
        let targetIndex = currentIndex;
        if (direction === 'left') targetIndex = Math.max(0, currentIndex - 1);
        if (direction === 'right') targetIndex = Math.min(cards.length - 1, currentIndex + 1);

        if (direction === 'up' || direction === 'down') {
            const rows = visualProductRows(cards);
            const currentRowIndex = rows.findIndex((row) => row.cards.some((entry) => entry.card === cards[currentIndex]));
            const targetRow = rows[currentRowIndex + (direction === 'up' ? -1 : 1)];
            if (targetRow) {
                const currentBounds = cards[currentIndex].getBoundingClientRect();
                const currentCenter = currentBounds.left + (currentBounds.width / 2);
                const target = targetRow.cards.reduce((closest, entry) => (
                    Math.abs(entry.center - currentCenter) < Math.abs(closest.center - currentCenter) ? entry : closest
                ));
                targetIndex = cards.indexOf(target.card);
            }
        }

        setActiveProduct(targetIndex, { focus });
    };
    const productCard = (product) => {
        const price = sellingPrice(product);
        const imageUrl = typeof product.image_url === 'string' && product.image_url !== ''
            ? product.image_url
            : null;
        const payload = JSON.stringify({
            id: product.id,
            name: product.name,
            barcode: product.barcode,
            unit: product.unit,
            price,
            stock: product.stock_quantity ?? product.stock ?? 0,
            image_url: imageUrl,
        }).replace(/'/g, '&#039;');
        return `<button type="button" class="tf-pos-product-card" data-product='${payload}' tabindex="-1" role="option" aria-selected="false">
            <div class="tf-pos-product-image">${imageUrl ? `<img src="${escapeHtml(imageUrl)}" alt="" data-pos-product-image>` : '<i class="bi bi-box-seam"></i>'}</div>
            <strong>${escapeHtml(product.name)}</strong><small>${escapeHtml(product.barcode || '')}</small>
            <span>${currency(price)}</span><em>${product.stock_quantity ?? product.stock ?? 0} ${escapeHtml(product.unit || '')}</em>
        </button>`;
    };
    const renderProducts = (products, { preserveScroll = false } = {}) => {
        // Product searches and category changes intentionally start at the
        // beginning of a new result set. Cart activity, however, must never
        // move a cashier away from the products currently being browsed.
        const previousScrollTop = preserveScroll ? grid.scrollTop : 0;
        const activeCard = preserveScroll ? $$('[data-product]')[activeProduct] : null;
        const activeProductId = Number(parseProduct(activeCard)?.id || 0);

        grid.innerHTML = products.length ? products.map(productCard).join('') : '<div class="text-muted p-3">No matching products.</div>';
        $('[data-pos-product-count]').textContent = `${products.length} available`;
        const restoredIndex = activeProductId
            ? products.findIndex((product) => Number(product.id) === activeProductId)
            : -1;
        setActiveProduct(products.length ? Math.max(0, restoredIndex) : -1, { ensureVisible: !preserveScroll });

        if (preserveScroll) {
            requestAnimationFrame(() => {
                grid.scrollTop = Math.min(previousScrollTop, Math.max(0, grid.scrollHeight - grid.clientHeight));
            });
        }
    };

    // A genuinely unavailable file should degrade to the existing product
    // placeholder. A normal cart/search refresh never changes image URLs.
    grid.addEventListener('error', (event) => {
        const image = event.target;
        if (!(image instanceof HTMLImageElement) || !image.matches('[data-pos-product-image]')) return;
        image.closest('.tf-pos-product-image')?.replaceChildren(Object.assign(document.createElement('i'), {
            className: 'bi bi-box-seam',
        }));
    }, true);
    const cartRow = (line, index) => {
        const standardPrice = whole(line.standardPrice ?? line.price);
        const hasPriceOverride = whole(line.price) !== standardPrice;
        const quantity = `<input class="form-control form-control-sm tf-pos-cart-quantity-input" type="number" min="1" max="${line.stock}" step="1" inputmode="numeric" value="${whole(line.quantity)}" data-cart-field="quantity">`;
        const priceInput = `<input class="form-control form-control-sm tf-pos-cart-price-input" type="number" min="0" step="1" inputmode="numeric" value="${whole(line.price)}" data-cart-field="price" ${config.canUseCustomPrice ? '' : 'readonly aria-readonly="true" tabindex="-1"'}>`;
        const priceContext = `<small class="d-block tf-pos-price-context ${hasPriceOverride ? 'is-overridden' : ''}" data-cart-price-context>Std: ${currency(standardPrice)}${hasPriceOverride ? ' · Override' : ''}</small>`;
        const overrideReason = config.canUseCustomPrice
            ? `<div class="tf-pos-price-reason" data-cart-override-wrap ${hasPriceOverride ? '' : 'hidden'}><input class="form-control form-control-sm" type="text" maxlength="500" value="${escapeHtml(line.priceOverrideReason || '')}" placeholder="Override reason" data-cart-override-reason ${hasPriceOverride ? 'required' : ''}><button type="button" class="btn btn-sm btn-link" data-cart-action="reset-price">Standard</button></div>`
            : '';
        const price = `Rs ${numberWithCommas(line.price)}${hasPriceOverride ? `<small class="d-block text-warning">Standard: Rs ${numberWithCommas(standardPrice)} · Override</small>` : ''}`;
        const actions = '<button type="button" class="btn btn-sm btn-outline-danger" data-cart-action="remove">Delete</button>';
        return `<tr data-cart-id="${line.id}" tabindex="0" class="${selectedCartId === line.id ? 'is-selected' : ''}">
            <td>${index + 1}</td><td class="tf-pos-product-cell"><strong>${escapeHtml(line.name)}</strong><small class="d-block text-muted">${escapeHtml(line.barcode || '')} | Stock ${line.stock}</small></td>
            <td>${quantity}</td><td>${priceInput}${priceContext}${overrideReason}</td>
            <td class="tf-pos-line-total"><strong data-cart-line-total>${currency(line.lineTotal)}</strong></td><td class="tf-pos-cart-actions-cell"><div class="tf-pos-cart-actions">${actions}</div></td>
        </tr>`;
    };
    const numberWithCommas = (value) => whole(value).toLocaleString();
    const renderCart = ({ syncDraft = true } = {}) => {
        totals();
        if (selectedCartId !== null && !cart.has(selectedCartId)) selectedCartId = null;
        if (selectedCartId === null && cart.size) selectedCartId = [...cart.keys()][0];
        cartBody.innerHTML = cart.size
            ? [...cart.values()].map(cartRow).join('')
            : '<tr data-pos-empty><td colspan="6" class="text-center text-muted py-5">Scan or select a product to start a sale.</td></tr>';
        window.initTradeFlowMoneyInputs?.(cartBody);
        const values = updateTotals();
        if (syncDraft) scheduleDraftSync();
        return values;
    };
    const refreshInlineRow = (row, line) => {
        const lineTotalCell = $('[data-cart-line-total]', row);
        if (lineTotalCell) lineTotalCell.textContent = currency(lineTotal(line));
        updateTotals();
    };
    const addProduct = (product) => {
        const id = Number(product.id);
        const stock = Number(product.stock_quantity ?? product.stock ?? 0);
        const existing = cart.get(id);
        if (existing) {
            if (existing.quantity >= existing.stock) return flash('warning', 'Cannot add more units', `Available stock is ${existing.stock} and all units are already in the cart.`);
            existing.quantity += 1;
        } else {
            if (stock < 1) return flash('warning', 'Out of stock');
            cart.set(id, {
                id,
                name: product.name,
                barcode: product.barcode || '',
                stock,
                quantity: 1,
                price: sellingPrice(product),
                standardPrice: sellingPrice(product),
                discount: 0,
                discountType: 'none',
                discountValue: 0,
                tax: 0,
                unit: product.unit || '',
            });
        }
        selectedCartId = id;
        keyboardProductSelection = false;
        barcode.value = '';
        // This is an intentional add/scan action, so make its quantity ready
        // for immediate replacement without changing any other navigation flow.
        renderCart();
        focusElement($(`[data-cart-id="${id}"] [data-cart-field="quantity"]`), true);
    };
    const parseProduct = (card) => {
        try { return JSON.parse(card.dataset.product); } catch (_) { return null; }
    };
    const searchProducts = async ({ preserveScroll = false } = {}) => {
        const version = ++searchVersion;
        const params = new URLSearchParams({ q: search.value.trim() });
        const category = $('[data-pos-categories] .active')?.dataset.category;
        if (category) params.set('category_id', category);
        try {
            const payload = await request(`${config.productsUrl}?${params}`);
            if (version === searchVersion) renderProducts(payload.products || [], { preserveScroll });
        } catch (_) {
            if (version === searchVersion) flash('error', 'Unable to load POS data', 'Please refresh and try again.');
        }
    };
    const scan = async () => {
        const code = barcode.value.trim();
        if (!code) return;
        try {
            const payload = await request(`${config.barcodeUrl}?barcode=${encodeURIComponent(code)}`);
            barcode.value = '';
            addProduct(payload.product);
        } catch (error) {
            flash('warning', 'Barcode not found', error.message);
            barcode.select();
        }
    };
    const addSelectedSearchProduct = async () => {
        const term = search.value.trim();
        const activeCard = $$('[data-product]')[activeProduct];
        const activeProductData = activeCard ? parseProduct(activeCard) : null;
        if (activeProductData) return addProduct(activeProductData);

        const params = new URLSearchParams({ q: term });
        const category = $('[data-pos-categories] .active')?.dataset.category;
        if (category) params.set('category_id', category);

        try {
            // Resolve the current term before adding so a fast Enter cannot use
            // a stale product grid while the debounced visual search is pending.
            const payload = await request(`${config.productsUrl}?${params}`);
            const matches = payload.products || [];
            if (matches.length) return addProduct(matches[0]);
        } catch (_) {
            // The user-facing message below covers a failed lookup too.
        }

        flash('warning', 'Product not found', 'Select a matching product before adding it.');
    };
    const clearCart = async ({ reportFailure = true } = {}) => {
        if (clearingCart) return pendingDraftClear || Promise.resolve();

        clearingCart = true;
        cart.clear();
        selectedCartId = null;
        renderCart({ syncDraft: false });
        search.value = '';
        barcode.value = '';
        focusElement(search);

        if (!config.registerId || !config.draftUrl) {
            clearingCart = false;
            return;
        }

        window.clearTimeout(draftSyncTimer);
        draftSyncTimer = null;
        draftSyncController?.abort();
        const clearGeneration = ++draftGeneration;
        const clearRequest = clearServerDraft(clearGeneration);
        pendingDraftClear = clearRequest;
        draftClearError = null;
        if (clearButton) clearButton.disabled = true;

        try {
            await clearRequest;
        } catch (error) {
            draftClearError = error;
            if (reportFailure) {
                await flash('error', 'Unable to clear the current sale', 'Please try again.');
            }
            throw error;
        } finally {
            if (pendingDraftClear === clearRequest) pendingDraftClear = null;
            clearingCart = false;
            if (clearButton) clearButton.disabled = false;
        }
    };
    finishCompletedSale = () => {
        clearCart({ reportFailure: false }).catch(() => {});
        currentHeldSale = null;
        customer.value = '';
        quickCustomerName && (quickCustomerName.value = '');
        if (quickCustomerPhone) {
            if (window.TradeFlowPhone?.setNumber) window.TradeFlowPhone.setNumber(quickCustomerPhone, '');
            else quickCustomerPhone.value = '';
        }
        quickCustomerCity && (quickCustomerCity.value = '');
        quickCustomerAddress && (quickCustomerAddress.value = '');
        if (deliveryRequired) deliveryRequired.value = '0';
        if (deliveryAddress) deliveryAddress.value = '';
        deliveryDetails?.classList.add('d-none');
        discount.value = 0;
        tax.value = 0;
        paymentMethod.value = 'Cash';
        splitPayments = [];
        setPaymentMode('single');
        cash.value = '';
        reference.value = '';
        $('[data-pos-invoice]').textContent = 'New sale';
        syncCustomerMode(false);
        updateTotals();
        focusElement(search);
    };
    const checkoutPayload = (includeHeldSale = true) => ({
        customer_id: isQuickCustomer() ? null : customer.value || null,
        quick_customer: isQuickCustomer() ? {
            name: quickCustomerName?.value.trim() || '',
            phone: window.TradeFlowPhone?.e164(quickCustomerPhone) || '',
            city: quickCustomerCity?.value.trim() || '',
            address: quickCustomerAddress?.value.trim() || '',
        } : null,
        delivery_required: deliveryRequired?.value === '1',
        delivery_address: deliveryAddress?.value.trim() || (isQuickCustomer() ? quickCustomerAddress?.value.trim() || null : null),
        discount: whole(discount.value),
        tax_rate: whole(tax.value),
        payment_type: isSplitPayment() ? 'Split' : paymentMethod.value,
        payment_method: isSplitPayment() ? 'Split' : paymentMethod.value,
        cash_received: isSplitPayment() ? splitPaymentSummary(totals().grand).cashTendered : (cash.value === '' ? 0 : whole(cash.value)),
        reference: isSplitPayment() ? null : (reference.value.trim() || null),
        split_payments: isSplitPayment() ? splitPayments.map((payment) => ({ method: payment.method, amount: whole(payment.amount), reference: payment.reference?.trim() || null })) : [],
        ...(includeHeldSale ? { held_sale_id: currentHeldSale?.id || null } : {}),
        items: [...cart.values()].map((line) => ({
            product_id: line.id,
            quantity: whole(line.quantity),
            unit_price: whole(line.price),
            price_override_reason: line.priceOverrideReason || null,
            discount_type: line.discountType || 'none',
            discount_value: Number(line.discountValue ?? line.discount ?? 0),
            discount_rate: line.discountType === 'percentage' ? whole(line.discountValue) : 0,
            tax_rate: whole(line.tax),
        })),
    });
    const syncServerDraft = async (cartSnapshot = [...cart.values()], generation = draftGeneration) => {
        if (!config.registerId || !config.draftUrl) return;

        const controller = new AbortController();
        draftSyncController = controller;

        try {
            const payload = await request(config.draftUrl, 'PUT', {
                register_id: config.registerId,
                cart: cartSnapshot,
                draft_generation: generation,
            }, { signal: controller.signal });
            if (!cartSnapshot.length && Number(payload.item_count) !== 0) {
                throw new Error('The current sale could not be cleared.');
            }
        } finally {
            if (draftSyncController === controller) draftSyncController = null;
        }
    };
    const clearServerDraft = async (generation) => {
        if (!config.registerId || !config.draftClearUrl) return;

        const controller = new AbortController();
        draftSyncController = controller;

        try {
            const payload = await request(config.draftClearUrl, 'POST', {
                register_id: config.registerId,
                draft_generation: generation,
            }, { signal: controller.signal });
            draftGeneration = Math.max(draftGeneration, Number(payload.generation || 0));
            if (Number(payload.item_count) !== 0) {
                throw new Error('The current sale could not be cleared.');
            }
        } finally {
            if (draftSyncController === controller) draftSyncController = null;
        }
    };
    const scheduleDraftSync = () => {
        if (!config.registerId || !config.draftUrl) return;

        const cartSnapshot = [...cart.values()];
        const generation = ++draftGeneration;
        draftClearError = null;
        window.clearTimeout(draftSyncTimer);
        draftSyncTimer = window.setTimeout(() => {
            syncServerDraft(cartSnapshot, generation).catch((error) => {
                if (error.name === 'AbortError') return;
                // Resume still has its server-side guard; a transient draft
                // sync failure must not interrupt ordinary cart editing.
            });
        }, 80);
    };
    const complete = async () => {
        if (!config.registerId) {
            flash('warning', 'Open register first', 'Open your register before completing a sale.');
            return;
        }
        if (!cart.size || submitting) return;
        const invalidPrice = [...cart.values()].find((line) => whole(line.price) <= 0);
        if (invalidPrice) {
            flash('warning', 'Valid price required', `${invalidPrice.name} needs a valid unit price before checkout.`);
            focusElement($(`[data-cart-id="${invalidPrice.id}"] [data-cart-field="price"]`), true);
            return;
        }
        const missingOverrideReason = [...cart.values()].find((line) => (
            whole(line.price) !== whole(line.standardPrice ?? line.price)
            && !String(line.priceOverrideReason || '').trim()
        ));
        if (missingOverrideReason) {
            flash('warning', 'Override reason required', 'Provide a reason for each overridden unit price.');
            focusElement($(`[data-cart-id="${missingOverrideReason.id}"] [data-cart-override-reason]`), true);
            return;
        }
        const values = totals();
        const splitMode = isSplitPayment();
        if (splitMode && !values.split.valid) {
            flash('warning', 'Check split payments', values.split.nonCash > values.grand ? 'Non-cash payment amounts cannot exceed the amount due.' : 'Add each payment method once with a whole amount greater than Rs 0.');
            focusElement($('[data-pos-split-amount]'), true);
            return;
        }
        if (values.due > 0 && (!customer.value || isQuickCustomer())) {
            flash('warning', 'Customer required', 'Select a registered customer before leaving a balance due.');
            focusElement(customer);
            return;
        }
        if (deliveryRequired?.value === '1' && !customer.value) {
            flash('warning', 'Customer required', 'Select or create a customer before requesting delivery.');
            focusElement(customer);
            return;
        }
        if (deliveryRequired?.value === '1' && !(deliveryAddress?.value.trim() || (isQuickCustomer() && quickCustomerAddress?.value.trim()))) {
            flash('warning', 'Delivery address required', 'Enter a delivery address before completing this sale.');
            focusElement(deliveryAddress || quickCustomerAddress);
            return;
        }
        if (isQuickCustomer() && !quickCustomerIsValid()) {
            flash('warning', 'Customer details required', 'Enter at least a customer name or phone number.');
            focusElement(quickCustomerName);
            return;
        }
        if (!splitMode && paymentMethod.value !== 'Credit' && !cashIsValid()) {
            flash('warning', 'Invalid received amount', 'Enter a valid received amount.');
            focusElement(cash, true);
            return;
        }
        if (!splitMode && paymentMethod.value === 'Cash' && roundCash(cash.value) < values.grand) {
            flash('warning', 'Insufficient cash received', `Required amount is ${currency(values.grand)}.`);
            focusElement(cash, true);
            return;
        }
        if (!splitMode && paymentMethod.value !== 'Cash' && paymentMethod.value !== 'Credit' && roundCash(cash.value) < 1) {
            flash('warning', 'Invalid received amount', 'Enter a valid received amount.');
            focusElement(cash, true);
            return;
        }
        if (!splitMode && paymentMethod.value !== 'Cash' && paymentMethod.value !== 'Credit' && roundCash(cash.value) > values.grand) {
            flash('warning', 'Payment exceeds amount due', `Payment amount cannot exceed ${currency(values.grand)} for this payment method.`);
            focusElement(cash, true);
            return;
        }
        submitting = true;
        updateTotals();
        try {
            const payload = await request(config.saleUrl, 'POST', checkoutPayload());
            await showReceiptActions(payload);
        } catch (error) {
            flash('error', 'Sale not completed', error.message);
        } finally {
            submitting = false;
            updateTotals();
        }
    };
    const hold = async () => {
        if (holdingSale) return;
        if (!config.registerId) return flash('warning', 'Open register first', 'Open your register before holding a sale.');
        if (!cart.size) return flash('warning', 'Cart is empty');
        holdingSale = true;
        let retryManualHold = false;
        const reholding = Boolean(currentHeldSale);
        if (holdInput) holdInput.disabled = true;
        try {
            const payload = await request(config.holdUrl, 'POST', {
                register_id: config.registerId,
                hold_number: holdInput?.value.trim() || currentHeldSale?.holdNumber || null,
                held_sale_id: currentHeldSale?.id || null,
                cart: [...cart.values()],
                checkout: checkoutPayload(false),
            });
            if (holdInput) holdInput.value = payload.held_sale.hold_number;
            flash('success', reholding ? 'Sale held again successfully' : 'Sale held successfully', `Hold No: ${payload.held_sale.hold_number}`);
            currentHeldSale = null;
            await clearCart({ reportFailure: false }).catch(() => {});
        } catch (error) {
            const holdError = error.payload?.errors?.hold_number?.[0] || '';
            const duplicate = holdError.includes('already in use') || holdError.includes('belongs to another held sale');
            if (duplicate) {
                await flash('error', 'Hold ID already exists', holdError);
                retryManualHold = true;
            } else {
                flash('error', 'Unable to hold sale', error.message);
            }
        } finally {
            holdingSale = false;
            if (holdInput) {
                holdInput.disabled = !config.registerId;
                if (retryManualHold) {
                    focusElement(holdInput, true);
                } else {
                    window.setTimeout(() => { if (holdInput) holdInput.value = ''; }, 1800);
                }
            }
        }
    };
    const canResumeAfterDraftClear = async () => {
        const pendingClear = pendingDraftClear;

        if (pendingClear) {
            try {
                await pendingClear;
            } catch (_) {
                return false;
            }
        }

        return !draftClearError;
    };
    const resume = async (id) => {
        if (resumingHeldSale) return;
        resumingHeldSale = true;
        try {
            if (!await canResumeAfterDraftClear()) {
                await flash('error', 'Unable to clear the current sale', 'Please try again.');
                focusElement(resumeInput, true);
                return;
            }
            if (cart.size || currentHeldSale) {
                await flash('warning', 'Current sale in progress', 'Please hold or clear the current cart before resuming another sale.');
                focusElement(resumeInput, true);
                return;
            }
            const payload = await request(`${config.holdUrl.replace('/hold', '')}/resume/${id}`, 'POST');
            const held = payload.held_sale;
            currentHeldSale = { id: held.id, holdNumber: held.hold_number };
            cart.clear();
            (held.cart_payload || []).forEach((line) => cart.set(Number(line.id || line.product_id), { ...line, id: Number(line.id || line.product_id) }));
            const checkout = held.checkout_payload || {};
            customer.value = checkout.quick_customer ? '__new__' : checkout.customer_id || '';
            discount.value = checkout.discount || 0;
            tax.value = checkout.tax_rate || 0;
            paymentMethod.value = checkout.payment_method && checkout.payment_method !== 'Split' ? checkout.payment_method : 'Cash';
            splitPayments = Array.isArray(checkout.split_payments) ? checkout.split_payments.map((payment) => ({ method: payment.method, amount: payment.amount ?? '', reference: payment.reference || '' })) : [];
            setPaymentMode(checkout.payment_type === 'Split' ? 'split' : 'single', { preserve: true });
            cash.value = checkout.cash_received || '';
            reference.value = checkout.reference || '';
            if (deliveryRequired) deliveryRequired.value = checkout.delivery_required ? '1' : '0';
            if (deliveryAddress) deliveryAddress.value = checkout.delivery_address || '';
            deliveryDetails?.classList.toggle('d-none', deliveryRequired?.value !== '1');
            if (checkout.quick_customer) {
                quickCustomerName && (quickCustomerName.value = checkout.quick_customer.name || '');
                if (quickCustomerPhone) {
                    if (window.TradeFlowPhone?.setNumber) window.TradeFlowPhone.setNumber(quickCustomerPhone, checkout.quick_customer.phone || '');
                    else quickCustomerPhone.value = checkout.quick_customer.phone || '';
                }
                quickCustomerCity && (quickCustomerCity.value = checkout.quick_customer.city || '');
                quickCustomerAddress && (quickCustomerAddress.value = checkout.quick_customer.address || '');
            }
            syncCustomerMode(false);
            selectedCartId = cart.size ? [...cart.keys()][0] : null;
            renderCart();
            if (holdInput) holdInput.value = held.hold_number;
            if (resumeInput) resumeInput.value = '';
            resumeLookupMessage = '';
            resumeMatches = [];
            activeResumeMatch = -1;
            closeSuggestions(resumeInput, resumeSuggestions);
            clearInlineError(resumeError);
            focusElement(search);
        } catch (error) {
            flash('error', 'Unable to resume sale', error.message);
        } finally {
            resumingHeldSale = false;
        }
    };
    const normalizedReference = (value) => String(value || '').trim().toUpperCase();
    const referenceMatch = (term, matches, activeIndex, prefix, key) => {
        const normalized = normalizedReference(term);
        const padded = /^\d+$/.test(normalized) ? `${prefix}-${normalized.padStart(6, '0')}` : normalized;
        return matches.find((match) => normalizedReference(match[key]) === normalized || normalizedReference(match[key]) === padded)
            || matches[activeIndex]
            || matches[0];
    };
    const renderResumeSuggestions = () => renderSuggestions(
        resumeInput, resumeSuggestions, resumeMatches, activeResumeMatch,
        (item, match) => {
            item.innerHTML = `<strong>${escapeHtml(match.hold_number)}</strong><small>${escapeHtml(match.customer_name)}</small>`;
        },
        (index) => resumeMatches[index] && resume(resumeMatches[index].id),
    );
    const searchHeldSales = async () => {
        const term = resumeInput?.value.trim() || '';
        if (!term) {
            resumeLookupMessage = '';
            resumeMatches = [];
            activeResumeMatch = -1;
            renderResumeSuggestions();
            return [];
        }
        try {
            const payload = await request(`${config.heldSearchUrl}?${new URLSearchParams({ q: term })}`);
            resumeMatches = payload.held_sales || [];
            resumeLookupMessage = payload.message || '';
            activeResumeMatch = resumeMatches.length ? 0 : -1;
            renderResumeSuggestions();
            return resumeMatches;
        } catch (error) {
            resumeMatches = [];
            activeResumeMatch = -1;
            renderResumeSuggestions();
            resumeLookupMessage = 'Unable to search held sales. Please try again.';
            showInlineError(resumeError, resumeLookupMessage);
            return [];
        }
    };
    const submitResumeSearch = async () => {
        clearInlineError(resumeError);
        const term = resumeInput?.value.trim() || '';
        if (!term) return;
        if (cart.size || currentHeldSale) {
            await flash('warning', 'Current sale in progress', 'Please hold or clear the current cart before resuming another sale.');
            focusElement(resumeInput, true);
            return;
        }
        const matches = await searchHeldSales();
        const match = referenceMatch(term, matches, activeResumeMatch, 'HOLD', 'hold_number');
        if (!match) {
            showInlineError(resumeError, resumeLookupMessage || 'Hold number not found.');
            focusElement(resumeInput, true);
            return;
        }
        resume(match.id);
    };
    const renderHistorySuggestions = () => renderSuggestions(
        historyInput, historySuggestions, historyMatches, activeHistoryMatch,
        (item, match) => {
            item.innerHTML = `<strong>${escapeHtml(match.number)}</strong><small>${escapeHtml(match.customer_name)} - ${currency(match.amount)}</small>`;
        },
        (index) => historyMatches[index] && window.open(historyMatches[index].url, '_blank', 'noopener'),
    );
    const searchHistory = async () => {
        const term = historyInput?.value.trim() || '';
        if (!term) {
            historyMatches = [];
            activeHistoryMatch = -1;
            renderHistorySuggestions();
            return [];
        }
        try {
            const payload = await request(`${config.invoiceSearchUrl}?${new URLSearchParams({ q: term })}`);
            historyMatches = payload.invoices || [];
            activeHistoryMatch = historyMatches.length ? 0 : -1;
            renderHistorySuggestions();
            return historyMatches;
        } catch (error) {
            historyMatches = [];
            activeHistoryMatch = -1;
            renderHistorySuggestions();
            showInlineError(historyError, 'Unable to search sale history. Please try again.');
            return [];
        }
    };
    const submitHistorySearch = async () => {
        clearInlineError(historyError);
        const term = historyInput?.value.trim() || '';
        if (!term) return;
        const matches = await searchHistory();
        const match = referenceMatch(term, matches, activeHistoryMatch, 'INV', 'number');
        if (!match) {
            showInlineError(historyError, 'Invoice number not found.');
            focusElement(historyInput, true);
            return;
        }
        window.open(match.url, '_blank', 'noopener');
        closeSuggestions(historyInput, historySuggestions);
    };
    const openRegister = async () => {
        const result = await Swal.fire({
            title: 'Open Register',
            html: `<div class="tf-pos-register-dialog">
                <div><label for="pos-opening-cash">Opening Cash</label><input id="pos-opening-cash" class="swal2-input" type="number" min="0" step="1" inputmode="numeric" value="0"></div>
                <div><label for="pos-opening-note">Opening Note <span>Optional</span></label><textarea id="pos-opening-note" class="swal2-textarea" rows="3" maxlength="500"></textarea></div>
            </div>`,
            showCancelButton: true,
            confirmButtonText: 'Open Register',
            cancelButtonText: 'Cancel',
            buttonsStyling: false,
            focusConfirm: false,
            didOpen: (popup) => {
                submitRegisterDialogOnEnter(popup);
                focusElement(document.getElementById('pos-opening-cash'), true, true);
            },
            customClass: {
                popup: 'tf-pos-register-modal',
                actions: 'tf-pos-register-actions',
                confirmButton: 'btn btn-tf-primary',
                cancelButton: 'btn btn-outline-secondary',
            },
            preConfirm: () => {
                const openingCashValue = document.getElementById('pos-opening-cash').value.trim();
                if (!/^\d+$/.test(openingCashValue)) {
                    Swal.showValidationMessage('Opening cash must be a whole number of Rs 0 or more.');
                    return false;
                }

                return {
                    opening_cash: whole(openingCashValue),
                    opening_note: document.getElementById('pos-opening-note').value.trim(),
                };
            },
        });
        if (!result.isConfirmed) return;
        try {
            const payload = await request(config.openRegisterUrl, 'POST', result.value);
            const register = payload.register;
            config.registerId = register.id;
            registerStatus?.classList.add('is-open');
            if (registerLabel) registerLabel.textContent = 'Register Open';
            if (openingCash) openingCash.textContent = currency(register.opening_cash);
            holdInput?.removeAttribute('disabled');
            registerRequired?.classList.add('d-none');
            cashActions?.removeAttribute('hidden');

            if (registerAction) {
                registerAction.innerHTML = config.canCloseRegister
                    ? '<button type="button" class="btn btn-outline-danger" data-pos-close-register><i class="bi bi-lock"></i><span>Close Register</span></button>'
                    : '';
                $('[data-pos-close-register]', registerAction)?.addEventListener('click', closeRegister);
            }

            updateTotals();
            showRegisterFeedback('opened');
            focusElement(search);
        } catch (error) {
            flash('error', 'Unable to open register', error.message);
        }
    };
    const closeRegister = async () => {
        if (!config.registerId) return;

        let reconciliation;
        try {
            const payload = await request(`${config.registerBaseUrl}/${config.registerId}/reconciliation`);
            reconciliation = payload.reconciliation;
        } catch (error) {
            flash('error', 'Unable to load reconciliation', error.message);
            return;
        }

        const reconciliationRow = (label, amount, tone = '') => `<div class="tf-pos-reconciliation-row ${tone}"><span>${label}</span><strong>${currency(amount)}</strong></div>`;

        const result = await Swal.fire({
            title: 'Close Register & Reconcile',
            html: `<p class="tf-pos-register-confirmation-copy">Review the expected cash before confirming this shift.</p><div class="tf-pos-reconciliation-summary">
                ${reconciliationRow('Opening Cash', reconciliation.opening_cash)}
                ${reconciliationRow('Cash Sales', reconciliation.cash_sales)}
                ${reconciliationRow('Cash Refunds', -reconciliation.cash_refunds, 'is-negative')}
                ${reconciliationRow('Cash In', reconciliation.cash_in)}
                ${reconciliationRow('Cash Out', -reconciliation.cash_out, 'is-negative')}
                ${reconciliationRow('Expected Closing Cash', reconciliation.expected_cash, 'is-total')}
            </div><div class="tf-pos-register-dialog">
                <div><label for="pos-closing-cash">Actual Closing Cash</label><input id="pos-closing-cash" class="swal2-input" type="number" min="0" step="1" inputmode="numeric" value="0"></div>
                <div class="tf-pos-reconciliation-row is-variance"><span data-pos-variance-label>Cash Shortage</span><strong data-pos-variance>Rs 0</strong></div>
                <div><label for="pos-closing-note">Closing Note <span>Optional</span></label><textarea id="pos-closing-note" class="swal2-textarea" rows="3" maxlength="500"></textarea></div>
            </div>`,
            showCancelButton: true,
            confirmButtonText: 'Close Register',
            cancelButtonText: 'Cancel',
            buttonsStyling: false,
            focusConfirm: false,
            showLoaderOnConfirm: true,
            allowOutsideClick: () => !Swal.isLoading(),
            allowEscapeKey: () => !Swal.isLoading(),
            didOpen: (popup) => {
                submitRegisterDialogOnEnter(popup);
                const actualCash = document.getElementById('pos-closing-cash');
                const variance = popup.querySelector('[data-pos-variance]');
                const varianceLabel = popup.querySelector('[data-pos-variance-label]');
                const renderVariance = () => {
                    const difference = whole(actualCash.value) - whole(reconciliation.expected_cash);
                    variance.textContent = currency(Math.abs(difference));
                    varianceLabel.textContent = difference > 0 ? 'Cash Excess' : difference < 0 ? 'Cash Shortage' : 'Balanced';
                    variance.closest('.tf-pos-reconciliation-row').classList.toggle('is-positive', difference > 0);
                    variance.closest('.tf-pos-reconciliation-row').classList.toggle('is-negative', difference < 0);
                };
                actualCash.addEventListener('input', renderVariance);
                renderVariance();
                focusElement(actualCash, true, true);
            },
            customClass: {
                popup: 'tf-pos-register-modal',
                actions: 'tf-pos-register-actions',
                confirmButton: 'btn btn-danger',
                cancelButton: 'btn btn-outline-secondary',
            },
            preConfirm: async () => {
                const closingCashValue = document.getElementById('pos-closing-cash').value.trim();
                if (!/^\d+$/.test(closingCashValue)) {
                    Swal.showValidationMessage('Closing cash must be a whole number of Rs 0 or more.');
                    return false;
                }

                try {
                    return await request(`${config.openRegisterUrl.replace('/open', '')}/${config.registerId}/close`, 'PATCH', {
                        closing_cash: whole(closingCashValue),
                        closing_note: document.getElementById('pos-closing-note').value.trim(),
                    });
                } catch (error) {
                    Swal.showValidationMessage(error.message || 'Unable to close the register.');
                    return false;
                }
            },
        });
        if (!result.isConfirmed) return;

        await showRegisterFeedback('closed');
        window.location.reload();
    };
    const recordCashMovement = async (type) => {
        if (!config.registerId) return;

        const result = await Swal.fire({
            title: type,
            html: `<div class="tf-pos-register-dialog"><div><label for="pos-cash-movement-amount">Amount</label><input id="pos-cash-movement-amount" class="swal2-input" type="number" min="1" step="1" inputmode="numeric"></div><div><label for="pos-cash-movement-reason">Reason</label><textarea id="pos-cash-movement-reason" class="swal2-textarea" rows="3" maxlength="500"></textarea></div><div><label for="pos-cash-movement-reference">Reference <span>Optional</span></label><input id="pos-cash-movement-reference" class="swal2-input" maxlength="120"></div></div>`,
            showCancelButton: true,
            confirmButtonText: `Record ${type}`,
            cancelButtonText: 'Cancel',
            buttonsStyling: false,
            focusConfirm: false,
            showLoaderOnConfirm: true,
            allowOutsideClick: () => !Swal.isLoading(),
            customClass: { popup: 'tf-pos-register-modal', actions: 'tf-pos-register-actions', confirmButton: type === 'Cash In' ? 'btn btn-success' : 'btn btn-warning', cancelButton: 'btn btn-outline-secondary' },
            didOpen: (popup) => { submitRegisterDialogOnEnter(popup); focusElement(document.getElementById('pos-cash-movement-amount'), true, true); },
            preConfirm: async () => {
                const amount = document.getElementById('pos-cash-movement-amount').value.trim();
                const reason = document.getElementById('pos-cash-movement-reason').value.trim();
                if (!/^\d+$/.test(amount) || whole(amount) < 1) { Swal.showValidationMessage('Enter a whole amount greater than Rs 0.'); return false; }
                if (!reason) { Swal.showValidationMessage('A reason is required.'); return false; }
                try {
                    return await request(`${config.registerBaseUrl}/${config.registerId}/cash-movements`, 'POST', { type, amount: whole(amount), reason, reference: document.getElementById('pos-cash-movement-reference').value.trim() });
                } catch (error) {
                    Swal.showValidationMessage(error.message || `Unable to record ${type}.`);
                    return false;
                }
            },
        });
        if (result.isConfirmed) flash('success', `${type} recorded`);
    };
    /* Legacy modal cart editing intentionally disabled. Inline cart fields below are the only editor.
    const editLine = async (line) => {
        if (!window.Swal) return beginEdit(line);
        const canDiscount = Boolean(config.canApplyDiscount);
        const currentType = line.discountType || (whole(line.discount) > 0 ? 'percentage' : 'none');
        const standardPrice = whole(line.standardPrice ?? line.price);
        const hasPriceOverride = whole(line.price) !== standardPrice;
        const result = await Swal.fire({
            title: 'Edit cart item',
            html: `<div class="row g-2 text-start">
                <div class="col-6"><label class="form-label">Quantity</label><input id="pos-line-qty" class="swal2-input m-0 w-100" type="number" min="1" max="${line.stock}" step="1" value="${line.quantity}"></div>
                <div class="col-6"><label class="form-label">Unit price</label><input id="pos-line-price" class="swal2-input m-0 w-100" type="number" min="0" step="1" value="${line.price}" ${config.canUseCustomPrice ? '' : 'readonly'}></div>
                <div class="col-12"><small class="text-muted">Standard price: <strong>Rs ${numberWithCommas(standardPrice)}</strong>${config.canUseCustomPrice ? ' · Any different price requires a reason.' : ''}</small></div>
                ${config.canUseCustomPrice ? `<div id="pos-line-override-reason-wrap" class="col-12 ${hasPriceOverride ? '' : 'd-none'}"><label class="form-label">Override reason <span class="text-danger">*</span></label><div class="input-group"><input id="pos-line-override-reason" class="swal2-input m-0 flex-grow-1" maxlength="500" value="${escapeHtml(line.priceOverrideReason || '')}" placeholder="Why is this price being changed?"><button type="button" id="pos-line-reset-price" class="btn btn-outline-secondary">Use standard</button></div></div>` : ''}
                ${canDiscount ? `<div class="col-6"><label class="form-label">Discount type</label><select id="pos-line-discount-type" class="swal2-select m-0 w-100"><option value="none">None</option><option value="percentage">Percentage</option><option value="fixed">Fixed Amount</option></select></div><div class="col-6"><label class="form-label" id="pos-line-discount-label">Discount value</label><input id="pos-line-discount-value" class="swal2-input m-0 w-100" type="number" min="0" step="0.01" value="${Number(line.discountValue ?? line.discount ?? 0)}"></div>` : '<div class="col-12"><small class="text-muted">You are not permitted to apply line discounts.</small></div>'}
                <div class="col-12 small border-top pt-2 mt-2"><div class="d-flex justify-content-between"><span>Gross total</span><strong id="pos-line-gross"></strong></div><div class="d-flex justify-content-between"><span>Discount</span><strong id="pos-line-discount-amount"></strong></div><div class="d-flex justify-content-between"><span>Net total</span><strong id="pos-line-net"></strong></div></div>
            </div>`,
            showCancelButton: true, confirmButtonText: 'Update Item', focusConfirm: false,
            didOpen: () => {
                const type = document.querySelector('#pos-line-discount-type');
                const value = document.querySelector('#pos-line-discount-value');
                const qtyInput = document.querySelector('#pos-line-qty'); const priceInput = document.querySelector('#pos-line-price');
                const overrideReason = document.querySelector('#pos-line-override-reason'); const overrideReasonWrap = document.querySelector('#pos-line-override-reason-wrap');
                const resetPrice = document.querySelector('#pos-line-reset-price');
                if (type) type.value = currentType;
                const preview = () => { const qtyValue = whole(qtyInput.value), priceValue = whole(priceInput.value), kind = type?.value || 'none', amount = Number(value?.value || 0) || 0, gross = qtyValue * priceValue, applied = kind === 'percentage' ? gross * amount / 100 : (kind === 'fixed' ? amount : 0); if (overrideReasonWrap) overrideReasonWrap.classList.toggle('d-none', priceValue === standardPrice); const discountLabel = document.querySelector('#pos-line-discount-label'); if (discountLabel) discountLabel.textContent = kind === 'percentage' ? 'Discount %' : 'Discount amount'; document.querySelector('#pos-line-gross').textContent = currency(gross); document.querySelector('#pos-line-discount-amount').textContent = `- ${currency(applied)}`; document.querySelector('#pos-line-net').textContent = currency(Math.max(0, gross - applied)); };
                [type, value, qtyInput, priceInput].filter(Boolean).forEach(input => { input.addEventListener('input', preview); input.addEventListener('change', preview); }); preview(); qtyInput.focus(); qtyInput.select();
                resetPrice?.addEventListener('click', () => { priceInput.value = standardPrice; if (overrideReason) overrideReason.value = ''; preview(); priceInput.focus(); });
            },
            preConfirm: () => {
                const quantity = whole(document.querySelector('#pos-line-qty').value); const price = whole(document.querySelector('#pos-line-price').value);
                const type = document.querySelector('#pos-line-discount-type')?.value || 'none'; const value = Number(document.querySelector('#pos-line-discount-value')?.value || 0);
                const priceOverrideReason = (document.querySelector('#pos-line-override-reason')?.value || '').trim();
                const gross = quantity * price;
                if (quantity < 1 || quantity > line.stock) return Swal.showValidationMessage(`Quantity must be between 1 and ${line.stock}.`);
                if (price !== standardPrice && !config.canUseCustomPrice) return Swal.showValidationMessage('You are not permitted to override the standard POS price.');
                if (price !== standardPrice && !priceOverrideReason) return Swal.showValidationMessage('Provide a reason for the price override.');
                if (!Number.isFinite(value) || value < 0 || (type === 'percentage' && value > 100) || (type === 'fixed' && value > gross)) return Swal.showValidationMessage('Enter a valid line discount.');
                return { quantity, price, priceOverrideReason: price === standardPrice ? null : priceOverrideReason, discountType: type, discountValue: type === 'none' ? 0 : value };
            },
        });
        if (!result.isConfirmed) return;
        Object.assign(line, result.value, { standardPrice, discount: result.value.discountType === 'percentage' ? result.value.discountValue : 0 });
        renderCart();
        focusElement(search);
    };
    */
    const syncInlinePriceState = (row, line) => {
        const standardPrice = whole(line.standardPrice ?? line.price);
        const isOverride = whole(line.price) !== standardPrice;
        const context = $('[data-cart-price-context]', row);
        const wrap = $('[data-cart-override-wrap]', row);
        const reason = $('[data-cart-override-reason]', row);

        if (context) {
            context.textContent = `Std: ${currency(standardPrice)}${isOverride ? ' · Override' : ''}`;
            context.classList.toggle('is-overridden', isOverride);
        }
        if (wrap) wrap.hidden = !isOverride;
        if (reason) {
            reason.required = isOverride;
            if (!isOverride) {
                line.priceOverrideReason = null;
                reason.value = '';
            }
        }
    };
    const setWholeCartField = (input, line, row) => {
        const field = input.dataset.cartField;
        let message = '';
        const rawValue = rawMoney(input.value);
        if (field === 'price' && !config.canUseCustomPrice) {
            message = 'You do not have permission to override the standard POS price.';
        } else if (!/^\d+$/.test(rawValue)) {
            message = 'Only whole numbers are allowed.';
        } else {
            const value = whole(rawValue);
            if (value < (field === 'quantity' ? 1 : 0)) {
                message = field === 'quantity' ? 'Quantity must be at least 1.' : 'Unit price cannot be negative.';
            } else if (field === 'quantity' && value > line.stock) {
                message = `Insufficient stock. Only ${line.stock} units are available.`;
            } else {
                line[field] = value;
            }
        }
        input.setCustomValidity(message);
        input.classList.toggle('is-invalid', Boolean(message));
        if (!message && field === 'price') syncInlinePriceState(row, line);
        return !message;
    };
    const selectCartRow = (id) => {
        if (!cart.has(id)) return;
        selectedCartId = id;
        $$('.tf-pos-cart-table tbody tr[data-cart-id]').forEach((row) => row.classList.toggle('is-selected', Number(row.dataset.cartId) === id));
    };
    const cartFields = (row) => $$('[data-cart-field]', row);
    const checkoutFields = () => [
        customer,
        ...(isQuickCustomer() ? [quickCustomerName, quickCustomerPhone, quickCustomerCity, quickCustomerAddress].filter(Boolean) : []),
        deliveryRequired,
        ...(deliveryRequired?.value === '1' ? [deliveryAddress].filter(Boolean) : []),
        discount,
        tax,
        paymentMode,
        ...(isSplitPayment() ? $$('[data-pos-split-method], [data-pos-split-amount], [data-pos-split-reference]') : [cash, paymentMethod, reference]),
        completeButton,
    ];
    const visibleCheckoutFields = () => checkoutFields().filter((field) => field
        && !field.disabled
        && field.type !== 'hidden'
        && !field.closest('.d-none, [hidden], [aria-hidden="true"]')
        && field.getClientRects().length);
    const focusNextCheckoutField = (field) => {
        const fields = visibleCheckoutFields();
        const next = fields[fields.indexOf(field) + 1];
        if (next) focusElement(next, next.matches('input, textarea'));
    };
    const focusPreviousIfEmpty = (event, fields) => {
        const field = event.target;
        const currentIndex = fields.indexOf(field);
        const hasTextValue = field.matches('input, textarea') && field.value !== '';
        if (event.key !== 'Backspace' || hasTextValue || currentIndex < 1) return false;
        event.preventDefault();
        focusElement(fields[currentIndex - 1], true);
        return true;
    };
    const syncCustomerMode = (focusNew = false) => {
        const creating = isQuickCustomer();
        quickCustomerPanel?.classList.toggle('d-none', !creating);
        if (creating && focusNew) {
            requestAnimationFrame(() => quickCustomerName?.focus());
        }
    };

    let nativeCheckoutSelectField = null;
    let nativeCheckoutSelectOpen = false;
    let nativeCheckoutSelectAdvanceTimer = null;
    const syncDeliveryMode = () => {
        const required = deliveryRequired?.value === '1';
        deliveryDetails?.classList.toggle('d-none', !required);
        if (!required && deliveryAddress) deliveryAddress.value = '';
    };
    const advanceFromNativeCheckoutSelect = (field) => {
        requestAnimationFrame(() => {
            if (field === deliveryRequired && deliveryRequired.value === '1') {
                focusElement(deliveryAddress);
            } else if (field === customer && isQuickCustomer()) {
                focusElement(quickCustomerName);
            } else {
                focusNextCheckoutField(field);
            }
        });
    };
    checkoutPanel?.addEventListener('change', (event) => {
        const field = event.target;
        if (!(field instanceof HTMLSelectElement) || !checkoutPanel.contains(field)) return;
        if (field === deliveryRequired) syncDeliveryMode();

        const advanceAfterSelection = nativeCheckoutSelectField === field;
        nativeCheckoutSelectField = null;
        nativeCheckoutSelectOpen = false;
        if (nativeCheckoutSelectAdvanceTimer) {
            window.clearTimeout(nativeCheckoutSelectAdvanceTimer);
            nativeCheckoutSelectAdvanceTimer = null;
        }
        if (advanceAfterSelection) advanceFromNativeCheckoutSelect(field);
    });

    grid.addEventListener('click', (event) => {
        const card = event.target.closest('[data-product]');
        const product = card ? parseProduct(card) : null;
        if (product) addProduct(product);
    });
    grid.addEventListener('keydown', (event) => {
        const card = event.target.closest('[data-product]');
        if (!card) return;

        const directions = {
            ArrowLeft: 'left',
            ArrowRight: 'right',
            ArrowUp: 'up',
            ArrowDown: 'down',
        };
        if (directions[event.key]) {
            event.preventDefault();
            moveActiveProduct(directions[event.key], true);
            return;
        }
        if (event.key !== 'Enter') return;

        event.preventDefault();
        const product = parseProduct(card);
        if (product) addProduct(product);
    });
    cartBody.addEventListener('click', (event) => {
        const row = event.target.closest('[data-cart-id]');
        if (!row) return;
        const line = cart.get(Number(row.dataset.cartId));
        if (!line) return;
        selectCartRow(line.id);
        const action = event.target.closest('[data-cart-action]')?.dataset.cartAction;
        if (!action) return;
        if (action === 'reset-price') {
            line.price = whole(line.standardPrice ?? line.price);
            line.priceOverrideReason = null;
            const priceInput = $('[data-cart-field="price"]', row);
            if (priceInput) priceInput.value = line.price;
            syncInlinePriceState(row, line);
            refreshInlineRow(row, line);
            scheduleDraftSync();
            return;
        }
        if (action === 'remove') {
            cart.delete(line.id);
            renderCart();
            focusElement(search);
        }
    });
    const applyInlineCartField = (event) => {
        const input = event.target.closest('[data-cart-field]');
        const row = event.target.closest('[data-cart-id]');
        if (!input || !row) return;
        const line = cart.get(Number(row.dataset.cartId));
        if (!line || !setWholeCartField(input, line, row)) return;
        refreshInlineRow(row, line);
        scheduleDraftSync();
    };
    cartBody.addEventListener('input', applyInlineCartField);
    cartBody.addEventListener('change', applyInlineCartField);
    cartBody.addEventListener('input', (event) => {
        const input = event.target.closest('[data-cart-override-reason]');
        const row = event.target.closest('[data-cart-id]');
        if (!input || !row) return;
        const line = cart.get(Number(row.dataset.cartId));
        if (!line) return;
        line.priceOverrideReason = input.value.trim() || null;
        input.setCustomValidity(input.required && !line.priceOverrideReason ? 'Provide a reason for the price override.' : '');
        scheduleDraftSync();
    });
    cartBody.addEventListener('keydown', (event) => {
        const row = event.target.closest('[data-cart-id]');
        if (!row) return;
        if (event.target.matches('[data-cart-field]') && ['e', 'E', '+', '-', '.', ','].includes(event.key)) {
            event.preventDefault();
            return;
        }
        if (event.key === 'Tab' && !event.shiftKey && event.target.matches('[data-cart-field], [data-cart-override-reason]')) {
            const priceInput = $('[data-cart-field="price"]', row);
            const overrideReason = $('[data-cart-override-reason]', row);
            event.preventDefault();

            if (event.target.matches('[data-cart-field="quantity"]') && priceInput && !priceInput.readOnly) {
                focusElement(priceInput, true);
            } else if (event.target === priceInput && overrideReason && !overrideReason.closest('[hidden]')) {
                focusElement(overrideReason, true);
            } else {
                // End cart editing at product search, not Checkout. This lets
                // a cashier immediately add the next item to the same sale.
                focusElement(search, true);
            }
            return;
        }
        if (event.key === 'Enter' && event.target.matches('[data-cart-field], [data-cart-override-reason]')) {
            event.preventDefault();
            const fields = [...cartFields(row), $('[data-cart-override-reason]', row)]
                .filter((field) => field && !field.readOnly && !field.disabled && !field.closest('[hidden]'));
            const currentIndex = fields.indexOf(event.target);
            if (event.target.validity.valid && currentIndex !== -1 && currentIndex < fields.length - 1) {
                focusElement(fields[currentIndex + 1], true);
            } else {
                focusElement(search, true);
            }
        }
    });
    [discount, tax].forEach((input) => {
        input.addEventListener('input', updateTotals);
        input.addEventListener('change', updateTotals);
    });
    cash.addEventListener('input', () => {
        cash.setCustomValidity(cash.value && !cashIsValid() ? 'Enter a valid cash amount.' : '');
        updateTotals();
    });
    cash.addEventListener('keydown', (event) => {
        if (['e', 'E', '+', '-', ','].includes(event.key)) event.preventDefault();
    });
    customer.addEventListener('change', () => {
        syncCustomerMode(false);
        updateTotals();
    });
    [quickCustomerName, quickCustomerPhone, quickCustomerCity, quickCustomerAddress]
        .filter(Boolean)
        .forEach((input) => input.addEventListener('input', () => updateTotals()));
    paymentMode?.addEventListener('change', () => {
        setPaymentMode(paymentMode.value);
        updateTotals();
    });
    paymentMethod.addEventListener('change', () => {
        paymentType.value = paymentMethod.value;
        updateTotals();
    });
    addSplitPayment?.addEventListener('click', addSplitPaymentRow);
    splitPaymentRows?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-pos-remove-split-payment]');
        if (!button) return;
        const row = button.closest('[data-pos-split-row]');
        splitPayments.splice(Number(row?.dataset.posSplitRow), 1);
        renderSplitPayments();
        updateTotals();
    });
    splitPaymentRows?.addEventListener('input', (event) => {
        const row = event.target.closest('[data-pos-split-row]');
        const payment = splitPayments[Number(row?.dataset.posSplitRow)];
        if (!payment) return;
        if (event.target.matches('[data-pos-split-amount]')) payment.amount = event.target.value;
        if (event.target.matches('[data-pos-split-reference]')) payment.reference = event.target.value;
        updateTotals();
    });
    splitPaymentRows?.addEventListener('change', (event) => {
        if (!event.target.matches('[data-pos-split-method]')) return;
        const row = event.target.closest('[data-pos-split-row]');
        const payment = splitPayments[Number(row?.dataset.posSplitRow)];
        if (!payment) return;
        payment.method = event.target.value;
        if (payment.method === 'Cash') payment.reference = '';
        renderSplitPayments();
        updateTotals();
    });
    setPaymentMode('single', { preserve: true });
    search.addEventListener('input', () => {
        keyboardProductSelection = false;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(searchProducts, 250);
    });
    search.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowRight') {
            event.preventDefault();
            keyboardProductSelection = true;
            moveActiveProduct('right');
            return;
        }
        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            keyboardProductSelection = true;
            moveActiveProduct('left');
            return;
        }
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            keyboardProductSelection = true;
            moveActiveProduct('down');
            return;
        }
        if (event.key === 'ArrowUp') {
            event.preventDefault();
            keyboardProductSelection = true;
            moveActiveProduct('up');
            return;
        }
        if (event.key !== 'Enter') return;
        event.preventDefault();
        if (!search.value.trim()) {
            if (!config.registerId) {
                openRegister();
                return;
            }
            const activeCard = $$('[data-product]')[activeProduct];
            const activeProductData = activeCard ? parseProduct(activeCard) : null;
            if (keyboardProductSelection && activeProductData) {
                addProduct(activeProductData);
                return;
            }
            focusElement(customer);
            return;
        }
        addSelectedSearchProduct();
    });
    barcode.addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); scan(); } });
    $('[data-pos-barcode-submit]').addEventListener('click', scan);
    $('[data-pos-categories]').addEventListener('click', (event) => {
        const button = event.target.closest('[data-category]');
        if (!button) return;
        $$('[data-category]').forEach((item) => item.classList.toggle('active', item === button));
        searchProducts();
    });
    clearButton?.addEventListener('click', () => clearCart().catch(() => {}));
    completeButton.addEventListener('click', complete);
    holdInput?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || event.isComposing || event.repeat) return;
        event.preventDefault();
        event.stopPropagation();
        hold();
    });
    $('[data-pos-open-register]')?.addEventListener('click', openRegister);
    $('[data-pos-close-register]')?.addEventListener('click', closeRegister);
    $('[data-pos-cash-in]')?.addEventListener('click', () => recordCashMovement('Cash In'));
    $('[data-pos-cash-out]')?.addEventListener('click', () => recordCashMovement('Cash Out'));
    const bindTopSearch = (input, suggestions, error, getMatches, getActive, setActive, render, search, submit) => {
        if (!input) return;
        input.addEventListener('input', () => {
            clearInlineError(error);
            const timer = input === resumeInput ? resumeSearchTimer : historySearchTimer;
            window.clearTimeout(timer);
            const nextTimer = window.setTimeout(search, 180);
            if (input === resumeInput) resumeSearchTimer = nextTimer;
            else historySearchTimer = nextTimer;
        });
        input.addEventListener('keydown', (event) => {
            if (event.isComposing || event.repeat) return;
            if (event.key === 'Escape') {
                event.preventDefault();
                closeSuggestions(input, suggestions);
                return;
            }
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                const matches = getMatches();
                if (!matches.length) return;
                event.preventDefault();
                const next = Math.max(0, Math.min(matches.length - 1, getActive() + (event.key === 'ArrowDown' ? 1 : -1)));
                setActive(next);
                render();
                return;
            }
            if (event.key !== 'Enter') return;
            event.preventDefault();
            event.stopPropagation();
            submit();
        });
        input.addEventListener('blur', () => window.setTimeout(() => closeSuggestions(input, suggestions), 120));
    };
    bindTopSearch(
        resumeInput, resumeSuggestions, resumeError,
        () => resumeMatches, () => activeResumeMatch, (index) => { activeResumeMatch = index; },
        renderResumeSuggestions, searchHeldSales, submitResumeSearch,
    );
    bindTopSearch(
        historyInput, historySuggestions, historyError,
        () => historyMatches, () => activeHistoryMatch, (index) => { activeHistoryMatch = index; },
        renderHistorySuggestions, searchHistory, submitHistorySearch,
    );
    checkoutPanel?.addEventListener('keydown', (event) => {
        const field = event.target.closest('input, select, textarea, button');
        if (!field || !checkoutPanel.contains(field)) return;
        const fields = visibleCheckoutFields();
        if (focusPreviousIfEmpty(event, fields)) return;
        if (event.key !== 'Enter') return;

        // Keep browser-native select accessibility intact: Enter opens a
        // closed select, arrows navigate its option list, and Enter confirms.
        // No synthetic click or focus change is made while that list is open.
        if (field.matches('select')) {
            const isConfirmingSelection = nativeCheckoutSelectOpen && nativeCheckoutSelectField === field;
            nativeCheckoutSelectField = field;
            if (isConfirmingSelection) {
                // A same-value selection does not emit change, so only then
                // advance after the browser has closed the native menu.
                nativeCheckoutSelectAdvanceTimer = window.setTimeout(() => {
                    if (!nativeCheckoutSelectOpen || nativeCheckoutSelectField !== field) return;
                    nativeCheckoutSelectOpen = false;
                    nativeCheckoutSelectField = null;
                    if (field === deliveryRequired) syncDeliveryMode();
                    advanceFromNativeCheckoutSelect(field);
                }, 0);
            } else {
                nativeCheckoutSelectOpen = true;
            }
            return;
        }

        // Buttons keep their native Enter/click behaviour. This avoids an
        // accidental double Complete Sale call.
        if (field.matches('button')) return;

        // A delivery address is intentionally multiline only with Shift+Enter.
        if (field === deliveryAddress && event.shiftKey) return;

        event.preventDefault();
        if (field === customer) syncCustomerMode(false);
        if (field === discount || field === tax) updateTotals();
        if (field === cash) {
            const values = totals();
            if (!cashIsValid() || (paymentType.value === 'Cash' && roundCash(cash.value) < values.grand)) {
                flash('warning', 'Insufficient cash received', `Required amount is ${currency(values.grand)}.`);
                focusElement(cash, true);
                return;
            }
        }

        focusNextCheckoutField(field);
    });
    root.addEventListener('wheel', (event) => { if (event.target === cash) event.preventDefault(); }, { passive: false });

    setActiveProduct(visibleProductCards().length ? 0 : -1);
    syncCustomerMode(false);
    search.value = '';
    barcode.value = '';
    focusElement(search);
    renderCart();
})();
