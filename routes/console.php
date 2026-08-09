<?php

use App\Models\Order;
use App\Services\FinanceCalculator;
use App\Services\SubscriptionLifecycleService;
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

Schedule::command('tradeflow:sync-subscription-lifecycle')->daily();
