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
    let submitting = false;
    let keyboardProductSelection = false;

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
    const discount = $('[data-pos-discount]');
    const tax = $('[data-pos-tax]');
    const paymentType = $('[data-pos-payment-type]');
    const paymentMethod = $('[data-pos-payment-method]');
    const cash = $('[data-pos-cash]');
    const reference = $('[data-pos-reference]');
    const completeButton = $('[data-pos-complete]');
    const registerStatus = $('[data-pos-register-status]');
    const registerLabel = $('[data-pos-register-label]');
    const openingCash = $('[data-pos-opening-cash]');
    const registerAction = $('[data-pos-register-action]');
    const registerRequired = $('[data-pos-register-required]');
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

    const currency = (amount) => `Rs ${Math.round(Number(amount) || 0).toLocaleString()}`;
    const whole = (value) => Math.max(0, Math.trunc(Number(value) || 0));
    const roundCash = (value) => Math.round(Number(value) || 0);
    const cashIsValid = () => /^\d+(?:\.\d{1,2})?$/.test(cash.value.trim());
    const csrfHeaders = { 'X-CSRF-TOKEN': config.csrf, Accept: 'application/json', 'Content-Type': 'application/json' };
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
    const showReceiptActions = async (payload) => {
        const historyUrl = payload.history_url || config.historyUrl;
        if (!window.Swal) {
            window.location.assign(historyUrl);
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
            showCancelButton: true,
            showConfirmButton: false,
            cancelButtonText: 'Sales History',
            buttonsStyling: false,
            allowOutsideClick: false,
            allowEscapeKey: false,
            customClass: {
                popup: 'tf-pos-receipt-modal',
                actions: 'tf-pos-receipt-modal-actions',
                cancelButton: 'btn btn-outline-secondary',
            },
            didOpen: (popup) => {
                const viewButton = popup.querySelector('[data-pos-receipt-view]');
                const printButton = popup.querySelector('[data-pos-receipt-print]');
                const downloadButton = popup.querySelector('[data-pos-receipt-download]');
                const historyButton = Swal.getCancelButton();
                const actions = [viewButton, printButton, downloadButton, historyButton].filter(Boolean);

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
                    if (event.key === 'ArrowRight') nextIndex = Math.min(actions.length - 1, currentIndex + 1);
                    if (event.key === 'ArrowLeft') nextIndex = Math.max(0, currentIndex - 1);
                    if (event.key === 'ArrowDown' && currentIndex < 3) nextIndex = Math.min(3, actions.length - 1);
                    if (event.key === 'ArrowUp' && currentIndex === 3) nextIndex = 0;
                    actions[nextIndex]?.focus({ preventScroll: true });
                });
            },
        });

        if (result.dismiss === Swal.DismissReason.cancel) window.location.assign(historyUrl);
    };
    const request = async (url, method = 'GET', body = null) => {
        const response = await fetch(url, {
            method,
            headers: body ? csrfHeaders : { Accept: 'application/json' },
            body: body ? JSON.stringify(body) : null,
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || Object.values(payload.errors || {}).flat()[0] || 'Unable to process this POS action.');
        return payload;
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
            due: Math.max(0, grand - roundedCash),
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
        const customerAllowed = !['Credit', 'Split'].includes(paymentType.value)
            || (Boolean(customer.value) && quickCustomerIsValid());
        const roundedCash = roundCash(cash.value);
        const paymentAllowed = paymentType.value === 'Credit'
            || (paymentType.value === 'Cash'
                ? cashIsValid() && roundedCash >= values.grand
                : cashIsValid() && roundedCash > 0);
        completeButton.disabled = !config.registerId || cart.size === 0 || !customerAllowed || !paymentAllowed || submitting;
        return values;
    };

    const setActiveProduct = (index) => {
        const cards = $$('[data-product]');
        activeProduct = cards.length ? Math.max(0, Math.min(cards.length - 1, index)) : -1;
        cards.forEach((card, cardIndex) => card.classList.toggle('is-keyboard-active', cardIndex === activeProduct));
        cards[activeProduct]?.scrollIntoView({ block: 'nearest' });
    };
    const productCard = (product) => {
        const payload = JSON.stringify({
            id: product.id,
            name: product.name,
            barcode: product.barcode,
            unit: product.unit,
            price: product.retail_price ?? product.price ?? product.wholesale_price ?? 0,
            stock: product.stock_quantity ?? product.stock ?? 0,
            image: product.image,
        }).replace(/'/g, '&#039;');
        return `<button type="button" class="tf-pos-product-card" data-product='${payload}'>
            <div class="tf-pos-product-image">${product.image ? `<img src="${escapeHtml(`/storage/${product.image}`)}" alt="">` : '<i class="bi bi-box-seam"></i>'}</div>
            <strong>${escapeHtml(product.name)}</strong><small>${escapeHtml(product.barcode || '')}</small>
            <span>${currency(product.retail_price ?? product.price ?? product.wholesale_price ?? 0)}</span><em>${product.stock_quantity ?? product.stock ?? 0} ${escapeHtml(product.unit || '')}</em>
        </button>`;
    };
    const renderProducts = (products) => {
        grid.innerHTML = products.length ? products.map(productCard).join('') : '<div class="text-muted p-3">No matching products.</div>';
        $('[data-pos-product-count]').textContent = `${products.length} available`;
        setActiveProduct(products.length ? 0 : -1);
    };
    const cartRow = (line, index) => {
        const isEditing = editingId === line.id;
        const numericField = (field, value, min = 0, max = '') => `<input class="form-control form-control-sm" type="number" min="${min}"${max !== '' ? ` max="${max}"` : ''} step="1" inputmode="numeric" value="${value}" data-cart-field="${field}">`;
        const quantity = isEditing ? numericField('quantity', line.quantity, 1, line.stock) : line.quantity;
        const price = isEditing && config.canUseCustomPrice ? numericField('price', line.price, 0) : `Rs ${numberWithCommas(line.price)}`;
        const lineDiscount = isEditing ? numericField('discount', line.discount, 0, 100) : `${line.discount}%`;
        const lineTax = isEditing ? numericField('tax', line.tax, 0, 100) : `${line.tax}%`;
        const actions = isEditing
            ? '<button type="button" class="btn btn-sm btn-outline-success" data-cart-action="save">Save</button><button type="button" class="btn btn-sm btn-outline-secondary" data-cart-action="cancel">Cancel</button>'
            : '<button type="button" class="btn btn-sm btn-outline-primary" data-cart-action="edit">Edit</button><button type="button" class="btn btn-sm btn-outline-danger" data-cart-action="remove">Delete</button>';
        return `<tr data-cart-id="${line.id}" tabindex="0" class="${selectedCartId === line.id ? 'is-selected' : ''}">
            <td>${index + 1}</td><td class="tf-pos-product-cell"><strong>${escapeHtml(line.name)}</strong><small class="d-block text-muted">${escapeHtml(line.barcode || '')} | Stock ${line.stock}</small></td>
            <td>${quantity}</td><td>${price}</td><td>${lineDiscount}</td><td>${lineTax}</td>
            <td class="tf-pos-line-total"><strong data-cart-line-total>${currency(line.lineTotal)}</strong></td><td><div class="d-flex gap-1">${actions}</div>${isEditing ? '<small class="d-block text-danger mt-1" data-cart-error aria-live="polite"></small>' : ''}</td>
        </tr>`;
    };
    const numberWithCommas = (value) => Number(value || 0).toLocaleString();
    const renderCart = () => {
        totals();
        if (selectedCartId !== null && !cart.has(selectedCartId)) selectedCartId = null;
        if (editingId !== null && !cart.has(editingId)) editingId = null;
        if (selectedCartId === null && cart.size) selectedCartId = [...cart.keys()][0];
        cartBody.innerHTML = cart.size
            ? [...cart.values()].map(cartRow).join('')
            : '<tr data-pos-empty><td colspan="8" class="text-center text-muted py-5">Scan or select a product to start a sale.</td></tr>';
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
                price: whole(product.retail_price ?? product.price ?? product.wholesale_price ?? 0),
                discount: 0,
                tax: 0,
                unit: product.unit || '',
            });
        }
        selectedCartId = id;
        keyboardProductSelection = false;
        search.value = '';
        barcode.value = '';
        searchProducts();
        beginEdit(cart.get(id));
    };
    const parseProduct = (card) => {
        try { return JSON.parse(card.dataset.product); } catch (_) { return null; }
    };
    const searchProducts = async () => {
        const version = ++searchVersion;
        const params = new URLSearchParams({ q: search.value.trim() });
        const category = $('[data-pos-categories] .active')?.dataset.category;
        if (category) params.set('category_id', category);
        try {
            const payload = await request(`${config.productsUrl}?${params}`);
            if (version === searchVersion) renderProducts(payload.products || []);
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
    const checkoutPayload = () => ({
        customer_id: isQuickCustomer() ? null : customer.value || null,
        quick_customer: isQuickCustomer() ? {
            name: quickCustomerName?.value.trim() || '',
            phone: quickCustomerPhone?.value.trim() || '',
            city: quickCustomerCity?.value.trim() || '',
            address: quickCustomerAddress?.value.trim() || '',
        } : null,
        discount: whole(discount.value),
        tax_rate: whole(tax.value),
        payment_type: paymentType.value,
        payment_method: paymentMethod.value,
        cash_received: cash.value || 0,
        reference: reference.value.trim() || null,
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
        if (!cart.size) return flash('warning', 'Cart is empty');
        try {
            const payload = await request(config.holdUrl, 'POST', { register_id: config.registerId, cart: [...cart.values()], checkout: checkoutPayload() });
            flash('success', 'Sale held', payload.held_sale.hold_number);
            clearCart();
        } catch (error) {
            flash('error', 'Unable to hold sale', error.message);
        }
    };
    const resume = async (id) => {
        try {
            const payload = await request(`${config.holdUrl.replace('/hold', '')}/resume/${id}`, 'POST');
            const held = payload.held_sale;
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
            if (checkout.quick_customer) {
                quickCustomerName && (quickCustomerName.value = checkout.quick_customer.name || '');
                quickCustomerPhone && (quickCustomerPhone.value = checkout.quick_customer.phone || '');
                quickCustomerCity && (quickCustomerCity.value = checkout.quick_customer.city || '');
                quickCustomerAddress && (quickCustomerAddress.value = checkout.quick_customer.address || '');
            }
            syncCustomerMode(false);
            selectedCartId = cart.size ? [...cart.keys()][0] : null;
            renderCart();
            window.bootstrap?.Modal.getOrCreateInstance($('#posHeldModal')).hide();
            focusElement(search);
        } catch (error) {
            flash('error', 'Unable to resume sale', error.message);
        }
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
            didOpen: () => focusElement(document.getElementById('pos-opening-cash'), true, true),
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
            $('[data-pos-hold]')?.removeAttribute('disabled');
            registerRequired?.classList.add('d-none');

            if (registerAction) {
                registerAction.innerHTML = '<button type="button" class="btn btn-outline-danger" data-pos-close-register><i class="bi bi-lock"></i><span>Close Register</span></button>';
                $('[data-pos-close-register]', registerAction)?.addEventListener('click', closeRegister);
            }

            updateTotals();
            flash('success', 'Register opened');
            focusElement(search);
        } catch (error) {
            flash('error', 'Unable to open register', error.message);
        }
    };
    const closeRegister = async () => {
        const result = await Swal.fire({
            title: 'Close Register',
            html: `<div class="tf-pos-register-dialog">
                <div><label for="pos-closing-cash">Actual Closing Cash</label><input id="pos-closing-cash" class="swal2-input" type="number" min="0" step="1" inputmode="numeric" value="0"></div>
                <div><label for="pos-closing-note">Closing Note <span>Optional</span></label><textarea id="pos-closing-note" class="swal2-textarea" rows="3" maxlength="500"></textarea></div>
            </div>`,
            showCancelButton: true,
            confirmButtonText: 'Close Register',
            cancelButtonText: 'Cancel',
            buttonsStyling: false,
            focusConfirm: false,
            didOpen: () => focusElement(document.getElementById('pos-closing-cash'), true, true),
            customClass: {
                popup: 'tf-pos-register-modal',
                actions: 'tf-pos-register-actions',
                confirmButton: 'btn btn-tf-primary',
                cancelButton: 'btn btn-outline-secondary',
            },
            preConfirm: () => {
                const closingCashValue = document.getElementById('pos-closing-cash').value.trim();
                if (!/^\d+$/.test(closingCashValue)) {
                    Swal.showValidationMessage('Closing cash must be a whole number of Rs 0 or more.');
                    return false;
                }

                return {
                    closing_cash: whole(closingCashValue),
                    closing_note: document.getElementById('pos-closing-note').value.trim(),
                };
            },
        });
        if (!result.isConfirmed) return;
        try { await request(`${config.openRegisterUrl.replace('/open', '')}/${config.registerId}/close`, 'PATCH', result.value); window.location.reload(); } catch (error) { flash('error', 'Unable to close register', error.message); }
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
        if (!/^\d+$/.test(input.value)) {
            message = 'Only whole numbers are allowed.';
        } else {
            const value = Number(input.value);
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
        discount,
        tax,
        cash,
        paymentMethod,
        reference,
        completeButton,
    ];
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

    grid.addEventListener('click', (event) => {
        const card = event.target.closest('[data-product]');
        const product = card ? parseProduct(card) : null;
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
    quickCustomerPhone?.addEventListener('input', () => {
        const valid = quickCustomerPhone.value === '' || /^\d{11}$/.test(quickCustomerPhone.value);
        quickCustomerPhone.setCustomValidity(valid ? '' : 'Enter a valid 11-digit phone number.');
    });
    paymentType.addEventListener('change', () => {
        paymentMethod.value = paymentType.value === 'Cash' ? 'Cash' : paymentType.value;
        updateTotals();
    });
    search.addEventListener('input', () => {
        keyboardProductSelection = false;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(searchProducts, 250);
    });
    search.addEventListener('keydown', (event) => {
        if (['ArrowRight', 'ArrowDown'].includes(event.key)) {
            event.preventDefault();
            keyboardProductSelection = true;
            setActiveProduct(activeProduct + 1);
            return;
        }
        if (['ArrowLeft', 'ArrowUp'].includes(event.key)) {
            event.preventDefault();
            keyboardProductSelection = true;
            setActiveProduct(activeProduct - 1);
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
    $('[data-pos-hold]')?.addEventListener('click', hold);
    $('[data-pos-open-register]')?.addEventListener('click', openRegister);
    $('[data-pos-close-register]')?.addEventListener('click', closeRegister);
    $('[data-pos-resume]').addEventListener('click', () => window.bootstrap?.Modal.getOrCreateInstance($('#posHeldModal')).show());
    $('[data-pos-held-list]').addEventListener('click', (event) => {
        const button = event.target.closest('[data-held-id]');
        if (button) resume(button.dataset.heldId);
    });
    [customer, quickCustomerName, quickCustomerPhone, quickCustomerCity, quickCustomerAddress, discount, tax, cash, paymentMethod, reference, completeButton]
        .filter(Boolean)
        .forEach((field) => field.addEventListener('keydown', (event) => {
            const fields = checkoutFields();
            if (focusPreviousIfEmpty(event, fields)) return;
            if (event.key !== 'Enter') return;

            event.preventDefault();
            if (field === completeButton) {
                complete();
                return;
            }
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

            const nextFields = checkoutFields();
            const currentIndex = nextFields.indexOf(field);
            focusElement(nextFields[currentIndex + 1], true);
        }));
    root.addEventListener('wheel', (event) => { if (event.target === cash) event.preventDefault(); }, { passive: false });

    setActiveProduct($$('[data-product]').length ? 0 : -1);
    syncCustomerMode(false);
    search.value = '';
    barcode.value = '';
    focusElement(search);
    renderCart();
})();
