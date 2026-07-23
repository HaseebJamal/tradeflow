<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('customers')->whereNull('credit_limit')->update(['credit_limit' => 0]);
        DB::table('customers')->whereNull('opening_balance')->update(['opening_balance' => 0]);
    }

    public function down(): void
    {
        // Existing customer financial data is intentionally preserved.
    }
};
