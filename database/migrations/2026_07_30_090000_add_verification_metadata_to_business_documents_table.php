<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('business_documents', 'verified_by')) {
                $table->foreignId('verified_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
                $table->timestamp('verified_at')->nullable()->after('verified_by');
                $table->foreignId('rejected_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
                $table->text('rejection_reason')->nullable()->after('rejected_at');
                $table->foreignId('reupload_requested_by')->nullable()->after('rejection_reason')->constrained('users')->nullOnDelete();
                $table->timestamp('reupload_requested_at')->nullable()->after('reupload_requested_by');
                $table->text('reupload_reason')->nullable()->after('reupload_requested_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('business_documents', function (Blueprint $table): void {
            if (Schema::hasColumn('business_documents', 'verified_by')) {
                $table->dropConstrainedForeignId('verified_by');
                $table->dropColumn('verified_at');
                $table->dropConstrainedForeignId('rejected_by');
                $table->dropColumn(['rejected_at', 'rejection_reason']);
                $table->dropConstrainedForeignId('reupload_requested_by');
                $table->dropColumn(['reupload_requested_at', 'reupload_reason']);
            }
        });
    }
};
