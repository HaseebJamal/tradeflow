@extends('layouts.dashboard')
@section('page-title', 'Invoice')
@section('page-subtitle', $invoice->invoice_number ?? 'Invoice')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="mb-3 text-end d-flex flex-wrap gap-2 justify-content-end">
    @companyCan('sales.invoice_export')<button onclick="window.print()" class="btn btn-tf-primary"><i class="bi bi-printer me-1"></i>Print</button>@endcompanyCan
    @companyCan('sales.invoice_export')<a href="{{ route('business.sales.invoices.pdf', $order) }}" target="_blank" rel="noopener" class="btn btn-outline-primary"><i class="bi bi-filetype-pdf me-1"></i>View PDF</a><a href="{{ route('business.sales.invoices.pdf.download', $order) }}" class="btn btn-outline-secondary"><i class="bi bi-download me-1"></i>Download PDF</a>@endcompanyCan
    @if($invoice->status === 'Draft')
        @companyCan('sales.invoices')<form method="POST" action="{{ route('business.sales.invoices.issue', $invoice) }}">@csrf @method('PATCH')<button class="btn btn-outline-success">Issue Invoice</button></form>@endcompanyCan
    @elseif(!in_array($invoice->status, ['Void','Cancelled'], true))
        @companyCan('sales.invoices')<button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#creditNoteModal">Credit Note</button>@endcompanyCan
        @companyCan('sales.invoices')<button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#voidInvoiceModal">Void Invoice</button>@endcompanyCan
    @endif
    @if(in_array($invoice->status, ['Void','Cancelled'], true))
        @companyCan('sales.invoices')<form method="POST" action="{{ route('business.sales.invoices.reissue', $invoice) }}">@csrf @method('PATCH')<button class="btn btn-outline-success">Reissue Invoice</button></form>@endcompanyCan
    @endif
    <button type="button" class="btn btn-outline-secondary">Share Manually</button>
</div>
@if($invoice->status === 'Draft' && app(\App\Services\CompanyPermissionService::class)->allowsUser(auth()->user(), 'sales.invoices'))
<div class="tf-card p-4 mb-4">
    <h2 class="h5">Draft Invoice Settings</h2>
    <form method="POST" action="{{ route('business.sales.invoices.update', $invoice) }}" class="row g-3">@csrf @method('PATCH')
        <div class="col-md-4"><label class="form-label">Due Date</label><input type="date" name="due_date" value="{{ $invoice->due_date?->format('Y-m-d') }}" class="form-control"></div>
        <div class="col-md-8"><label class="form-label">Notes</label><input name="notes" value="{{ $invoice->notes }}" class="form-control"></div>
        <div class="col-12"><button class="btn btn-outline-primary">Save Draft</button></div>
    </form>
</div>
@endif
@include('business.invoices._thermal-invoice', ['invoice' => $invoice, 'order' => $order, 'paper' => 80])
@if($invoice->creditNotes->isNotEmpty())
<div class="tf-card p-4 mt-4"><h2 class="h5">Credit Notes</h2><x-table><thead><tr><th>No</th><th>Date</th><th>Reason</th><th>Amount</th></tr></thead><tbody>@foreach($invoice->creditNotes as $note)<tr><td>{{ $note->credit_note_number }}</td><td>{{ $note->date?->format('M d, Y') }}</td><td>{{ $note->reason }}</td><td>Rs {{ number_format($note->amount) }}</td></tr>@endforeach</tbody></x-table></div>
@endif
<div class="modal fade" id="voidInvoiceModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('business.sales.invoices.void', $invoice) }}">@csrf @method('PATCH')<div class="modal-header"><h2 class="h5 modal-title">Void Invoice</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><label class="form-label">Reason</label><textarea name="void_reason" class="form-control" required></textarea></div><div class="modal-footer"><button class="btn btn-danger">Void Invoice</button></div></form></div></div></div>
<div class="modal fade" id="creditNoteModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('business.sales.invoices.credit-notes.store', $invoice) }}">@csrf<div class="modal-header"><h2 class="h5 modal-title">Create Credit Note</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body row g-3"><div class="col-12"><label class="form-label">Amount</label><input name="amount" type="number" step="0.01" min="0.01" max="{{ $invoice->grand_total }}" class="form-control" required></div><div class="col-12"><label class="form-label">Reason</label><textarea name="reason" class="form-control" required></textarea></div></div><div class="modal-footer"><button class="btn btn-warning">Post Credit Note</button></div></form></div></div></div>
@endsection
