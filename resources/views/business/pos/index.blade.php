@extends('layouts.dashboard')
@section('title', 'POS | TradeFlow')
@section('page-title', 'Point of Sale')
@section('page-subtitle', 'Counter sales, receipts, and register control')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

@if(!$register)
    <section class="tf-card p-4 mx-auto" style="max-width:680px"><div class="d-flex gap-3 align-items-center mb-3"><span class="tf-brand-mark bg-blue"><i class="bi bi-cash-register"></i></span><div><h2 class="h5 mb-1">Open POS Register</h2><p class="tf-muted mb-0">Record opening cash before processing counter sales.</p></div></div><form method="POST" action="{{ route('business.pos.register.open') }}" class="row g-3">@csrf<div class="col-md-6"><label class="form-label" for="opening_cash">Opening Cash</label><input id="opening_cash" name="opening_cash" type="number" min="0" step="0.01" value="0" class="form-control" required></div><div class="col-md-6"><label class="form-label" for="opening_note">Opening Note</label><input id="opening_note" name="opening_note" class="form-control"></div><div class="col-12">@companyCan('pos.open_register')<button class="btn btn-tf-primary"><i class="bi bi-unlock me-1"></i>Open Register</button>@endcompanyCan</div></form></section>
@else
    @php($categories = $products->pluck('category.name')->filter()->unique()->sort()->values())
    <div class="pos-screen">
    <section class="pos-workspace-header tf-card p-3 mb-3">
        <div class="pos-workspace-meta"><span class="tf-badge tf-badge-success"><i class="bi bi-circle-fill me-1"></i>Register Open</span><span class="small tf-muted">Opening cash <strong>Rs {{ number_format($register->opening_cash, 2) }}</strong></span><span class="small tf-muted">Current invoice <strong data-pos-invoice-number>New sale</strong></span></div>
        <div class="pos-workspace-actions"><button type="button" class="btn btn-outline-secondary" id="pos-hold-sale"><i class="bi bi-pause-circle me-1"></i>Hold Sale</button><a href="{{ route('business.pos.history') }}" class="btn btn-outline-primary"><i class="bi bi-clock-history me-1"></i>Sales History</a>@companyCan('pos.close_register')<button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#closeRegisterModal"><i class="bi bi-lock me-1"></i>Close Register</button>@endcompanyCan</div>
    </section>

    <form method="POST" action="{{ route('business.pos.sales.store') }}" class="pos-sale-form" data-pos-form data-pos-hold-key="tradeflow-pos-held-sale-{{ $register->business_id }}" data-custom-price="{{ app(\App\Services\CompanyPermissionService::class)->allowsUser(auth()->user(), 'pos.custom_price') ? '1' : '0' }}">@csrf
        <div class="pos-workspace">
            <section class="tf-card p-3 pos-products-panel">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h2 class="h5 mb-0">Products</h2><small class="tf-muted">Click a product or scan its code to add it to the cart.</small></div><span class="small tf-muted" id="pos-product-count">{{ $products->count() }} products</span></div>
                <div class="pos-workspace-search mb-3"><div class="input-group"><span class="input-group-text"><i class="bi bi-upc-scan"></i></span><input id="pos-barcode" class="form-control" placeholder="Scan barcode" autocomplete="off" autofocus></div><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input id="pos-search" class="form-control" placeholder="Search product name or barcode" autocomplete="off"></div></div>
                <div class="pos-category-chips mb-3" id="pos-category-chips"><button type="button" class="btn btn-sm btn-tf-primary active" data-pos-category-chip="">All</button>@foreach($categories as $category)<button type="button" class="btn btn-sm btn-outline-secondary" data-pos-category-chip="{{ strtolower($category) }}">{{ $category }}</button>@endforeach</div>
                <div class="pos-product-grid" id="pos-products">
                    @forelse($products as $product)
                        <div class="pos-product" data-search="{{ strtolower($product->name.' '.$product->barcode) }}" data-category="{{ strtolower($product->category?->name ?? '') }}">
                            <button type="button" class="pos-product-card {{ $product->stock_quantity < 1 ? 'is-out-of-stock' : '' }}" data-product-id="{{ $product->id }}" data-product-name="{{ $product->name }}" data-price="{{ $product->retail_price }}" data-stock="{{ $product->stock_quantity }}" data-unit="{{ $product->unit }}" data-product-barcode="{{ $product->barcode }}" @disabled($product->stock_quantity < 1)>
                                @if($product->image)<img src="{{ asset('storage/'.$product->image) }}" class="pos-product-image" alt="{{ $product->name }}">@else<div class="pos-product-image pos-product-image-placeholder"><i class="bi bi-box-seam"></i></div>@endif
                                <span class="pos-product-name">{{ $product->name }}</span><span class="pos-product-code">{{ $product->barcode ?: 'No barcode' }}</span><span class="pos-product-price">Rs {{ number_format($product->retail_price, 2) }}</span><span class="pos-product-stock {{ $product->stock_quantity > 0 ? 'text-success' : 'text-danger' }}">{{ $product->stock_quantity }} {{ $product->unit }} {{ $product->stock_quantity == 1 ? 'available' : 'available' }}</span>
                            </button>
                        </div>
                    @empty <div class="text-center tf-muted py-5">No active products are available for POS.</div>@endforelse
                </div>
            </section>

            <section class="tf-card p-3 pos-cart-panel">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-3"><div><h2 class="h5 mb-0">Current Cart</h2><small class="tf-muted">Add products and update quantities.</small></div><button type="button" class="btn btn-sm btn-outline-danger" id="pos-clear-cart"><i class="bi bi-trash me-1"></i>Clear Cart</button></div>
                <div class="pos-quick-entry border rounded p-2 mb-3"><div class="row g-2 align-items-end"><div class="col-md-6"><label class="form-label small">Add product</label><select id="pos-entry-product" class="form-select"><option value="">Search product</option>@foreach($products as $product)<option value="{{ $product->id }}" data-name="{{ $product->name }}" data-price="{{ $product->retail_price }}" data-stock="{{ $product->stock_quantity }}" data-unit="{{ $product->unit }}" data-barcode="{{ $product->barcode }}" @disabled($product->stock_quantity < 1)>{{ $product->name }}</option>@endforeach</select></div><div class="col-3"><label class="form-label small">Stock</label><input id="pos-entry-stock" class="form-control" readonly></div><div class="col-3"><label class="form-label small">Qty</label><input id="pos-entry-qty" type="number" min="1" step="1" value="1" class="form-control js-whole-number"></div><div class="col-8"><label class="form-label small">Unit Price</label><input id="pos-entry-price" class="form-control" readonly></div><div class="col-4 d-grid"><button type="button" id="pos-entry-add" class="btn btn-tf-primary" aria-label="Add item"><i class="bi bi-plus-lg me-1"></i>Add</button></div></div><div id="pos-entry-error" class="invalid-feedback d-block d-none mt-2"></div></div>
                <div id="pos-cart" class="pos-cart-table"><p class="tf-muted text-center py-4 mb-0">Add products to start a sale.</p></div>
            </section>

            <aside class="tf-card p-3 pos-checkout-panel">
                <div class="pos-checkout-body">
                <div class="mb-3"><label class="form-label" for="pos-customer">Customer</label><select name="customer_id" id="pos-customer" class="form-select"><option value="walk_in">Walk-in Customer</option><option value="new">Create New Customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->display_name }}{{ $customer->business_name ? ' — '.$customer->business_name : '' }}{{ $customer->phone ? ' - '.$customer->phone : '' }}</option>@endforeach</select></div>
                <div id="pos-new-customer" class="row g-2 mb-3 d-none"><div class="col-md-6"><input name="new_customer_name" class="form-control" placeholder="Customer name"></div><div class="col-md-6"><input name="new_customer_phone" class="form-control" placeholder="Phone"></div><div class="col-md-6"><input name="new_customer_city" class="form-control" placeholder="City"></div><div class="col-md-6"><input name="new_customer_address" class="form-control" placeholder="Address"></div></div>
                <div class="row g-2 border-top pt-3"><div class="col-6"><label class="form-label" for="pos-discount-value">Discount</label><div class="input-group"><select name="discount_type" id="pos-discount-type" class="form-select"><option value="percentage">%</option><option value="fixed">Rs</option></select><input name="discount_value" id="pos-discount-value" type="number" min="0" step="1" value="0" class="form-control js-whole-number"></div></div><div class="col-6"><label class="form-label" for="pos-tax-rate">Tax %</label><input name="tax_rate" id="pos-tax-rate" type="number" min="0" max="100" step="1" value="0" class="form-control js-whole-number"></div><div class="col-12"><label class="form-label" for="pos-payment-mode">Payment Type</label><select name="payment_mode" id="pos-payment-mode" class="form-select"><option value="cash">Cash Sale</option>@companyCan('pos.credit_sale')<option value="credit">Credit / Khata Sale</option>@endcompanyCan @companyCan('pos.split_payment')<option value="split">Split Payment</option>@endcompanyCan</select></div><div class="col-12" id="pos-payments"></div><div class="col-12 d-none" id="pos-add-payment-wrap">@companyCan('pos.split_payment')<button type="button" class="btn btn-sm btn-outline-primary" id="pos-add-payment"><i class="bi bi-plus-lg me-1"></i>Add Payment</button>@endcompanyCan</div></div>
                <div class="pos-totals mt-3"><div><span>Subtotal</span><strong id="pos-subtotal">Rs 0.00</strong></div><div><span>Discount</span><strong id="pos-discount">Rs 0.00</strong></div><div><span>Tax</span><strong id="pos-tax">Rs 0.00</strong></div><div><span>Paid Amount</span><strong id="pos-paid">Rs 0.00</strong></div><div><span>Due / Balance</span><strong id="pos-balance">Rs 0.00</strong></div><div><span>Change</span><strong id="pos-change">Rs 0.00</strong></div><div class="pos-grand-total"><span>Grand Total</span><strong id="pos-total">Rs 0.00</strong></div></div>
                </div>
                <div class="pos-checkout-footer">
                @companyCan('pos.create_sale')<button type="submit" id="pos-complete-sale" class="btn btn-tf-primary w-100 mt-3" disabled><i class="bi bi-check2-circle me-1"></i>Complete Sale</button>@endcompanyCan
                </div>
            </aside>
        </div>
    </form>
    </div>

    <div class="modal fade" id="closeRegisterModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('business.pos.register.close', $register) }}">@csrf @method('PATCH')<div class="modal-header"><h2 class="h5 modal-title">Close POS Register</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><label class="form-label" for="closing_cash">Closing Cash</label><input id="closing_cash" name="closing_cash" type="number" min="0" step="0.01" class="form-control" required><label class="form-label mt-3" for="closing_note">Closing Note</label><textarea id="closing_note" name="closing_note" class="form-control" rows="3"></textarea></div><div class="modal-footer"><button class="btn btn-tf-primary">Close Register</button></div></form></div></div></div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-pos-form]');
    if (!form || form.dataset.posInitialized === 'true') return;
    form.dataset.posInitialized = 'true';
    const products = document.getElementById('pos-products'), cartElement = document.getElementById('pos-cart'), paymentsElement = document.getElementById('pos-payments'), paymentMode = document.getElementById('pos-payment-mode'), completeButton = document.getElementById('pos-complete-sale'), holdButton = document.getElementById('pos-hold-sale'), holdKey = form.dataset.posHoldKey, customPrice = form.dataset.customPrice === '1';
    const barcode = document.getElementById('pos-barcode'), search = document.getElementById('pos-search'), entryProduct = document.getElementById('pos-entry-product'), entryStock = document.getElementById('pos-entry-stock'), entryQuantity = document.getElementById('pos-entry-qty'), entryPrice = document.getElementById('pos-entry-price'), entryError = document.getElementById('pos-entry-error');
    const cart = new Map(); let editingProductId = null, activeCategory = '';
    const money = value => 'Rs ' + Number(value || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const productFromButton = button => ({id:Number(button.dataset.productId), name:button.dataset.productName, price:Number(button.dataset.price), stock:Number(button.dataset.stock), unit:button.dataset.unit || 'Piece', barcode:button.dataset.productBarcode || '', quantity:1});
    const syncSelect = select => window.syncTradeFlowTomSelect?.(select);
    const focusBarcode = () => setTimeout(() => barcode?.focus(), 0);
    const selectedOption = () => entryProduct?.selectedOptions[0];
    const paymentRow = index => '<div class="row g-2 payment-row mt-1"><div class="col-5"><select name="payments[' + index + '][method]" class="form-select"><option>Cash</option><option>Bank Transfer</option><option>JazzCash Manual</option><option>Easypaisa Manual</option><option>Cheque</option></select></div><div class="col-4"><input name="payments[' + index + '][amount]" type="number" min="0" step="0.01" class="form-control pos-payment-amount" placeholder="Paid amount"></div><div class="col-3"><input name="payments[' + index + '][reference_number]" class="form-control" placeholder="Reference"></div></div>';
    function totals() { const subtotal = [...cart.values()].reduce((sum,item) => sum + item.price * item.quantity, 0); const discountValue = Math.max(0, Number(document.getElementById('pos-discount-value').value || 0)); const discount = document.getElementById('pos-discount-type').value === 'percentage' ? subtotal * Math.min(100,discountValue) / 100 : Math.min(subtotal,discountValue); const tax = (subtotal-discount) * Math.max(0,Number(document.getElementById('pos-tax-rate').value || 0)) / 100; return {subtotal,discount,tax,total:subtotal-discount+tax}; }
    function updateSummary(current) { let paid = paymentMode.value === 'credit' ? 0 : [...paymentsElement.querySelectorAll('.pos-payment-amount')].reduce((sum,input) => sum + Math.max(0,Number(input.value || 0)),0); if (paymentMode.value === 'cash') paid = current.total; document.getElementById('pos-subtotal').textContent=money(current.subtotal); document.getElementById('pos-discount').textContent=money(current.discount); document.getElementById('pos-tax').textContent=money(current.tax); document.getElementById('pos-total').textContent=money(current.total); document.getElementById('pos-paid').textContent=money(paid); document.getElementById('pos-balance').textContent=money(Math.max(0,current.total-paid)); document.getElementById('pos-change').textContent=money(Math.max(0,paid-current.total)); }
    function setEntryError(message='') { entryQuantity.setCustomValidity(message); entryQuantity.classList.toggle('is-invalid', Boolean(message)); entryError.textContent=message; entryError.classList.toggle('d-none', !message); }
    function entryAvailableStock() { const option=selectedOption(), existing=cart.get(Number(option?.value)); return Math.max(0, Number(option?.dataset.stock || 0) - (editingProductId === Number(option?.value) ? 0 : Number(existing?.quantity || 0))); }
    function validateEntry() { const option=selectedOption(), requested=Number(entryQuantity.value || 0), available=entryAvailableStock(); if (!option?.value) { setEntryError(''); return false; } entryQuantity.max=String(available); const message=!Number.isInteger(requested) || requested < 1 ? 'Quantity must be at least 1.' : (requested > available ? 'Insufficient stock. Only '+Number(option.dataset.stock || 0)+' units are available.' : ''); setEntryError(message); return !message; }
    function syncEntry() { const option = selectedOption(), addButton=document.getElementById('pos-entry-add'); if (!option?.value) { entryStock.value=''; entryPrice.value=''; entryQuantity.removeAttribute('max'); addButton.disabled=false; setEntryError(''); return; } entryStock.value=`${option.dataset.stock} ${option.dataset.unit || ''}`; entryPrice.value=option.dataset.price || 0; const valid=validateEntry(); addButton.disabled=!valid || entryAvailableStock() < 1; }
    function resetEntry() { editingProductId=null; entryProduct.value=''; entryQuantity.value=1; entryQuantity.removeAttribute('max'); entryPrice.value=''; entryStock.value=''; document.getElementById('pos-entry-add').disabled=false; setEntryError(''); syncSelect(entryProduct); focusBarcode(); }
    function render() { const current=totals(); cartElement.innerHTML=cart.size ? '<div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>#</th><th>Product</th><th>Quantity</th><th>Unit Price</th><th>Discount</th><th>Line Total</th><th></th></tr></thead><tbody>'+[...cart.values()].map((item,index)=>'<tr><td>'+(index+1)+'</td><td><strong>'+item.name+'</strong><small class="d-block tf-muted">'+(item.barcode || '')+'</small><input type="hidden" name="items['+index+'][product_id]" value="'+item.id+'"></td><td><div class="input-group input-group-sm pos-quantity-control"><button type="button" class="btn btn-outline-secondary" data-cart-decrease="'+item.id+'" aria-label="Decrease quantity">−</button><input type="number" min="1" max="'+item.stock+'" step="1" inputmode="numeric" class="form-control text-center js-whole-number" value="'+item.quantity+'" data-cart-quantity="'+item.id+'"><button type="button" class="btn btn-outline-secondary" data-cart-increase="'+item.id+'" aria-label="Increase quantity">+</button></div><input type="hidden" name="items['+index+'][quantity]" value="'+item.quantity+'"></td><td>'+money(item.price)+'<input type="hidden" name="items['+index+'][price]" value="'+item.price+'"></td><td>Rs 0.00</td><td><strong>'+money(item.price*item.quantity)+'</strong></td><td><button type="button" class="btn btn-sm btn-outline-danger" data-cart-remove="'+item.id+'" aria-label="Remove '+item.name+'"><i class="bi bi-trash"></i></button></td></tr>').join('')+'</tbody></table></div>' : '<p class="tf-muted text-center py-4 mb-0">Add products to start a sale.</p>'; if (!paymentsElement.children.length) paymentsElement.innerHTML=paymentRow(0); const credit=paymentMode.value==='credit'; paymentsElement.querySelectorAll('input,select').forEach(input=>input.disabled=credit); document.getElementById('pos-add-payment-wrap').classList.toggle('d-none',paymentMode.value!=='split'); if(paymentMode.value==='cash'){const cash=paymentsElement.querySelector('.pos-payment-amount');if(cash)cash.value=current.total.toFixed(2);} updateSummary(current); if(completeButton)completeButton.disabled=cart.size===0; }
    function addProduct(product) { if(!product.id || product.stock<1)return; const existing=cart.get(product.id), requested=Number(product.quantity || 1), combined=(existing?.quantity || 0) + requested; if(!Number.isInteger(requested) || requested < 1){setEntryError('Quantity must be at least 1.');return;} if(combined > product.stock){setEntryError('Insufficient stock. Only '+product.stock+' units are available.');return;} if(existing){ existing.quantity=combined; existing.price=customPrice?Number(product.price || existing.price):existing.price; }else cart.set(product.id,product); render(); }
    function filterProducts() { const term=search.value.trim().toLowerCase(); let visible=0; products.querySelectorAll('.pos-product').forEach(card=>{const show=card.dataset.search.includes(term)&&(!activeCategory||card.dataset.category===activeCategory);card.classList.toggle('d-none',!show);if(show)visible++;});document.getElementById('pos-product-count').textContent=visible+' product'+(visible===1?'':'s'); }
    function findExact(code){const query=code.trim().toLowerCase();return [...products.querySelectorAll('[data-product-id]')].find(item=>(item.dataset.productBarcode || '').toLowerCase()===query||(item.dataset.productName || '').toLowerCase()===query);}
    function holdState(){return {cart:[...cart.values()],customer:document.getElementById('pos-customer').value,discountType:document.getElementById('pos-discount-type').value,discountValue:document.getElementById('pos-discount-value').value,taxRate:document.getElementById('pos-tax-rate').value,paymentMode:paymentMode.value,payments:[...paymentsElement.querySelectorAll('.payment-row')].map(row=>({method:row.querySelector('select').value,amount:row.querySelector('.pos-payment-amount').value,reference:row.querySelector('[name$="[reference_number]"]').value})),newCustomer:['new_customer_name','new_customer_phone','new_customer_city','new_customer_address'].reduce((values,name)=>({...values,[name]:form.querySelector(`[name="${name}"]`)?.value || ''}),{})};}
    function refreshHoldButton(){const held=sessionStorage.getItem(holdKey);holdButton.innerHTML=held?'<i class="bi bi-play-circle me-1"></i>Resume Held Sale':'<i class="bi bi-pause-circle me-1"></i>Hold Sale';}
    function restoreHeldSale(){try{const state=JSON.parse(sessionStorage.getItem(holdKey)||'null');if(!state)return;cart.clear();(state.cart||[]).forEach(item=>cart.set(Number(item.id),item));['pos-discount-type','pos-discount-value','pos-tax-rate'].forEach((id,index)=>document.getElementById(id).value=[state.discountType,state.discountValue,state.taxRate][index] ?? document.getElementById(id).value);paymentMode.value=state.paymentMode || 'cash';document.getElementById('pos-customer').value=state.customer || 'walk_in';syncSelect(document.getElementById('pos-customer'));syncSelect(paymentMode);Object.entries(state.newCustomer || {}).forEach(([name,value])=>{const input=form.querySelector(`[name="${name}"]`);if(input)input.value=value;});document.getElementById('pos-new-customer').classList.toggle('d-none',(state.customer||'walk_in')!=='new');paymentsElement.innerHTML=(state.payments||[]).map((payment,index)=>{const wrapper=document.createElement('div');wrapper.innerHTML=paymentRow(index);const row=wrapper.firstElementChild;row.querySelector('select').value=payment.method;row.querySelector('.pos-payment-amount').value=payment.amount;row.querySelector('[name$="[reference_number]"]').value=payment.reference;return row.outerHTML;}).join('')||paymentRow(0);sessionStorage.removeItem(holdKey);render();refreshHoldButton();focusBarcode();}catch(_){sessionStorage.removeItem(holdKey);refreshHoldButton();}}
    products.addEventListener('click',event=>{const button=event.target.closest('[data-product-id]');if(button&&!button.disabled&&products.contains(button))addProduct(productFromButton(button));});
    document.querySelectorAll('[data-pos-category-chip]').forEach(chip=>chip.addEventListener('click',()=>{activeCategory=chip.dataset.posCategoryChip;document.querySelectorAll('[data-pos-category-chip]').forEach(item=>{item.classList.toggle('btn-tf-primary',item===chip);item.classList.toggle('btn-outline-secondary',item!==chip);item.classList.toggle('active',item===chip);});filterProducts();}));
    entryProduct.addEventListener('change',syncEntry);entryQuantity.addEventListener('input',syncEntry);document.getElementById('pos-entry-add').addEventListener('click',()=>{const option=selectedOption(),stock=Number(option?.dataset.stock||0),quantity=Number(entryQuantity.value||0);if(!option?.value){setEntryError('Select a product.');return;}if(!validateEntry())return;if(editingProductId&&editingProductId!==Number(option.value))cart.delete(editingProductId);addProduct({id:Number(option.value),name:option.dataset.name,price:Number(option.dataset.price),stock,unit:option.dataset.unit||'Piece',barcode:option.dataset.barcode||'',quantity});resetEntry();});
    barcode.addEventListener('keydown',event=>{if(event.key!=='Enter')return;event.preventDefault();const button=findExact(barcode.value);if(button){addProduct(productFromButton(button));barcode.value='';}focusBarcode();});search.addEventListener('input',filterProducts);search.addEventListener('keydown',event=>{if(event.key!=='Enter')return;const button=findExact(search.value);if(!button)return;event.preventDefault();addProduct(productFromButton(button));search.value='';filterProducts();focusBarcode();});
    const setCartQuantityError = (input, message='') => { input?.setCustomValidity(message); input?.classList.toggle('is-invalid', Boolean(message)); if(message) setEntryError(message); };
    const syncCartControls = () => cartElement.querySelectorAll('[data-cart-increase]').forEach(button => { const item=cart.get(Number(button.dataset.cartIncrease)); button.disabled=Boolean(item && item.quantity >= item.stock); });
    cartElement.addEventListener('click',event=>{const increase=event.target.closest('[data-cart-increase]'),item=cart.get(Number(increase?.dataset.cartIncrease));if(increase && item && item.quantity >= item.stock){event.preventDefault();event.stopImmediatePropagation();setEntryError('Insufficient stock. Only '+item.stock+' units are available.');}});
    cartElement.addEventListener('input',event=>{const input=event.target,id=Number(input.dataset.cartQuantity),item=cart.get(id);if(!item)return;const requested=Number(input.value || 0),message=!Number.isInteger(requested)||requested<1?'Quantity must be at least 1.':(requested>item.stock?'Insufficient stock. Only '+item.stock+' units are available.':'');if(message){event.stopImmediatePropagation();setCartQuantityError(input,message);}else setCartQuantityError(input);});
    cartElement.addEventListener('click',event=>{const remove=event.target.closest('[data-cart-remove]'),increase=event.target.closest('[data-cart-increase]'),decrease=event.target.closest('[data-cart-decrease]'),id=Number(remove?.dataset.cartRemove||increase?.dataset.cartIncrease||decrease?.dataset.cartDecrease),item=cart.get(id);if(!item)return;if(increase){item.quantity=Math.min(item.stock,item.quantity+1);render();return;}if(decrease){item.quantity=Math.max(1,item.quantity-1);render();return;}if(remove){cart.delete(id);render();}});cartElement.addEventListener('input',event=>{const id=Number(event.target.dataset.cartQuantity),item=cart.get(id);if(!item)return;item.quantity=Math.max(1,Math.min(item.stock,parseInt(event.target.value||'1',10)||1));render();});
    new MutationObserver(syncCartControls).observe(cartElement,{childList:true,subtree:true});
    paymentsElement.addEventListener('input',()=>updateSummary(totals()));document.getElementById('pos-add-payment')?.addEventListener('click',()=>{paymentsElement.insertAdjacentHTML('beforeend',paymentRow(paymentsElement.querySelectorAll('.payment-row').length));window.initTradeFlowTomSelect?.(paymentsElement);});document.getElementById('pos-customer').addEventListener('change',event=>document.getElementById('pos-new-customer').classList.toggle('d-none',event.target.value!=='new'));document.getElementById('pos-clear-cart').addEventListener('click',()=>{cart.clear();render();focusBarcode();});['pos-discount-value','pos-discount-type','pos-tax-rate','pos-payment-mode'].forEach(id=>{const input=document.getElementById(id);input.addEventListener('input',render);input.addEventListener('change',render);});
    holdButton.addEventListener('click',()=>{if(sessionStorage.getItem(holdKey)){restoreHeldSale();return;}if(!cart.size){window.Swal?.fire({icon:'info',title:'Cart is empty',text:'Add at least one product before holding this sale.'});return;}sessionStorage.setItem(holdKey,JSON.stringify(holdState()));cart.clear();form.reset();paymentsElement.innerHTML='';document.getElementById('pos-new-customer').classList.add('d-none');syncSelect(entryProduct);syncSelect(document.getElementById('pos-customer'));syncSelect(document.getElementById('pos-discount-type'));syncSelect(paymentMode);render();refreshHoldButton();focusBarcode();window.Swal?.fire({icon:'success',title:'Sale held',text:'The cart is saved for this browser session and can be resumed from POS.'});});
    form.addEventListener('submit',event=>{if(!cart.size){event.preventDefault();window.Swal?.fire({icon:'info',title:'Cart is empty',text:'Add at least one product before completing this sale.'});return;}if(form.dataset.submitting==='1'){event.preventDefault();return;}form.dataset.submitting='1';sessionStorage.removeItem(holdKey);if(completeButton){completeButton.disabled=true;completeButton.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Completing Sale';}});
    syncEntry();render();syncCartControls();refreshHoldButton();focusBarcode();
});
</script>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-pos-form]');
    const cart = document.getElementById('pos-cart');
    if (!form || !cart || form.dataset.posItemRatesReady === '1') return;
    form.dataset.posItemRatesReady = '1';

    const itemRates = new Map();
    const money = value => 'Rs ' + Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const clampRate = value => Math.max(0, Math.min(100, Number(value || 0)));
    const paymentMode = document.getElementById('pos-payment-mode');
    const discountValue = document.getElementById('pos-discount-value');
    const discountType = document.getElementById('pos-discount-type');
    const taxRate = document.getElementById('pos-tax-rate');

    const updateTotals = () => {
        let subtotal = 0;
        cart.querySelectorAll('tbody tr').forEach(row => {
            const productId = row.querySelector('[name$="[product_id]"]')?.value;
            const quantity = Number(row.querySelector('[data-cart-quantity]')?.value || 0);
            const unitPrice = Number(row.querySelector('[name$="[price]"]')?.value || 0);
            const rates = itemRates.get(productId) || { discount: 0, tax: 0 };
            const base = quantity * unitPrice;
            const itemDiscount = base * clampRate(rates.discount) / 100;
            const itemTax = (base - itemDiscount) * clampRate(rates.tax) / 100;
            const total = base - itemDiscount + itemTax;
            subtotal += total;
            row.querySelector('[data-pos-item-total]').textContent = money(total);
        });
        const saleDiscountValue = Math.max(0, Number(discountValue.value || 0));
        const saleDiscount = discountType.value === 'percentage'
            ? subtotal * Math.min(100, saleDiscountValue) / 100
            : Math.min(subtotal, saleDiscountValue);
        const saleTax = (subtotal - saleDiscount) * clampRate(taxRate.value) / 100;
        const total = subtotal - saleDiscount + saleTax;
        let paid = 0;
        if (paymentMode.value === 'cash') paid = total;
        if (paymentMode.value === 'split') {
            paid = [...form.querySelectorAll('.pos-payment-amount')].reduce((sum, input) => sum + Math.max(0, Number(input.value || 0)), 0);
        }
        document.getElementById('pos-subtotal').textContent = money(subtotal);
        document.getElementById('pos-discount').textContent = money(saleDiscount);
        document.getElementById('pos-tax').textContent = money(saleTax);
        document.getElementById('pos-total').textContent = money(total);
        document.getElementById('pos-paid').textContent = money(paid);
        document.getElementById('pos-balance').textContent = money(Math.max(0, total - paid));
        document.getElementById('pos-change').textContent = money(Math.max(0, paid - total));
    };

    const decorateCart = () => {
        let changed = false;
        const header = cart.querySelector('thead tr');
        if (header && !header.querySelector('[data-pos-tax-header]')) {
            const taxHeader = document.createElement('th');
            taxHeader.dataset.posTaxHeader = '';
            taxHeader.textContent = 'Tax';
            header.querySelector('th:nth-last-child(2)')?.before(taxHeader);
            changed = true;
        }
        cart.querySelectorAll('tbody tr').forEach((row, index) => {
            const productId = row.querySelector('[name$="[product_id]"]')?.value;
            if (!productId) return;
            if (row.dataset.posItemRatesDecorated === '1') return;
            const discountCell = row.children[4];
            const totalCell = row.children[5];
            if (!discountCell || !totalCell) return;
            const rates = itemRates.get(productId) || { discount: 0, tax: 0 };
            itemRates.set(productId, rates);
            row.dataset.posItemRatesDecorated = '1';
            changed = true;
            discountCell.innerHTML = '<input type="number" min="0" max="100" step="1" value="' + rates.discount + '" class="form-control form-control-sm js-whole-number" data-pos-item-discount="' + productId + '"><input type="hidden" name="items[' + index + '][discount_rate]" value="' + rates.discount + '">';
            const taxCell = document.createElement('td');
            taxCell.innerHTML = '<input type="number" min="0" max="100" step="1" value="' + rates.tax + '" class="form-control form-control-sm js-whole-number" data-pos-item-tax="' + productId + '"><input type="hidden" name="items[' + index + '][tax_rate]" value="' + rates.tax + '">';
            totalCell.before(taxCell);
            totalCell.innerHTML = '<strong data-pos-item-total></strong>';
            row.querySelector('[data-pos-item-discount]')?.addEventListener('input', event => {
                const value = Number(event.target.value || 0);
                event.target.setCustomValidity(Number.isInteger(value) && value >= 0 && value <= 100 ? '' : 'Only whole numbers from 0 to 100 are allowed.');
                event.target.classList.toggle('is-invalid', !event.target.validity.valid);
                if (event.target.validity.valid) {
                    rates.discount = value;
                    row.querySelector('[name$="[discount_rate]"]').value = value;
                    updateTotals();
                }
            });
            row.querySelector('[data-pos-item-tax]')?.addEventListener('input', event => {
                const value = Number(event.target.value || 0);
                event.target.setCustomValidity(Number.isInteger(value) && value >= 0 && value <= 100 ? '' : 'Only whole numbers from 0 to 100 are allowed.');
                event.target.classList.toggle('is-invalid', !event.target.validity.valid);
                if (event.target.validity.valid) {
                    rates.tax = value;
                    row.querySelector('[name$="[tax_rate]"]').value = value;
                    updateTotals();
                }
            });
        });
        // Updating the summary changes text nodes inside the observed cart. Only do
        // it after a newly rendered cart row/header was actually decorated.
        if (changed) updateTotals();
    };

    new MutationObserver(() => decorateCart()).observe(cart, { childList: true, subtree: true });
    [discountValue, discountType, taxRate, paymentMode].forEach(input => input?.addEventListener('input', updateTotals));
    form.addEventListener('input', event => {
        if (event.target.matches('.pos-payment-amount, [data-cart-quantity]')) updateTotals();
    });
    decorateCart();
    updateTotals();
});
</script>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-pos-form]');
    if (!form || form.dataset.posKeyboardInitialized === 'true') return;

    form.dataset.posKeyboardInitialized = 'true';
    const completeButton = document.getElementById('pos-complete-sale');
    const customer = document.getElementById('pos-customer');
    const paymentMode = document.getElementById('pos-payment-mode');
    const checkoutPanel = form.querySelector('.pos-checkout-panel');

    const cartIsValid = () => {
        const quantities = [...form.querySelectorAll('[data-cart-quantity]')];
        return quantities.length > 0 && quantities.every((input) => {
            const quantity = Number(input.value || 0);
            const maximum = Number(input.max || 0);
            return Number.isInteger(quantity) && quantity > 0 && (!maximum || quantity <= maximum) && !input.classList.contains('is-invalid');
        });
    };

    const customerIsValid = () => {
        if (customer?.value !== 'new') return paymentMode?.value === 'cash' || Boolean(customer?.value && customer.value !== 'walk_in');

        return Boolean(
            form.querySelector('[name="new_customer_name"]')?.value.trim()
            || form.querySelector('[name="new_customer_phone"]')?.value.trim()
        );
    };

    const checkoutIsValid = () => {
        const discount = Number(form.querySelector('#pos-discount-value')?.value || 0);
        const tax = Number(form.querySelector('#pos-tax-rate')?.value || 0);
        const ratesAreValid = Number.isInteger(discount) && discount >= 0
            && Number.isInteger(tax) && tax >= 0 && tax <= 100;

        if (!ratesAreValid) return false;

        if (customer?.value !== 'new') return true;

        return Boolean(
            form.querySelector('[name="new_customer_name"]')?.value.trim()
            || form.querySelector('[name="new_customer_phone"]')?.value.trim()
        );
    };
    const saleIsReady = () => cartIsValid() && customerIsValid() && checkoutIsValid() && form.dataset.submitting !== '1';
    const syncCompleteButton = () => {
        if (completeButton) completeButton.disabled = !saleIsReady();
    };

    const isVisible = (element) => Boolean(
        element
        && !element.disabled
        && (element.tomselect?.wrapper?.offsetParent !== null || element.offsetParent !== null)
    );
    const checkoutControls = () => {
        const controls = [
            customer,
            form.querySelector('[name="new_customer_name"]'),
            form.querySelector('[name="new_customer_phone"]'),
            form.querySelector('[name="new_customer_city"]'),
            form.querySelector('[name="new_customer_address"]'),
            form.querySelector('#pos-discount-type'),
            form.querySelector('#pos-discount-value'),
            form.querySelector('#pos-tax-rate'),
            paymentMode,
            ...form.querySelectorAll('#pos-payments select, #pos-payments input'),
            completeButton,
        ];

        return controls.filter(isVisible);
    };

    const controlForTarget = (target) => {
        const wrapper = target.closest?.('.ts-wrapper');
        return wrapper?.querySelector('select') || target;
    };

    const hasOpenDropdown = () => [...checkoutPanel.querySelectorAll('select')]
        .some((select) => select.tomselect?.isOpen);

    const focusControl = (control) => {
        if (!control) return;

        const tomSelect = control.tomselect;
        if (tomSelect) tomSelect.focus();
        else control.focus({ preventScroll: true });

        const scrollTarget = tomSelect?.wrapper || control;
        scrollTarget.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
    };

    const submitSale = () => {
        if (!saleIsReady()) {
            if (!cartIsValid()) {
                window.Swal?.fire({ icon: 'info', title: 'Cart is empty or invalid', text: 'Add at least one valid product before completing this sale.' });
            } else if (!customerIsValid()) {
                window.Swal?.fire({ icon: 'info', title: 'Customer required', text: 'Select or create a customer for credit or split payments.' });
            } else {
                form.reportValidity();
            }
            return;
        }

        form.requestSubmit();
    };

    form.addEventListener('input', syncCompleteButton);
    form.addEventListener('change', syncCompleteButton);
    new MutationObserver(syncCompleteButton).observe(document.getElementById('pos-cart'), { childList: true, subtree: true });

    form.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || event.isComposing || event.defaultPrevented) return;

        const target = event.target;
        if (!(target instanceof Element)
            || target.matches('textarea, #pos-barcode, #pos-search')
            || target.closest('.ts-dropdown, .dropdown-menu, .modal')
            || !checkoutPanel.contains(target)) {
            return;
        }

        if (hasOpenDropdown()) return;

        event.preventDefault();
        const controls = checkoutControls();
        const current = controlForTarget(target);
        const currentIndex = controls.indexOf(current);
        const nextIndex = event.shiftKey ? currentIndex - 1 : currentIndex + 1;

        if (current === completeButton || nextIndex >= controls.length) {
            submitSale();
            return;
        }

        focusControl(controls[Math.max(0, nextIndex)]);
    });

    syncCompleteButton();
});
</script>
@endpush
