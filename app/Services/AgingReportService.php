<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\BalanceAdjustment;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\SalesReturn;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Read-only receivable/payable aging over the transaction balances already
 * maintained by Sales and Purchases. This deliberately does not create a new
 * balance or allocate an unlinked ledger payment to an arbitrary document.
 */
class AgingReportService
{
    public const BUCKETS = ['current', 'days_1_30', 'days_31_60', 'days_61_90', 'days_90_plus'];

    public function customerReport(int $businessId, array $filters): array
    {
        $asOf = $this->asOf($filters);
        $query = $this->customerAggregateQuery($businessId, $filters, $asOf);

        return $this->withUnallocatedCurrent($this->reportFrom($query, $filters, 'GREATEST(0, COALESCE(orders.balance, 0) - COALESCE(return_credits.credits, 0))'), $businessId, $filters, $asOf, 'customer');
    }

    public function supplierReport(int $businessId, array $filters): array
    {
        $asOf = $this->asOf($filters);
        $query = $this->supplierAggregateQuery($businessId, $filters, $asOf);

        return $this->withUnallocatedCurrent($this->reportFrom($query, $filters, 'COALESCE(purchases.balance, 0)'), $businessId, $filters, $asOf, 'supplier');
    }

    public function customerDetails(int $businessId, Customer $customer, array $filters): Collection
    {
        $asOf = $this->asOf($filters);
        $returns = $this->customerReturnCredits($businessId, $asOf);
        $agingDate = 'COALESCE(invoices.due_date, DATE(orders.order_date), DATE(orders.created_at))';
        $outstanding = 'GREATEST(0, COALESCE(orders.balance, 0) - COALESCE(return_credits.credits, 0))';

        return Order::query()
            ->leftJoin('invoices', 'invoices.order_id', '=', 'orders.id')
            ->leftJoinSub($returns, 'return_credits', fn ($join) => $join->on('return_credits.order_id', '=', 'orders.id'))
            ->where('orders.business_id', $businessId)
            ->where('orders.customer_id', $customer->id)
            ->whereNotIn('orders.status', ['Cancelled', 'Void'])
            ->whereDate('orders.order_date', '<=', $asOf->toDateString())
            ->selectRaw('orders.id, orders.order_number, orders.order_date, invoices.invoice_number, invoices.due_date, COALESCE(orders.grand_total, orders.total, 0) as original_amount, COALESCE(orders.paid_amount, 0) as paid_amount, COALESCE(return_credits.credits, 0) as credits, '.$outstanding.' as outstanding, '.$agingDate.' as aging_date')
            ->whereRaw($outstanding.' > 0')
            ->orderByRaw($agingDate.' asc')
            ->get()
            ->map(fn ($row) => $this->detailRow($row, $asOf));
    }

    public function supplierDetails(int $businessId, Supplier $supplier, array $filters): Collection
    {
        $asOf = $this->asOf($filters);
        $returns = $this->supplierReturnCredits($businessId, $asOf);
        $agingDate = 'COALESCE(purchases.due_date, DATE(purchases.purchase_date), DATE(purchases.created_at))';

        return Purchase::query()
            ->leftJoinSub($returns, 'return_credits', fn ($join) => $join->on('return_credits.purchase_id', '=', 'purchases.id'))
            ->where('purchases.business_id', $businessId)
            ->where('purchases.supplier_id', $supplier->id)
            ->where('purchases.balance', '>', 0)
            ->whereNotIn('purchases.status', ['Draft', 'Cancelled'])
            ->whereDate('purchases.purchase_date', '<=', $asOf->toDateString())
            ->selectRaw('purchases.id, purchases.purchase_number, purchases.supplier_invoice_number, purchases.purchase_date, purchases.due_date, COALESCE(purchases.grand_total, 0) as original_amount, COALESCE(purchases.paid_amount, 0) as paid_amount, COALESCE(return_credits.credits, 0) as credits, COALESCE(purchases.balance, 0) as outstanding, '.$agingDate.' as aging_date')
            ->orderByRaw($agingDate.' asc')
            ->get()
            ->map(fn ($row) => $this->detailRow($row, $asOf));
    }

    public function bucketFor(?Carbon $dueDate, Carbon $asOf): string
    {
        if (! $dueDate || $dueDate->gte($asOf->copy()->startOfDay())) {
            return 'current';
        }

        return match (true) {
            $dueDate->diffInDays($asOf, false) <= 30 => 'days_1_30',
            $dueDate->diffInDays($asOf, false) <= 60 => 'days_31_60',
            $dueDate->diffInDays($asOf, false) <= 90 => 'days_61_90',
            default => 'days_90_plus',
        };
    }

