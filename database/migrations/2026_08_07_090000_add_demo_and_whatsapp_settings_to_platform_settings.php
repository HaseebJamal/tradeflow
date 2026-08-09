<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table): void {
            $table->string('demo_title')->nullable()->after('support_phone');
            $table->text('demo_subtitle')->nullable()->after('demo_title');
            $table->string('demo_video_type', 20)->nullable()->after('demo_subtitle');
            $table->string('demo_video_url', 2048)->nullable()->after('demo_video_type');
            $table->string('demo_poster')->nullable()->after('demo_video_url');
            $table->boolean('demo_is_active')->default(false)->after('demo_poster');
            $table->string('whatsapp_number', 20)->nullable()->after('demo_is_active');
            $table->text('whatsapp_message')->nullable()->after('whatsapp_number');
            $table->string('whatsapp_tooltip')->nullable()->after('whatsapp_message');
            $table->boolean('whatsapp_is_active')->default(false)->after('whatsapp_tooltip');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'demo_title', 'demo_subtitle', 'demo_video_type', 'demo_video_url', 'demo_poster', 'demo_is_active',
                'whatsapp_number', 'whatsapp_message', 'whatsapp_tooltip', 'whatsapp_is_active',
            ]);
        });
    }
};
