<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'paid_amount')) {
                $table->decimal('paid_amount', 12, 2)->default(0)->after('grand_total');
            }

            if (!Schema::hasColumn('orders', 'balance')) {
                $table->decimal('balance', 12, 2)->default(0)->after('paid_amount');
            }

            if (!Schema::hasColumn('orders', 'payment_status')) {
                $table->string('payment_status')->default('Pending')->after('payment_type');
            }
        });

        DB::statement('
            UPDATE orders
            SET discount_percentage = COALESCE(discount_percentage, discount, 0),
                discount_amount = ROUND(COALESCE(subtotal, 0) * (COALESCE(discount_percentage, discount, 0) / 100), 2),
                grand_total = GREATEST(0, ROUND(COALESCE(subtotal, 0) - (COALESCE(subtotal, 0) * (COALESCE(discount_percentage, discount, 0) / 100)), 2)),
                total = GREATEST(0, ROUND(COALESCE(subtotal, 0) - (COALESCE(subtotal, 0) * (COALESCE(discount_percentage, discount, 0) / 100)), 2))
        ');

        DB::statement('
            UPDATE orders
            SET paid_amount = COALESCE((SELECT SUM(payments.amount) FROM payments WHERE payments.order_id = orders.id), 0)
        ');

        DB::statement('
            UPDATE orders
            SET balance = GREATEST(0, COALESCE(grand_total, total, 0) - COALESCE(paid_amount, 0)),
                payment_status = CASE
                    WHEN COALESCE(paid_amount, 0) >= COALESCE(grand_total, total, 0) AND COALESCE(grand_total, total, 0) > 0 THEN "Paid"
                    WHEN COALESCE(paid_amount, 0) > 0 THEN "Partial"
                    ELSE "Pending"
                END
        ');
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'payment_status')) {
                $table->dropColumn('payment_status');
            }

            if (Schema::hasColumn('orders', 'balance')) {
                $table->dropColumn('balance');
            }

            if (Schema::hasColumn('orders', 'paid_amount')) {
                $table->dropColumn('paid_amount');
            }
        });
    }
};
