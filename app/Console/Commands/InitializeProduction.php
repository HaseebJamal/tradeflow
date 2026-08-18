<?php

namespace App\Console\Commands;

use App\Models\PlatformSetting;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class InitializeProduction extends Command
{
    protected $signature = 'tradeflow:initialize-production
        {--admin-name= : Super Admin display name}
        {--admin-email= : Super Admin email address}
        {--platform-name=Profit Point : Public platform name}
        {--trial-days=14 : Free trial duration in days}';

    protected $description = 'Initialize a clean production database without creating demo businesses, orders, or users';

    public function handle(): int
    {
        $adminName = trim((string) ($this->option('admin-name') ?: $this->ask('Super Admin name')));
        $adminEmail = trim((string) ($this->option('admin-email') ?: $this->ask('Super Admin email')));
        $platformName = trim((string) $this->option('platform-name'));
        $trialDays = (int) $this->option('trial-days');

        $existingAdmin = $adminEmail ? User::query()->where('email', $adminEmail)->first() : null;
        $adminPassword = null;

        if (! $existingAdmin) {
            $adminPassword = (string) $this->secret('Super Admin password');
        }

        $data = Validator::make([
            'admin_name' => $adminName,
            'admin_email' => $adminEmail,
            'admin_password' => $adminPassword,
            'platform_name' => $platformName,
            'trial_days' => $trialDays,
        ], [
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email:rfc', 'max:255'],
            'admin_password' => [$existingAdmin ? 'nullable' : 'required', 'string', Password::min(12)->mixedCase()->numbers()->symbols()],
            'platform_name' => ['required', 'string', 'max:255'],
            'trial_days' => ['required', 'integer', 'min:1', 'max:365'],
        ])->validate();

        if ($existingAdmin && $existingAdmin->role !== 'super_admin') {
            $this->error('The supplied email already belongs to a non-admin user. Choose a different Super Admin email.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($data, $existingAdmin): void {
            $plan = SubscriptionPlan::query()
                ->where('status', 'Active')
                ->whereNull('archived_at')
                ->orderBy('sort_order')
                ->first();

            if (! $plan) {
                $plan = SubscriptionPlan::create([
                    'name' => 'Starter',
                    'slug' => 'starter',
                    'short_description' => 'Default plan for new workspaces.',
                    'price' => 0,
                    'monthly_price' => 0,
                    'yearly_price' => 0,
                    'trial_days' => $data['trial_days'],
                    'product_limit' => 100,
                    'staff_limit' => 3,
                    'order_limit' => 500,
                    'included_modules' => [],
                    'features' => [],
                    'is_public' => true,
                    'is_recommended' => true,
                    'sort_order' => 1,
                    'status' => 'Active',
                ]);
            }

            $settings = PlatformSetting::query()->firstOrNew();
            $settings->fill([
                'company_name' => $data['platform_name'],
                'trial_days' => $data['trial_days'],
                'default_plan_id' => $plan->id,
                'max_upload_size' => $settings->max_upload_size ?: 2048,
            ]);
            $settings->save();

            if (! $existingAdmin) {
                User::create([
                    'name' => $data['admin_name'],
                    'email' => $data['admin_email'],
                    'password' => Hash::make($data['admin_password']),
                    'role' => 'super_admin',
                    'status' => 'active',
                ]);
            }
        });

        PlatformSetting::forgetCurrent();

        $this->info('Profit Point production database initialized without demo data.');

        return self::SUCCESS;
    }
}
