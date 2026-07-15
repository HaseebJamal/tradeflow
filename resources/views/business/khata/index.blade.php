@extends('layouts.dashboard')
@section('page-title', 'Ledger')
@section('page-subtitle', 'Double-entry bookkeeping, ledgers, and financial summaries')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="dashboard-cards mb-4">
    <x-card label="Total Sales" :value="'Rs '.number_format($totalSales)" icon="bi-graph-up" color="bg-blue" />
    <x-card label="Accounts Receivable" :value="'Rs '.number_format($accountsReceivable)" icon="bi-wallet2" color="bg-warning" />
    <x-card label="Cash Received" :value="'Rs '.number_format($cashReceived)" icon="bi-cash-stack" color="bg-success" />
    <x-card label="Total Expenses" :value="'Rs '.number_format($totalExpenses)" icon="bi-receipt" color="bg-danger" />
    <x-card label="Net Profit" :value="'Rs '.number_format($netProfit)" icon="bi-bar-chart" color="bg-green" />
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
    @foreach(['general'=>'General Ledger','customer'=>'Customer Ledger','supplier'=>'Supplier Ledger','trial'=>'Trial Balance','profit'=>'Profit & Loss','balance'=>'Balance Sheet','journal'=>'Journal Entries'] as $key => $label)
        <li class="nav-item"><button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-{{ $key }}" type="button">{{ $label }}</button></li>
    @endforeach
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="tab-general">
        <div class="tf-card p-4 mb-3">
            <form class="row g-2 align-items-end">
                <div class="col-md-2"><label class="form-label">Date From</label><input type="date" name="date_from" value="{{ request('date_from', now()->startOfMonth()->toDateString()) }}" class="form-control"></div>
                <div class="col-md-2"><label class="form-label">Date To</label><input type="date" name="date_to" value="{{ request('date_to', now()->toDateString()) }}" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">Account</label><select name="account_id" class="form-select"><option value="">All</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected(request('account_id') == $account->id)>{{ $account->code }} - {{ $account->name }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label">Customer</label><select name="customer_id" class="form-select"><option value="">All</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(request('customer_id') == $customer->id)>{{ $customer->display_name }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label">Supplier</label><select name="supplier_id" class="form-select"><option value="">All</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(request('supplier_id') == $supplier->id)>{{ $supplier->company_name ?: $supplier->supplier_name }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All</option><option value="posted" @selected(request('status') === 'posted')>Posted</option><option value="draft" @selected(request('status') === 'draft')>Draft</option><option value="void" @selected(request('status') === 'void')>Void</option></select></div>
                <div class="col-md-3"><label class="form-label">Search</label><input name="search" value="{{ request('search') }}" class="form-control" placeholder="Voucher or description"></div>
                <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
            </form>
        </div>
        <x-table>
            <thead><tr><th>Posted At</th><th>Voucher</th><th>Account</th><th>Description</th><th>Debit</th><th>Credit</th><th>Running Balance</th></tr></thead>
            <tbody>
            @php($running = 0)
            @forelse($ledgerLines as $line)
                @php($running += $line->debit - $line->credit)
                <tr>
                    <td><x-date-time :value="$line->journalEntry?->posted_at ?? $line->journalEntry?->created_at" /></td>
                    <td>{{ $line->journalEntry?->voucher_number }}</td>
                    <td>{{ $line->account?->code }} - {{ $line->account?->name }}</td>
                    <td>{{ $line->description ?: $line->journalEntry?->description }}</td>
                    <td>Rs {{ number_format($line->debit) }}</td>
                    <td>Rs {{ number_format($line->credit) }}</td>
                    <td>Rs {{ number_format($running) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center tf-muted py-4">No journal lines yet.</td></tr>
            @endforelse
            </tbody>
        </x-table>
        <div class="mt-3">{{ $ledgerLines->links() }}</div>
    </div>

    <div class="tab-pane fade" id="tab-customer">
        <x-table>
            <thead><tr><th>Customer</th><th>Total Debits</th><th>Total Credits</th><th>Outstanding</th><th></th></tr></thead>
            <tbody>@forelse($customerSummaries as $row)<tr><td>{{ $row['customer']->display_name }}</td><td>Rs {{ number_format($row['debit'], 2) }}</td><td>Rs {{ number_format($row['credit'], 2) }}</td><td>Rs {{ number_format($row['balance'], 2) }}</td><td><a href="{{ route('business.customers.show', $row['customer']) }}" class="btn btn-sm btn-outline-primary">Profile</a></td></tr>@empty<tr><td colspan="5" class="text-center tf-muted py-4">No customer ledger entries.</td></tr>@endforelse</tbody>
        </x-table>
    </div>

    <div class="tab-pane fade" id="tab-supplier">
        <x-table>
            <thead><tr><th>Supplier</th><th>Total Purchases / Credits</th><th>Total Payments / Debits</th><th>Remaining Payable</th><th></th></tr></thead>
            <tbody>@forelse($supplierSummaries as $row)<tr><td>{{ $row['supplier']->company_name ?: $row['supplier']->supplier_name }}</td><td>Rs {{ number_format($row['credit']) }}</td><td>Rs {{ number_format($row['debit']) }}</td><td>Rs {{ number_format($row['balance']) }}</td><td><a href="{{ route('business.suppliers.show', $row['supplier']) }}" class="btn btn-sm btn-outline-primary">Profile</a></td></tr>@empty<tr><td colspan="5" class="text-center tf-muted py-4">No supplier ledger entries.</td></tr>@endforelse</tbody>
        </x-table>
    </div>

    <div class="tab-pane fade" id="tab-trial">
        <x-table>
            <thead><tr><th>Account Code</th><th>Account Name</th><th>Account Type</th><th>Debit</th><th>Credit</th><th>Balance</th></tr></thead>
            <tbody>@foreach($trialBalance as $row)<tr><td>{{ $row['account']->code }}</td><td>{{ $row['account']->name }}</td><td>{{ $row['account']->account_type }}</td><td>Rs {{ number_format($row['debit']) }}</td><td>Rs {{ number_format($row['credit']) }}</td><td>Rs {{ number_format($row['balance']) }}</td></tr>@endforeach</tbody>
        </x-table>
    </div>

    <div class="tab-pane fade" id="tab-profit">
        <div class="row g-3">
            <div class="col-md-4"><div class="tf-card p-4"><div class="tf-muted">Sales Revenue</div><div class="h3">Rs {{ number_format($totalSales) }}</div></div></div>
            <div class="col-md-4"><div class="tf-card p-4"><div class="tf-muted">Expenses</div><div class="h3">Rs {{ number_format($totalExpenses) }}</div></div></div>
            <div class="col-md-4"><div class="tf-card p-4"><div class="tf-muted">Net Profit</div><div class="h3">Rs {{ number_format($netProfit) }}</div></div></div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-balance">
        <x-table>
            <thead><tr><th>Account</th><th>Type</th><th>Balance</th></tr></thead>
            <tbody>@foreach($trialBalance->whereIn('account.account_type', ['Asset','Liability','Equity']) as $row)<tr><td>{{ $row['account']->name }}</td><td>{{ $row['account']->account_type }}</td><td>Rs {{ number_format($row['balance']) }}</td></tr>@endforeach</tbody>
        </x-table>
    </div>

    <div class="tab-pane fade" id="tab-journal">
        <div class="tf-card p-4 mb-4">
            <h2 class="h5">Automatic Journal Entries</h2>
            <p class="tf-muted mb-0">Journal entries are posted automatically from purchases, sales, payments, returns, deliveries, and expenses. Manual entries are disabled to prevent duplicate accounting.</p>
        </div>
        <x-table><thead><tr><th>Posted At</th><th>Voucher</th><th>Description</th><th>Status</th></tr></thead><tbody>@foreach($journalEntries as $entry)<tr><td><x-date-time :value="$entry->posted_at ?? $entry->created_at" /></td><td>{{ $entry->voucher_number }}</td><td>{{ $entry->description }}</td><td>{{ ucfirst($entry->status) }}</td></tr>@endforeach</tbody></x-table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-journal-form]');

    if (!form) {
        return;
    }

    const debitInputs = [...form.querySelectorAll('[data-journal-debit]')];
    const creditInputs = [...form.querySelectorAll('[data-journal-credit]')];
    const accountInputs = [...form.querySelectorAll('select[name$="[account_id]"]')];

    const totalDebitElement = form.querySelector('[data-journal-total-debit]');
    const totalCreditElement = form.querySelector('[data-journal-total-credit]');
    const differenceElement = form.querySelector('[data-journal-difference]');
    const submitButton = form.querySelector('[data-journal-submit]');
    const messageElement = form.querySelector('[data-journal-message]');

    const amount = (value) => {
        const parsed = Number.parseFloat(value);
        return Number.isFinite(parsed) ? Math.max(parsed, 0) : 0;
    };

    const money = (value) => Number(value).toFixed(2);

    function setFieldState(input, valid) {
        input.classList.toggle('is-invalid', !valid);
    }

    function validateJournal() {
        let totalDebit = 0;
        let totalCredit = 0;
        let validLines = 0;
        let hasMissingAccount = false;
        let hasInvalidLine = false;

        debitInputs.forEach((debitInput, index) => {
            const creditInput = creditInputs[index];
            const accountInput = accountInputs[index];

            const debit = amount(debitInput.value);
            const credit = amount(creditInput.value);
            const hasAccount = Boolean(accountInput.value);

            totalDebit += debit;
            totalCredit += credit;

            const hasOneSideOnly = (debit > 0 && credit === 0) || (credit > 0 && debit === 0);
            const lineValid = hasAccount && hasOneSideOnly;

            setFieldState(accountInput, hasAccount || (debit === 0 && credit === 0));
            setFieldState(debitInput, !(debit > 0 && credit > 0));
            setFieldState(creditInput, !(debit > 0 && credit > 0));

            if (debit > 0 || credit > 0 || hasAccount) {
                if (!hasAccount) {
                    hasMissingAccount = true;
                }

                if (!hasOneSideOnly) {
                    hasInvalidLine = true;
                }

                if (lineValid) {
                    validLines++;
                }
            }
        });

        totalDebit = Math.round((totalDebit + Number.EPSILON) * 100) / 100;
        totalCredit = Math.round((totalCredit + Number.EPSILON) * 100) / 100;
        const difference = Math.round((totalDebit - totalCredit + Number.EPSILON) * 100) / 100;

        totalDebitElement.textContent = money(totalDebit);
        totalCreditElement.textContent = money(totalCredit);
        differenceElement.textContent = money(Math.abs(difference));

        const descriptionValid = form.querySelector('[name="description"]').value.trim() !== '';
        const balanced = totalDebit > 0 && totalCredit > 0 && Math.abs(difference) < 0.005;
        const canPost = validLines >= 2
            && !hasMissingAccount
            && !hasInvalidLine
            && descriptionValid
            && balanced;

        submitButton.disabled = !canPost;

        messageElement.classList.remove('alert-success', 'alert-warning', 'alert-danger');

        if (!descriptionValid) {
            messageElement.classList.add('alert-warning');
            messageElement.textContent = 'Enter a journal description before posting.';
        } else if (hasMissingAccount) {
            messageElement.classList.add('alert-danger');
            messageElement.textContent = 'Select an account for every journal line that contains an amount.';
        } else if (hasInvalidLine) {
            messageElement.classList.add('alert-danger');
            messageElement.textContent = 'Each line must contain either a debit or a credit amount, not both.';
        } else if (validLines < 2) {
            messageElement.classList.add('alert-warning');
            messageElement.textContent = 'Add at least two valid journal lines.';
        } else if (!balanced) {
            messageElement.classList.add('alert-danger');

            if (difference > 0) {
                messageElement.textContent = `Debit exceeds credit by Rs ${money(difference)}.`;
            } else {
                messageElement.textContent = `Credit exceeds debit by Rs ${money(Math.abs(difference))}.`;
            }
        } else {
            messageElement.classList.add('alert-success');
            messageElement.textContent = 'Journal entry is balanced and ready to post.';
        }
    }

    debitInputs.forEach((input, index) => {
        input.addEventListener('input', function () {
            if (amount(this.value) > 0) {
                creditInputs[index].value = '0';
            }
            validateJournal();
        });
    });

    creditInputs.forEach((input, index) => {
        input.addEventListener('input', function () {
            if (amount(this.value) > 0) {
                debitInputs[index].value = '0';
            }
            validateJournal();
        });
    });

    accountInputs.forEach(input => input.addEventListener('change', validateJournal));
    form.querySelector('[name="description"]').addEventListener('input', validateJournal);

    form.addEventListener('submit', function (event) {
        validateJournal();

        if (submitButton.disabled) {
            event.preventDefault();
            messageElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Posting...';
    });

    validateJournal();
});
</script>

@endsection
