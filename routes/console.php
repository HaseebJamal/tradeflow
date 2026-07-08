<?php

use App\Models\Order;
use App\Services\FinanceCalculator;
use Illuminate\Support\Facades\Artisan;

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
