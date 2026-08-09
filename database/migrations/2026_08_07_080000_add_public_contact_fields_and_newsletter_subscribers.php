<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('support_tickets', 'source')) $table->string('source')->nullable()->after('type');
            if (! Schema::hasColumn('support_tickets', 'contact_name')) $table->string('contact_name')->nullable()->after('source');
            if (! Schema::hasColumn('support_tickets', 'contact_email')) $table->string('contact_email')->nullable()->after('contact_name');
            if (! Schema::hasColumn('support_tickets', 'contact_phone')) $table->string('contact_phone', 32)->nullable()->after('contact_email');
            if (! Schema::hasColumn('support_tickets', 'submitted_at')) $table->timestamp('submitted_at')->nullable()->after('contact_phone');
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('status')->default('Active');
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
        Schema::table('support_tickets', function (Blueprint $table) {
            foreach (['submitted_at', 'contact_phone', 'contact_email', 'contact_name', 'source'] as $column) {
                if (Schema::hasColumn('support_tickets', $column)) $table->dropColumn($column);
            }
        });
    }
};
