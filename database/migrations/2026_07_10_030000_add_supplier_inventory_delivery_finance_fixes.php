<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->string('supplier_name');
                $table->string('company_name')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->text('address')->nullable();
                $table->string('city')->nullable();
                $table->decimal('opening_balance', 15, 2)->default(0);
                $table->string('status')->default('Active');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('inventory_movements')) {
            Schema::create('inventory_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
                $table->string('type');
                $table->integer('quantity');
                $table->integer('previous_stock')->default(0);
                $table->integer('new_stock')->default(0);
                $table->string('note')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('movement_date')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'added_date')) {
                $table->timestamp('added_date')->nullable()->after('created_by');
            }
        });

        Schema::table('deliveries', function (Blueprint $table) {
            if (!Schema::hasColumn('deliveries', 'payment_status')) {
                $table->string('payment_status')->nullable()->after('amount');
            }
            if (!Schema::hasColumn('deliveries', 'received_amount')) {
                $table->decimal('received_amount', 15, 2)->nullable()->after('collected_amount');
            }
            if (!Schema::hasColumn('deliveries', 'payment_proof')) {
                $table->string('payment_proof')->nullable()->after('payment_proof_image');
            }
            if (!Schema::hasColumn('deliveries', 'received_by')) {
                $table->foreignId('received_by')->nullable()->after('payment_proof')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('deliveries', 'received_at')) {
                $table->timestamp('received_at')->nullable()->after('received_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            if (Schema::hasColumn('deliveries', 'received_by')) {
                $table->dropForeign(['received_by']);
                $table->dropColumn('received_by');
            }
            foreach (['received_at', 'payment_proof', 'received_amount', 'payment_status'] as $column) {
                if (Schema::hasColumn('deliveries', $column)) $table->dropColumn($column);
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'added_date')) {
                $table->dropColumn('added_date');
            }
        });

        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('suppliers');
    }
};
