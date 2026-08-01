@extends('layouts.dashboard')
@section('page-title', 'Receive Goods')
@section('page-subtitle', $purchase->purchase_number.' · '.$purchase->supplier?->supplier_name)
@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('business.purchases.receipts.store', $purchase) }}" class="tf-card p-4" data-grn-form enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="submission_token" value="{{ $submissionToken }}">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div><h2 class="h5 mb-1">Goods Receipt</h2><p class="tf-muted mb-0">Only accepted quantities increase inventory. Damaged and rejected quantities remain off-stock.</p></div>
        <a href="{{ route('business.purchases.show', $purchase) }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-md-4"><label class="form-label">Received at</label><input class="form-control" name="received_at" type="datetime-local" value="{{ old('received_at', now()->format('Y-m-d\TH:i')) }}"></div>
        <div class="col-md-8"><label class="form-label">Attachment</label><input class="form-control" name="attachment" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf"><div class="form-text">Optional JPG, PNG, WebP, or PDF up to 5 MB.</div></div>
    </div>
    <div class="table-responsive border rounded"><table class="table align-middle mb-0"><thead><tr><th>Product</th><th>Ordered</th><th>Previously processed</th><th>Remaining</th><th>Accepted</th><th>Damaged</th><th>Rejected</th></tr></thead><tbody>
        @foreach($purchase->items as $index => $item)
            @php($processed = (float) $item->received_quantity + (float) $item->damaged_quantity + (float) $item->rejected_quantity)
            @php($remaining = max(0, (float) $item->quantity - $processed))
            <tr data-grn-row data-remaining-quantity="{{ $remaining }}">
                <td><strong>{{ $item->product_name_snapshot }}</strong><small class="d-block tf-muted">{{ $item->unit_snapshot ?: ($item->product?->unit ?: 'Unit') }}</small><input type="hidden" name="items[{{ $index }}][purchase_item_id]" value="{{ $item->id }}"></td>
                <td><x-quantity :value="$item->quantity" /></td><td><x-quantity :value="$processed" /></td><td><x-quantity :value="$remaining" /></td>
                @if($remaining <= 0)
                    <td colspan="3"><span class="tf-badge tf-badge-success"><i class="bi bi-check2-circle me-1"></i>Fully Processed</span></td>
                @else
                    @foreach(['accepted_quantity' => 'Accepted', 'damaged_quantity' => 'Damaged', 'rejected_quantity' => 'Rejected'] as $field => $label)
                        <td><input class="form-control js-whole-number js-no-number-spinner js-no-wheel-change" name="items[{{ $index }}][{{ $field }}]" type="number" min="0" max="{{ $remaining }}" step="1" value="{{ old("items.$index.$field", 0) }}" data-grn-quantity aria-label="{{ $label }} {{ $item->product_name_snapshot }}">@if($field === 'accepted_quantity')<div class="invalid-feedback d-none" data-grn-row-error></div>@endif</td>
                    @endforeach
                @endif
            </tr>
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

  rows.forEach(row => {
    row.querySelectorAll('[data-grn-quantity]').forEach(field => {
      field.addEventListener('input', () => validateRow(row));
      field.addEventListener('change', () => validateRow(row));
    });
    validateRow(row);
  });

  form.addEventListener('submit', event => {
    const invalidRow = rows.find(row => !validateRow(row));
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
