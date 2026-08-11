<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('renewal_invoices', function (Blueprint $table) {
            $table->timestamp('email_draft_opened_at')->nullable()->after('email_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('renewal_invoices', function (Blueprint $table) {
            $table->dropColumn('email_draft_opened_at');
        });
    }
};
