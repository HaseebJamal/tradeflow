<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PhoneNumberService;
use Illuminate\Console\Command;

class NormalizeLegacyPhoneNumbers extends Command
{
    protected $signature = 'tradeflow:normalize-legacy-phones {--dry-run : Report changes without saving them}';

    protected $description = 'Normalize clearly identifiable legacy Pakistani phone numbers to E.164 without changing ambiguous records';

    public function handle(PhoneNumberService $phones): int
    {
        $updated = 0;
        $review = 0;

        foreach ([[Business::class, 'phone'], [User::class, 'phone'], [Customer::class, 'phone'], [Supplier::class, 'phone'], [Delivery::class, 'receiver_phone']] as [$model, $field]) {
            $model::query()->whereNotNull($field)->where($field, '!=', '')->chunkById(100, function ($records) use ($phones, $field, &$updated, &$review) {
                foreach ($records as $record) {
                    $normalized = $phones->normalizeLegacyPakistaniNumber($record->{$field});
                    if ($normalized === null) {
                        $review++;
                        continue;
                    }
                    if ($normalized === $record->{$field}) {
                        continue;
                    }

                    $updated++;
                    if (!$this->option('dry-run')) {
                        $record->forceFill([$field => $normalized])->saveQuietly();
                    }
                }
            });
        }

        $this->info(($this->option('dry-run') ? 'Would normalize' : 'Normalized')." {$updated} phone number(s).");
        $this->warn("{$review} ambiguous or malformed phone number(s) require manual review.");

        return self::SUCCESS;
    }
}
