<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            if (!$this->hasIndex('audit_logs_created_at_index')) {
                $table->index('created_at', 'audit_logs_created_at_index');
            }

            if (!$this->hasIndex('audit_logs_ip_address_index')) {
                $table->index('ip_address', 'audit_logs_ip_address_index');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            if ($this->hasIndex('audit_logs_created_at_index')) {
                $table->dropIndex('audit_logs_created_at_index');
            }

            if ($this->hasIndex('audit_logs_ip_address_index')) {
                $table->dropIndex('audit_logs_ip_address_index');
            }
        });
    }

    private function hasIndex(string $name): bool
    {
        return collect(Schema::getIndexes('audit_logs'))
            ->contains(fn (array $index): bool => $index['name'] === $name);
    }
};
