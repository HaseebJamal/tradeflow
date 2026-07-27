<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            if (!Schema::hasColumn('purchases', 'supplier_invoice_date')) $table->date('supplier_invoice_date')->nullable()->after('supplier_invoice_number');
            if (!Schema::hasColumn('purchases', 'supplier_reference')) $table->string('supplier_reference')->nullable()->after('supplier_invoice_date');
            if (!Schema::hasColumn('purchases', 'purchase_order_reference')) $table->string('purchase_order_reference')->nullable()->after('supplier_reference');
            if (!Schema::hasColumn('purchases', 'payment_terms')) $table->string('payment_terms')->nullable()->after('purchase_date');
            if (!Schema::hasColumn('purchases', 'due_date')) $table->date('due_date')->nullable()->after('payment_terms');
            if (!Schema::hasColumn('purchases', 'other_charges')) $table->decimal('other_charges', 15, 2)->default(0)->after('tax_amount');
            if (!Schema::hasColumn('purchases', 'payment_method')) $table->string('payment_method')->nullable()->after('payment_status');
            if (!Schema::hasColumn('purchases', 'payment_date')) $table->date('payment_date')->nullable()->after('payment_method');
            if (!Schema::hasColumn('purchases', 'payment_reference')) $table->string('payment_reference')->nullable()->after('payment_date');
            if (!Schema::hasColumn('purchases', 'cheque_number')) $table->string('cheque_number')->nullable()->after('payment_reference');
            if (!Schema::hasColumn('purchases', 'cheque_due_date')) $table->date('cheque_due_date')->nullable()->after('cheque_number');
            if (!Schema::hasColumn('purchases', 'payment_account_id')) $table->foreignId('payment_account_id')->nullable()->after('cheque_due_date')->constrained('accounts')->nullOnDelete();
            if (!Schema::hasColumn('purchases', 'updated_by')) $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            if (!Schema::hasColumn('purchases', 'confirmed_by')) $table->foreignId('confirmed_by')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();
            if (!Schema::hasColumn('purchases', 'confirmed_at')) $table->timestamp('confirmed_at')->nullable()->after('received_at');
        });

        Schema::table('purchase_items', function (Blueprint $table): void {
            $table->decimal('quantity', 15, 3)->change();
            $table->decimal('received_quantity', 15, 3)->default(0)->change();
            if (Schema::hasColumn('purchase_items', 'selling_price')) $table->decimal('selling_price', 14, 2)->nullable()->change();
        });

        Schema::table('supplier_payments', function (Blueprint $table): void {
            if (!Schema::hasColumn('supplier_payments', 'cheque_number')) $table->string('cheque_number')->nullable()->after('reference_number');
            if (!Schema::hasColumn('supplier_payments', 'cheque_due_date')) $table->date('cheque_due_date')->nullable()->after('cheque_number');
            if (!Schema::hasColumn('supplier_payments', 'account_id')) $table->foreignId('account_id')->nullable()->after('purchase_id')->constrained('accounts')->nullOnDelete();
        });

        // Quantities introduced by purchases may be fractional. These stores
        // hold the same inventory state and must retain that precision.
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('stock_quantity', 15, 3)->default(0)->change();
            if (Schema::hasColumn('products', 'current_stock')) $table->decimal('current_stock', 15, 3)->default(0)->change();
        });
        Schema::table('inventories', function (Blueprint $table): void {
            if (Schema::hasColumn('inventories', 'available_stock')) $table->decimal('available_stock', 15, 3)->default(0)->change();
        });
        Schema::table('stock_movements', function (Blueprint $table): void {
            if (Schema::hasColumn('stock_movements', 'quantity')) $table->decimal('quantity', 15, 3)->change();
        });
        Schema::table('inventory_movements', function (Blueprint $table): void {
            if (Schema::hasColumn('inventory_movements', 'quantity')) $table->decimal('quantity', 15, 3)->change();
            if (Schema::hasColumn('inventory_movements', 'previous_stock')) $table->decimal('previous_stock', 15, 3)->default(0)->change();
            if (Schema::hasColumn('inventory_movements', 'new_stock')) $table->decimal('new_stock', 15, 3)->default(0)->change();
        });

        foreach ([
            ['purchases', 'purchases.edit', 'action', 'Edit Draft Purchases'],
            ['purchases', 'purchases.confirm', 'action', 'Confirm Purchases'],
            ['purchases', 'purchases.cancel', 'action', 'Cancel Purchases'],
        ] as [$module, $key, $type, $label]) {
            DB::table('permission_definitions')->updateOrInsert(
                ['permission_key' => $key],
                ['module' => $module, 'permission_type' => $type, 'label' => $label, 'status' => 'active', 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // Existing businesses that could create purchases retain the ability
        // to work with their drafts and confirm them after this upgrade.
        DB::table('company_permissions')->where('permission_key', 'purchases.create')->get()->each(function (object $permission): void {
            foreach (['purchases.edit', 'purchases.confirm'] as $key) {
                DB::table('company_permissions')->updateOrInsert(
                    ['company_id' => $permission->company_id, 'permission_key' => $key],
                    ['allowed' => $permission->allowed, 'assigned_by' => $permission->assigned_by, 'updated_at' => now(), 'created_at' => now()]
                );
            }
            Cache::forget('tradeflow.company-permissions.'.$permission->company_id);
        });
        Cache::forget('tradeflow.permission-definition-keys');
    }

    public function down(): void
    {
        // Existing purchase data must remain available. This upgrade is
        // intentionally forward-only once decimal quantities are in use.
        Cache::forget('tradeflow.permission-definition-keys');
    }
};
