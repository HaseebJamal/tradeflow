<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            if (!Schema::hasColumn('deliveries', 'signature_image')) {
                $table->string('signature_image')->nullable()->after('proof_image');
            }
            if (!Schema::hasColumn('deliveries', 'receiver_name')) {
                $table->string('receiver_name')->nullable()->after('signature_image');
            }
            if (!Schema::hasColumn('deliveries', 'receiver_phone')) {
                $table->string('receiver_phone')->nullable()->after('receiver_name');
            }
            if (!Schema::hasColumn('deliveries', 'collected_amount')) {
                $table->decimal('collected_amount', 12, 2)->nullable()->after('receiver_phone');
            }
            if (!Schema::hasColumn('deliveries', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('collected_amount');
            }
            if (!Schema::hasColumn('deliveries', 'payment_reference')) {
                $table->string('payment_reference')->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('deliveries', 'payment_proof_image')) {
                $table->string('payment_proof_image')->nullable()->after('payment_reference');
            }
            if (!Schema::hasColumn('deliveries', 'failure_reason')) {
                $table->text('failure_reason')->nullable()->after('payment_proof_image');
            }
            if (!Schema::hasColumn('deliveries', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('failure_reason');
            }
            if (!Schema::hasColumn('deliveries', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('started_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            foreach (['delivered_at', 'started_at', 'failure_reason', 'payment_proof_image', 'payment_reference', 'payment_method', 'collected_amount', 'receiver_phone', 'receiver_name', 'signature_image'] as $column) {
                if (Schema::hasColumn('deliveries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
