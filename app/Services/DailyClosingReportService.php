<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PosRegister;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\SalesReturn;
use App\Models\SupplierPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DailyClosingReportService
{
    public function __construct(
        private readonly ProfitabilityBreakdownService $profitability,
        private readonly PosSaleService $pos,
    ) {}

    /**
     * Builds a read-only daily report from the same sources used by the
     * existing financial reports and POS reconciliation. No totals are posted
     * or persisted by this service.
     *
     * @param array{purchases?: bool, pos?: bool} $sections
     */
    public function forDate(int $businessId, Carbon $date, array $sections = []): array
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();
        $day = $date->toDateString();
        $purchasesVisible = (bool) ($sections['purchases'] ?? false);
        $posVisible = (bool) ($sections['pos'] ?? false);

        // This is the canonical P&L formula shared with Reports and Khata.
        $profitability = $this->profitability->forPeriod($businessId, [
            'from' => $start,
            'to' => $end,
            'status' => null,
            'customer_id' => null,
            'product_id' => null,
        ]);

        $sales = Order::query()
            ->where('business_id', $businessId)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotIn('status', ['Cancelled', 'Void']);

        $saleCount = (clone $sales)->count();
        $saleValue = round((float) (clone $sales)
            ->selectRaw('COALESCE(SUM(COALESCE(NULLIF(grand_total, 0), total, 0)), 0) AS total')
            ->value('total'), 2);
        $todaySalePayments = $this->paymentLinesForSales($businessId, $start, $end, $day);
        $paymentBreakdown = $this->breakdown($todaySalePayments);
        $paidSales = round((float) $todaySalePayments->sum('amount'), 2);

        // Payments for an invoice created before this day are collections, not
        // new sales revenue. Keeping them separate prevents revenue inflation.
        $customerCollections = Payment::query()
            ->where('business_id', $businessId)
            ->whereDate('payment_date', $day)
            ->whereHas('order', fn ($orders) => $orders
                ->where('created_at', '<', $start)
                ->whereNotIn('status', ['Cancelled', 'Void']))
            ->selectRaw('COALESCE(SUM(amount), 0) AS total')
            ->value('total');

        $report = [
            'date' => $start,
            'start' => $start,
            'end' => $end,
            'sales' => [
                'invoice_count' => $saleCount,
                'gross_sales' => $profitability['gross_sales'],
                // These are explanatory only: line discounts are already
                // included in saved line totals and are never subtracted again.
                'line_discounts_included' => $profitability['line_discounts_included'],
                'invoice_discounts' => $profitability['invoice_discounts'],
                'total_discounts' => round($profitability['line_discounts_included'] + $profitability['invoice_discounts'], 2),
                'sales_returns' => $profitability['sales_returns'],
                'net_sales' => $profitability['net_sales'],
                'paid_sales' => $paidSales,
                'credit_sales' => round(max(0, $saleValue - $paidSales), 2),
            ],
            'payments' => [
                'breakdown' => $paymentBreakdown,
                'cash_sales' => (float) ($paymentBreakdown['Cash'] ?? 0),
                'customer_collections' => round((float) $customerCollections, 2),
            ],
            'expenses' => [
                'total' => $profitability['expenses'],
                'categories' => $profitability['expense_categories'],
            ],
            'profitability' => $profitability,
            'purchases' => $purchasesVisible ? $this->purchases($businessId, $start, $end, $day) : null,
            'registers' => $posVisible ? $this->registers($businessId, $start, $end) : null,
        ];

        return $report + [
            'status' => ($report['registers']['open_count'] ?? 0) > 0 ? 'Open' : 'Reconciled',
        ];
    }

    private function paymentLinesForSales(int $businessId, Carbon $start, Carbon $end, string $day): Collection
    {
        return Payment::query()
            ->where('business_id', $businessId)
            ->whereDate('payment_date', $day)
            ->whereHas('order', fn ($orders) => $orders
                ->whereBetween('created_at', [$start, $end])
                ->whereNotIn('status', ['Cancelled', 'Void']))
            ->get(['method', 'amount']);
    }

    /** @return array<string, float> */
    private function breakdown(Collection $payments): array
    {
        return $payments
            ->groupBy(fn (Payment $payment) => filled($payment->method) ? trim((string) $payment->method) : 'Other')
            ->map(fn (Collection $lines) => round((float) $lines->sum('amount'), 2))
            ->sortKeys()
            ->all();
    }

    private function purchases(int $businessId, Carbon $start, Carbon $end, string $day): array
    {
        $purchases = Purchase::query()
            ->where('business_id', $businessId)
            ->whereBetween('purchase_date', [$start, $end])
            ->whereNotIn('status', ['Draft', 'Cancelled']);

        $purchaseReturns = PurchaseReturn::query()
            ->where('business_id', $businessId)
            ->whereDate('return_date', $day);

        $supplierPayments = SupplierPayment::query()
            ->where('business_id', $businessId)
            ->whereDate('payment_date', $day);

        $grns = GoodsReceipt::query()
            ->where('business_id', $businessId)
            ->whereBetween('received_at', [$start, $end]);

        return [
            'count' => (clone $purchases)->count(),
            // Purchase totals are stored payable totals, so bonus/free units
            // have no monetary effect here.
            'amount' => round((float) (clone $purchases)->sum('grand_total'), 2),
            'paid_amount' => round((float) (clone $purchases)->sum('paid_amount'), 2),
            'purchase_returns' => round((float) (clone $purchaseReturns)->sum('total_amount'), 2),
            'supplier_payments' => round((float) (clone $supplierPayments)->sum('amount'), 2),
            'grn_count' => (clone $grns)->count(),
        ];
    }

    private function registers(int $businessId, Carbon $start, Carbon $end): array
    {
        $registers = PosRegister::query()
            ->where('business_id', $businessId)
            ->whereBetween('opened_at', [$start, $end])
            ->with('user:id,name')
            ->orderBy('opened_at')
            ->get();

        $rows = $registers->map(function (PosRegister $register) use ($businessId): array {
            $summary = $register->status === 'Closed'
                ? [
                    'opening_cash' => (float) $register->opening_cash,
                    'cash_sales' => (float) $register->cash_sales,
                    'cash_refunds' => (float) $register->cash_refunds,
                    'cash_in' => (float) $register->cash_in,
                    'cash_out' => (float) $register->cash_out,
                    'expected_cash' => (float) $register->expected_cash,
                ]
                : $this->pos->reconciliation($register, $businessId, (int) $register->user_id);

            $actual = $register->status === 'Closed' && $register->closing_cash !== null
                ? (float) $register->closing_cash
                : null;
            $variance = $actual === null ? null : round($actual - (float) $summary['expected_cash'], 2);

            return [
                'id' => $register->id,
                'cashier' => $register->user?->name ?: 'Unknown cashier',
                'status' => $register->status,
                'opened_at' => $register->opened_at,
                'closed_at' => $register->closed_at,
                'opening_cash' => round((float) $summary['opening_cash'], 2),
                'cash_sales' => round((float) $summary['cash_sales'], 2),
                'cash_refunds' => round((float) $summary['cash_refunds'], 2),
                'cash_in' => round((float) $summary['cash_in'], 2),
                'cash_out' => round((float) $summary['cash_out'], 2),
                'expected_cash' => round((float) $summary['expected_cash'], 2),
                'actual_cash' => $actual,
                'variance' => $variance,
            ];
        })->values();

        $closed = $rows->where('status', 'Closed');

        return [
            'rows' => $rows,
            'open_count' => $rows->where('status', 'Open')->count(),
            'opening_cash' => round((float) $rows->sum('opening_cash'), 2),
            'cash_sales' => round((float) $rows->sum('cash_sales'), 2),
            'cash_refunds' => round((float) $rows->sum('cash_refunds'), 2),
            'cash_in' => round((float) $rows->sum('cash_in'), 2),
            'cash_out' => round((float) $rows->sum('cash_out'), 2),
            'expected_cash' => round((float) $rows->sum('expected_cash'), 2),
            'actual_cash' => $closed->isEmpty() ? null : round((float) $closed->sum('actual_cash'), 2),
            'variance' => $closed->isEmpty() ? null : round((float) $closed->sum('variance'), 2),
        ];
    }
}
