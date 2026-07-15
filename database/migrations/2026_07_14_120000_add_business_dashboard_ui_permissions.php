<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $permissions = [
            ['dashboard.quick_add_product', 'Show Add Product button'], ['dashboard.quick_create_sale', 'Show Create Sale button'], ['dashboard.quick_pos_sale', 'Show New POS Sale button'], ['dashboard.quick_add_customer', 'Show Add Customer button'], ['dashboard.quick_new_purchase', 'Show New Purchase button'],
            ['dashboard.card_total_sales', 'Show Total Sales card'], ['dashboard.card_today_sales', 'Show Today Sales card'], ['dashboard.card_inventory_value', 'Show Inventory Value card'], ['dashboard.card_receivables', 'Show Receivables card'], ['dashboard.card_payables', 'Show Payables card'], ['dashboard.card_profit_loss', 'Show Profit / Loss card'], ['dashboard.card_low_stock', 'Show Low Stock card'], ['dashboard.card_total_customers', 'Show Total Customers card'], ['dashboard.card_total_suppliers', 'Show Total Suppliers card'], ['dashboard.card_pending_deliveries', 'Show Pending Deliveries card'], ['dashboard.card_pending_customer_payments', 'Show Pending Customer Payments card'], ['dashboard.card_pending_supplier_payments', 'Show Pending Supplier Payments card'], ['dashboard.card_today_purchases', 'Show Today Purchases card'], ['dashboard.card_monthly_profit', 'Show Monthly Profit card'], ['dashboard.widget_recent_sales', 'Show Recent Sales widget'], ['dashboard.widget_low_stock_alerts', 'Show Low Stock Alerts widget'],
        ];
        foreach ($permissions as [$key, $label]) {
            DB::table('permission_definitions')->updateOrInsert(['permission_key' => $key], ['module' => 'dashboard', 'permission_type' => 'feature', 'label' => $label, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('company_permissions')->where('permission_key', 'dashboard.view')->where('allowed', true)->get()->each(function (object $permission) use ($permissions): void {
            foreach ($permissions as [$key]) DB::table('company_permissions')->updateOrInsert(['company_id' => $permission->company_id, 'permission_key' => $key], ['allowed' => true, 'assigned_by' => $permission->assigned_by, 'created_at' => now(), 'updated_at' => now()]);
        });
        Cache::forget('tradeflow.permission-definition-keys');
    }

    public function down(): void
    {
        DB::table('company_permissions')->where('permission_key', 'like', 'dashboard.%')->where('permission_key', '!=', 'dashboard.view')->delete();
        DB::table('permission_definitions')->where('module', 'dashboard')->where('permission_key', '!=', 'dashboard.view')->delete();
        Cache::forget('tradeflow.permission-definition-keys');
    }
};
