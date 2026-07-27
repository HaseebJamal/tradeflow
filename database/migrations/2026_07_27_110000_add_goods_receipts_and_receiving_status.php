<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            if (!Schema::hasColumn('purchases', 'receiving_status')) $table->string('receiving_status')->default('Not Received')->after('status');
        });
        Schema::table('purchase_items', function (Blueprint $table): void {
            if (!Schema::hasColumn('purchase_items', 'damaged_quantity')) $table->decimal('damaged_quantity', 15, 3)->default(0)->after('received_quantity');
            if (!Schema::hasColumn('purchase_items', 'rejected_quantity')) $table->decimal('rejected_quantity', 15, 3)->default(0)->after('damaged_quantity');
        });
        Schema::create('goods_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('grn_number');
            $table->uuid('submission_token')->nullable();
            $table->string('attachment_path')->nullable();
            $table->timestamp('received_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['business_id', 'grn_number']);
            $table->unique(['business_id', 'submission_token']);
            $table->index(['business_id', 'purchase_id']);
        });
        Schema::create('goods_receipt_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('accepted_quantity', 15, 3)->default(0);
            $table->decimal('damaged_quantity', 15, 3)->default(0);
            $table->decimal('rejected_quantity', 15, 3)->default(0);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->timestamps();
            $table->index(['purchase_item_id', 'product_id']);
        });
        Schema::table('stock_movements', function (Blueprint $table): void {
            if (!Schema::hasColumn('stock_movements', 'goods_receipt_id')) $table->foreignId('goods_receipt_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
        });
        Schema::table('inventory_movements', function (Blueprint $table): void {
            if (!Schema::hasColumn('inventory_movements', 'goods_receipt_id')) $table->foreignId('goods_receipt_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
        });
        Schema::table('journal_entries', function (Blueprint $table): void {
            if (!Schema::hasColumn('journal_entries', 'purchase_id')) $table->foreignId('purchase_id')->nullable()->after('business_id')->constrained()->nullOnDelete();
            if (!Schema::hasColumn('journal_entries', 'goods_receipt_id')) $table->foreignId('goods_receipt_id')->nullable()->after('purchase_id')->constrained()->nullOnDelete();
            if (!Schema::hasColumn('journal_entries', 'purchase_return_id')) $table->foreignId('purchase_return_id')->nullable()->after('goods_receipt_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Forward-only: GRN and receiving history must remain auditable.
    }
};
