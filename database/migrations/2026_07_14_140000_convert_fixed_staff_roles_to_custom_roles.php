<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $roles = ['business_admin' => 'Business Admin', 'business_sub_admin' => 'Business Sub-Admin', 'manager' => 'Manager', 'sales_staff' => 'Sales Staff', 'inventory_staff' => 'Inventory Staff', 'accountant' => 'Accountant', 'delivery_staff' => 'Delivery Staff', 'cashier' => 'Cashier', 'support_staff' => 'Support Staff'];
        foreach ($roles as $role => $name) {
            DB::table('users')->where('role', $role)->whereNotNull('business_id')->orderBy('id')->get()->each(function (object $user) use ($name): void {
                DB::table('staff_profiles')->where('user_id', $user->id)->update(['custom_role_name' => $name]);
                DB::table('users')->where('id', $user->id)->update(['role' => 'custom_staff']);
            });
        }
    }
    public function down(): void {}
};
