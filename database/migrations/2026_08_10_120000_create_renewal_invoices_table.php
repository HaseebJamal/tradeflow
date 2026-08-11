<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('renewal_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('platform_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number')->unique();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('last_payment_method', 80)->nullable();
            $table->date('access_starts_at')->nullable();
            $table->date('access_ends_at');
            $table->date('due_date');
            $table->string('status', 32)->default('Generated');
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('whatsapp_opened_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('email_error')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['business_id', 'access_ends_at'], 'renewal_invoice_business_cycle_unique');
            $table->index(['status', 'access_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renewal_invoices');
    }
};
