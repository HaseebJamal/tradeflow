<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('khata_ledgers', function (Blueprint $table) {
            if (!Schema::hasColumn('khata_ledgers', 'entry_type')) {
                $table->string('entry_type')->default('purchase')->after('type');
            }
            if (!Schema::hasColumn('khata_ledgers', 'customer_debit')) {
                $table->decimal('customer_debit', 12, 2)->default(0)->after('amount');
            }
            if (!Schema::hasColumn('khata_ledgers', 'customer_credit')) {
                $table->decimal('customer_credit', 12, 2)->default(0)->after('customer_debit');
            }
            if (!Schema::hasColumn('khata_ledgers', 'business_debit')) {
                $table->decimal('business_debit', 12, 2)->default(0)->after('customer_credit');
            }
            if (!Schema::hasColumn('khata_ledgers', 'business_credit')) {
                $table->decimal('business_credit', 12, 2)->default(0)->after('business_debit');
            }
            if (!Schema::hasColumn('khata_ledgers', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('business_credit');
            }
        });

        DB::statement("UPDATE khata_ledgers SET entry_type = CASE WHEN description LIKE 'Payment%' OR type = 'credit' THEN 'payment' ELSE 'purchase' END");
        DB::statement("UPDATE khata_ledgers SET customer_credit = amount, business_debit = amount, customer_debit = 0, business_credit = 0 WHERE entry_type = 'purchase'");
        DB::statement("UPDATE khata_ledgers SET customer_debit = amount, business_debit = amount, customer_credit = 0, business_credit = 0 WHERE entry_type = 'payment'");
    }

    public function down(): void
    {
        Schema::table('khata_ledgers', function (Blueprint $table) {
            foreach (['payment_method', 'business_credit', 'business_debit', 'customer_credit', 'customer_debit', 'entry_type'] as $column) {
                if (Schema::hasColumn('khata_ledgers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
