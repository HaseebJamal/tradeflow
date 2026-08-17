<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Services\CompanyPermissionService;
use App\Services\ProductPerformanceAnalyticsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductPerformanceController extends Controller
{
    public function __construct(private ProductPerformanceAnalyticsService $analytics, private CompanyPermissionService $permissions) {}

    public function index(Request $request)
    {
        $this->authorize($request);
        $filters = $this->filters($request);
        $rows = $this->filteredRows((int) $request->user()->business_id, $filters);

        return view('business.reports.product-performance', $this->payload($request, $filters, $rows));
    }

    public function show(Request $request, Product $product)
    {
        $this->authorize($request);
        abort_unless((int) $product->business_id === (int) $request->user()->business_id, 404);
        $filters = $this->filters($request);
        $row = $this->analytics->report((int) $request->user()->business_id, $filters['from'], $filters['to'])->firstWhere('product_id', $product->id);
        abort_unless($row, 404);

        return view('business.reports.product-performance-show', [
            'row' => $row, 'filters' => $filters,
            'canViewReturns' => $this->permissions->allowsUser($request->user(), 'sales_returns.view'),
            'canEditProducts' => $this->permissions->allowsUser($request->user(), 'products.edit'),
        ]);
    }

    public function pdf(Request $request)
    {
        $this->authorize($request, true);
        $filters = $this->filters($request);
        $rows = $this->filteredRows((int) $request->user()->business_id, $filters);

        return Pdf::loadView('business.reports.product-performance-pdf', [
            'business' => $request->user()->business?->load(['documentFooter', 'owner:id,email']),
            'filters' => $filters, 'rows' => $rows, 'summary' => $this->summary($rows),
            'generatedAt' => now(config('app.timezone')),
        ])->setPaper('a4')->stream('tradeflow-product-performance-'.$filters['label'].'.pdf');
    }

    private function payload(Request $request, array $filters, $rows): array
    {
        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = $filters['per_page'];
        $analytics = new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, [
            'path' => $request->url(), 'query' => $request->query(),
        ]);
        $businessId = (int) $request->user()->business_id;

        return [
            'analytics' => $analytics, 'filters' => $filters, 'summary' => $this->summary($rows),
            'categories' => Category::where('business_id', $businessId)->where('type', 'Product')->orderBy('name')->get(['id', 'name']),
            'units' => Unit::where('business_id', $businessId)->where('status', 'Active')->orderBy('unit_name')->get(['id', 'unit_name', 'short_code']),
            'canExport' => $this->permissions->allowsUser($request->user(), 'reports.export'),
        ];
    }

    private function filteredRows(int $businessId, array $filters)
    {
        $rows = $this->analytics->report($businessId, $filters['from'], $filters['to']);
        $search = strtolower(trim((string) $filters['search']));
        $rows = $rows->filter(function (object $row) use ($filters, $search): bool {
            if ($search !== '' && ! str_contains(strtolower($row->name), $search) && ! str_contains(strtolower((string) $row->barcode), $search)) return false;
            if ($filters['category_id'] && (int) $row->category_id !== (int) $filters['category_id']) return false;
            if ($filters['unit_id'] && (int) $row->unit_id !== (int) $filters['unit_id']) return false;

            return match ($filters['performance_type']) {
                'low-margin' => $row->gross_margin !== null && $row->gross_profit >= 0,
                'loss-making' => $row->gross_profit < 0,
                'high-return-rate' => $row->return_rate !== null && $row->return_rate > 0,
                default => true,
            };
        });
        $sort = $filters['sort'] === 'auto' ? match ($filters['performance_type']) {
            'high-profit' => 'gross_profit', 'low-margin' => 'gross_margin_asc', 'loss-making' => 'gross_profit_asc',
            'high-return-rate' => 'return_rate', 'high-revenue' => 'net_sales', default => 'net_sales',
        } : $filters['sort'];

        return match ($sort) {
            'qty_sold' => $rows->sortByDesc('qty_sold')->values(),
            'cogs' => $rows->sortByDesc('cogs')->values(),
            'gross_profit' => $rows->sortByDesc('gross_profit')->values(),
            'gross_profit_asc' => $rows->sortBy('gross_profit')->values(),
            'gross_margin' => $rows->sortByDesc(fn (object $row) => $row->gross_margin ?? -PHP_FLOAT_MAX)->values(),
            'gross_margin_asc' => $rows->sortBy(fn (object $row) => $row->gross_margin ?? PHP_FLOAT_MAX)->values(),
            'returned_qty' => $rows->sortByDesc('qty_returned')->values(),
            'return_value' => $rows->sortByDesc('return_value')->values(),
            'return_rate' => $rows->sortByDesc(fn (object $row) => $row->return_rate ?? -PHP_FLOAT_MAX)->values(),
            default => $rows->sortByDesc('net_sales')->values(),
        };
    }

    private function summary($rows): array
    {
        $netSales = round((float) $rows->sum('net_sales'), 2);
        $grossProfit = round((float) $rows->sum('gross_profit'), 2);
        $sold = (float) $rows->sum('qty_sold');
        $returned = (float) $rows->sum('qty_returned');

        return [
            'net_sales' => $netSales, 'gross_profit' => $grossProfit,
            'average_margin' => $netSales > 0 ? round(($grossProfit / $netSales) * 100, 2) : null,
            'return_value' => round((float) $rows->sum('return_value'), 2),
            'return_rate' => $sold > 0 ? round(($returned / $sold) * 100, 2) : null,
            'loss_count' => $rows->where('gross_profit', '<', 0)->count(),
            'has_activity' => $rows->isNotEmpty(),
        ];
    }

    private function filters(Request $request): array
    {
        $data = $request->validate([
            'period' => ['nullable', Rule::in(['last_30', 'last_60', 'last_90', 'this_month', 'previous_month', 'custom'])],
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:120'], 'category_id' => ['nullable', 'integer'], 'unit_id' => ['nullable', 'integer'],
            'performance_type' => ['nullable', Rule::in(['all', 'high-revenue', 'high-profit', 'low-margin', 'loss-making', 'high-return-rate'])],
            'sort' => ['nullable', Rule::in(['auto', 'qty_sold', 'net_sales', 'cogs', 'gross_profit', 'gross_margin', 'returned_qty', 'return_value', 'return_rate'])],
            'per_page' => ['nullable', Rule::in([10, 25, 50, 100])],
        ]);
        $period = $data['period'] ?? 'last_30';
        $now = now(config('app.timezone'));
        if ($period === 'custom') {
            if (empty($data['date_from']) || empty($data['date_to'])) throw ValidationException::withMessages(['date_from' => 'Select both custom dates.']);
            $from = Carbon::parse($data['date_from'], config('app.timezone'))->startOfDay();
            $to = Carbon::parse($data['date_to'], config('app.timezone'))->endOfDay();
            if ($to->lt($from)) throw ValidationException::withMessages(['date_to' => 'Date To must be after or equal to Date From.']);
            $label = 'custom';
        } else {
            [$from, $to, $label] = match ($period) {
                'last_60' => [$now->copy()->subDays(59)->startOfDay(), $now->copy()->endOfDay(), 'last-60-days'],
                'last_90' => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay(), 'last-90-days'],
                'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'this-month'],
                'previous_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth(), 'previous-month'],
                default => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay(), 'last-30-days'],
            };
        }

        return array_merge($data, [
            'period' => $period, 'date_from' => $data['date_from'] ?? null, 'date_to' => $data['date_to'] ?? null,
            'search' => $data['search'] ?? null, 'category_id' => $data['category_id'] ?? null, 'unit_id' => $data['unit_id'] ?? null,
            'performance_type' => $data['performance_type'] ?? 'all', 'sort' => $data['sort'] ?? 'auto', 'per_page' => (int) ($data['per_page'] ?? 10),
            'from' => $from, 'to' => $to, 'label' => $label,
        ]);
    }

    private function authorize(Request $request, bool $export = false): void
    {
        abort_unless(
            $this->permissions->allowsUser($request->user(), 'reports.view')
            && $this->permissions->allowsUser($request->user(), 'sales.view')
            && $this->permissions->allowsUser($request->user(), 'reports.sales_analytics')
            && $this->permissions->allowsUser($request->user(), 'reports.finance_reports'),
            403
        );
        if ($export) abort_unless($this->permissions->allowsUser($request->user(), 'reports.export'), 403);
    }
}
