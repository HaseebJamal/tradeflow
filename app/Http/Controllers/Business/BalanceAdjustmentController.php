<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\BalanceAdjustment;
use App\Models\Customer;
use App\Models\Supplier;
use App\Services\BalanceAdjustmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BalanceAdjustmentController extends Controller
{
    public function __construct(private readonly BalanceAdjustmentService $adjustments) {}

    public function storeCustomer(Request $request, Customer $customer)
    {
        abort_unless($customer->business_id === $request->user()->business_id, 403);
        $data = $this->validated($request, BalanceAdjustmentService::CUSTOMER_TYPES);
        $adjustment = $this->adjustments->postCustomer($customer, $request->user(), $data);
        return back()->with('success', 'Customer balance adjustment '.$adjustment->reference.' posted.');
    }

    public function storeSupplier(Request $request, Supplier $supplier)
    {
        abort_unless($supplier->business_id === $request->user()->business_id, 403);
        $data = $this->validated($request, BalanceAdjustmentService::SUPPLIER_TYPES);
        $adjustment = $this->adjustments->postSupplier($supplier, $request->user(), $data);
        return back()->with('success', 'Supplier balance adjustment '.$adjustment->reference.' posted.');
    }

    public function reverseCustomer(Request $request, Customer $customer, BalanceAdjustment $adjustment)
    {
        $this->assertAdjustment($request, $adjustment, 'customer', $customer->id);
        $reversal = $this->adjustments->reverse($adjustment, $request->user(), $request->validate(['submission_token' => ['required', 'uuid']])['submission_token']);
        return back()->with('success', 'Adjustment '.$adjustment->reference.' reversed by '.$reversal->reference.'.');
    }

    public function reverseSupplier(Request $request, Supplier $supplier, BalanceAdjustment $adjustment)
    {
        $this->assertAdjustment($request, $adjustment, 'supplier', $supplier->id);
        $reversal = $this->adjustments->reverse($adjustment, $request->user(), $request->validate(['submission_token' => ['required', 'uuid']])['submission_token']);
        return back()->with('success', 'Adjustment '.$adjustment->reference.' reversed by '.$reversal->reference.'.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, array $types): array
    {
        return $request->validate([
            'adjustment_type' => ['required', Rule::in($types)],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'reason' => ['required', Rule::in(BalanceAdjustmentService::REASONS)],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000', 'required_if:reason,Other'],
            'submission_token' => ['required', 'uuid'],
        ]);
    }

    private function assertAdjustment(Request $request, BalanceAdjustment $adjustment, string $type, int $partyId): void
    {
        abort_unless($adjustment->business_id === $request->user()->business_id && $adjustment->party_type === $type && (int) $adjustment->party_id === $partyId, 404);
    }
}
