<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

/**
 * Safe baseline data for every environment.
 *
 * This seeder deliberately never creates demo users, businesses, products,
 * orders, payments, or reports. Use tradeflow:initialize-production to create
 * the first Super Admin for a new production database.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $plan = SubscriptionPlan::query()->firstOrCreate(
            ['slug' => 'custom-access'],
            [
                'name' => 'Custom Access',
                'short_description' => 'Internal record for individually negotiated access.',
                'price' => 0,
                'monthly_price' => 0,
                'yearly_price' => 0,
                'trial_days' => 14,
                'product_limit' => 100,
                'staff_limit' => 3,
                'order_limit' => 500,
                'included_modules' => [],
                'features' => [],
                'is_public' => false,
                'is_recommended' => false,
                'sort_order' => 1,
                'status' => 'Active',
            ],
        );

        PlatformSetting::query()->firstOrCreate([], [
            'company_name' => 'Profit Point',
            'trial_days' => 14,
            'default_plan_id' => $plan->id,
            'max_upload_size' => 2048,
        ]);

        PlatformSetting::forgetCurrent();
    }
}
