<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Historical purchases predate GRNs. Preserve their finished receipt
        // state instead of making them look available for new receiving.
        DB::table('purchases')->whereIn('status', ['Received', 'Completed'])->update(['receiving_status' => 'Fully Received']);
        DB::table('purchases')->where('status', 'Partially Returned')->update(['receiving_status' => 'Partially Returned']);
        DB::table('purchases')->where('status', 'Returned')->update(['receiving_status' => 'Returned']);
    }

    public function down(): void
    {
        // Historical status reconstruction is intentionally non-destructive.
    }
};
