<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Unit;
use App\Services\CompanyPermissionService;
use App\Services\StockMovementAnalyticsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class StockMovementAnalyticsController extends Controller
{
    public function __construct(private StockMovementAnalyticsService $analytics, private CompanyPermissionService $permissions) {}

    public function index(Request $request)
    {
        $this->authorize($request);
        $filters = $this->filters($request);
        $rows = $this->filteredRows((int) $request->user()->business_id, $filters);

        return view('business.reports.stock-movement-analytics', $this->payload($request, $filters, $rows));
    }

    public function pdf(Request $request)
    {
        $this->authorize($request, true);
        $filters = $this->filters($request);
        $rows = $this->filteredRows((int) $request->user()->business_id, $filters);
        $summary = $this->summary($rows);

        return Pdf::loadView('business.reports.stock-movement-analytics-pdf', [
            'business' => $request->user()->business?->load(['documentFooter', 'owner:id,email']),
            'filters' => $filters, 'rows' => $rows, 'summary' => $summary,
            'generatedAt' => now(config('app.timezone')),
        ])->setPaper('a4')->stream('tradeflow-stock-movement-analysis-'.$filters['period'].'-days.pdf');
    }

    private function payload(Request $request, array $filters, $rows): array
    {
        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = $filters['per_page'];
        $paginator = new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, [
            'path' => $request->url(), 'query' => $request->query(),
        ]);
        $businessId = (int) $request->user()->business_id;

        return [
            'analytics' => $paginator, 'filters' => $filters, 'summary' => $this->summary($rows),
            'categories' => Category::where('business_id', $businessId)->where('type', 'Product')->orderBy('name')->get(['id', 'name']),
            'units' => Unit::where('business_id', $businessId)->where('status', 'Active')->orderBy('unit_name')->get(['id', 'unit_name', 'short_code']),
            'canExport' => $this->permissions->allowsUser($request->user(), 'reports.export'),
            'canViewProducts' => $this->permissions->allowsUser($request->user(), 'products.view'),
        ];
    }

    private function filteredRows(int $businessId, array $filters)
    {
        $rows = $this->analytics->report($businessId, $filters['from'], $filters['to'], $filters['dead_threshold']);
        $search = strtolower(trim((string) $filters['search']));
        $rows = $rows->filter(function (object $row) use ($filters, $search): bool {
            if ($search !== '' && ! str_contains(strtolower($row->name), $search) && ! str_contains(strtolower((string) $row->product->barcode), $search)) return false;
            if ($filters['category_id'] && (int) $row->category_id !== (int) $filters['category_id']) return false;
            if ($filters['unit_id'] && (int) $row->unit_id !== (int) $filters['unit_id']) return false;
            if ($filters['status'] !== 'all' && str($row->movement_status)->slug()->toString() !== $filters['status']) return false;
            return true;
        });

        return match ($filters['sort']) {
            'stock' => $rows->sortByDesc('current_stock')->values(),
            'inventory_value' => $rows->sortByDesc('inventory_value')->values(),
            'last_sale' => $rows->sortByDesc(fn (object $row) => $row->last_sale_at?->timestamp ?? 0)->values(),
            'days_since_sale' => $rows->sortByDesc(fn (object $row) => $row->days_since_sale ?? -1)->values(),
            default => $rows->sortByDesc('qty_sold')->values(),
        };
    }

    private function summary($rows): array
    {
        return [
            'fast' => $rows->where('movement_status', 'Fast Moving')->count(),
            'slow' => $rows->where('movement_status', 'Slow Moving')->count(),
            'dead' => $rows->where('movement_status', 'Dead Stock')->count(),
            'dead_value' => round((float) $rows->where('movement_status', 'Dead Stock')->sum('inventory_value'), 2),
        ];
    }

    private function filters(Request $request): array
    {
        $data = $request->validate([
            'period' => ['nullable', Rule::in([30, 60, 90])],
            'dead_threshold' => ['nullable', Rule::in([30, 60, 90, 180])],
            'search' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer'], 'unit_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['all', 'fast-moving', 'slow-moving', 'dead-stock', 'normal', 'no-sales-history'])],
            'sort' => ['nullable', Rule::in(['qty_sold', 'stock', 'inventory_value', 'last_sale', 'days_since_sale'])],
            'per_page' => ['nullable', Rule::in([10, 25, 50, 100])],
        ]);
        $period = (int) ($data['period'] ?? 30);
        $now = now(config('app.timezone'));

        return array_merge($data, [
            'period' => $period, 'dead_threshold' => (int) ($data['dead_threshold'] ?? 90),
            'search' => $data['search'] ?? null, 'category_id' => $data['category_id'] ?? null, 'unit_id' => $data['unit_id'] ?? null,
            'status' => $data['status'] ?? 'all', 'sort' => $data['sort'] ?? 'qty_sold', 'per_page' => (int) ($data['per_page'] ?? 10),
            'from' => $now->copy()->subDays($period - 1)->startOfDay(), 'to' => $now->copy()->endOfDay(),
        ]);
    }

    private function authorize(Request $request, bool $export = false): void
    {
        abort_unless($this->permissions->allowsUser($request->user(), 'reports.view') && $this->permissions->allowsUser($request->user(), 'inventory.view'), 403);
        if ($export) abort_unless($this->permissions->allowsUser($request->user(), 'reports.export'), 403);
    }
}
