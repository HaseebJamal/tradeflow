<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_document_footers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('footer_title')->nullable();
            $table->string('footer_message', 500)->nullable();
            $table->boolean('show_company_name')->default(true);
            $table->boolean('show_address')->default(true);
            $table->boolean('show_phone')->default(true);
            $table->boolean('show_email')->default(true);
            $table->boolean('show_website')->default(true);
            $table->boolean('show_tax_number')->default(true);
            $table->boolean('show_powered_by')->default(true);
            $table->timestamps();
        });

        // Keep every existing company printable while preserving its records.
        $now = now();
        DB::table('businesses')->orderBy('id')->eachById(function (object $business) use ($now): void {
            DB::table('business_document_footers')->updateOrInsert(
                ['business_id' => $business->id],
                [
                    'footer_title' => $business->business_name,
                    'footer_message' => 'Thank you for your business!',
                    'show_company_name' => true,
                    'show_address' => true,
                    'show_phone' => true,
                    'show_email' => true,
                    'show_website' => true,
                    'show_tax_number' => true,
                    'show_powered_by' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_document_footers');
    }
};
