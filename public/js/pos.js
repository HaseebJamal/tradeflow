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
    let editingId = null;
    let editSnapshot = null;
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
    let keyboardProductSelection = false;
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
    const paymentMethod = $('[data-pos-payment-method]');
    const cash = $('[data-pos-cash]');
    const tenderLabel = $('[data-pos-tender-label]');
    const changeRow = $('[data-pos-change-row]');
    const changeReturn = $('[data-pos-change]');
    const reference = $('[data-pos-reference]');
    const completeButton = $('[data-pos-complete]');
    const checkoutPanel = $('.tf-pos-checkout-panel');
    const registerStatus = $('[data-pos-register-status]');
    const registerLabel = $('[data-pos-register-label]');
    const openingCash = $('[data-pos-opening-cash]');
    const registerAction = $('[data-pos-register-action]');
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
            html: `<div class="tf-pos-receipt-actions">
                <button type="button" class="btn btn-outline-primary" data-pos-receipt-view><i class="bi bi-eye"></i>View Receipt</button>
                <button type="button" class="btn btn-outline-secondary" data-pos-receipt-print><i class="bi bi-printer"></i>Print Receipt</button>
                <a class="btn btn-outline-success" href="${escapeHtml(payload.receipt_download_url || '#')}" data-pos-receipt-download><i class="bi bi-download"></i>Download PDF</a>
            </div>`,
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
    const request = async (url, method = 'GET', body = null) => {
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

    const lineTotal = (line) => {
        const base = line.quantity * line.price;
        const lineDiscount = base * (line.discount / 100);
        const lineTax = (base - lineDiscount) * (line.tax / 100);
        return base - lineDiscount + lineTax;
    };
    const totals = () => {
        let subtotal = 0;
        cart.forEach((line) => {
            line.lineTotal = lineTotal(line);
            subtotal += line.lineTotal;
        });
        const orderDiscount = subtotal * (whole(discount.value) / 100);
        const taxAmount = (subtotal - orderDiscount) * (whole(tax.value) / 100);
        const grand = Math.round(subtotal - orderDiscount + taxAmount);
        const roundedCash = roundCash(cash.value);
        return {
            subtotal,
            discount: orderDiscount,
            tax: taxAmount,
            grand,
            paid: paymentType.value === 'Credit' ? 0 : Math.min(roundedCash, grand),
            due: Math.max(0, grand - (paymentType.value === 'Credit' ? 0 : Math.min(roundedCash, grand))),
            change: Math.max(0, roundedCash - grand),
        };
    };
    const isQuickCustomer = () => customer.value === '__new__';
    const quickCustomerIsValid = () => !isQuickCustomer()
        || Boolean(quickCustomerName?.value.trim() || quickCustomerPhone?.value.trim());
    const updateTotals = () => {
        const values = totals();
        const payable = $('[data-total="grand"]');
        if (payable) payable.textContent = currency(values.grand);
        const isCash = paymentType.value === 'Cash';
        if (tenderLabel) tenderLabel.textContent = isCash ? 'Cash Received' : 'Payment Amount';
        if (changeRow) changeRow.classList.toggle('d-none', !isCash);
        if (changeReturn) changeReturn.value = currency(isCash ? values.change : 0);
        cash.max = isCash ? '' : String(values.grand);
        const tenderInvalid = cash.value !== '' && !cashIsValid();
        const nonCashOverpayment = !isCash && paymentType.value !== 'Credit' && roundCash(cash.value) > values.grand;
        cash.setCustomValidity(tenderInvalid
            ? 'Enter a whole-number payment amount.'
            : (nonCashOverpayment ? `Payment amount cannot exceed ${currency(values.grand)} for this payment method.` : ''));
        const customerAllowed = !['Credit', 'Split'].includes(paymentType.value)
            || (Boolean(customer.value) && quickCustomerIsValid());
        const roundedCash = roundCash(cash.value);
        const paymentAllowed = paymentType.value === 'Credit'
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
        const isEditing = editingId === line.id;
        const numericField = (field, value, min = 0, max = '') => `<input class="form-control form-control-sm" type="number" min="${min}"${max !== '' ? ` max="${max}"` : ''} step="1" inputmode="numeric" value="${value}" data-cart-field="${field}">`;
        const quantity = isEditing ? numericField('quantity', line.quantity, 1, line.stock) : line.quantity;
        const price = isEditing && config.canUseCustomPrice ? numericField('price', line.price, 0) : `Rs ${numberWithCommas(line.price)}`;
        const actions = isEditing
            ? '<button type="button" class="btn btn-sm btn-outline-success" data-cart-action="save">Save</button><button type="button" class="btn btn-sm btn-outline-secondary" data-cart-action="cancel">Cancel</button>'
            : '<button type="button" class="btn btn-sm btn-outline-primary" data-cart-action="edit">Edit</button><button type="button" class="btn btn-sm btn-outline-danger" data-cart-action="remove">Delete</button>';
        return `<tr data-cart-id="${line.id}" tabindex="0" class="${selectedCartId === line.id ? 'is-selected' : ''}">
            <td>${index + 1}</td><td class="tf-pos-product-cell"><strong>${escapeHtml(line.name)}</strong><small class="d-block text-muted">${escapeHtml(line.barcode || '')} | Stock ${line.stock}</small></td>
            <td>${quantity}</td><td>${price}</td>
            <td class="tf-pos-line-total"><strong data-cart-line-total>${currency(line.lineTotal)}</strong></td><td class="tf-pos-cart-actions-cell"><div class="tf-pos-cart-actions">${actions}</div>${isEditing ? '<small class="d-block text-danger mt-1" data-cart-error aria-live="polite"></small>' : ''}</td>
        </tr>`;
    };
    const numberWithCommas = (value) => whole(value).toLocaleString();
    const renderCart = () => {
        totals();
        if (selectedCartId !== null && !cart.has(selectedCartId)) selectedCartId = null;
        if (editingId !== null && !cart.has(editingId)) editingId = null;
        if (selectedCartId === null && cart.size) selectedCartId = [...cart.keys()][0];
        cartBody.innerHTML = cart.size
            ? [...cart.values()].map(cartRow).join('')
            : '<tr data-pos-empty><td colspan="6" class="text-center text-muted py-5">Scan or select a product to start a sale.</td></tr>';
        window.initTradeFlowMoneyInputs?.(cartBody);
        return updateTotals();
    };
    const refreshEditedRow = (row, line) => {
        const lineTotalCell = $('[data-cart-line-total]', row);
        if (lineTotalCell) lineTotalCell.textContent = currency(lineTotal(line));
        updateTotals();
    };
    const focusEditedRow = () => focusElement($('[data-cart-id].is-selected [data-cart-field]'), true);
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
                discount: 0,
                tax: 0,
                unit: product.unit || '',
            });
        }
        selectedCartId = id;
        keyboardProductSelection = false;
        barcode.value = '';
        // Adding to the cart only changes cart state. Keep the same product
        // DOM node, search term, category, focus context, and scroll offset
        // so a cashier can continue selecting nearby products uninterrupted.
        beginEdit(cart.get(id));
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
    const clearCart = () => {
        cart.clear();
        editingId = null;
        selectedCartId = null;
        renderCart();
        search.value = '';
        barcode.value = '';
        focusElement(search);
    };
    finishCompletedSale = () => {
        clearCart();
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
        paymentType.value = 'Cash';
        paymentMethod.value = 'Cash';
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
        payment_type: paymentType.value,
        payment_method: paymentMethod.value,
        cash_received: cash.value === '' ? 0 : whole(cash.value),
        reference: reference.value.trim() || null,
        ...(includeHeldSale ? { held_sale_id: currentHeldSale?.id || null } : {}),
        items: [...cart.values()].map((line) => ({
            product_id: line.id,
            quantity: whole(line.quantity),
            unit_price: whole(line.price),
            discount_rate: whole(line.discount),
            tax_rate: whole(line.tax),
        })),
    });
    const complete = async () => {
        if (!config.registerId) {
            flash('warning', 'Open register first', 'Open your register before completing a sale.');
            return;
        }
        if (!cart.size || submitting) return;
        const values = totals();
        if (['Credit', 'Split'].includes(paymentType.value) && !customer.value) {
            flash('warning', 'Customer required', 'Select a registered customer for this payment type.');
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
        if (paymentType.value !== 'Credit' && !cashIsValid()) {
            flash('warning', 'Invalid received amount', 'Enter a valid received amount.');
            focusElement(cash, true);
            return;
        }
        if (paymentType.value === 'Cash' && roundCash(cash.value) < values.grand) {
            flash('warning', 'Insufficient cash received', `Required amount is ${currency(values.grand)}.`);
            focusElement(cash, true);
            return;
        }
        if (paymentType.value !== 'Cash' && paymentType.value !== 'Credit' && roundCash(cash.value) < 1) {
            flash('warning', 'Invalid received amount', 'Enter a valid received amount.');
            focusElement(cash, true);
            return;
        }
        if (paymentType.value !== 'Cash' && paymentType.value !== 'Credit' && roundCash(cash.value) > values.grand) {
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
            clearCart();
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
    const resume = async (id) => {
        if (resumingHeldSale) return;
        if (cart.size || currentHeldSale) {
            await flash('warning', 'Current sale in progress', 'Please hold or clear the current cart before resuming another sale.');
            focusElement(resumeInput, true);
            return;
        }
        resumingHeldSale = true;
        try {
            const payload = await request(`${config.holdUrl.replace('/hold', '')}/resume/${id}`, 'POST', {
                current_cart_item_count: cart.size,
                has_active_sale: Boolean(currentHeldSale),
            });
            const held = payload.held_sale;
            currentHeldSale = { id: held.id, holdNumber: held.hold_number };
            cart.clear();
            (held.cart_payload || []).forEach((line) => cart.set(Number(line.id || line.product_id), { ...line, id: Number(line.id || line.product_id) }));
            const checkout = held.checkout_payload || {};
            customer.value = checkout.quick_customer ? '__new__' : checkout.customer_id || '';
            discount.value = checkout.discount || 0;
            tax.value = checkout.tax_rate || 0;
            paymentType.value = checkout.payment_type || 'Cash';
            paymentMethod.value = checkout.payment_method || 'Cash';
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

            if (registerAction) {
                registerAction.innerHTML = '<button type="button" class="btn btn-outline-danger" data-pos-close-register><i class="bi bi-lock"></i><span>Close Register</span></button>';
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

        const result = await Swal.fire({
            title: 'Close register?',
            html: `<p class="tf-pos-register-confirmation-copy">Are you sure you want to close the current POS register?</p><div class="tf-pos-register-dialog">
                <div><label for="pos-closing-cash">Actual Closing Cash</label><input id="pos-closing-cash" class="swal2-input" type="number" min="0" step="1" inputmode="numeric" value="0"></div>
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
                focusElement(document.getElementById('pos-closing-cash'), true, true);
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
    const beginEdit = (line) => {
        editingId = line.id;
        selectedCartId = line.id;
        editSnapshot = { ...line };
        renderCart();
        focusEditedRow();
    };
    const cancelEdit = () => {
        if (editingId !== null && editSnapshot) cart.set(editingId, editSnapshot);
        editingId = null;
        editSnapshot = null;
        renderCart();
    };
    const saveEdit = () => {
        const invalidField = $(`[data-cart-id="${editingId}"] [data-cart-field]:invalid`);
        if (invalidField) {
            const line = cart.get(editingId);
            const message = $('[data-cart-error]', invalidField.closest('[data-cart-id]'))?.textContent || invalidField.validationMessage;
            flash('warning', invalidField.dataset.cartField === 'quantity' ? 'Insufficient stock' : 'Invalid cart value', message || `Only ${line?.stock ?? 0} units are available.`);
            invalidField.reportValidity();
            invalidField.focus();
            return;
        }
        editingId = null;
        editSnapshot = null;
        renderCart();
        search.value = '';
        barcode.value = '';
        focusElement(search);
    };
    const syncEditedRowValidation = (row) => {
        const fields = $$('[data-cart-field]', row);
        fields.forEach((field) => field.classList.toggle('is-invalid', !field.validity.valid));
        const invalidField = fields.find((field) => !field.validity.valid);
        const error = $('[data-cart-error]', row);
        const save = $('[data-cart-action="save"]', row);
        if (error) error.textContent = invalidField?.validationMessage || '';
        if (save) save.disabled = Boolean(invalidField);
        return !invalidField;
    };
    const setWholeCartField = (input, line, row) => {
        const field = input.dataset.cartField;
        const max = field === 'quantity' ? line.stock : field === 'discount' || field === 'tax' ? 100 : null;
        let message = '';
        const rawValue = rawMoney(input.value);
        if (!/^\d+$/.test(rawValue)) {
            message = 'Only whole numbers are allowed.';
        } else {
            const value = whole(rawValue);
            if (value < (field === 'quantity' ? 1 : 0)) {
                message = field === 'quantity' ? 'Quantity must be at least 1.' : `Enter a value between 0 and ${max}.`;
            } else if (max !== null && value > max) {
                message = field === 'quantity' ? `Insufficient stock. Only ${line.stock} units are available.` : `Enter a value between 0 and ${max}.`;
            } else {
                line[field] = value;
            }
        }
        input.setCustomValidity(message);
        return syncEditedRowValidation(row);
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
        cash,
        paymentMethod,
        reference,
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
        if (action === 'edit') beginEdit(line);
        if (action === 'cancel') cancelEdit();
        if (action === 'save') saveEdit();
        if (action === 'remove') {
            cart.delete(line.id);
            if (editingId === line.id) { editingId = null; editSnapshot = null; }
            renderCart();
            focusElement(search);
        }
    });
    cartBody.addEventListener('input', (event) => {
        const input = event.target.closest('[data-cart-field]');
        const row = event.target.closest('[data-cart-id]');
        if (!input || !row) return;
        const line = cart.get(Number(row.dataset.cartId));
        if (!line || !setWholeCartField(input, line, row)) return;
        refreshEditedRow(row, line);
    });
    cartBody.addEventListener('keydown', (event) => {
        const row = event.target.closest('[data-cart-id]');
        if (!row) return;
        if (event.target.matches('[data-cart-field]') && ['e', 'E', '+', '-', '.', ','].includes(event.key)) {
            event.preventDefault();
            return;
        }
        if (event.key === 'Enter' && editingId === Number(row.dataset.cartId)) {
            event.preventDefault();
            const fields = cartFields(row);
            const currentIndex = fields.indexOf(event.target);
            if (event.target.validity.valid && currentIndex !== -1 && currentIndex < fields.length - 1) {
                focusElement(fields[currentIndex + 1], true);
            } else {
                saveEdit();
            }
        }
        if (editingId === Number(row.dataset.cartId)) focusPreviousIfEmpty(event, cartFields(row));
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
    paymentType.addEventListener('change', () => {
        paymentMethod.value = paymentType.value === 'Cash' ? 'Cash' : paymentType.value;
        updateTotals();
    });
    paymentMethod.addEventListener('change', () => {
        paymentType.value = paymentMethod.value;
        updateTotals();
    });
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
    $('[data-pos-clear]').addEventListener('click', clearCart);
    completeButton.addEventListener('click', complete);
    holdInput?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || event.isComposing || event.repeat) return;
        event.preventDefault();
        event.stopPropagation();
        hold();
    });
    $('[data-pos-open-register]')?.addEventListener('click', openRegister);
    $('[data-pos-close-register]')?.addEventListener('click', closeRegister);
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
