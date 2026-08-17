<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Unit;
use App\Services\BusinessActivityService;
use App\Services\CompanyPermissionService;
use App\Services\ReorderSuggestionService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReorderSuggestionController extends Controller
{
    public function __construct(
        private ReorderSuggestionService $suggestions,
        private BusinessActivityService $activity,
        private CompanyPermissionService $permissions,
    ) {}

    public function index(Request $request)
    {
        $businessId = (int) $request->user()->business_id;
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer'],
            'unit_id' => ['nullable', 'integer'],
            'supplier_id' => ['nullable', 'integer'],
            'stock_status' => ['nullable', Rule::in(['all', 'below_reorder', 'out_of_stock'])],
            'per_page' => ['nullable', Rule::in([10, 25, 50, 100])],
        ]);
        $filters['stock_status'] ??= 'all';
        $filters['per_page'] ??= 10;
        $rows = $this->suggestions->suggestions($businessId, $filters);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, (int) $filters['per_page'])->values(),
            $rows->count(),
            (int) $filters['per_page'],
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('business.inventory.purchase-suggestions.index', [
            'suggestions' => $paginator,
            'summary' => [
                'products' => $rows->count(),
                'units' => $rows->sum('suggested_quantity'),
                'estimated_cost' => $rows->whereNotNull('estimated_cost')->sum('estimated_cost'),
                'has_estimated_cost' => $rows->contains(fn (object $row) => $row->estimated_cost !== null),
                'out_of_stock' => $rows->where('status', 'Out of Stock')->count(),
            ],
            'categories' => Category::query()->where('business_id', $businessId)->where('type', 'Product')->orderBy('name')->get(['id', 'name']),
            'units' => Unit::query()->where('business_id', $businessId)->where('status', 'Active')->orderBy('unit_name')->get(['id', 'unit_name', 'short_code']),
            'suppliers' => $rows->filter(fn (object $row) => $row->supplier_id)->map(fn (object $row) => (object) ['id' => $row->supplier_id, 'name' => $row->supplier])->unique('id')->sortBy('name')->values(),
            'filters' => $filters,
            'canCreatePurchase' => $this->permissions->allowsUser($request->user(), 'purchases.create'),
            'canConfigure' => $this->permissions->allowsUser($request->user(), 'inventory.low_stock_alerts'),
        ]);
    }

    public function settings(Request $request)
    {
        $businessId = (int) $request->user()->business_id;
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer'],
        ]);
        $products = Product::query()->with(['category:id,name', 'unitRecord:id,unit_name,short_code'])
            ->where('business_id', $businessId)->where('status', 'Active')
            ->when(filled($filters['search'] ?? null), fn ($query) => $query->where('name', 'like', '%'.trim($filters['search']).'%'))
            ->when(filled($filters['category_id'] ?? null), fn ($query) => $query->where('category_id', $filters['category_id']) )
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('business.inventory.purchase-suggestions.settings', [
            'products' => $products,
            'filters' => $filters,
            'categories' => Category::query()->where('business_id', $businessId)->where('type', 'Product')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $businessId = (int) $request->user()->business_id;
        $data = $request->validate([
            'products' => ['required', 'array', 'min:1'],
            'products.*.id' => ['required', 'integer'],
            'products.*.reorder_level' => ['required', 'integer', 'min:0'],
            'products.*.target_stock_level' => ['required', 'integer', 'min:0'],
        ]);

        collect($data['products'])->each(function (array $row): void {
            if ((float) $row['target_stock_level'] + 0.0001 < (float) $row['reorder_level']) {
                throw ValidationException::withMessages([
                    'products' => 'Target stock must be greater than or equal to its reorder level.',
                ]);
            }
        });

        $changed = [];
        DB::transaction(function () use ($data, $businessId, &$changed): void {
            foreach ($data['products'] as $row) {
                $product = Product::query()->where('business_id', $businessId)->lockForUpdate()->findOrFail($row['id']);
                $reorder = round((float) $row['reorder_level'], 3);
                $target = round((float) $row['target_stock_level'], 3);
                if ((float) $product->low_stock_alert_qty === $reorder && (float) $product->target_stock_level === $target) continue;

                $old = ['reorder_level' => (float) $product->low_stock_alert_qty, 'target_stock_level' => (float) $product->target_stock_level];
                $product->update(['low_stock_alert_qty' => $reorder, 'target_stock_level' => $target]);
                Inventory::updateOrCreate(
                    ['business_id' => $businessId, 'product_id' => $product->id],
                    ['available_stock' => $product->stock_quantity, 'low_stock_alert' => $reorder]
                );
                $changed[] = [$product, $old, ['reorder_level' => $reorder, 'target_stock_level' => $target]];
            }
        });

        foreach ($changed as [$product, $old, $new]) {
            $this->activity->record($businessId, 'Inventory', 'Reorder settings updated for '.$product->name.'.', $product->id, $old, $new);
        }

        return redirect()->route('business.inventory.purchase-suggestions.settings')->with('success', $changed === [] ? 'No reorder settings changed.' : count($changed).' reorder setting(s) updated.');
    }
}
