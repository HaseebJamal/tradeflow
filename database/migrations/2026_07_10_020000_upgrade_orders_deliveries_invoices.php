<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'order_date')) {
                $table->date('order_date')->nullable()->after('created_by');
            }
            if (!Schema::hasColumn('orders', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('cancelled_at');
            }
            if (!Schema::hasColumn('orders', 'void_reason')) {
                $table->text('void_reason')->nullable()->after('voided_at');
            }
            if (!Schema::hasColumn('orders', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'product_name_snapshot')) {
                $table->string('product_name_snapshot')->nullable()->after('product_id');
            }
            if (!Schema::hasColumn('order_items', 'sku_snapshot')) {
                $table->string('sku_snapshot')->nullable()->after('product_name_snapshot');
            }
            if (!Schema::hasColumn('order_items', 'unit')) {
                $table->string('unit')->nullable()->after('quantity');
            }
            if (!Schema::hasColumn('order_items', 'unit_price')) {
                $table->decimal('unit_price', 14, 2)->default(0)->after('unit');
            }
            if (!Schema::hasColumn('order_items', 'purchase_cost_snapshot')) {
                $table->decimal('purchase_cost_snapshot', 14, 2)->default(0)->after('unit_price');
            }
            if (!Schema::hasColumn('order_items', 'line_total')) {
                $table->decimal('line_total', 14, 2)->default(0)->after('purchase_cost_snapshot');
            }
        });

        Schema::table('deliveries', function (Blueprint $table) {
            if (!Schema::hasColumn('deliveries', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('deliveries', 'failed_at')) {
                $table->timestamp('failed_at')->nullable()->after('delivered_at');
            }
            if (!Schema::hasColumn('deliveries', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('failed_at');
            }
            if (!Schema::hasColumn('deliveries', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('note')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'business_id')) {
                $table->foreignId('business_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            }
            if (!Schema::hasColumn('invoices', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('invoices', 'invoice_date')) {
                $table->date('invoice_date')->nullable()->after('invoice_number');
            }
            if (!Schema::hasColumn('invoices', 'due_date')) {
                $table->date('due_date')->nullable()->after('invoice_date');
            }
            if (!Schema::hasColumn('invoices', 'subtotal')) {
                $table->decimal('subtotal', 14, 2)->default(0)->after('due_date');
            }
            if (!Schema::hasColumn('invoices', 'discount_percentage')) {
                $table->decimal('discount_percentage', 6, 2)->default(0)->after('subtotal');
            }
            if (!Schema::hasColumn('invoices', 'discount_amount')) {
                $table->decimal('discount_amount', 14, 2)->default(0)->after('discount_percentage');
            }
            if (!Schema::hasColumn('invoices', 'grand_total')) {
                $table->decimal('grand_total', 14, 2)->default(0)->after('discount_amount');
            }
            if (!Schema::hasColumn('invoices', 'payment_status')) {
                $table->string('payment_status')->default('Pending')->after('balance');
            }
            if (!Schema::hasColumn('invoices', 'status')) {
                $table->string('status')->default('Draft')->after('payment_status');
            }
            if (!Schema::hasColumn('invoices', 'notes')) {
                $table->text('notes')->nullable()->after('status');
            }
            if (!Schema::hasColumn('invoices', 'issued_by')) {
                $table->foreignId('issued_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('invoices', 'issued_at')) {
                $table->timestamp('issued_at')->nullable()->after('issued_by');
            }
            if (!Schema::hasColumn('invoices', 'voided_by')) {
                $table->foreignId('voided_by')->nullable()->after('issued_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('invoices', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('voided_by');
            }
            if (!Schema::hasColumn('invoices', 'void_reason')) {
                $table->text('void_reason')->nullable()->after('voided_at');
            }
        });

        if (!Schema::hasTable('invoice_items')) {
            Schema::create('invoice_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
                $table->string('product_name_snapshot');
                $table->string('sku_snapshot')->nullable();
                $table->integer('quantity');
                $table->string('unit')->nullable();
                $table->decimal('unit_price', 14, 2)->default(0);
                $table->decimal('line_total', 14, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('credit_notes')) {
            Schema::create('credit_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
                $table->string('credit_note_number');
                $table->date('date');
                $table->text('reason')->nullable();
                $table->decimal('amount', 14, 2)->default(0);
                $table->string('status')->default('Posted');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['business_id', 'credit_note_number']);
            });
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('audit_logs', 'business_id')) {
                $table->foreignId('business_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('audit_logs', 'module')) {
                $table->string('module')->nullable()->after('action');
            }
            if (!Schema::hasColumn('audit_logs', 'record_id')) {
                $table->unsignedBigInteger('record_id')->nullable()->after('module');
            }
            if (!Schema::hasColumn('audit_logs', 'old_values')) {
                $table->json('old_values')->nullable()->after('record_id');
            }
            if (!Schema::hasColumn('audit_logs', 'new_values')) {
                $table->json('new_values')->nullable()->after('old_values');
            }
        });
    }

    public function down(): void
    {
        //
    }
};
