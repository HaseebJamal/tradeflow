@extends('layouts.dashboard')
@section('page-title', 'Receive Goods')
@section('page-subtitle', $purchase->purchase_number.' · '.$purchase->supplier?->supplier_name)
@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('business.purchases.receipts.store', $purchase) }}" class="tf-card p-4" data-grn-form data-tf-no-saving-state enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="submission_token" value="{{ $submissionToken }}">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div><h2 class="h5 mb-1">Goods Receipt</h2><p class="tf-muted mb-0">Accepted paid and bonus quantities increase inventory. Bonus quantities never increase supplier payable.</p></div>
        <a href="{{ route('business.purchases.show', $purchase) }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-md-4"><label class="form-label">Received at</label><input class="form-control" name="received_at" type="datetime-local" value="{{ old('received_at', now()->format('Y-m-d\TH:i')) }}"></div>
        <div class="col-md-8"><label class="form-label">Attachment</label><input class="form-control" name="attachment" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf"><div class="form-text">Optional JPG, PNG, WebP, or PDF up to 5 MB.</div></div>
    </div>
    <div class="table-responsive border rounded"><table class="table align-middle mb-0"><thead><tr><th>Product</th><th>Purchased</th><th>Free</th><th>Total expected</th><th>Previously processed</th><th>Remaining</th><th>Accepted</th><th>Damaged</th><th>Rejected</th></tr></thead><tbody>
        @foreach($purchase->items as $index => $item)
            @php($processed = (float) $item->received_quantity + (float) $item->damaged_quantity + (float) $item->rejected_quantity)
            @php($totalExpected = (float) $item->quantity + (float) ($item->free_quantity ?? 0))
            @php($remaining = max(0, $totalExpected - $processed))
            <tr data-grn-row data-remaining-quantity="{{ $remaining }}" data-batch-tracked="{{ $item->product?->has_batch_tracking ? '1' : '0' }}">
                <td><strong>{{ $item->product_name_snapshot }}</strong><small class="d-block tf-muted">{{ $item->unit_snapshot ?: ($item->product?->unit ?: 'Unit') }}</small><input type="hidden" name="items[{{ $index }}][purchase_item_id]" value="{{ $item->id }}"></td>
                <td><x-quantity :value="$item->quantity" /></td><td><x-quantity :value="$item->free_quantity ?? 0" /></td><td><x-quantity :value="$totalExpected" /></td><td><x-quantity :value="$processed" /></td><td><x-quantity :value="$remaining" /></td>
                @if($remaining <= 0)
                    <td colspan="3"><span class="tf-badge tf-badge-success"><i class="bi bi-check2-circle me-1"></i>Fully Processed</span></td>
                @else
                    @foreach(['accepted_quantity' => 'Accepted', 'damaged_quantity' => 'Damaged', 'rejected_quantity' => 'Rejected'] as $field => $label)
                        <td><input class="form-control js-whole-number js-no-number-spinner js-no-wheel-change" name="items[{{ $index }}][{{ $field }}]" type="number" min="0" max="{{ $remaining }}" step="1" value="{{ old("items.$index.$field", 0) }}" data-grn-quantity aria-label="{{ $label }} {{ $item->product_name_snapshot }}">@if($field === 'accepted_quantity')<div class="invalid-feedback d-none" data-grn-row-error></div>@endif</td>
                    @endforeach
                @endif
            </tr>
            @if($remaining > 0 && $item->product?->has_batch_tracking)
            <tr class="d-none" data-grn-batch-row data-product-name="{{ $item->product_name_snapshot }}" data-item-index="{{ $index }}"><td colspan="9" class="bg-light-subtle">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2"><div><strong class="small">Batch details</strong><span class="tf-muted small ms-1">Required for accepted stock. Batch quantities must total accepted quantity.</span></div><button type="button" class="btn btn-sm btn-outline-primary" data-add-grn-batch><i class="bi bi-plus-lg me-1"></i>Add batch</button></div>
                <div data-grn-batch-list></div><div class="small text-danger d-none" data-grn-batch-error></div>
                <template data-grn-batch-template><div class="row g-2 align-items-end border-top pt-2 mb-2" data-grn-batch-line><div class="col-md-3"><label class="form-label small mb-1">Batch number</label><input required class="form-control form-control-sm" name="items[{{ $index }}][batches][__INDEX__][batch_number]"></div><div class="col-md-2"><label class="form-label small mb-1">Qty</label><input required min="0" step="0.001" class="form-control form-control-sm" type="number" name="items[{{ $index }}][batches][__INDEX__][quantity]" data-grn-batch-quantity></div><div class="col-md-3"><label class="form-label small mb-1">Manufactured</label><input class="form-control form-control-sm" type="date" name="items[{{ $index }}][batches][__INDEX__][manufacturing_date]"></div><div class="col-md-3"><label class="form-label small mb-1">Expiry</label><input required min="{{ now()->toDateString() }}" class="form-control form-control-sm" type="date" name="items[{{ $index }}][batches][__INDEX__][expiry_date]"></div><div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-grn-batch aria-label="Remove batch"><i class="bi bi-x-lg"></i></button></div></div></template>
            </td></tr>
            @endif
        @endforeach
    </tbody></table></div>
    <div class="d-flex justify-content-end mt-3"><button class="btn btn-tf-primary" data-grn-submit>Record Goods Receipt</button></div>
