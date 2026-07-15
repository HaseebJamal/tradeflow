@extends('layouts.dashboard')
@section('page-title', 'Expenses')
@section('page-subtitle', 'Expense CRUD and filtered profit/loss data')
@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

@companyCan('expenses.create')<div class="tf-card p-4 mb-4">
    <h2 class="h5">Add Expense</h2>
    <form method="POST" action="{{ route('business.expenses.store') }}" class="row g-3">@csrf
        <div class="col-md-3"><label class="form-label">Title</label><input name="title" class="form-control" placeholder="Title" required></div>
        <div class="col-md-2"><label class="form-label">Category</label><select name="category" class="form-select">@foreach($categories ?? [] as $cat)<option>{{ $cat }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Amount</label><input name="amount" type="number" step="0.01" class="form-control" placeholder="Amount" required></div>
        <div class="col-md-2"><label class="form-label">Expense Date</label><input name="expense_date" type="date" class="form-control" value="{{ now()->format('Y-m-d') }}"></div>
        <div class="col-md-3"><label class="form-label">Reason / Description</label><input name="description" class="form-control" placeholder="Reason"></div>
        <div class="col-12"><button class="btn btn-tf-primary">Save Expense</button></div>
    </form>
</div>@endcompanyCan

<form class="tf-card p-4 mb-4 row g-3">
    <div class="col-md-3"><label class="form-label">Search</label><input name="search" class="form-control" value="{{ request('search') }}" placeholder="Title or reason"></div>
    <div class="col-md-2"><label class="form-label">Category</label><select name="category" class="form-select"><option value="">All</option>@foreach($categories ?? [] as $cat)<option value="{{ $cat }}" @selected(request('category') === $cat)>{{ $cat }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Amount From</label><input name="amount_from" type="number" step="0.01" class="form-control" value="{{ request('amount_from') }}"></div>
    <div class="col-md-2"><label class="form-label">Amount To</label><input name="amount_to" type="number" step="0.01" class="form-control" value="{{ request('amount_to') }}"></div>
    <div class="col-md-3"><label class="form-label">Date From</label><input name="date_from" type="date" class="form-control" value="{{ request('date_from', now()->format('Y-m-d')) }}"></div>
    <div class="col-md-3"><label class="form-label">Date To</label><input name="date_to" type="date" class="form-control" value="{{ request('date_to', now()->format('Y-m-d')) }}"></div>
    <div class="col-md-2"><label class="form-label">Month</label><select name="month" class="form-select"><option value="">All</option>@for($m = 1; $m <= 12; $m++)<option value="{{ $m }}" @selected((string) request('month') === (string) $m)>{{ \Illuminate\Support\Carbon::create()->month($m)->format('F') }}</option>@endfor</select></div>
    <div class="col-md-2"><label class="form-label">Year</label><input name="year" type="number" min="2000" max="2100" class="form-control" value="{{ request('year', now()->year) }}"></div>
    <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary w-100">Apply Filters</button></div>
    <div class="col-md-3 d-flex align-items-end"><a href="{{ route('business.expenses.index') }}" class="btn btn-outline-secondary w-100">Clear</a></div>
</form>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="tf-card p-4"><div class="tf-muted">Total Expenses After Filters</div><div class="h3 fw-bold">Rs {{ number_format($totalExpenses ?? 0, 2) }}</div></div></div>
    <div class="col-md-8"><div class="tf-card p-4"><h2 class="h6">Monthly Expense Summary</h2><div class="d-flex flex-wrap gap-2">@forelse($monthlySummary ?? [] as $summary)<span class="badge text-bg-light border">{{ \Illuminate\Support\Carbon::create($summary->year, $summary->month, 1)->format('M Y') }}: Rs {{ number_format($summary->total, 2) }}</span>@empty<span class="tf-muted">No monthly expense data yet.</span>@endforelse</div></div></div>
</div>

<x-table><thead><tr><th>Date</th><th>Title</th><th>Category</th><th>Reason</th><th>Amount</th><th></th></tr></thead><tbody>@forelse($expenses as $expense)<tr><td>{{ ($expense->expense_date ?: $expense->date)?->format('M d, Y') }}</td><td>{{ $expense->title }}</td><td>{{ $expense->category }}</td><td>{{ $expense->description ?: '-' }}</td><td>Rs {{ number_format($expense->amount, 2) }}</td><td>@companyCan('expenses.delete')<form method="POST" action="{{ route('business.expenses.destroy', $expense) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>@endcompanyCan</td></tr>@empty<tr><td colspan="6" class="text-center tf-muted py-4">No expenses found.</td></tr>@endforelse</tbody></x-table>
@if(method_exists($expenses, 'links'))<div class="mt-3">{{ $expenses->links() }}</div>@endif
@endsection
