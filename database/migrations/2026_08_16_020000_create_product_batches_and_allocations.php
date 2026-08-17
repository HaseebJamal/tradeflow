<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('goods_receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_number', 120);
            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('received_quantity', 15, 3)->default(0);
            $table->decimal('remaining_quantity', 15, 3)->default(0);
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->string('source', 30)->default('GRN');
            $table->timestamps();

            $table->unique(['business_id', 'product_id', 'batch_number', 'expiry_date'], 'product_batches_identity_unique');
            $table->index(['business_id', 'product_id', 'expiry_date'], 'product_batches_fefo_index');
        });

        Schema::create('product_batch_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_batch_id')->constrained('product_batches')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->string('type', 30)->default('Sale');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['business_id', 'order_item_id']);
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_movements', 'product_batch_id')) {
                $table->foreignId('product_batch_id')->nullable()->after('product_id')->constrained('product_batches')->nullOnDelete();
            }
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_movements', 'product_batch_id')) {
                $table->foreignId('product_batch_id')->nullable()->after('product_id')->constrained('product_batches')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('stock_movements', 'product_batch_id')) $table->dropConstrainedForeignId('product_batch_id');
        });
        Schema::table('inventory_movements', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_movements', 'product_batch_id')) $table->dropConstrainedForeignId('product_batch_id');
        });
        Schema::dropIfExists('product_batch_allocations');
        Schema::dropIfExists('product_batches');
    }
};
