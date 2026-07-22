<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Services\BusinessActivityService;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(private BusinessActivityService $activity) {}

    public function index()
    {
        $businessId = auth()->user()->business_id;
        $filters = request()->validate([
            'category' => ['nullable', 'string', 'max:100'],
            'amount_from' => ['nullable', 'integer', 'min:0'],
            'amount_to' => ['nullable', 'integer', 'min:0'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        if (!empty($filters['date_from']) && !empty($filters['date_to']) && $filters['date_to'] < $filters['date_from']) {
            return back()->withErrors(['date_to' => 'Date To must be after or equal to Date From.'])->withInput();
        }

        $query = Expense::where('business_id', $businessId)
            ->when($filters['category'] ?? null, fn ($q, $value) => $q->where('category', $value))
            ->when($filters['amount_from'] ?? null, fn ($q, $value) => $q->where('amount', '>=', $value))
            ->when($filters['amount_to'] ?? null, fn ($q, $value) => $q->where('amount', '<=', $value))
            ->when($filters['date_from'] ?? null, fn ($q, $value) => $q->whereDate('expense_date', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($q, $value) => $q->whereDate('expense_date', '<=', $value))
            ->when($filters['month'] ?? null, fn ($q, $value) => $q->whereMonth('expense_date', $value))
            ->when($filters['year'] ?? null, fn ($q, $value) => $q->whereYear('expense_date', $value))
            ->when($filters['search'] ?? null, fn ($q, $value) => $q->where(fn ($inner) => $inner->where('title', 'like', "%{$value}%")->orWhere('description', 'like', "%{$value}%")));

        return view('business.expenses.index', [
            'expenses' => (clone $query)->latest('expense_date')->paginate(12)->withQueryString(),
            'totalExpenses' => (clone $query)->sum('amount'),
            'monthlySummary' => Expense::where('business_id', $businessId)
                ->selectRaw('YEAR(expense_date) as year, MONTH(expense_date) as month, SUM(amount) as total')
                ->groupByRaw('YEAR(expense_date), MONTH(expense_date)')
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->take(6)
                ->get(),
            'categories' => ['Salary', 'Rent', 'Transport', 'Electricity', 'Marketing', 'Purchase', 'Maintenance', 'Other'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['category' => ['required', 'in:Salary,Rent,Transport,Electricity,Marketing,Purchase,Maintenance,Other'], 'title' => ['required', 'max:255'], 'amount' => ['required', 'integer', 'min:0'], 'date' => ['nullable', 'date'], 'expense_date' => ['nullable', 'date'], 'description' => ['nullable']]);
        $data['date'] = $data['expense_date'] ?? $data['date'] ?? today();
        $data['expense_date'] = $data['date'];
        $expense = Expense::create($data + ['business_id' => auth()->user()->business_id]);
        $this->activity->record($expense->business_id, 'Expenses', 'Expense recorded: '.$expense->title, $expense->id, null, ['amount' => $expense->amount, 'category' => $expense->category]);
        return back()->with('success', 'Expense saved.');
    }

    public function destroy(Expense $expense)
    {
        abort_unless($expense->business_id === auth()->user()->business_id, 403);
        $old = ['title' => $expense->title, 'amount' => $expense->amount, 'category' => $expense->category];
        $expense->delete();
        $this->activity->record((int) auth()->user()->business_id, 'Expenses', 'Expense deleted: '.$old['title'], $expense->id, $old);
        return back()->with('success', 'Expense deleted.');
    }
}
