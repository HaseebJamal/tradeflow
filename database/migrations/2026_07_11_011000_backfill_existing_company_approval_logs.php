<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('businesses')->orderBy('id')->each(function ($company) {
            $exists = DB::table('company_approval_logs')->where('company_id', $company->id)->exists();
            if ($exists) {
                return;
            }

            DB::table('company_approval_logs')->insert([
                'company_id' => $company->id,
                'old_status' => null,
                'new_status' => $company->status ?: 'Pending',
                'note' => 'Historical baseline created for an existing company.',
                'changed_by' => null,
                'changed_at' => $company->created_at ?? now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('company_approval_logs')->where('note', 'Historical baseline created for an existing company.')->delete();
    }
};
