<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_access_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->date('paid_access_start_at');
            $table->date('paid_access_end_at');
            // Positive rows grant complimentary days; negative rows record a
            // later reduction of that complimentary allowance only.
            $table->integer('days');
            $table->string('kind', 32)->default('manual_extra');
            $table->text('note')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at');
            $table->timestamps();
            $table->index(['subscription_id', 'paid_access_end_at'], 'subscription_extension_cycle_index');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'access_ended_at')) {
                $table->date('access_ended_at')->nullable()->after('ends_at');
            }
        });

        // Convert only the historical manual extensions that were previously
        // merged into subscriptions.ends_at. Platform payment periods remain
        // immutable; the subscription is restored to that financial period and
        // the difference is preserved as a complimentary-extension ledger row.
        if (! Schema::hasTable('platform_payments')) {
            return;
        }

        DB::table('subscriptions')->orderBy('id')->each(function (object $subscription): void {
            if (! $subscription->ends_at || ! $subscription->business_id) {
                return;
            }

            $payment = DB::table('platform_payments')
                ->where('business_id', $subscription->business_id)
                ->where('subscription_id', $subscription->id)
                ->where('status', 'Received')
                ->whereNotNull('period_starts_at')
                ->whereNotNull('period_ends_at')
                ->orderByDesc('period_ends_at')
                ->first();

            if (! $payment) {
                return;
            }

            $storedEnd = Carbon::parse($subscription->ends_at)->startOfDay();
            $paidEnd = Carbon::parse($payment->period_ends_at)->startOfDay();
            $extraDays = $paidEnd->diffInDays($storedEnd, false);
            if ($extraDays <= 0) {
                return;
            }

            DB::table('subscription_access_extensions')->insert([
                'subscription_id' => $subscription->id,
                'business_id' => $subscription->business_id,
                'paid_access_start_at' => $payment->period_starts_at,
                'paid_access_end_at' => $payment->period_ends_at,
                'days' => $extraDays,
                'kind' => 'legacy_manual_extra',
                'note' => 'Converted from a legacy merged paid-access extension.',
                'granted_by' => null,
                'granted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('subscriptions')->where('id', $subscription->id)->update([
                'ends_at' => $payment->period_ends_at,
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'access_ended_at')) {
                $table->dropColumn('access_ended_at');
            }
        });
        Schema::dropIfExists('subscription_access_extensions');
    }
};
