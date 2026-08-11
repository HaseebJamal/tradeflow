<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table): void {
            foreach (['en', 'ur'] as $locale) {
                $table->string("demo_{$locale}_title")->nullable();
                $table->text("demo_{$locale}_subtitle")->nullable();
                $table->string("demo_{$locale}_video_type", 20)->nullable();
                $table->string("demo_{$locale}_video_url", 2048)->nullable();
                $table->string("demo_{$locale}_poster")->nullable();
                $table->boolean("demo_{$locale}_is_active")->default(false);
            }
        });

        // Preserve an already configured single-language demo as English.
        \Illuminate\Support\Facades\DB::table('platform_settings')->whereNull('demo_en_video_url')->update([
            'demo_en_title' => \Illuminate\Support\Facades\DB::raw('demo_title'), 'demo_en_subtitle' => \Illuminate\Support\Facades\DB::raw('demo_subtitle'),
            'demo_en_video_type' => \Illuminate\Support\Facades\DB::raw('demo_video_type'), 'demo_en_video_url' => \Illuminate\Support\Facades\DB::raw('demo_video_url'),
            'demo_en_poster' => \Illuminate\Support\Facades\DB::raw('demo_poster'), 'demo_en_is_active' => \Illuminate\Support\Facades\DB::raw('demo_is_active'),
        ]);
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table): void {
            $table->dropColumn(['demo_en_title', 'demo_en_subtitle', 'demo_en_video_type', 'demo_en_video_url', 'demo_en_poster', 'demo_en_is_active', 'demo_ur_title', 'demo_ur_subtitle', 'demo_ur_video_type', 'demo_ur_video_url', 'demo_ur_poster', 'demo_ur_is_active']);
        });
    }
};