</form>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('[data-grn-form]');
  if (!form || form.dataset.ready) return;
  form.dataset.ready = '1';

  const rows = [...form.querySelectorAll('[data-grn-row]')];
  const messageFor = remaining => `Total processed quantity cannot exceed remaining quantity (${remaining}).`;
  // Blank optional quantity fields are submitted as zero. Only a non-blank
  // value must be a whole number.
  const wholeNumber = value => {
    const normalized = String(value ?? '').trim();
    return normalized === '' || /^\d+$/.test(normalized);
  };

  const validateRow = row => {
    const remaining = Number(row.dataset.remainingQuantity || 0);
    const fields = [...row.querySelectorAll('[data-grn-quantity]')];
    const total = fields.reduce((sum, field) => sum + (Number(field.value || 0) || 0), 0);
    const invalidNumber = fields.some(field => !wholeNumber(field.value) || Number(field.value || 0) < 0);
    const invalid = invalidNumber || total > remaining;
    const message = invalidNumber ? 'Enter whole numbers of zero or more.' : messageFor(remaining);

    fields.forEach(field => {
      field.classList.toggle('is-invalid', invalid);
      field.setCustomValidity(invalid ? message : '');
    });
    const error = row.querySelector('[data-grn-row-error]');
    if (error) {
      error.textContent = invalid ? message : '';
      error.classList.toggle('d-none', !invalid);
    }
    return !invalid;
  };

  const batchRowFor = row => row.nextElementSibling?.matches('[data-grn-batch-row]') ? row.nextElementSibling : null;
  const addBatch = batchRow => {
    const list = batchRow.querySelector('[data-grn-batch-list]');
    const index = list.querySelectorAll('[data-grn-batch-line]').length;
    list.insertAdjacentHTML('beforeend', batchRow.querySelector('[data-grn-batch-template]').innerHTML.replaceAll('__INDEX__', index));
  };
  const validateBatches = row => {
    if (row.dataset.batchTracked !== '1') return true;
    const batchRow = batchRowFor(row);
    const accepted = Number(row.querySelector('[name*="[accepted_quantity]"]')?.value || 0);
    const lines = [...(batchRow?.querySelectorAll('[data-grn-batch-line]') || [])];
    const total = lines.reduce((sum, line) => sum + (Number(line.querySelector('[data-grn-batch-quantity]')?.value || 0) || 0), 0);
    const invalid = accepted > 0 && (lines.length === 0 || Math.abs(total - accepted) > 0.0001 || lines.some(line => !line.querySelector('input[name$="[batch_number]"]')?.value || !line.querySelector('input[name$="[expiry_date]"]')?.value));
    if (batchRow) {
      batchRow.querySelectorAll('input').forEach(input => { input.disabled = accepted <= 0; });
      batchRow.classList.toggle('d-none', accepted <= 0);
      const error = batchRow.querySelector('[data-grn-batch-error]');
      error.textContent = invalid ? 'Add valid batch details whose quantities total the accepted quantity.' : '';
      error.classList.toggle('d-none', !invalid);
    }
    return !invalid;
  };

  rows.forEach(row => {
    row.querySelectorAll('[data-grn-quantity]').forEach(field => {
      field.addEventListener('input', () => validateRow(row));
      field.addEventListener('input', () => validateBatches(row));
      field.addEventListener('change', () => { validateRow(row); validateBatches(row); });
    });
    const batchRow = batchRowFor(row);
    if (batchRow) {
      addBatch(batchRow);
      batchRow.querySelector('[data-add-grn-batch]').addEventListener('click', () => addBatch(batchRow));
      batchRow.addEventListener('click', event => { if (event.target.closest('[data-remove-grn-batch]')) event.target.closest('[data-grn-batch-line]')?.remove(); });
      batchRow.addEventListener('input', () => validateBatches(row));
    }
    validateRow(row);
  });

  form.addEventListener('submit', event => {
    const invalidRow = rows.find(row => !validateRow(row) || !validateBatches(row));
    if (invalidRow) {
      event.preventDefault();
      const remaining = invalidRow.dataset.remainingQuantity || '0';
      const message = messageFor(remaining);
      const firstField = invalidRow.querySelector('[data-grn-quantity]');
      firstField?.focus();
      if (window.Swal) window.Swal.fire({ icon: 'warning', title: 'Please review', text: message });
      return;
    }

    const button = form.querySelector('[data-grn-submit]');
    if (button.disabled) { event.preventDefault(); return; }
    button.disabled = true;
    button.textContent = 'Recording...';
  });
});
</script>
@endpush
@endsection
