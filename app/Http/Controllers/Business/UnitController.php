<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\StoreOrUpdateUnitRequest;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $query = Unit::where('business_id', $request->user()->business_id);

        if ($request->input('status') === 'Archived' || $request->boolean('archived')) {
            $query->onlyTrashed();
        } elseif (in_array($request->input('status'), ['Active', 'Inactive'], true)) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->trim();
            $query->where(fn ($builder) => $builder
                ->where('unit_name', 'like', "%{$search}%")
                ->orWhere('short_code', 'like', "%{$search}%"));
        }

        return view('business.units.index', [
            'units' => $query->latest()->paginate(12)->withQueryString(),
        ]);
    }

    public function create()
    {
        return view('business.units.form', [
            'usedUnitTypes' => $this->usedUnitTypes(),
        ]);
    }

    public function store(StoreOrUpdateUnitRequest $request)
    {
        $data = $request->validated();
        $unit = Unit::create([
            ...$data,
            'business_id' => $request->user()->business_id,
            'short_code' => $this->generateShortCode($data['unit_name'], $request->user()->business_id),
            'created_by' => $request->user()->id,
        ]);

        $this->audit($request, 'Unit Created', $unit);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Unit created successfully.',
                'unit' => $unit->only(['id', 'unit_name', 'short_code', 'status']),
            ], 201);
        }

        return redirect()->route('business.units.index')->with('success', 'Unit created successfully.');
    }

    public function show(Request $request, int $unit)
    {
        $unit = Unit::withTrashed()
            ->where('business_id', $request->user()->business_id)
            ->findOrFail($unit);
        $this->ensureBusiness($unit);

        return view('business.units.show', compact('unit'));
    }

    public function edit(Unit $unit)
    {
        $this->ensureBusiness($unit);

        return view('business.units.form', [
            'unit' => $unit,
            'usedUnitTypes' => $this->usedUnitTypes($unit),
        ]);
    }

    public function update(StoreOrUpdateUnitRequest $request, Unit $unit)
    {
        $this->ensureBusiness($unit);
        $unit->update($request->validated());

        $this->audit($request, 'Unit Updated', $unit);

        return redirect()->route('business.units.index')->with('success', 'Unit updated successfully.');
    }

    public function toggleStatus(Request $request, Unit $unit)
    {
        $this->ensureBusiness($unit);
        $unit->update(['status' => $unit->status === 'Active' ? 'Inactive' : 'Active']);
        $this->audit($request, $unit->status === 'Active' ? 'Unit Activated' : 'Unit Deactivated', $unit);

        return back()->with('success', 'Unit status updated.');
    }

    public function archive(Request $request, Unit $unit)
    {
        $this->ensureBusiness($unit);
        $unit->update(['status' => 'Inactive']);
        $unit->delete();
        $this->audit($request, 'Unit Archived', $unit);

        return back()->with('success', 'Record archived successfully.');
    }

    public function restore(Request $request, int $unit)
    {
        $record = Unit::withTrashed()
            ->where('business_id', $request->user()->business_id)
            ->findOrFail($unit);
        $record->restore();
        $record->update(['status' => 'Active']);
        $this->audit($request, 'Unit Restored', $record);

        return back()->with('success', 'Record restored successfully.');
    }

    public function destroy(Request $request, int $unit)
    {
        $unit = Unit::withTrashed()
            ->where('business_id', $request->user()->business_id)
            ->findOrFail($unit);
        $this->ensureBusiness($unit);

        if (Product::withTrashed()->where('business_id', $unit->business_id)->where('unit_id', $unit->id)->exists()) {
            return back()->with('error', 'This unit is assigned to one or more products and cannot be deleted.');
        }

        $this->audit($request, 'Unit Permanently Deleted', $unit);
        $unit->forceDelete();

        return back()->with('success', 'Record permanently deleted.');
    }

    private function generateShortCode(string $unitName, int $businessId): string
    {
        $normalised = strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $unitName)));
        $aliases = [
            'piece' => 'PC', 'pieces' => 'PC', 'pc' => 'PC',
            'kilogram' => 'KG', 'kilograms' => 'KG', 'kg' => 'KG', 'kilo' => 'KG',
            'liter' => 'L', 'litre' => 'L', 'liters' => 'L', 'litres' => 'L',
            'carton' => 'CTN', 'cartons' => 'CTN',
            'bottle' => 'BTL', 'bottles' => 'BTL',
        ];
        $words = array_values(array_filter(explode(' ', $normalised)));
        $base = $aliases[$normalised] ?? (count($words) > 1
            ? implode('', array_map(fn ($word) => strtoupper(substr($word, 0, 1)), $words))
            : strtoupper(substr($words[0] ?? 'UNIT', 0, 3)));
        $base = substr($base ?: 'UNIT', 0, 18);
        $candidate = $base;
        $suffix = 2;

        while (Unit::withTrashed()
            ->where('business_id', $businessId)
            ->where('short_code', $candidate)
            ->exists()) {
            $candidate = substr($base, 0, 20 - strlen((string) $suffix)).$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function ensureBusiness(Unit $unit): void
    {
        abort_unless($unit->business_id === auth()->user()->business_id || auth()->user()->role === 'super_admin', 404);
    }

    private function usedUnitTypes(?Unit $except = null): array
    {
        return Unit::withTrashed()
            ->where('business_id', auth()->user()->business_id)
            ->when($except, fn ($query) => $query->where('unit_type', '!=', $except->unit_type))
            ->pluck('unit_type')
            ->all();
    }

    private function audit(Request $request, string $action, Unit $unit): void
    {
        AuditLog::create([
            'business_id' => $unit->business_id,
            'module' => 'Units',
            'action' => $action,
            'description' => $unit->unit_name,
            'record_type' => Unit::class,
            'record_id' => $unit->id,
            'occurred_at' => now(),
        ]);
    }
}
