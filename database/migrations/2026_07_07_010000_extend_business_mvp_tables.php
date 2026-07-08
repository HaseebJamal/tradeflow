<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'profile_image')) {
                $table->string('profile_image')->nullable()->after('status');
            }
            if (!Schema::hasColumn('users', 'permissions')) {
                $table->json('permissions')->nullable()->after('profile_image');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'purchase_cost')) {
                $table->decimal('purchase_cost', 12, 2)->default(0)->after('wholesale_price');
            }
            if (!Schema::hasColumn('products', 'unit')) {
                $table->string('unit')->default('Piece')->after('stock_quantity');
            }
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_movements', 'note')) {
                $table->text('note')->nullable()->after('reason');
            }
            if (!Schema::hasColumn('stock_movements', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'grand_total')) {
                $table->decimal('grand_total', 12, 2)->default(0)->after('total');
            }
            if (!Schema::hasColumn('orders', 'payment_type')) {
                $table->string('payment_type')->default('Credit')->after('grand_total');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'business_id')) {
                $table->foreignId('business_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            }
            if (!Schema::hasColumn('payments', 'reference_number')) {
                $table->string('reference_number')->nullable()->after('transaction_reference');
            }
            if (!Schema::hasColumn('payments', 'screenshot')) {
                $table->string('screenshot')->nullable()->after('proof_image');
            }
        });

        Schema::table('khata_ledgers', function (Blueprint $table) {
            if (!Schema::hasColumn('khata_ledgers', 'business_id')) {
                $table->foreignId('business_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            }
            if (!Schema::hasColumn('khata_ledgers', 'payment_id')) {
                $table->foreignId('payment_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('khata_ledgers', 'balance_after')) {
                $table->decimal('balance_after', 12, 2)->default(0)->after('balance');
            }
            if (!Schema::hasColumn('khata_ledgers', 'entry_date')) {
                $table->date('entry_date')->nullable()->after('balance_after');
            }
        });

        Schema::table('deliveries', function (Blueprint $table) {
            if (!Schema::hasColumn('deliveries', 'business_id')) {
                $table->foreignId('business_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            }
            if (!Schema::hasColumn('deliveries', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->after('delivery_staff_id')->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('deliveries', 'amount')) {
                $table->decimal('amount', 12, 2)->default(0)->after('address');
            }
            if (!Schema::hasColumn('deliveries', 'note')) {
                $table->text('note')->nullable()->after('proof_image');
            }
        });

        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'expense_date')) {
                $table->date('expense_date')->nullable()->after('date');
            }
        });
    }

    public function down(): void
    {
        //
    }
};
