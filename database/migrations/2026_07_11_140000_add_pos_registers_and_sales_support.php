<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pos_registers')) {
            Schema::create('pos_registers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->decimal('opening_cash', 14, 2)->default(0);
                $table->decimal('expected_cash', 14, 2)->default(0);
                $table->decimal('closing_cash', 14, 2)->nullable();
                $table->decimal('variance', 14, 2)->nullable();
                $table->string('status')->default('Open');
                $table->timestamp('opened_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->text('opening_note')->nullable();
                $table->text('closing_note')->nullable();
                $table->timestamps();
                $table->index(['business_id', 'user_id', 'status']);
            });
        }

        if (!Schema::hasTable('pos_payments')) {
            Schema::create('pos_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('pos_register_id')->nullable()->constrained('pos_registers')->nullOnDelete();
                $table->string('method');
                $table->decimal('amount', 14, 2);
                $table->string('reference_number')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['business_id', 'order_id']);
            });
        }

        if (!Schema::hasTable('pos_returns')) {
            Schema::create('pos_returns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('refund_amount', 14, 2)->default(0);
                $table->string('refund_method')->default('Cash');
                $table->text('reason')->nullable();
                $table->timestamp('returned_at')->nullable();
                $table->timestamps();
                $table->index(['business_id', 'order_id']);
            });
        }

        if (!Schema::hasTable('pos_return_items')) {
            Schema::create('pos_return_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pos_return_id')->constrained('pos_returns')->cascadeOnDelete();
                $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
                $table->integer('quantity');
                $table->decimal('refund_total', 14, 2);
                $table->timestamps();
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'sale_channel')) {
                $table->string('sale_channel')->default('business')->after('payment_type');
            }
            if (!Schema::hasColumn('orders', 'tax_rate')) {
                $table->decimal('tax_rate', 7, 2)->default(0)->after('discount_amount');
            }
            if (!Schema::hasColumn('orders', 'tax_amount')) {
                $table->decimal('tax_amount', 14, 2)->default(0)->after('tax_rate');
            }
            if (!Schema::hasColumn('orders', 'pos_register_id')) {
                $table->foreignId('pos_register_id')->nullable()->after('retailer_id')->constrained('pos_registers')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['pos_register_id', 'tax_amount', 'tax_rate', 'sale_channel'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('pos_return_items');
        Schema::dropIfExists('pos_returns');
        Schema::dropIfExists('pos_payments');
        Schema::dropIfExists('pos_registers');
    }
};
