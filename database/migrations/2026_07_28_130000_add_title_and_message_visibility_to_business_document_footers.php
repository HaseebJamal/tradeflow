<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_document_footers', function (Blueprint $table): void {
            if (! Schema::hasColumn('business_document_footers', 'show_footer_title')) {
                $table->boolean('show_footer_title')->default(true)->after('show_company_name');
            }

            if (! Schema::hasColumn('business_document_footers', 'show_footer_message')) {
                $table->boolean('show_footer_message')->default(true)->after('show_footer_title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('business_document_footers', function (Blueprint $table): void {
            if (Schema::hasColumn('business_document_footers', 'show_footer_message')) {
                $table->dropColumn('show_footer_message');
            }

            if (Schema::hasColumn('business_document_footers', 'show_footer_title')) {
                $table->dropColumn('show_footer_title');
            }
        });
    }
};
