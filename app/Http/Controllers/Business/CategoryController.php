<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\StoreOrUpdateCategoryRequest;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::where('business_id', $request->user()->business_id)->where('type', 'Product');
        if ($request->input('status') === 'Archived' || $request->boolean('archived')) {
            $query->onlyTrashed();
        } elseif (in_array($request->input('status'), ['Active', 'Inactive'], true)) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('search')) $query->where('name', 'like', '%'.$request->input('search').'%');

        return view('business.categories.index', ['categories' => $query->latest()->paginate(15)->withQueryString()]);
    }

    public function create()
    {
        return view('business.categories.form');
    }

    public function store(StoreOrUpdateCategoryRequest $request)
    {
        $category = Category::create([...$request->validated(), 'business_id' => $request->user()->business_id, 'type' => 'Product', 'created_by' => $request->user()->id]);
        $this->audit('Category Created', $category);

        return redirect()->route('business.categories.index')->with('success', 'Category created successfully.');
    }

    public function show(Request $request, int $category)
    {
        $category = Category::withTrashed()
            ->where('business_id', $request->user()->business_id)
            ->where('type', 'Product')
            ->findOrFail($category);
        $this->ensureBusiness($category);
        return view('business.categories.show', compact('category'));
    }

    public function edit(Category $category)
    {
        $this->ensureBusiness($category);
        return view('business.categories.form', compact('category'));
    }

    public function update(StoreOrUpdateCategoryRequest $request, Category $category)
    {
        $this->ensureBusiness($category);
        $category->update($request->validated());
        $this->audit('Category Updated', $category);

        return redirect()->route('business.categories.index')->with('success', 'Category updated successfully.');
    }

    public function toggleStatus(Category $category)
    {
        $this->ensureBusiness($category);
        $category->update(['status' => $category->status === 'Active' ? 'Inactive' : 'Active']);
        $this->audit($category->status === 'Active' ? 'Category Activated' : 'Category Deactivated', $category);

        return back()->with('success', 'Category status updated.');
    }

    public function archive(Category $category)
    {
        $this->ensureBusiness($category);
        $category->update(['status' => 'Inactive']);
        $category->delete();
        $this->audit('Category Archived', $category);

        return back()->with('success', 'Record archived successfully.');
    }

    public function restore(Request $request, int $category)
    {
        $record = Category::withTrashed()->where('business_id', $request->user()->business_id)->where('type', 'Product')->findOrFail($category);
        $record->restore();
        $record->update(['status' => 'Active']);
        $this->audit('Category Restored', $record);

        return back()->with('success', 'Record restored successfully.');
    }

    public function destroy(Request $request, int $category)
    {
        $category = Category::withTrashed()
            ->where('business_id', $request->user()->business_id)
            ->where('type', 'Product')
            ->findOrFail($category);
        $this->ensureBusiness($category);
        if (Product::withTrashed()->where('business_id', $category->business_id)->where('category_id', $category->id)->exists()) {
            return back()->with('error', 'This category is assigned to one or more products and cannot be deleted.');
        }

        $this->audit('Category Permanently Deleted', $category);
        $category->forceDelete();

        return back()->with('success', 'Record permanently deleted.');
    }

    private function ensureBusiness(Category $category): void
    {
        abort_unless($category->business_id === auth()->user()->business_id && $category->type === 'Product', 404);
    }

    private function audit(string $action, Category $category): void
    {
        AuditLog::create(['business_id' => $category->business_id, 'module' => 'Categories', 'action' => $action, 'description' => $category->name, 'record_type' => Category::class, 'record_id' => $category->id, 'occurred_at' => now()]);
    }
}
