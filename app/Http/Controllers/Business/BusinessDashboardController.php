<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\CompanyPermissionService;
use App\Services\SubscriptionManagementAccessService;
use App\Services\SubscriptionLifecycleService;
use Illuminate\Support\Facades\DB;

class BusinessDashboardController extends Controller
{
    public function __invoke(
        CompanyPermissionService $companyPermissions,
        SubscriptionManagementAccessService $subscriptionAccess,
        SubscriptionLifecycleService $subscriptionLifecycle,
    )
    {
        $user = auth()->user();
        $businessId = $user->business_id;
        $business = Business::with('subscription.plan')->findOrFail($businessId);
        $subscriptionState = $subscriptionLifecycle->forBusiness($business, true);
        abort_if($user->role !== 'super_admin' && ! $subscriptionState['can_access_business'], 403);
        $canManageSubscription = $subscriptionAccess->canManage($user);
        // The dashboard reminder is a calculated access-state warning, not a
        // billing-management action. Keep it visible to the owner and to the
        // same authorised staff who may view subscription/access information,
        // while ordinary workspace users remain excluded.
        $canReceiveAccessExpiryAlert = $user->role === 'business_owner'
            || $companyPermissions->allowsUser($user, 'subscriptions.view', $business);
        $accessExpiryAlert = $canReceiveAccessExpiryAlert
            ? $subscriptionLifecycle->dashboardExpiryAlert($subscriptionState)
            : null;
        // Dashboard access is a core workspace capability. Operational cards
        // are shown only when at least one operational module is enabled.
        $hasOperationalAccess = collect([
            'products', 'inventory', 'suppliers', 'purchases', 'purchase_returns', 'customers',
            'sales', 'pos', 'sales_returns', 'deliveries', 'accounting',
            'expenses', 'reports', 'staff', 'audit_logs', 'settings',
        ])->contains(fn (string $module) => $companyPermissions->allowsUser(auth()->user(), $module.'.view'));

        if (!$hasOperationalAccess) {
            return view('business.dashboard', [
                'hasOperationalAccess' => false,
                'canManageSubscription' => $canManageSubscription,
                'subscription' => $canManageSubscription ? $subscriptionState['subscription'] : null,
                'subscriptionState' => $subscriptionState,
                'accessExpiryAlert' => $accessExpiryAlert,
            ]);
        }
        $can = fn (string $dashboardPermission, string $permission): bool => $companyPermissions->allowsUser($user, $dashboardPermission)
            && $companyPermissions->allowsUser($user, $permission);
        $dashboardPermissions = [
            'sales' => $can('dashboard.card_today_sales', 'sales.view'),
            'receivables' => $can('dashboard.card_receivables', 'sales.view') && $companyPermissions->allowsUser($user, 'customers.view'),
            'payables' => $can('dashboard.card_payables', 'purchases.view'),
            'monthly_profit' => $can('dashboard.card_monthly_profit', 'accounting.view'),
            'inventory' => $can('dashboard.card_inventory_value', 'inventory.view'),
            'customers' => $can('dashboard.card_total_customers', 'customers.view'),
            'suppliers' => $can('dashboard.card_total_suppliers', 'suppliers.view'),
            'purchases' => $can('dashboard.card_today_purchases', 'purchases.view'),
            'low_stock' => $can('dashboard.card_low_stock', 'inventory.view'),
            'deliveries' => $can('dashboard.card_pending_deliveries', 'deliveries.view'),
        ];
        $canViewSalesActivity = $dashboardPermissions['sales']
            && $companyPermissions->allowsUser($user, 'dashboard.widget_recent_sales');
        $saleDataRequired = $dashboardPermissions['sales'] || $dashboardPermissions['receivables']
            || $dashboardPermissions['monthly_profit'] || $canViewSalesActivity;
        $saleBase = $saleDataRequired
            ? Order::where('business_id', $businessId)->whereNotIn('status', ['Cancelled', 'Void', 'Returned'])
            : null;
        $monthlySales = $dashboardPermissions['monthly_profit']
            ? (clone $saleBase)->whereMonth('order_date', now()->month)->whereYear('order_date', now()->year)
            : null;
        $monthlyProfit = 0;
        if ($dashboardPermissions['monthly_profit']) {
            $expenses = Expense::where('business_id', $businessId)->whereMonth('expense_date', now()->month)->whereYear('expense_date', now()->year)->sum('amount');
            $monthlyCostOfSales = OrderItem::whereHas('order', fn ($query) => $query->where('business_id', $businessId)->whereNotIn('status', ['Cancelled', 'Void', 'Returned'])->whereMonth('order_date', now()->month)->whereYear('order_date', now()->year))->sum(DB::raw('quantity * purchase_cost_snapshot'));
            $monthlyProfit = (clone $monthlySales)->sum('grand_total') - $monthlyCostOfSales - $expenses;
        }
        $inventoryValue = $dashboardPermissions['inventory']
            ? Product::where('business_id', $businessId)->get()->sum(fn (Product $product) => (float) $product->stock_quantity * (float) ($product->purchase_cost ?: $product->wholesale_price))
            : 0;
        $salesTrend = [];
        if ($dashboardPermissions['sales']) {
            $trendStart = now()->subDays(6)->startOfDay();
            $salesByDay = (clone $saleBase)->whereBetween('order_date', [$trendStart->toDateString(), now()->toDateString()])->selectRaw('DATE(order_date) as day, COALESCE(SUM(grand_total), 0) as total')->groupBy('day')->pluck('total', 'day');
            $salesTrend = collect(range(0, 6))->map(function (int $offset) use ($trendStart, $salesByDay): array {
                $day = $trendStart->copy()->addDays($offset);
                return ['label' => $day->format('D'), 'date' => $day->toDateString(), 'total' => (float) ($salesByDay[$day->toDateString()] ?? 0)];
            })->all();
        }
        $activityModules = collect([
            $companyPermissions->allowsUser($user, 'products.view') ? ['Products', 'Categories', 'Units'] : [],
            $companyPermissions->allowsUser($user, 'inventory.view') ? ['Inventory'] : [],
            $companyPermissions->allowsUser($user, 'suppliers.view') ? ['Suppliers'] : [],
            $companyPermissions->allowsUser($user, 'purchases.view') ? ['Purchases'] : [],
            $companyPermissions->allowsUser($user, 'deliveries.view') ? ['Deliveries'] : [],
        ])->flatten()->unique()->values();
        $canViewActivity = $companyPermissions->allowsUser($user, 'audit_logs.view') && $activityModules->isNotEmpty();

        return view('business.dashboard', [
            'hasOperationalAccess' => true,
            'dashboardPermissions' => $dashboardPermissions,
            'todaySales' => $dashboardPermissions['sales'] ? (clone $saleBase)->whereDate('order_date', today())->sum('grand_total') : 0,
            // Receivables is the single authoritative outstanding-customer metric.
            'receivables' => $dashboardPermissions['receivables'] ? (clone $saleBase)->where('balance', '>', 0)->sum('balance') : 0,
            'payables' => $dashboardPermissions['payables'] ? Purchase::where('business_id', $businessId)->sum('balance') + Supplier::where('business_id', $businessId)->sum('opening_balance') : 0,
            'inventoryValue' => $inventoryValue,
            'suppliersCount' => $dashboardPermissions['suppliers'] ? Supplier::where('business_id', $businessId)->count() : 0,
            'pendingDeliveries' => $dashboardPermissions['deliveries'] ? Delivery::where('business_id', $businessId)->whereIn('status', ['Pending', 'Assigned', 'Out For Delivery'])->count() : 0,
            'customersCount' => $dashboardPermissions['customers'] ? Customer::where('business_id', $businessId)->count() : 0,
            'todayPurchases' => $dashboardPermissions['purchases'] ? Purchase::where('business_id', $businessId)->whereDate('purchase_date', today())->sum('grand_total') : 0,
            'lowStock' => $dashboardPermissions['low_stock'] ? Product::where('business_id', $businessId)->whereColumn('stock_quantity', '<=', 'low_stock_alert_qty')->count() : 0,
            'monthlyProfit' => $monthlyProfit,
            'salesTrend' => $salesTrend,
            'canManageSubscription' => $canManageSubscription,
            'subscription' => $canManageSubscription ? $subscriptionState['subscription'] : null,
            'subscriptionState' => $subscriptionState,
            'accessExpiryAlert' => $accessExpiryAlert,
            'recentOrders' => $canViewSalesActivity ? Order::with('customer')->where('business_id', $businessId)->latest()->take(5)->get() : collect(),
            'recentActivity' => $canViewActivity ? AuditLog::where('business_id', $businessId)->whereIn('module', $activityModules)->latest('occurred_at')->take(5)->get() : collect(),
            'canViewSalesActivity' => $canViewSalesActivity,
            'canViewActivity' => $canViewActivity,
        ]);
    }
}
