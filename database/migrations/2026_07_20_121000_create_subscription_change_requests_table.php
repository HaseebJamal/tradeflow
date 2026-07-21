<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('current_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->foreignId('requested_plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('billing_cycle', 20);
            $table->unsignedBigInteger('expected_amount')->default(0);
            $table->string('payment_method', 80)->nullable();
            $table->text('note')->nullable();
            $table->string('status', 30)->default('Pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_change_requests');
    }
};
