<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_counts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 20);
            $table->timestamp('counted_at');
            $table->string('status', 20)->default('Draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'reference']);
            $table->index(['business_id', 'status', 'counted_at']);
        });

        Schema::create('stock_count_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('stock_counts')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('system_quantity', 15, 3)->default(0);
            $table->decimal('physical_quantity', 15, 3)->nullable();
            $table->decimal('variance', 15, 3)->nullable();
            $table->decimal('current_system_quantity', 15, 3)->nullable();
            $table->decimal('applied_variance', 15, 3)->nullable();
            $table->boolean('review_required')->default(false);
            $table->string('reason', 80)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->unique(['stock_count_id', 'product_id']);
            $table->index(['business_id', 'product_id']);
        });

        Schema::table('inventory_movements', function (Blueprint $table): void {
            if (!Schema::hasColumn('inventory_movements', 'stock_count_id')) {
                $table->foreignId('stock_count_id')->nullable()->after('goods_receipt_id')->constrained('stock_counts')->nullOnDelete();
            }
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            if (!Schema::hasColumn('stock_movements', 'stock_count_id')) {
                $table->foreignId('stock_count_id')->nullable()->after('goods_receipt_id')->constrained('stock_counts')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            if (Schema::hasColumn('inventory_movements', 'stock_count_id')) {
                $table->dropConstrainedForeignId('stock_count_id');
            }
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            if (Schema::hasColumn('stock_movements', 'stock_count_id')) {
                $table->dropConstrainedForeignId('stock_count_id');
            }
        });

        Schema::dropIfExists('stock_count_items');
        Schema::dropIfExists('stock_counts');
    }
};
