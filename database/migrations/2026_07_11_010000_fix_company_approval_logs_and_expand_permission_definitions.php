<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_approval_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->text('note')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index(['company_id', 'changed_at'], 'cal_company_changed_index');
        });

        if (Schema::hasTable('company_approval_histories')) {
            DB::table('company_approval_histories')->orderBy('id')->each(function ($history) {
                DB::table('company_approval_logs')->updateOrInsert(
                    ['company_id' => $history->business_id, 'changed_at' => $history->changed_at, 'new_status' => $history->new_status],
                    [
                        'old_status' => $history->old_status,
                        'note' => $history->note,
                        'changed_by' => $history->changed_by,
                        'created_at' => $history->created_at ?? now(),
                        'updated_at' => $history->updated_at ?? now(),
                    ]
                );
            });
        }

        if (!Schema::hasColumn('permission_definitions', 'permission_type')) {
            Schema::table('permission_definitions', function (Blueprint $table) {
                $table->string('permission_type')->default('action')->after('module');
            });
        }

        DB::table('permission_definitions')->where('permission_key', 'like', '%.view')->update(['permission_type' => 'module']);
        DB::table('permission_definitions')->whereIn('permission_key', ['products.bulk_import', 'products.export', 'inventory.view_history', 'reports.export', 'accounting.export'])->update(['permission_type' => 'feature']);

        $definitions = [
            ['dashboard', 'dashboard.view', 'module', 'Enable Dashboard'],
            ['pos', 'pos.view', 'module', 'Enable POS'],
            ['products', 'products.barcode_scanning', 'feature', 'Barcode Scanning'], ['products', 'products.batch_tracking', 'feature', 'Batch Tracking'], ['products', 'products.expiry_tracking', 'feature', 'Expiry Tracking'], ['products', 'products.archive', 'action', 'Archive Products'], ['products', 'products.restore', 'action', 'Restore Products'],
            ['inventory', 'inventory.stock_transfer', 'feature', 'Stock Transfer'], ['inventory', 'inventory.damage_tracking', 'feature', 'Damage Tracking'], ['inventory', 'inventory.low_stock_alerts', 'feature', 'Low Stock Alerts'],
            ['accounting', 'accounting.trial_balance', 'feature', 'Trial Balance'], ['accounting', 'accounting.profit_loss', 'feature', 'Profit & Loss'], ['accounting', 'accounting.balance_sheet', 'feature', 'Balance Sheet'], ['accounting', 'accounting.customer_ledger', 'feature', 'Customer Ledger'], ['accounting', 'accounting.supplier_ledger', 'feature', 'Supplier Ledger'], ['accounting', 'accounting.reverse_journal', 'action', 'Reverse Journal Entries'],
            ['reports', 'reports.sales_analytics', 'feature', 'Sales Analytics'], ['reports', 'reports.inventory_analytics', 'feature', 'Inventory Analytics'], ['reports', 'reports.finance_reports', 'feature', 'Finance Reports'],
            ['pos', 'pos.split_payments', 'feature', 'Split Payments'], ['pos', 'pos.returns', 'feature', 'Returns'], ['pos', 'pos.register_sessions', 'feature', 'Register Sessions'], ['pos', 'pos.thermal_receipt', 'feature', 'Thermal Receipt'], ['pos', 'pos.reports', 'feature', 'POS Reports'],
            ['pos', 'pos.create_sale', 'action', 'Create Sale'], ['pos', 'pos.apply_discount', 'action', 'Apply Discount'], ['pos', 'pos.custom_price', 'action', 'Custom Price'], ['pos', 'pos.credit_sale', 'action', 'Credit Sale'], ['pos', 'pos.split_payment', 'action', 'Split Payment'], ['pos', 'pos.process_return', 'action', 'Process Return'], ['pos', 'pos.void_sale', 'action', 'Void Sale'], ['pos', 'pos.open_register', 'action', 'Open Register'], ['pos', 'pos.close_register', 'action', 'Close Register'], ['pos', 'pos.print_receipt', 'action', 'Print Receipt'],
            ['customers', 'customers.restore', 'action', 'Restore Customers'], ['orders', 'orders.print_invoice', 'action', 'Print Invoice'], ['deliveries', 'deliveries.record_collection', 'action', 'Record Collection'], ['invoices', 'invoices.void', 'action', 'Void Invoices'],
        ];

        foreach ($definitions as [$module, $key, $type, $label]) {
            DB::table('permission_definitions')->updateOrInsert(
                ['permission_key' => $key],
                ['module' => $module, 'permission_type' => $type, 'label' => $label, 'status' => 'active', 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_approval_logs');
        if (Schema::hasColumn('permission_definitions', 'permission_type')) {
            Schema::table('permission_definitions', fn (Blueprint $table) => $table->dropColumn('permission_type'));
        }
    }
};
