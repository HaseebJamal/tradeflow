<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Supplier;
use App\Services\AgingReportService;
use App\Services\CompanyPermissionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AgingReportController extends Controller
{
    public function __construct(
        private AgingReportService $aging,
        private CompanyPermissionService $permissions,
    ) {}

    public function customers(Request $request)
    {
        $this->authorize($request, 'customers.view');
        $filters = $this->filters($request);
        $report = $this->aging->customerReport((int) $request->user()->business_id, $filters);

        return view('business.reports.customer-aging', $report + [
            'filters' => $filters,
            'customers' => Customer::where('business_id', $request->user()->business_id)->orderBy('name')->get(['id', 'name', 'business_name']),
        ]);
    }

    public function customerDetails(Request $request, Customer $customer)
    {
        $this->authorize($request, 'customers.view');
        abort_unless($customer->business_id === $request->user()->business_id, 404);
        $filters = $this->filters($request);

        return view('business.reports.aging-details', [
            'type' => 'customer',
            'party' => $customer,
            'filters' => $filters,
            'rows' => $this->aging->customerDetails((int) $request->user()->business_id, $customer, $filters),
        ]);
    }

    public function customerPdf(Request $request)
    {
        $this->authorize($request, 'customers.view');
        $filters = $this->filters($request);
        $report = $this->aging->customerReport((int) $request->user()->business_id, $filters);

        return $this->pdf($request, 'customer', $filters, $report);
    }

    public function suppliers(Request $request)
    {
        $this->authorize($request, 'suppliers.view');
        $filters = $this->filters($request);
        $report = $this->aging->supplierReport((int) $request->user()->business_id, $filters);

        return view('business.reports.supplier-aging', $report + [
            'filters' => $filters,
            'suppliers' => Supplier::where('business_id', $request->user()->business_id)->orderBy('supplier_name')->get(['id', 'supplier_name', 'company_name']),
        ]);
    }

    public function supplierDetails(Request $request, Supplier $supplier)
    {
        $this->authorize($request, 'suppliers.view');
        abort_unless($supplier->business_id === $request->user()->business_id, 404);
        $filters = $this->filters($request);

        return view('business.reports.aging-details', [
            'type' => 'supplier',
            'party' => $supplier,
            'filters' => $filters,
            'rows' => $this->aging->supplierDetails((int) $request->user()->business_id, $supplier, $filters),
        ]);
    }

    public function supplierPdf(Request $request)
    {
        $this->authorize($request, 'suppliers.view');
        $filters = $this->filters($request);
        $report = $this->aging->supplierReport((int) $request->user()->business_id, $filters);

        return $this->pdf($request, 'supplier', $filters, $report);
    }

    private function pdf(Request $request, string $type, array $filters, array $report)
    {
        return Pdf::loadView('business.reports.aging-pdf', $report + [
            'type' => $type,
            'filters' => $filters,
            'business' => $request->user()->business?->load(['documentFooter', 'owner:id,email']),
            'generatedAt' => now(config('app.timezone')),
        ])->setPaper('a4')->stream('tradeflow-'.$type.'-aging-'.$filters['as_of'].'.pdf');
    }

    private function filters(Request $request): array
    {
        $today = now(config('app.timezone'))->toDateString();
        $data = $request->validate([
            'party_id' => ['nullable', 'integer'],
            'bucket' => ['nullable', Rule::in(AgingReportService::BUCKETS)],
            'as_of' => ['nullable', 'date', 'before_or_equal:'.$today],
            'minimum_outstanding' => ['nullable', 'numeric', 'min:0'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);
        $data += ['party_id' => null, 'bucket' => null, 'as_of' => $today, 'minimum_outstanding' => null, 'search' => null];

        // Payments and adjustments that are not tied to a specific document
        // have no safe historic invoice allocation in the current ledger.
        // Refuse to present an invented historical aging snapshot.
        if ($data['as_of'] !== $today) {
            throw ValidationException::withMessages([
                'as_of' => 'Historical aging snapshots are not available until every legacy payment has a document allocation. Use today’s date for the current authoritative aging view.',
            ]);
        }

        return $data;
    }

    private function authorize(Request $request, string $partyPermission): void
    {
        abort_unless(
            $this->permissions->allowsUser($request->user(), 'reports.view')
            && $this->permissions->allowsUser($request->user(), $partyPermission),
            403
        );
    }
}