    private function customerAggregateQuery(int $businessId, array $filters, Carbon $asOf): Builder
    {
        $returns = $this->customerReturnCredits($businessId, $asOf);
        $agingDate = 'COALESCE(invoices.due_date, DATE(orders.order_date), DATE(orders.created_at))';
        $outstanding = 'GREATEST(0, COALESCE(orders.balance, 0) - COALESCE(return_credits.credits, 0))';
        $query = Order::query()
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->leftJoin('invoices', 'invoices.order_id', '=', 'orders.id')
            ->leftJoinSub($returns, 'return_credits', fn ($join) => $join->on('return_credits.order_id', '=', 'orders.id'))
            ->where('orders.business_id', $businessId)
            ->whereNotNull('orders.customer_id')
            ->whereNotIn('orders.status', ['Cancelled', 'Void'])
            ->whereDate('orders.order_date', '<=', $asOf->toDateString())
            ->when($filters['party_id'] ?? null, fn ($q, $id) => $q->where('orders.customer_id', $id))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($match) => $match->where('customers.name', 'like', '%'.$search.'%')->orWhere('customers.business_name', 'like', '%'.$search.'%')))
            ->selectRaw("customers.id as party_id, COALESCE(NULLIF(customers.name, ''), NULLIF(customers.business_name, ''), 'Customer') as party_name")
            ->groupBy('customers.id', 'customers.name', 'customers.business_name');

        return $this->addAggregateColumns($query, $outstanding, $agingDate, $asOf);
    }

    private function supplierAggregateQuery(int $businessId, array $filters, Carbon $asOf): Builder
    {
        $agingDate = 'COALESCE(purchases.due_date, DATE(purchases.purchase_date), DATE(purchases.created_at))';
        $outstanding = 'COALESCE(purchases.balance, 0)';
        $query = Supplier::withTrashed()
            ->leftJoin('purchases', function ($join) use ($businessId, $asOf): void {
                $join->on('purchases.supplier_id', '=', 'suppliers.id')
                    ->where('purchases.business_id', '=', $businessId)
                    ->where('purchases.balance', '>', 0)
                    ->whereNotIn('purchases.status', ['Draft', 'Cancelled'])
                    ->whereDate('purchases.purchase_date', '<=', $asOf->toDateString());
            })
            ->where('suppliers.business_id', $businessId)
            ->when($filters['party_id'] ?? null, fn ($q, $id) => $q->where('suppliers.id', $id))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($match) => $match->where('suppliers.supplier_name', 'like', '%'.$search.'%')->orWhere('suppliers.company_name', 'like', '%'.$search.'%')))
            ->selectRaw("suppliers.id as party_id, COALESCE(NULLIF(suppliers.company_name, ''), NULLIF(suppliers.supplier_name, ''), 'Supplier') as party_name")
            ->groupBy('suppliers.id', 'suppliers.supplier_name', 'suppliers.company_name');

        return $this->addAggregateColumns($query, $outstanding, $agingDate, $asOf);
    }

    private function addAggregateColumns(Builder $query, string $outstanding, string $agingDate, Carbon $asOf): Builder
    {
        $date = $asOf->toDateString();
        $query->selectRaw('SUM('.$outstanding.') as total_outstanding')
            ->selectRaw('SUM(CASE WHEN '.$agingDate.' >= ? THEN '.$outstanding.' ELSE 0 END) as current', [$date])
            ->selectRaw('SUM(CASE WHEN DATEDIFF(?, '.$agingDate.') BETWEEN 1 AND 30 THEN '.$outstanding.' ELSE 0 END) as days_1_30', [$date])
            ->selectRaw('SUM(CASE WHEN DATEDIFF(?, '.$agingDate.') BETWEEN 31 AND 60 THEN '.$outstanding.' ELSE 0 END) as days_31_60', [$date])
            ->selectRaw('SUM(CASE WHEN DATEDIFF(?, '.$agingDate.') BETWEEN 61 AND 90 THEN '.$outstanding.' ELSE 0 END) as days_61_90', [$date])
            ->selectRaw('SUM(CASE WHEN DATEDIFF(?, '.$agingDate.') > 90 THEN '.$outstanding.' ELSE 0 END) as days_90_plus', [$date])
            ->selectRaw('MIN('.$agingDate.') as oldest_due')
            ->havingRaw('SUM('.$outstanding.') > 0');

        return $query;
    }

    private function reportFrom(Builder $query, array $filters, string $outstanding): array
    {
        $minimum = (float) ($filters['minimum_outstanding'] ?? 0);
        if ($minimum > 0) {
            $query->havingRaw('SUM('.$outstanding.') >= ?', [$minimum]);
        }
        if (in_array($filters['bucket'] ?? null, self::BUCKETS, true)) {
            $query->having($filters['bucket'], '>', 0);
        }

        $summaryRows = (clone $query)->get();
        $summary = ['total_outstanding' => 0.0] + array_fill_keys(self::BUCKETS, 0.0);
        foreach ($summaryRows as $row) {
            $summary['total_outstanding'] += (float) $row->total_outstanding;
            foreach (self::BUCKETS as $bucket) $summary[$bucket] += (float) $row->{$bucket};
        }

        return [
            'rows' => $query->orderByDesc('total_outstanding')->paginate(10)->withQueryString(),
            'summary' => array_map(fn ($value) => round((float) $value, 2), $summary),
        ];
    }

    /**
     * Opening balances and correction journals have no invoice/purchase due
     * date. Keep them in a transparent Current/Unallocated bucket rather than
     * inventing an allocation to an unrelated document.
     */
    private function withUnallocatedCurrent(array $report, int $businessId, array $filters, Carbon $asOf, string $partyType): array
    {
        $partyId = $filters['party_id'] ?? null;
        $opening = $partyType === 'customer'
            ? Customer::query()->where('business_id', $businessId)->when($partyId, fn ($q, $id) => $q->whereKey($id))->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($match) => $match->where('name', 'like', '%'.$search.'%')->orWhere('business_name', 'like', '%'.$search.'%')))->sum('opening_balance')
            : Supplier::withTrashed()->where('business_id', $businessId)->when($partyId, fn ($q, $id) => $q->whereKey($id))->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($match) => $match->where('supplier_name', 'like', '%'.$search.'%')->orWhere('company_name', 'like', '%'.$search.'%')))->sum('opening_balance');
        $adjustmentQuery = BalanceAdjustment::query()->where('business_id', $businessId)->where('party_type', $partyType)
            ->when($partyId, fn ($q, $id) => $q->where('party_id', $id))
            ->whereDate('created_at', '<=', $asOf->toDateString());
        if (filled($filters['search'] ?? null)) {
            $partyIds = $partyType === 'customer'
                ? Customer::query()->where('business_id', $businessId)->where(fn ($q) => $q->where('name', 'like', '%'.$filters['search'].'%')->orWhere('business_name', 'like', '%'.$filters['search'].'%'))->pluck('id')
                : Supplier::withTrashed()->where('business_id', $businessId)->where(fn ($q) => $q->where('supplier_name', 'like', '%'.$filters['search'].'%')->orWhere('company_name', 'like', '%'.$filters['search'].'%'))->pluck('id');
            $adjustmentQuery->whereIn('party_id', $partyIds);
        }
        $adjustments = $adjustmentQuery->get()
            ->sum(fn (BalanceAdjustment $item) => str_starts_with($item->adjustment_type, 'increase') ? (float) $item->amount : -(float) $item->amount);
        $unallocated = round((float) $opening + (float) $adjustments, 2);
        $report['summary']['unallocated_current'] = $unallocated;
        $report['summary']['current'] = round((float) $report['summary']['current'] + $unallocated, 2);
        $report['summary']['total_outstanding'] = round((float) $report['summary']['total_outstanding'] + $unallocated, 2);
        return $report;
    }

    private function customerReturnCredits(int $businessId, Carbon $asOf): Builder
    {
        return SalesReturn::query()
            ->selectRaw('order_id, SUM(refund_amount) as credits')
            ->where('business_id', $businessId)
            ->whereDate('returned_at', '<=', $asOf->toDateString())
            ->groupBy('order_id');
    }

    private function supplierReturnCredits(int $businessId, Carbon $asOf): Builder
    {
        return PurchaseReturn::query()
            ->selectRaw('purchase_id, SUM(total_amount) as credits')
            ->where('business_id', $businessId)
            ->whereDate('return_date', '<=', $asOf->toDateString())
            ->groupBy('purchase_id');
    }

    private function detailRow(object $row, Carbon $asOf): array
    {
        $agingDate = Carbon::parse($row->aging_date, config('app.timezone'))->startOfDay();
        $daysOverdue = max(0, $agingDate->diffInDays($asOf, false));

        return [
            'reference' => $row->invoice_number ?? $row->supplier_invoice_number ?? $row->order_number ?? $row->purchase_number,
            'date' => $row->order_date ?? $row->purchase_date,
            'due_date' => $row->due_date,
            'aging_date' => $agingDate,
            'original_amount' => (float) $row->original_amount,
            'paid_amount' => (float) $row->paid_amount,
            'credits' => (float) ($row->credits ?? 0),
            'outstanding' => (float) $row->outstanding,
            'days_overdue' => $daysOverdue,
            'bucket' => $this->bucketFor($agingDate, $asOf),
        ];
    }

    private function asOf(array $filters): Carbon
    {
        return Carbon::parse($filters['as_of'] ?? now(config('app.timezone'))->toDateString(), config('app.timezone'))->startOfDay();
    }
}
