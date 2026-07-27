<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table): void {
            if (!Schema::hasColumn('supplier_payments', 'is_advance')) $table->boolean('is_advance')->default(false)->after('amount');
            if (!Schema::hasColumn('supplier_payments', 'applied_amount')) $table->decimal('applied_amount', 15, 2)->default(0)->after('is_advance');
            if (!Schema::hasColumn('supplier_payments', 'remaining_amount')) $table->decimal('remaining_amount', 15, 2)->default(0)->after('applied_amount');
        });
        Schema::create('supplier_advance_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['purchase_id', 'supplier_payment_id'], 'supplier_advance_purchase_payment_unique');
        });
    }

    public function down(): void
    {
        // Forward-only: supplier payment allocations are accounting history.
    }
};
