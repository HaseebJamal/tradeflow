<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('purchase_number')->unique();
            $table->string('supplier_invoice_number')->nullable();
            $table->string('status')->default('Ordered');
            $table->timestamp('purchase_date');
            $table->timestamp('received_at')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('payment_status')->default('Pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'status']);
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_name_snapshot');
            $table->integer('quantity');
            $table->integer('received_quantity')->default(0);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
        });

        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('invoice_number')->unique();
            $table->date('invoice_date');
            $table->decimal('grand_total', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('status')->default('Received');
            $table->timestamps();
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('method')->default('Cash');
            $table->string('reference_number')->nullable();
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('return_number')->unique();
            $table->date('return_date');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
        });

        $definitions = [
            ['purchases', 'purchases.view', 'module', 'Enable Purchases'],
            ['purchases', 'purchases.create', 'action', 'Create Purchase Orders'],
            ['purchases', 'purchases.receive', 'action', 'Receive Goods'],
            ['purchases', 'purchases.invoice', 'action', 'Manage Purchase Invoices'],
            ['purchases', 'purchases.pay', 'action', 'Record Supplier Payments'],
            ['purchases', 'purchases.return', 'action', 'Process Purchase Returns'],
            ['sales', 'sales.view', 'module', 'Enable Sales'],
            ['sales', 'sales.create', 'action', 'Create Sales Orders'],
            ['sales', 'sales.update_status', 'action', 'Update Sales Status'],
            ['sales', 'sales.quotations', 'action', 'Manage Quotations'],
            ['sales', 'sales.returns', 'action', 'Process Sales Returns'],
            ['sales', 'sales.payments', 'action', 'Record Customer Payments'],
        ];

        foreach ($definitions as [$module, $key, $type, $label]) {
            DB::table('permission_definitions')->updateOrInsert(
                ['permission_key' => $key],
                ['module' => $module, 'permission_type' => $type, 'label' => $label, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // Preserve the enabled Sales capability of existing businesses while
        // moving the old Orders permission namespace to Sales.
        DB::table('company_permissions')->where('permission_key', 'orders.view')->get()->each(function ($permission) {
            DB::table('company_permissions')->updateOrInsert(
                ['company_id' => $permission->company_id, 'permission_key' => 'sales.view'],
                ['allowed' => $permission->allowed, 'assigned_by' => $permission->assigned_by, 'created_at' => now(), 'updated_at' => now()]
            );
        });
        foreach (['create' => 'create', 'update_status' => 'update_status'] as $old => $new) {
            DB::table('company_permissions')->where('permission_key', 'orders.'.$old)->get()->each(function ($permission) use ($new) {
                DB::table('company_permissions')->updateOrInsert(
                    ['company_id' => $permission->company_id, 'permission_key' => 'sales.'.$new],
                    ['allowed' => $permission->allowed, 'assigned_by' => $permission->assigned_by, 'created_at' => now(), 'updated_at' => now()]
                );
            });
        }
        DB::table('company_permissions')->where('permission_key', 'payments.create')->get()->each(function ($permission) {
            DB::table('company_permissions')->updateOrInsert(
                ['company_id' => $permission->company_id, 'permission_key' => 'sales.payments'],
                ['allowed' => $permission->allowed, 'assigned_by' => $permission->assigned_by, 'created_at' => now(), 'updated_at' => now()]
            );
        });
        Cache::forget('tradeflow.permission-definition-keys');
    }

    public function down(): void
    {
        DB::table('permission_definitions')->whereIn('module', ['purchases', 'sales'])->delete();
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('purchase_invoices');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
    }
};
