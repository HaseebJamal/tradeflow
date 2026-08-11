<?php

use App\Models\Order;
use App\Services\FinanceCalculator;
use App\Services\SubscriptionLifecycleService;
use App\Services\RenewalInvoiceService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('tradeflow:recalculate-payments', function (FinanceCalculator $finance) {
    $count = 0;

    Order::with('payments')->chunkById(100, function ($orders) use ($finance, &$count) {
        foreach ($orders as $order) {
            $finance->syncOrderPaymentSummary($order);
            $count++;
        }
    });

    $this->info("Recalculated payment summaries for {$count} orders.");
})->purpose('Recalculate order paid amount, balance, and payment status from manual payments.');

Artisan::command('tradeflow:sync-subscription-lifecycle', function (SubscriptionLifecycleService $lifecycle) {
    $lifecycle->synchronizeAll();
    $this->info('Subscription lifecycle status and notification milestones synchronized.');
})->purpose('Synchronize trial/subscription expiry states and lifecycle notifications.');

Artisan::command('tradeflow:generate-renewal-invoices', function (RenewalInvoiceService $renewals) {
    $generated = $renewals->generateDue();
    $this->info("Generated {$generated} renewal invoice(s); overdue renewal invoices were synchronized.");
})->purpose('Generate idempotent custom renewal invoices before paid access expires.');

Schedule::command('tradeflow:sync-subscription-lifecycle')->daily();
Schedule::command('tradeflow:generate-renewal-invoices')
    ->dailyAt('00:05')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();
